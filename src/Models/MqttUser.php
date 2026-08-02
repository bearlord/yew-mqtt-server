<?php

namespace App\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_user}}".
 *
 * @property int $id Primary key
 * @property string $user_name MQTT login username
 * @property string $password_hash Hashed password
 * @property int $is_active Account status: 1 = active, 0 = disabled
 * @property string $created_at Record creation time
 * @property string $updated_at Record update time
 * @property string|null $scram_salt SCRAM salt (base64)
 * @property int|null $scram_iterations SCRAM iteration count (i)
 * @property string|null $scram_stored_key SCRAM StoredKey (base64), derived from SaltedPassword
 * @property string|null $scram_server_key SCRAM ServerKey (base64), derived from SaltedPassword
 */
class MqttUser extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_user}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['user_name', 'password_hash', 'created_at', 'updated_at'], 'required'],
            [['is_active', 'scram_iterations'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['user_name', 'scram_salt', 'scram_stored_key', 'scram_server_key'], 'string', 'max' => 64],
            [['password_hash'], 'string', 'max' => 240],
        ];
    }

    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'user_name' => 'User Name',
            'password_hash' => 'Password Hash',
            'is_active' => 'Is Active',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'scram_salt' => 'Scram Salt',
            'scram_iterations' => 'Scram Iterations',
            'scram_stored_key' => 'Scram Stored Key',
            'scram_server_key' => 'Scram Server Key',
        ];
    }
}
