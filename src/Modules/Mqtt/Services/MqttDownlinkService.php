<?php

namespace App\Modules\Mqtt\Services;

use App\Modules\Mqtt\Infrastructure\MqttConstants;
use Yew\Mqtt\Message\Publish;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\Topic\GetTopic;
use Yew\Plugins\Uid\GetUid;

/**
 * QoS-aware down-leg (broker -> subscriber) delivery.
 *
 * Shared by the live publish fan-out and the offline-message replay: builds
 * the Publish packet, mints a down-leg packet id, bookkeeps the ack row, and
 * sends it. Packet ids come from a monotonic counter that is kept separate
 * from client-supplied up-leg ids to avoid collisions.
 */
class MqttDownlinkService
{
    use GetBoostSend;
    use GetUid;
    use GetTopic;

    /** Monotonic down-leg packet id source. */
    private static int $downPacketId = 0;

    /** Mint a down-leg packet id in [1, 65535]. */
    public static function nextDownPacketId(): int
    {
        self::$downPacketId = self::$downPacketId >= 65535 ? 1 : self::$downPacketId + 1;
        return self::$downPacketId;
    }

    /**
     * Fan a published message out to every subscriber of a topic.
     *
     * @param string $topic Topic name being published.
     * @param string $message Payload.
     * @param int $qos Publisher-requested QoS (0/1/2).
     * @param string $senderClientId Publisher client identifier (excluded from delivery).
     * @param int $protocolLevel MQTT protocol version.
     * @param int $retain Whether to mark the message retained (0/1).
     * @param array<string, mixed> $properties MQTT 5 user properties / content-type.
     */
    public function deliverToSubscribers(
        string $topic,
        string $message,
        int    $qos,
        string $senderClientId,
        int    $protocolLevel,
        int    $retain = 0,
        array  $properties = []): void
    {
        $subscribers = $this->getSubscribers($topic);
        if (empty($subscribers)) {
            return;
        }

        foreach ($subscribers as $sub) {
            $uid = (string)$sub['uid'];
            $subQos = (int)($sub['qos'] ?? $qos);
            $deliverQos = min($qos, $subQos);
            $clientId = (string)($sub['client_id'] ?? $uid);
            $fd = (int)$this->getUidFd($uid);

            // Skip subscribers that are currently offline: buffer for later.
            if ($fd <= 0) {
                MqttServices::offline()->saveOfflineMessage([
                    'client_id' => $clientId,
                    'topic' => $topic,
                    'message' => $message,
                    'qos' => $deliverQos,
                    'retain' => $retain,
                ]);
                continue;
            }

            $this->send($fd, $clientId, $topic, $message, $deliverQos, $protocolLevel, $retain, $properties);
        }
    }

    /**
     * Send one message to one subscriber (used by offline replay too).
     *
     * @param int $fd Subscriber connection fd.
     * @param string $clientId Subscriber client identifier.
     * @param string $topic Topic name.
     * @param string $message Payload.
     * @param int $deliverQos Effective QoS (min publisher, subscriber).
     * @param int $protocolLevel MQTT protocol version.
     * @param int $retain Whether the message is retained (0/1).
     * @param array<string, mixed> $properties MQTT 5 user properties / content-type.
     */
    public function send(
        int    $fd,
        string $clientId,
        string $topic,
        string $message,
        int    $deliverQos,
        int    $protocolLevel,
        int    $retain = 0,
        array  $properties = []): void
    {
        $packetId = MqttConstants::isQosAcknowledged($deliverQos)
            ? self::nextDownPacketId()
            : 0;

        $packet = (new Publish())
            ->setQos($deliverQos)
            ->setMessageId($packetId)
            ->setTopic($topic)
            ->setMessage($message)
            ->setRetain($retain)
            ->setProperties($properties);

        $this->autoBoostSend($fd, $packet, $topic);

        if (MqttConstants::isQosAcknowledged($deliverQos)) {
            $mqttMessageId = MqttServices::message()->saveMessage([
                'client_id' => $clientId,
                'topic' => $topic,
                'message' => $message,
                'qos' => $deliverQos,
                'retain' => $retain,
                'direction' => MqttConstants::DIRECTION_DOWN,
            ]);

            MqttServices::ack()->markPublished(
                $mqttMessageId,
                MqttConstants::DIRECTION_DOWN,
                $clientId,
                $packetId,
                $deliverQos
            );
        }
    }
}
