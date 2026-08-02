<?php

namespace App\Modules\Mqtt\Services;

use App\Models\Extension\MqttSubscription;
use Yew\Mqtt\Hex\ReasonCode;
use Yew\Mqtt\Message\Publish;
use Yew\Mqtt\Message\SubAck;
use Yew\Mqtt\Message\UnSubAck;
use Yew\Mqtt\Tools\ProtocolLevel;
use Yew\Mqtt\Tools\TopicValidator;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Connection\GetConnection;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\Topic\GetTopic;

class MqttSubscriptionService
{
    use GetConnection;
    use GetBoostSend;
    use GetTopic;

    /**
     * Persist a single subscription record.
     *
     * @param array<string, mixed> $data Subscription attributes (client_id, topic, qos).
     */
    public function saveSubscription(array $data): bool
    {
        $model = new MqttSubscription();
        $model->setAttributes($data);
        $model->save(false);

        return true;
    }

    /**
     * Delete every subscription owned by a client (disconnect / session drop).
     *
     * @param string $clientId Client identifier.
     */
    public function deleteSubscriptionsByClientId(string $clientId): bool
    {
        MqttSubscription::deleteAll([
            'client_id' => $clientId
        ]);

        return true;

    }

    /**
     * Delete a single subscription for a client on a given topic.
     *
     * @param string $clientId Client identifier.
     * @param string $topic Topic filter to remove.
     */
    public function deleteSubscription(string $clientId, string $topic): bool
    {
        MqttSubscription::deleteAll([
            'client_id' => $clientId,
            'topic' => $topic,
        ]);

        return true;
    }

    /**
     * Handle SUBSCRIBE: validate filters, grant QoS, persist, register routing, reply SUBACK.
     *
     * @param int $fd Subscriber connection fd.
     * @param array $data Decoded subscribe payload. Expected keys:
     *                    protocol_level, client_id, data[message_id], data[topics].
     */
    public function subscribeProcess(int $fd, array $data): void
    {
        $protocolLevel = (int)($data['protocol_level'] ?? 4);
        $clientId      = (string)($data['client_id'] ?? '');
        $messageId     = (int)($data['data']['message_id'] ?? 0);
        $topics        = $data['data']['topics'] ?? null;

        // Required fields missing → drop the connection.
        if (empty($messageId) || empty($topics)) {
            Server::$instance->closeFd($fd);
            return;
        }

        // 1. Pick the reason code used for an invalid topic, per protocol level.
        if ($protocolLevel === ProtocolLevel::PROTOCOL_LEVEL_V3_1_1) {
            $invalidReasonCode = ReasonCode::UNSPECIFIED_ERROR;
        } elseif ($protocolLevel === ProtocolLevel::PROTOCOL_LEVEL_V5) {
            $invalidReasonCode = ReasonCode::TOPIC_FILTER_INVALID;
        } else {
            // Unsupported protocol level → drop the connection.
            Server::$instance->closeFd($fd);
            return;
        }

        // Validate each filter and compute the granted QoS (min(requested, 2)).
        $codes          = [];
        $grantedQos     = [];
        $topicValidator = new TopicValidator();
        foreach ($topics as $topic => $options) {
            $validateResult = $topicValidator->validateFilter($topic);
            if (!$validateResult) {
                $codes[] = $invalidReasonCode;
                continue;
            }
            $granted            = min($options['qos'], 2);
            $codes[]            = $granted;
            $grantedQos[$topic] = $granted;
        }

        // Pack and send the SUBACK (one reason code per requested topic).
        $subAckMessage = (new SubAck());
        $subAckMessage->setProtocolLevel($protocolLevel)
            ->setMessageId($messageId)
            ->setCodes($codes);

        $this->autoBoostSend($fd, $subAckMessage->getContents());

        // Persist each *valid* subscription and register it into the routing
        // table. Invalid filters are deliberately skipped: they already received
        // a failure reason code in the SUBACK above and must NOT create a routing
        // entry or receive retained messages, otherwise a malformed filter would
        // silently become a live subscription.
        $uid                    = $this->getFdSession($fd, 'uid');
        $retainedMessageService = MqttServices::retained();

        foreach ($grantedQos as $topic => $granted) {
            $this->saveSubscription([
                'client_id' => $clientId,
                'topic' => $topic,
                'qos' => $granted,
            ]);

            // Bind the topic to the subscriber uid for downstream PUBLISH routing.
            $this->addSubscription($topic, $uid);

            // Deliver any retained message matching the filter (MQTT 3.3.1.3 /
            // 4.3.2). Delivered QoS = min(granted, retained).
            $retainedMessages = $retainedMessageService->getRetainedByFilter($topic);
            foreach ($retainedMessages as $retained) {
                $retainQos  = (int)$retained->getAttribute('qos');
                $deliverQos = min($granted, $retainQos);

                $publishMessage = (new Publish())
                    ->setProtocolLevel($protocolLevel)
                    ->setQos($deliverQos)
                    ->setRetain(1)
                    ->setTopic($retained->getAttribute('topic'))
                    ->setMessage($retained->getAttribute('payload'));

                $this->autoBoostSend($fd, $publishMessage->getContents());
            }
        }
    }

    /**
     * Handle UNSUBSCRIBE: remove routing and persisted records, reply UNSUBACK.
     *
     * @param int $fd Subscriber connection fd.
     * @param array $data Decoded unsubscribe payload. Expected keys:
     *                    protocol_level, client_id, data[message_id], data[topics].
     */
    public function unsubscribeProcess(int $fd, array $data): void
    {
        $protocolLevel = (int)($data['protocol_level'] ?? 4);
        $clientId      = (string)($data['client_id'] ?? '');
        $messageId     = (int)($data['data']['message_id'] ?? 0);
        $topics        = $data['data']['topics'] ?? null;

        // Required fields missing → drop the connection.
        if (empty($messageId) || empty($topics)) {
            Server::$instance->closeFd($fd);
            return;
        }

        // Only MQTT 3.1.1 and MQTT 5 are supported (mirrors subscribe).
        $supported = $protocolLevel === ProtocolLevel::PROTOCOL_LEVEL_V3_1_1
            || $protocolLevel === ProtocolLevel::PROTOCOL_LEVEL_V5;
        if (!$supported) {
            Server::$instance->closeFd($fd);
            return;
        }

        $uid            = $this->getFdSession($fd, 'uid');
        $topicValidator = new TopicValidator();

        foreach ($topics as $topic) {
            // Skip invalid filters but still send UNSUBACK for valid ones.
            if (!$topicValidator->validateFilter($topic)) {
                continue;
            }

            $this->removeSubscription($topic, $uid);
            $this->deleteSubscription($clientId, $topic);
        }

        // Echo the original packet id in the UNSUBACK.
        $unSubAckMessage = (new UnSubAck());
        $unSubAckMessage->setProtocolLevel($protocolLevel)
            ->setMessageId($messageId);

        $this->autoBoostSend($fd, $unSubAckMessage->getContents());
    }
}
