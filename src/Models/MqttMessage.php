<?php

namespace App\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_message}}".
 *
 * @property int $id primary key
 * @property int $direction 1: up, 2: down
 * @property string|null $sender_id sender client id
 * @property string|null $receiver_id receiver client id
 * @property string $topic topic
 * @property string $payload payload
 * @property int $qos qos
 * @property int $retain retain
 * @property string $published_time published time
 * @property string|null $created_at created at
 * @property string|null $updated_at updated at
 */
class MqttMessage extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_message}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['direction', 'qos', 'retain'], 'integer'],
            [['topic', 'payload', 'published_time'], 'required'],
            [['payload'], 'string'],
            [['published_time', 'created_at', 'updated_at'], 'safe'],
            [['sender_id', 'receiver_id'], 'string', 'max' => 128],
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
            'direction' => 'Direction',
            'sender_id' => 'Sender ID',
            'receiver_id' => 'Receiver ID',
            'topic' => 'Topic',
            'payload' => 'Payload',
            'qos' => 'Qos',
            'retain' => 'Retain',
            'published_time' => 'Published Time',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
