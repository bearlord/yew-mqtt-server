<?php

namespace App\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_will_message}}".
 *
 * @property int $id Primary key
 * @property string $client_id MQTT client identifier
 * @property string $will_topic Will topic
 * @property string|null $will_payload Will message payload
 * @property int $will_qos Will QoS level (0,1,2)
 * @property int $will_retain Will retain flag (0: not retain, 1: retain)
 * @property int $will_delay_interval MQTT v5 will delay interval in seconds
 * @property int|null $payload_format_indicator MQTT v5 payload format indicator (0: UTF-8, 1: binary)
 * @property string|null $content_type MQTT v5 content type
 * @property string|null $response_topic MQTT v5 response topic
 * @property resource|null $correlation_data MQTT v5 correlation data
 * @property string $created_at Record creation time
 */
class MqttWillMessage extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_will_message}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['client_id', 'will_topic', 'will_qos'], 'required'],
            [['will_payload', 'correlation_data'], 'string'],
            [['will_qos', 'will_retain', 'will_delay_interval', 'payload_format_indicator'], 'integer'],
            [['created_at'], 'safe'],
            [['client_id', 'content_type'], 'string', 'max' => 128],
            [['will_topic', 'response_topic'], 'string', 'max' => 240],
            [['client_id'], 'unique'],
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
            'will_topic' => 'Will Topic',
            'will_payload' => 'Will Payload',
            'will_qos' => 'Will Qos',
            'will_retain' => 'Will Retain',
            'will_delay_interval' => 'Will Delay Interval',
            'payload_format_indicator' => 'Payload Format Indicator',
            'content_type' => 'Content Type',
            'response_topic' => 'Response Topic',
            'correlation_data' => 'Correlation Data',
            'created_at' => 'Created At',
        ];
    }
}
