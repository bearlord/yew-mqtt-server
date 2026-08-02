<?php

namespace App\Models;

use Yew\Yew;

/**
 * This is the model class for table "{{%mqtt_will_property}}".
 *
 * @property int $id Primary key
 * @property int $will_id Reference to mqtt_will_messages.id
 * @property string $name User property name
 * @property string|null $value User property value
 * @property string $created_at Record creation time
 */
class MqttWillProperty extends \Yew\Framework\Db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName(): string
    {
        return '{{%mqtt_will_property}}';
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['will_id', 'name'], 'required'],
            [['will_id'], 'integer'],
            [['created_at'], 'safe'],
            [['name'], 'string', 'max' => 128],
            [['value'], 'string', 'max' => 512],
        ];
    }

    /**
     * @return array
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'will_id' => 'Will ID',
            'name' => 'Name',
            'value' => 'Value',
            'created_at' => 'Created At',
        ];
    }
}
