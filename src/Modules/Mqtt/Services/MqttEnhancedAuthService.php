<?php

namespace App\Modules\Mqtt\Services;

use App\Modules\Mqtt\Services\MqttSaslAuthenticationService;
use Yew\Coroutine\Server\Server;
use Yew\Mqtt\Hex\ReasonCode;
use Yew\Mqtt\Message\Auth;
use Yew\Mqtt\Message\ConnAck;
use Yew\Mqtt\Tools\ProtocolLevel;
use Yew\Plugins\Connection\GetConnection;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Core\Plugins\Logger\GetLogger;

/**
 * MQTT 5 enhanced (SASL) authentication state machine.
 *
 * A CONNECT carrying an Authentication Method property starts a multi-step
 * challenge/response exchange driven by AUTH packets, until the SASL handshake
 * succeeds and the connection is finalized via completeConnect().
 *
 * Per-connection handshake state is kept in the in-memory session under the
 * 'enhanced_auth' key (fd-scoped) so the exchange survives across AUTH packets.
 */
class MqttEnhancedAuthService
{
    use GetBoostSend;
    use GetLogger;
    use GetConnection;

    public function __construct(
        private readonly MqttUserService $userService,
    ) {
    }

    /**
     * Start an MQTT 5 enhanced (SASL) authentication exchange for a CONNECT.
     *
     * @param int $fd Connection file descriptor.
     * @param int $protocolLevel MQTT protocol version.
     * @param string $clientId Client identifier from the CONNECT packet.
     * @param string|null $username Authenticated user name (may be null for SASL).
     * @param bool $sessionStart True when the client requested a persistent session.
     * @param string|null $ipAddress Remote peer address.
     * @param int|null $keepAlive Negotiated keep-alive (seconds).
     * @param string $authMethod Authentication Method property value.
     * @param string|null $authData Authentication Data property (client-first blob).
     * @param int|null $sessionExpiryInterval MQTT 5 session expiry interval (seconds).
     */
    public function beginEnhancedAuth(
        int $fd,
        int $protocolLevel,
        string $clientId,
        ?string $username,
        bool $sessionStart,
        ?string $ipAddress,
        ?int $keepAlive,
        string $authMethod,
        ?string $authData,
        ?int $sessionExpiryInterval): void
    {
        $sasl = new MqttSaslAuthenticationService($this->userService);

        // Unknown method → CONNACK with the dedicated reason code, then close.
        if (!$sasl->isSupported($authMethod)) {
            $this->warning("MQTT unsupported auth method {$authMethod} for client {$clientId}");
            $connAck = (new ConnAck());
            $connAck->setProtocolLevel($protocolLevel)->setSessionPresent(false)->setCode(ReasonCode::BAD_AUTHENTICATION_METHOD);
            $this->autoBoostSend($fd, $connAck->getContents());
            Server::$instance->closeFd($fd);
            return;
        }

        // Keep the CONNECT parameters so the AUTH step can finalize the connection.
        $sasl->begin($fd, $authMethod);
        $this->setFdSession($fd, 'enhanced_auth', [
            'client_id' => $clientId,
            'username' => $username,
            'session_start' => $sessionStart,
            'ip_address' => $ipAddress,
            'keep_alive' => $keepAlive,
            'session_expiry_interval' => $sessionExpiryInterval,
            'protocol_level' => $protocolLevel,
        ]);

        // Process the client-first data inline (one round-trip saved) or prompt.
        if ($authData !== null && $authData !== '') {
            $this->advanceEnhancedAuth($fd, $clientId, $authData, false);
            return;
        }

        $auth = (new Auth());
        $auth->setProtocolLevel($protocolLevel)
            ->setCode(ReasonCode::CONTINUE_AUTHENTICATION)
            ->setProperties([
                'authentication_method' => $authMethod,
            ]);
        $this->autoBoostSend($fd, $auth->getContents());
    }

    /**
     * Drive one step of the enhanced (SASL) auth exchange, replying with the next AUTH packet.
     *
     * @param int $fd Connection file descriptor.
     * @param string $clientId Client identifier from the AUTH packet.
     * @param string $authData Authentication Data from the AUTH packet.
     * @param bool $reAuth True for an in-session RE-AUTHENTICATE exchange.
     * @param array<string, mixed>|null $reAuthContext Minimal context for re-auth (method, username, protocol_level, ip_address).
     */
    public function advanceEnhancedAuth(int $fd, string $clientId, string $authData, bool $reAuth, ?array $reAuthContext = null): void
    {
        $sasl = new MqttSaslAuthenticationService($this->userService);

        // Re-auth has no stored CONNECT context; rebuild a minimal one so the
        // session is kept rather than re-created on success.
        if ($reAuth && $reAuthContext !== null) {
            $sasl->begin($fd, (string)($reAuthContext['method'] ?? ''));
            $this->setFdSession($fd, 'enhanced_auth', [
                'client_id' => $clientId,
                'username' => $reAuthContext['username'] ?? null,
                'session_start' => false,
                'ip_address' => $reAuthContext['ip_address'] ?? null,
                'keep_alive' => null,
                'session_expiry_interval' => null,
                'protocol_level' => $reAuthContext['protocol_level'] ?? ProtocolLevel::PROTOCOL_LEVEL_V5,
            ]);
        }

        $context = $this->getFdSession($fd, 'enhanced_auth');
        $username = $context['username'] ?? '';
        $protocolLevel = $context['protocol_level'] ?? ProtocolLevel::PROTOCOL_LEVEL_V5;

        $result = $sasl->step($fd, $authData, $clientId, (string)$username);

        // Handshake failed → report the reason and close the connection.
        if ($result->done && $result->code !== ReasonCode::SUCCESS) {
            $auth = (new Auth());
            $auth->setProtocolLevel($protocolLevel)->setCode($result->code);
            $this->autoBoostSend($fd, $auth->getContents());

            $this->setFdSession($fd, 'enhanced_auth', null);
            Server::$instance->closeFd($fd);
            return;
        }

        // More rounds needed → echo the server challenge to the client.
        if (!$result->done) {
            $auth = (new Auth());
            $auth->setProtocolLevel($protocolLevel)
                ->setCode(ReasonCode::CONTINUE_AUTHENTICATION)
                ->setProperties([
                    'authentication_method' => $context['method'] ?? '',
                    'authentication_data' => $result->data,
                ]);
            $this->autoBoostSend($fd, $auth->getContents());
            return;
        }

        // Success → AUTH(Success); finalize the connection unless re-authenticating.
        $auth = (new Auth());
        $auth->setProtocolLevel($protocolLevel)->setCode(ReasonCode::SUCCESS);
        $this->autoBoostSend($fd, $auth->getContents());

        if (!$reAuth) {
            $this->completeConnect(
                $fd,
                $protocolLevel,
                $clientId,
                $context['username'] ?? null,
                $context['session_start'] ?? false,
                $context['ip_address'] ?? null,
                $context['keep_alive'] ?? null,
                $context['session_expiry_interval'] ?? null
            );
        }
    }

    /**
     * Finalize a successful connection (persist client, register session, deliver offline, send CONNACK).
     *
     * @param int $fd Connection file descriptor.
     * @param int $protocolLevel MQTT protocol version.
     * @param string $clientId Client identifier.
     * @param string|null $username Authenticated user name.
     * @param bool $sessionStart True when the client requested a persistent session.
     * @param string|null $ipAddress Remote peer address.
     * @param int|null $keepAlive Negotiated keep-alive (seconds).
     * @param int|null $sessionExpiryInterval MQTT 5 session expiry interval (seconds).
     */
    public function completeConnect(
        int $fd,
        int $protocolLevel,
        string $clientId,
        ?string $username,
        bool $sessionStart,
        ?string $ipAddress,
        ?int $keepAlive,
        ?int $sessionExpiryInterval): void
    {
        $data = [
            'user_name' => $username,
            'protocol_level' => ProtocolLevel::getProtocolLevelName($protocolLevel),
            'client_id' => $clientId,
            'session_start' => $sessionStart,
            'is_active' => 1,
            'ip_address' => $ipAddress,
            'keep_alive' => $keepAlive
        ];

        if ($sessionExpiryInterval !== null) {
            $data['session_expiry_interval'] = $sessionExpiryInterval;
        }

        (new MqttClientService())->connectProcess($clientId, $fd, $protocolLevel, $data);
    }
}
