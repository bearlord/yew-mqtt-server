<?php

namespace App\Models\Extension;

use Carbon\Carbon;
use Yew\Framework\Behaviors\AttributeTypecastBehavior;
use Yew\Framework\Behaviors\TimestampBehavior;
use Yew\Framework\Db\BaseActiveRecord;

class MqttSession extends \App\Models\MqttSession
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
                // Generates a microsecond-precision timestamp matching the DATETIME(6) column, e.g. 2026-07-28 14:23:45.123456
                'value' => function () {
                    return Carbon::now()->format('Y-m-d H:i:s.u');
                },
            ],
            'typecast' => [
                'class' => AttributeTypecastBehavior::class,
                'attributeTypes' => [
                    // Primary key
                    'id' => AttributeTypecastBehavior::TYPE_INTEGER,
                    // Client identifier and broker session id
                    'client_id' => AttributeTypecastBehavior::TYPE_STRING,
                    'session_id' => AttributeTypecastBehavior::TYPE_STRING,
                    // Clean start and session expiry interval
                    'clean_start' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'session_expiry' => AttributeTypecastBehavior::TYPE_INTEGER,
                    // Online status and broker node
                    'is_online' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'node' => AttributeTypecastBehavior::TYPE_STRING,
                    // DATETIME(6) connection / disconnection timestamps kept as strings
                    'connected_at' => AttributeTypecastBehavior::TYPE_STRING,
                    'disconnected_at' => AttributeTypecastBehavior::TYPE_STRING,
                    // Reference to mqtt_will_messages.id
                    'will_id' => AttributeTypecastBehavior::TYPE_INTEGER,
                    // DATETIME(6) record timestamps kept as strings
                    'created_at' => AttributeTypecastBehavior::TYPE_STRING,
                    'updated_at' => AttributeTypecastBehavior::TYPE_STRING,
                ],
                // Typecast attributes after successful validation (convert request string inputs to proper types)
                'typecastAfterValidate' => true,
                // No need to typecast before save; the DB column type enforces the cast itself
                'typecastBeforeSave' => false,
                // Key: typecast after find so integers in JSON responses are emitted without quotes
                'typecastAfterFind' => true,
            ],
        ];
    }
}
