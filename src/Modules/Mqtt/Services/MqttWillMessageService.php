<?php

namespace App\Modules\Mqtt\Services;

use App\Models\Extension\MqttWillMessage;
use Yew\Framework\Db\BaseActiveRecord;
use Yew\Mqtt\Tools\ProtocolLevel;

/** Business service for MQTT Will (last-will) messages, published on unexpected disconnect. */
class MqttWillMessageService
{
    /**
     * Persist a Will to mqtt_will_messages so it survives broker restarts.
     *
     * Returns the new row id, or null when the will has no topic (invalid).
     *
     * @param array<string, mixed>|null $will Stored will payload (topic/message/qos/retain/properties).
     * @param string $clientId Owning client identifier.
     * @return int|null The persisted will id, or null when no will is present.
     */
    public function saveWill(?array $will, string $clientId): ?int
    {
        if (empty($will) || empty($will['topic'])) {
            return null;
        }

        $row = new MqttWillMessage();
        $row->client_id = $clientId;
        $row->will_topic = $will['topic'];
        $row->will_payload = $will['message'] ?? '';
        $row->will_qos = (int)($will['qos'] ?? 0);
        $row->will_retain = (int)($will['retain'] ?? 0);

        // MQTT 5 will properties (optional).
        if (!empty($will['properties'])) {
            $props = $will['properties'];
            $row->will_delay_interval = $props['will_delay_interval'] ?? null;
            $row->payload_format_indicator = $props['payload_format_indicator'] ?? null;
            $row->content_type = $props['content_type'] ?? null;
            $row->response_topic = $props['response_topic'] ?? null;
            $row->correlation_data = $props['correlation_data'] ?? null;
        }

        if (!$row->save(false)) {
            return null;
        }

        return $row->id;
    }

    /**
     * Drop a persisted will once it has been published (consumed).
     *
     * @param int|null $willId Persisted will id, or null when none was stored.
     */
    public function consumeWill(?int $willId): void
    {
        if ($willId === null) {
            return;
        }
        MqttWillMessage::deleteAll(['id' => $willId]);
    }

    /**
     * Publish a stored will message to its topic subscribers.
     * Reuses the normal publish pipeline; honours QoS/retain/routing.
     * MQTT 5 will_delay_interval defers publication via a Swoole timer.
     *
     * @param array<string, mixed>|null $will Stored will payload (topic/message/qos/retain/properties).
     * @param string $clientId Client whose session is ending.
     * @param int $protocolLevel MQTT protocol version.
     * @return bool True when a will was scheduled/published.
     */
    public function publishWill(?array $will, string $clientId, int $protocolLevel): bool
    {
        // No will configured for this connection.
        if (empty($will) || empty($will['topic'])) {
            return false;
        }

        $topic = $will['topic'];
        $message = $will['message'] ?? '';
        $qos = (int)($will['qos'] ?? 0);
        $retain = (int)($will['retain'] ?? 0);

        // Resolve MQTT 5 will properties (e.g. will_delay_interval).
        $delayInterval = 0;
        if ($protocolLevel === ProtocolLevel::PROTOCOL_LEVEL_V5 && !empty($will['properties'])) {
            $willProperty = MqttServices::willProperty();
            $will = $willProperty->applyWillProperties($will);
            $delayInterval = $willProperty->getWillDelayInterval($will);
        }

        // A Will is published by the broker itself, not by a connected client:
        // it is fanned out to current subscribers via the down-leg pipeline, which
        // mints real per-subscriber packet ids and tracks QoS acks. We deliberately
        // do NOT route it through publishProcess(), because that models an *up-leg*
        // client publish and would bookkeep a spurious ack row keyed on packet id 0
        // (no real publisher fd exists for a Will).
        $publish = fn() => MqttServices::publish()->publishServerMessage(
            $protocolLevel,
            $topic,
            $message,
            $qos,
            $retain,
            $clientId
        );

        // Defer publication when a will delay interval is configured.
        if ($delayInterval > 0) {
            \Swoole\Timer::after($delayInterval * 1000, $publish);

            return true;
        }

        $publish();

        return true;
    }
}
