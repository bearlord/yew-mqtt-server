<?php

namespace App\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_retained_message}}".
 *
 * @property int $id Primary key
 * @property string $topic Retained message topic
 * @property resource $payload Retained message payload
 * @property int $qos Retained message QoS level (0, 1, 2)
 * @property int $retain Retain flag: 1 = retained, 0 = not retained
 * @property string|null $created_at Record creation time
 * @property string|null $updated_at Record update time
 */
class MqttRetainedMessage extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_retained_message}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['topic', 'payload'], 'required'],
            [['payload'], 'string'],
            [['qos', 'retain'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
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
            'topic' => 'Topic',
            'payload' => 'Payload',
            'qos' => 'Qos',
            'retain' => 'Retain',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
