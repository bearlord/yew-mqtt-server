<?php

namespace App\Modules\Mqtt\Services;

use App\Modules\Mqtt\Infrastructure\SaslMechanismInterface;
use App\Modules\Mqtt\Infrastructure\SaslPlainMechanism;
use App\Modules\Mqtt\Infrastructure\SaslResult;
use App\Modules\Mqtt\Infrastructure\SaslScramSha256Mechanism;

/**
 * MQTT 5 enhanced (SASL) authentication helper, driven by AUTH packets.
 *
 * Maps an Authentication Method to a SaslMechanismInterface and forwards each
 * step; also exposes the advertised mechanism list. Mechanism instances are
 * constructed with the user store injected, so they stay unit-testable.
 */
class MqttSaslAuthenticationService
{
    public const MECHANISM_PLAIN = 'PLAIN';
    public const MECHANISM_SCRAM_SHA256 = 'SCRAM-SHA-256';

    /** @var array<string, class-string<SaslMechanismInterface>> */
    private const MECHANISMS = [
        self::MECHANISM_PLAIN => SaslPlainMechanism::class,
        self::MECHANISM_SCRAM_SHA256 => SaslScramSha256Mechanism::class,
    ];

    /** Authentication Method chosen at begin(), keyed by fd (so step() stays 4-arg). */
    private static array $methodByFd = [];

    public function __construct(
        private readonly MqttUserService $userService = new MqttUserService(),
    )
    {
    }

    /** @return string[] */
    public function getSupportedMechanisms(): array
    {
        return array_keys(self::MECHANISMS);
    }

    /**
     * Whether the broker supports the requested authentication method.
     *
     * @param string $method Authentication Method name (e.g. 'PLAIN').
     */
    public function isSupported(string $method): bool
    {
        return in_array($method, self::MECHANISMS, true);
    }

    /**
     * Start a new SASL handshake. Returns the first server AUTH data (empty for PLAIN).
     *
     * @param int    $fd     Connection fd owning the handshake.
     * @param string $method Authentication Method chosen by the client.
     */
    public function begin(int $fd, string $method): SaslResult
    {
        self::$methodByFd[$fd] = $method;
        return $this->mechanism($method)->begin($fd);
    }

    /**
     * Advance a SASL handshake with the client-supplied data.
     *
     * @param int    $fd               Connection fd owning the handshake.
     * @param string $authData         Raw Authentication Data from the client AUTH packet.
     * @param string $clientId         Publishing client identifier (for binding on success).
     * @param string $usernameFallback Optional username when the method carries none.
     */
    public function step(int $fd, string $authData, string $clientId, string $usernameFallback = ''): SaslResult
    {
        $method = self::$methodByFd[$fd] ?? '';
        return $this->mechanism($method)->step($fd, $authData, $clientId);
    }

    /**
     * Drop any half-finished SASL state for a connection (e.g. on disconnect).
     * @param int $fd Connection fd.
     * @return void
     * */
    public static function clearState(int $fd): void
    {
        (new SaslPlainMechanism(MqttServices::user()))->clear($fd);
        (new SaslScramSha256Mechanism(MqttServices::user()))->clear($fd);
        unset(self::$methodByFd[$fd]);
    }

    /**
     * Resolve the mechanism implementation for a method and wire in the user store.
     *
     * @param string $method Authentication Method name (must exist in MECHANISMS).
     * @return SaslMechanismInterface Mechanism instance with the user store injected.
     * @throws \InvalidArgumentException When the method is unsupported.
     */
    private function mechanism(string $method): SaslMechanismInterface
    {
        if (!isset(self::MECHANISMS[$method])) {
            throw new \InvalidArgumentException(sprintf('Unsupported SASL mechanism: %s', $method));
        }

        /** @var class-string<SaslMechanismInterface> $class */
        $class = self::MECHANISMS[$method];
        return new $class($this->userService);
    }
}
