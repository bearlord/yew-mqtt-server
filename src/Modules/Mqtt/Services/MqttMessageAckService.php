<?php

namespace App\Modules\Mqtt\Services;

use App\Models\Extension\MqttMessageAck;
use App\Modules\Mqtt\Infrastructure\MqttConstants;

/**
 * Tracks the QoS 1/2 acknowledgement handshake for a single message leg.
 *
 * Each row in mqtt_message_ack represents one (direction, receiver, packet id)
 * pair progressing through PUBLISHED -> PUBREC -> COMPLETED. Used by both the
 * up-leg (publisher) and down-leg (subscriber) handshakes.
 */
class MqttMessageAckService
{
    // Aliased to the shared vocabulary in MqttConstants for backward compatibility.
    public const STAGE_PUBLISHED = MqttConstants::STAGE_PUBLISHED;
    public const STAGE_PUBREC = MqttConstants::STAGE_PUBREC;
    public const STAGE_COMPLETED = MqttConstants::STAGE_COMPLETED;
    public const DIRECTION_UP = MqttConstants::DIRECTION_UP;
    public const DIRECTION_DOWN = MqttConstants::DIRECTION_DOWN;

    /**
     * Record the initial "published" state for a QoS 1/2 message leg.
     *
     * @param int $mqttMessageId Related mqtt_message row id.
     * @param int $direction MqttConstants::DIRECTION_UP or DIRECTION_DOWN.
     * @param string $receiverId Publisher or subscriber client identifier.
     * @param int $packetId Packet id for this leg.
     * @param int $qos QoS level (1 or 2).
     */
    public function markPublished(
        int    $mqttMessageId,
        int    $direction,
        string $receiverId,
        int    $packetId,
        int    $qos): void
    {
        $model = new MqttMessageAck();
        $model->setAttributes([
            'mqtt_message_id' => $mqttMessageId,
            'direction' => $direction,
            'receiver_id' => $receiverId,
            'packet_id' => $packetId,
            'qos' => $qos,
            'stage' => MqttConstants::STAGE_PUBLISHED,
            'created_at' => date('Y-m-d H:i:s'),
        ], false);
        $model->save(false);
    }

    /**
     * Update the ack stage for a given packet id / receiver / direction.
     *
     * @param int $direction MqttConstants::DIRECTION_UP or DIRECTION_DOWN.
     * @param string $receiverId Publisher or subscriber client identifier.
     * @param int $packetId Packet id for this leg.
     * @param int $stage New stage (STAGE_PUBLISHED / PUBREC / COMPLETED).
     * @param string|null $timeColumn Optional column to stamp with the current time.
     */
    public function updateStage(
        int     $direction,
        string  $receiverId,
        int     $packetId,
        int     $stage,
        ?string $timeColumn = null): bool
    {
        $row = MqttMessageAck::find()
            ->where(['direction' => $direction, 'receiver_id' => $receiverId, 'packet_id' => $packetId])
            ->one();

        if ($row === null) {
            return false;
        }

        $row->stage = $stage;
        if ($timeColumn !== null) {
            $row->{$timeColumn} = date('Y-m-d H:i:s');
        }
        $row->save(false);

        return true;
    }

    /**
     * Check whether an ack row exists (tells up-leg vs down-leg PUBREC apart).
     *
     * @param int $direction MqttConstants::DIRECTION_UP or DIRECTION_DOWN.
     * @param string $receiverId Publisher or subscriber client identifier.
     * @param int $packetId Packet id for this leg.
     */
    public function exists(int $direction, string $receiverId, int $packetId): bool
    {
        return MqttMessageAck::find()
            ->where(['direction' => $direction, 'receiver_id' => $receiverId, 'packet_id' => $packetId])
            ->exists();
    }
}
