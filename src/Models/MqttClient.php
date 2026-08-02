<?php

namespace App\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_client}}".
 *
 * @property int $id Primary key
 * @property string $client_id MQTT client identifier
 * @property string|null $user_name Authentication username
 * @property string $protocol_level MQTT protocol level (3.1, 3.1.1, 5.0)
 * @property int $clean_start MQTT v5 clean start flag (1: clean session, 0: resume session)
 * @property int|null $session_expiry_interval Session expiry interval in seconds (0 = never expire)
 * @property int $is_active Client enabled flag (1: active, 0: disabled)
 * @property string|null $ip_address Last connected IP address (IPv4/IPv6)
 * @property int|null $keep_alive Keep alive interval in seconds
 * @property string|null $last_connected_time Last successful connection time
 * @property string|null $last_disconnected_time Last disconnection time detected by broker
 * @property int|null $disconnect_reason MQTT v5 DISCONNECT reason code
 * @property string $created_at Record creation time
 * @property string $updated_at Record last update time
 */
class MqttClient extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_client}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['client_id'], 'required'],
            [['clean_start', 'session_expiry_interval', 'is_active', 'keep_alive', 'disconnect_reason'], 'integer'],
            [['last_connected_time', 'last_disconnected_time', 'created_at', 'updated_at'], 'safe'],
            [['client_id'], 'string', 'max' => 128],
            [['user_name'], 'string', 'max' => 64],
            [['protocol_level'], 'string', 'max' => 8],
            [['ip_address'], 'string', 'max' => 45],
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
            'user_name' => 'User Name',
            'protocol_level' => 'Protocol Level',
            'clean_start' => 'Clean Start',
            'session_expiry_interval' => 'Session Expiry Interval',
            'is_active' => 'Is Active',
            'ip_address' => 'Ip Address',
            'keep_alive' => 'Keep Alive',
            'last_connected_time' => 'Last Connected Time',
            'last_disconnected_time' => 'Last Disconnected Time',
            'disconnect_reason' => 'Disconnect Reason',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
