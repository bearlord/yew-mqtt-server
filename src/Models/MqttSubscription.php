<?php

namespace App\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_subscription}}".
 *
 * @property int $id Primary key
 * @property string $client_id MQTT client identifier
 * @property string $topic Subscribed topic filter, supports + and # wildcards
 * @property int $qos Subscription QoS level (0, 1, 2)
 * @property string|null $created_at Record creation time
 * @property string|null $updated_at Record update time
 */
class MqttSubscription extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_subscription}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['client_id', 'topic'], 'required'],
            [['qos'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['client_id'], 'string', 'max' => 128],
            [['topic'], 'string', 'max' => 240],
        ];
    }

    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'client_id' => 'Client ID',
            'topic' => 'Topic',
            'qos' => 'Qos',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
