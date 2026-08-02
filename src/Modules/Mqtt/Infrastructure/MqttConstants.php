<?php

namespace App\Modules\Mqtt\Infrastructure;

/** MQTT protocol constants shared by the broker services. */
final class MqttConstants
{
    /** Message direction. */
    public const DIRECTION_UP = 1;    // client -> broker
    public const DIRECTION_DOWN = 2;  // broker -> subscriber

    /** QoS acknowledgement stage (maps mqtt_message_ack.stage). */
    public const STAGE_PUBLISHED = 1;  // PUBLISH sent, awaiting PUBACK / PUBREC
    public const STAGE_PUBREC = 2;     // QoS2 PUBREC received, PUBREL sent, awaiting PUBCOMP
    public const STAGE_COMPLETED = 3;  // PUBACK or PUBCOMP received

    /** MQTT QoS levels. */
    public const QOS_AT_MOST_ONCE = 0;
    public const QOS_AT_LEAST_ONCE = 1;
    public const QOS_EXACTLY_ONCE = 2;

    /**
     * Whether a QoS level requires broker-side acknowledgement bookkeeping.
     */
    public static function isQosAcknowledged(int $qos): bool
    {
        return $qos === self::QOS_AT_LEAST_ONCE || $qos === self::QOS_EXACTLY_ONCE;
    }
}
