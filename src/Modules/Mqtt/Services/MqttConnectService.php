<?php

namespace App\Modules\Mqtt\Services;

use App\Modules\Mqtt\Services\MqttAuthService;
use Yew\Coroutine\Server\Server;
use Yew\Mqtt\Hex\ReasonCode;
use Yew\Mqtt\Message\ConnAck;
use Yew\Mqtt\Tools\ProtocolLevel;
use Yew\Plugins\Connection\GetConnection;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Core\Plugins\Logger\GetLogger;

/**
 * Orchestrates the MQTT CONNECT handshake.
 *
 */
class MqttConnectService
{
    use GetBoostSend;
    use GetLogger;
    use GetConnection;

    public function __construct(
        private readonly MqttUserService $userService,
        private ?MqttEnhancedAuthService $enhancedAuthService = null,
    ) {
    }

    /**
     * Lazily build the enhanced-auth service (avoids a circular constructor
     * dependency when the controller news up this service directly).
     */
    private function enhancedAuthService(): MqttEnhancedAuthService
    {
        return $this->enhancedAuthService ??= new MqttEnhancedAuthService($this->userService);
    }

    /**
     * Single entry point for a CONNECT packet (plain or MQTT 5 enhanced auth).
     *
     * @param int $fd Connection file descriptor.
     * @param array<string, mixed> $packet Decoded CONNECT packet (clientData payload).
     * @param string|null $ipAddress Remote peer address.
     */
    public function connectProcess(int $fd, array $data, ?string $ipAddress): void
    {
        $protocolLevel = $data['protocol_level'] ?? null;
        $clientId = $data['client_id'] ?? null;

        // Required fields missing → drop the connection.
        if (empty($protocolLevel) || empty($clientId)) {
            Server::$instance->closeFd($fd);
            return;
        }

        $username = $data['data']['username'] ?? null;
        $password = $data['data']['password'] ?? null;
        // "session_start" mirrors the MQTT clean_session / clean_start flag.
        $sessionStart = $data["data"]['clean_session'] ?? false;
        $keepAlive = $data["data"]['keep_alive'] ?? null;

        // MQTT 5 enhanced (SASL) auth: a CONNECT carrying an Authentication Method
        // property starts a challenge/response exchange driven by AUTH packets.
        $authMethod = $data['data']['properties']['authentication_method'] ?? null;
        $authData = $data['data']['properties']['authentication_data'] ?? null;
        $sessionExpiryInterval = $protocolLevel === ProtocolLevel::PROTOCOL_LEVEL_V5
            ? ($data["data"]['properties']['session_expiry_interval'] ?? null)
            : null;

        if ($authMethod !== null && $protocolLevel === ProtocolLevel::PROTOCOL_LEVEL_V5) {
            $this->enhancedAuthService()->beginEnhancedAuth(
                $fd, $protocolLevel, $clientId, $username, $sessionStart, $ipAddress, $keepAlive, $authMethod, $authData, $sessionExpiryInterval
            );
            return;
        }

        // Plain username/password authentication.
        $authOk = (new MqttAuthService($this->userService))->auth((string)$username, (string)$password);
        if (!$authOk) {
            $this->warning("MQTT auth failed for client {$clientId}, fd {$fd}");
            $connAck = (new ConnAck());
            $connAck->setProtocolLevel($protocolLevel)->setSessionPresent(false)->setCode(ReasonCode::NOT_AUTHORIZED);
            $this->autoBoostSend($fd, $connAck->getContents());
            Server::$instance->closeFd($fd);
            return;
        }

        $this->enhancedAuthService()->completeConnect(
            $fd, $protocolLevel, $clientId, $username, $sessionStart, $ipAddress, $keepAlive, $sessionExpiryInterval
        );
    }
}
