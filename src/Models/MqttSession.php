<?php

namespace App\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_session}}".
 *
 * @property int $id Primary key
 * @property string $client_id MQTT client identifier
 * @property string $session_id Broker internal session identifier (node+uuid)
 * @property int $clean_start MQTT v5 clean start flag (1: clean, 0: resume)
 * @property int $session_expiry Session expiry interval in seconds (0 = never expire)
 * @property int|null $is_online Session online status (1: online, 0: offline)
 * @property string|null $node Broker node name (for cluster mode)
 * @property string|null $connected_at Session connection time (UTC)
 * @property string|null $disconnected_at Session disconnection time (UTC)
 * @property int|null $will_id Reference to mqtt_will_messages.id
 * @property string $created_at Record creation time (UTC)
 * @property string $updated_at Record last update time (UTC)
 */
class MqttSession extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_session}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['client_id', 'session_id', 'clean_start', 'session_expiry'], 'required'],
            [['clean_start', 'session_expiry', 'is_online', 'will_id'], 'integer'],
            [['connected_at', 'disconnected_at', 'created_at', 'updated_at'], 'safe'],
            [['client_id'], 'string', 'max' => 128],
            [['session_id', 'node'], 'string', 'max' => 64],
            [['client_id', 'session_id'], 'unique', 'targetAttribute' => ['client_id', 'session_id']],
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
            'session_id' => 'Session ID',
            'clean_start' => 'Clean Start',
            'session_expiry' => 'Session Expiry',
            'is_online' => 'Is Online',
            'node' => 'Node',
            'connected_at' => 'Connected At',
            'disconnected_at' => 'Disconnected At',
            'will_id' => 'Will ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
