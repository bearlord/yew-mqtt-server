<?php

namespace App\Modules\Mqtt\Services;


use App\Models\Extension\MqttOfflineMessage;

class MqttOfflineMessageService
{
    /**
     * Persist an offline (buffered) message for later delivery.
     *
     * @param array<string, mixed> $data Message attributes (client_id, topic, message, qos, retain).
     */
    public function saveOfflineMessage(array $data): bool
    {
        $model = new MqttOfflineMessage();
        $model->setAttributes($data, false);
        $model->save(false);

        return true;
    }

    /**
     * Delete all buffered offline messages for a client.
     *
     * @param string $clientId Client identifier.
     */
    public function deleteOfflineMessageByClientId(string $clientId): bool
    {
        MqttOfflineMessage::deleteAll([
            'client_id' => $clientId
        ]);

        return true;
    }

}
