<?php

namespace App\Modules\Mqtt\Infrastructure;

use App\Modules\Mqtt\Services\MqttUserService;
use Yew\Mqtt\Hex\ReasonCode;

/**
 * SASL PLAIN mechanism. authData = "\0username\0password" (single round).
 *
 * The authorization identity (first NUL field) is ignored; the authentication
 * identity and password are verified against the user store.
 */
final class SaslPlainMechanism implements SaslMechanismInterface
{
    public function __construct(
        private readonly MqttUserService $userService,
    ) {
    }

    /** PLAIN needs no server challenge. */
    public function begin(int $fd): SaslResult
    {
        return new SaslResult(ReasonCode::CONTINUE_AUTHENTICATION, '', false, null);
    }

    public function step(int $fd, string $authData, string $clientId): SaslResult
    {
        $parts = explode("\0", $authData, 3);
        $username = $parts[1] ?? null;
        $password = $parts[2] ?? '';

        $ok = $this->userService->verifyPassword((string)$username, $password);

        return $ok
            ? new SaslResult(ReasonCode::SUCCESS, '', true, $username)
            : new SaslResult(ReasonCode::NOT_AUTHORIZED, '', true, null);
    }

    public function clear(int $fd): void
    {
        // PLAIN is single-round; nothing to discard.
    }
}
