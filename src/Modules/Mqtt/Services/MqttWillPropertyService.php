<?php

namespace App\Modules\Mqtt\Services;

/** Business service for MQTT 5 Will Message properties (e.g. will_delay_interval). */
class MqttWillPropertyService
{
    /**
     * Normalise will properties onto plain keys (will_delay_interval, default 0).
     *
     * @param array<string, mixed> $will Will payload carrying a 'properties' sub-array.
     */
    public function applyWillProperties(array $will): array
    {
        $properties = $will['properties'] ?? [];
        $will['will_delay_interval'] = (int)($properties['will_delay_interval'] ?? 0);

        return $will;
    }

    /**
     * Seconds to wait before publishing the will, or 0 for immediate.
     *
     * @param array<string, mixed> $will Will payload (after applyWillProperties).
     */
    public function getWillDelayInterval(array $will): int
    {
        return (int)($will['will_delay_interval'] ?? 0);
    }
}
