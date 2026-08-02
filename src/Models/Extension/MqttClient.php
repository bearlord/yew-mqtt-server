<?php

namespace App\Models\Extension;

use Carbon\Carbon;
use Yew\Framework\Behaviors\AttributeTypecastBehavior;
use Yew\Framework\Behaviors\TimestampBehavior;
use Yew\Framework\Db\BaseActiveRecord;

class MqttClient extends \App\Models\MqttClient
{
    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    BaseActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],

                'value' => function () {
                    return Carbon::now()->format('Y-m-d H:i:s.u');
                },
            ],
            'typecast' => [
                'class' => AttributeTypecastBehavior::class,
                'attributeTypes' => [
                    'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'client_id' => AttributeTypecastBehavior::TYPE_STRING,
                    'user_name' => AttributeTypecastBehavior::TYPE_STRING,
                    'protocol_level' => AttributeTypecastBehavior::TYPE_STRING,
                    'ip_address' => AttributeTypecastBehavior::TYPE_STRING,
                    'clean_start' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'session_expiry_interval' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'is_active' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'keep_alive' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'disconnect_reason' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'last_connected_time' => AttributeTypecastBehavior::TYPE_STRING,
                    'last_disconnected_time' => AttributeTypecastBehavior::TYPE_STRING,
                    'created_at' => AttributeTypecastBehavior::TYPE_STRING,
                    'updated_at' => AttributeTypecastBehavior::TYPE_STRING,
                ],
                // Typecast attributes after successful validation (convert request string inputs to proper types)
                'typecastAfterValidate' => true,
                // No need to typecast before save; the DB column type enforces the cast itself
                'typecastBeforeSave' => false,
                // Key: typecast after find so integers in JSON responses are emitted without quotes
                'typecastAfterFind' => true,
            ]
        ];
    }
}
