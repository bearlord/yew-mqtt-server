<?php

namespace App\Modules\Mqtt\Services;

use App\Modules\Mqtt\Infrastructure\MqttConstants;
use App\Modules\Mqtt\Infrastructure\QoSControlPacketFactory;
use Yew\Mqtt\Protocol\Types;
use Yew\Plugins\Connection\GetConnection;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Coroutine\Server\Server;

/**
 * Core publish flow for the MQTT broker.
 *
 * Responsibilities:
 *  - persist an incoming PUBLISH (up leg) and acknowledge it (PUBACK / PUBREC);
 *  - route the message to matching subscribers through MqttDownlinkService,
 *    which owns the QoS-aware down-leg delivery.
 *
 * Direction / stage terminology lives in MqttConstants so every service shares
 * one vocabulary (MqttMessageAckService aliases it for backward compatibility).
 */
class MqttPublishService
{
    use GetBoostSend;
    use GetConnection;

    // Kept for any external callers referencing the legacy constants.
    public const DIRECTION_UP = MqttConstants::DIRECTION_UP;
    public const DIRECTION_DOWN = MqttConstants::DIRECTION_DOWN;

    public function __construct(
        private readonly QoSControlPacketFactory $qosFactory = new QoSControlPacketFactory(),
    )
    {
    }

    /**
     * Handle a client PUBLISH (up leg).
     *
     * @param int $fd Publisher connection fd (used to ack it).
     * @param array $data Decoded publish payload. Expected keys:
     *                    protocol_level, client_id, topic, message,
     *                    qos, retain, message_id.
     */
    public function publishProcess(int $fd, array $data): void
    {
        $protocolLevel = (int)($data['protocol_level'] ?? 4);
        $clientId      = (string)($data['client_id'] ?? '');
        $topic         = $data['data']['topic'] ?? $data['topic'] ?? null;
        $message       = $data['data']['message'] ?? $data['message'] ?? null;
        $qos           = (int)($data['data']['qos'] ?? $data['qos'] ?? 0);
        $retain        = (int)($data['data']['retain'] ?? $data['retain'] ?? 0);
        $messageId     = (int)($data['data']['message_id'] ?? $data['message_id'] ?? 0);

        // Required fields missing → drop the connection.
        if (empty($topic) || empty($message)) {
            Server::$instance->closeFd($fd);
            return;
        }

        // Persist the inbound message and bookkeep the up-leg ack row.
        $mqttMessageId = MqttServices::message()->saveMessage([
            'client_id' => $clientId,
            'topic' => $topic,
            'message' => $message,
            'qos' => $qos,
            'retain' => $retain,
            'direction' => MqttConstants::DIRECTION_UP,
        ]);

        MqttServices::ack()->markPublished(
            $mqttMessageId,
            MqttConstants::DIRECTION_UP,
            $clientId,
            $messageId,
            $qos
        );

        // Acknowledge the publisher (QoS 1 -> PUBACK, QoS 2 -> PUBREC).
        if (MqttConstants::isQosAcknowledged($qos) && $fd > 0) {
            $type = $qos === MqttConstants::QOS_EXACTLY_ONCE ? Types::PUBREC : Types::PUBACK;
            $this->autoBoostSend($fd, $this->qosFactory->create($type, $messageId));
        }

        // Retain handling.
        if ($retain === 1) {
            MqttServices::retained()->saveRetainedMessage([
                'topic' => $topic,
                'payload' => $message,
                'qos' => $qos,
            ]);
        }

        // Fan out to subscribers.
        MqttServices::downlink()->deliverToSubscribers(
            $topic,
            $message,
            $qos,
            $clientId,
            $protocolLevel,
            $retain,
            $this->buildProperties()
        );
    }

    /**
     * Server-originated publish (Will messages, broker notices).
     *
     * Unlike publishProcess() this does NOT bookkeep an up-leg (publisher) ack
     * row, because the broker is the publisher and there is no client fd waiting
     * for PUBACK/PUBREC. The message is fanned out to subscribers via the
     * down-leg pipeline, which owns QoS-aware ack tracking and offline buffering.
     * Retained server messages are stored as retained.
     *
     * @param int $protocolLevel MQTT protocol version.
     * @param string $topic Topic name.
     * @param string $message Payload.
     * @param int $qos QoS (0/1/2).
     * @param int $retain Whether the message is retained (0/1).
     * @param string $senderClientId Client id to exclude from delivery (the will owner).
     */
    public function publishServerMessage(
        int    $protocolLevel,
        string $topic,
        string $message,
        int    $qos,
        int    $retain = 0,
        string $senderClientId = ''): void
    {
        // Retain handling: a retained Will stays available for future subscribers.
        if ($retain === 1) {
            MqttServices::retained()->saveRetainedMessage([
                'topic' => $topic,
                'payload' => $message,
                'qos' => $qos,
            ]);
        }

        // Fan out to subscribers; downlink owns QoS-aware down-leg ack bookkeeping.
        MqttServices::downlink()->deliverToSubscribers(
            $topic,
            $message,
            $qos,
            $senderClientId,
            $protocolLevel,
            $retain,
            $this->buildProperties()
        );
    }

    /**
     * Build the properties array passed down to subscribers.
     * (MQTT 5 would copy user properties / content-type here.)
     */
    private function buildProperties(): array
    {
        return [];
    }

    /**
     * QoS 2 (up leg): handle PUBREC from the publishing client, reply PUBREL.
     *
     * @param int $fd Publisher connection fd.
     * @param array $data Decoded pubrec payload. Expected keys:
     *                    client_id, protocol_level, data[message_id].
     */
    public function pubrecProcess(int $fd, array $data): void
    {
        $clientId  = (string)($data['client_id'] ?? '');
        $messageId = (int)($data['data']['message_id'] ?? 0);

        MqttServices::ack()->updateStage(
            MqttConstants::DIRECTION_UP,
            $clientId,
            $messageId,
            MqttConstants::STAGE_PUBREC,
            'pubrec_at'
        );

        $this->autoBoostSend($fd, $this->qosFactory->create(Types::PUBREL, $messageId));
    }

    /**
     * QoS 2 (up leg): handle PUBREL from the publishing client, reply PUBCOMP.
     *
     * @param int   $fd   Publisher connection fd.
     * @param array $data Decoded pubrel payload. Expected keys:
     *                    client_id, data[message_id].
     */
    public function pubrelProcess(int $fd, array $data): void
    {
        $clientId  = (string)($data['client_id'] ?? '');
        $messageId = (int)($data['data']['message_id'] ?? 0);

        // Required fields missing → drop the connection.
        if (empty($messageId)) {
            Server::$instance->closeFd($fd);
            return;
        }

        MqttServices::ack()->updateStage(
            MqttConstants::DIRECTION_UP,
            $clientId,
            $messageId,
            MqttConstants::STAGE_COMPLETED,
            'completed_at'
        );

        $this->autoBoostSend($fd, $this->qosFactory->create(Types::PUBCOMP, $messageId));
    }

    /**
     * QoS 2 (down leg): handle PUBCOMP from a subscriber; mark down-leg completed.
     *
     * @param int   $fd   Subscriber connection fd.
     * @param array $data Decoded pubcomp payload. Expected keys:
     *                    client_id, data[message_id].
     */
    public function pubcompProcess(int $fd, array $data): void
    {
        $clientId  = (string)($data['client_id'] ?? '');
        $messageId = (int)($data['data']['message_id'] ?? 0);

        // Required fields missing → drop the connection.
        if (empty($messageId)) {
            Server::$instance->closeFd($fd);
            return;
        }

        MqttServices::ack()->updateStage(
            MqttConstants::DIRECTION_DOWN,
            $clientId,
            $messageId,
            MqttConstants::STAGE_COMPLETED,
            'completed_at'
        );
    }

    /**
     * QoS 1 (down leg): handle PUBACK from a subscriber; mark down-leg completed.
     *
     * @param int   $fd   Subscriber connection fd.
     * @param array $data Decoded puback payload. Expected keys:
     *                    client_id, data[message_id].
     */
    public function pubackDownProcess(int $fd, array $data): void
    {
        $clientId  = (string)($data['client_id'] ?? '');
        $messageId = (int)($data['data']['message_id'] ?? 0);

        // Required fields missing → drop the connection.
        if (empty($messageId)) {
            Server::$instance->closeFd($fd);
            return;
        }

        MqttServices::ack()->updateStage(
            MqttConstants::DIRECTION_DOWN,
            $clientId,
            $messageId,
            MqttConstants::STAGE_COMPLETED,
            'completed_at'
        );
    }

    /**
     * QoS 2 (down leg): handle PUBREC from a subscriber, reply PUBREL.
     *
     * @param int $fd Subscriber connection fd.
     * @param array $data Decoded pubrec payload. Expected keys:
     *                    client_id, protocol_level, data[message_id].
     */
    public function pubrecDownProcess(int $fd, array $data): void
    {
        $clientId  = (string)($data['client_id'] ?? '');
        $messageId = (int)($data['data']['message_id'] ?? 0);

        MqttServices::ack()->updateStage(
            MqttConstants::DIRECTION_DOWN,
            $clientId,
            $messageId,
            MqttConstants::STAGE_PUBREC,
            'pubrec_at'
        );

        $this->autoBoostSend($fd, $this->qosFactory->create(Types::PUBREL, $messageId));
    }
}
