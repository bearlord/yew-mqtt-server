<?php

namespace App\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_acl}}".
 *
 * @property int $id
 * @property int $user_id Associated MQTT user ID
 * @property string|null $client_id Bound Client ID. Null means no restriction
 * @property string $topic MQTT Topic, supports + and # wildcards
 * @property int $access_type Permission type: 1=Subscribe, 2=Publish, 3=ReadWrite
 * @property string|null $created_at Record creation time
 * @property string|null $updated_at Record update time
 */
class MqttAcl extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_acl}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['user_id', 'topic'], 'required'],
            [['user_id', 'access_type'], 'integer'],
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
            'user_id' => 'User ID',
            'client_id' => 'Client ID',
            'topic' => 'Topic',
            'access_type' => 'Access Type',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
