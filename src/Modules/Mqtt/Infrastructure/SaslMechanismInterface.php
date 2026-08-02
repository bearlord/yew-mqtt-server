<?php

namespace App\Modules\Mqtt\Infrastructure;

/**
 * Strategy for one SASL authentication mechanism.
 *
 * A mechanism owns its half of the handshake state (keyed by fd) and advances
 * it step by step. Each step receives the raw Authentication Data from the
 * client and returns the next server AUTH frame as a SaslResult.
 */
interface SaslMechanismInterface
{
    /** Begin the handshake and return the first server challenge. */
    public function begin(int $fd): SaslResult;

    /**
     * Advance the handshake with client-supplied data.
     */
    public function step(int $fd, string $authData, string $clientId): SaslResult;

    /** Drop any half-finished state for a connection. */
    public function clear(int $fd): void;
}
