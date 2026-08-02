<?php

namespace App\Modules\Mqtt\Services;

use App\Models\Extension\MqttOfflineMessage;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\Uid\GetUid;

/** Delivers buffered offline messages when a client reconnects (clean_start = 0). */
class MqttOfflineMessageDeliveryService
{
    use GetBoostSend;
    use GetUid;

    /**
     * Deliver all buffered offline messages to the client's current fd; returns count delivered.
     *
     * @param string $clientId Reconnecting client identifier.
     * @param int $fd Client's current connection fd.
     * @param int $protocolLevel MQTT protocol version.
     */
    public function deliverOfflineMessages(string $clientId, int $fd, int $protocolLevel): int
    {
        $messages = (new MqttOfflineMessage())->where(['client_id' => $clientId])->all();
        if (empty($messages)) {
            return 0;
        }

        $downlink = MqttServices::downlink();
        $count = 0;
        foreach ($messages as $msg) {
            $downlink->send(
                $fd,
                $clientId,
                (string)$msg->topic,
                (string)$msg->message,
                (int)$msg->qos,
                $protocolLevel,
                (int)$msg->retain
            );
            $count++;
        }

        (new MqttOfflineMessage())->deleteAll(['client_id' => $clientId]);

        return $count;
    }
}
