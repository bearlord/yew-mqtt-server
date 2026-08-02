<?php

namespace App\Modules\Mqtt\Infrastructure;

/**
 * Immutable result of one SASL handshake step.
 *
 */
final class SaslResult
{
    public function __construct(
        public readonly int $code,
        public readonly string $data,
        public readonly bool $done,
        public readonly ?string $username,
    ) {
    }

    /** Whether the exchange finished successfully. */
    public function isSuccess(): bool
    {
        return $this->done && $this->code === \Yew\Mqtt\Hex\ReasonCode::SUCCESS;
    }
}
