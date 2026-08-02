<?php

namespace App\Modules\Mqtt\Services;


use App\Models\Extension\MqttMessage;

/** Business service for persisted MQTT messages (mqtt_message table). */
class MqttMessageService
{
    /**
     * Persist a message row; returns the inserted primary key.
     *
     * @param array<string, mixed> $data Message attributes (client_id, topic, message, qos, retain, direction).
     */
    public function saveMessage(array $data): int
    {
        $model = new MqttMessage();

        $model->setAttributes($data, false);
        $model->save(false);

        return (int)$model->getAttribute('id');
    }
}
