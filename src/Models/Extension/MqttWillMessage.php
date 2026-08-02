<?php

namespace App\Models\Extension;

use Carbon\Carbon;
use Yew\Framework\Behaviors\AttributeTypecastBehavior;
use Yew\Framework\Behaviors\TimestampBehavior;
use Yew\Framework\Db\BaseActiveRecord;

class MqttWillMessage extends \App\Models\MqttWillMessage
{
    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    BaseActiveRecord::EVENT_BEFORE_INSERT => ['created_at'],
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
                    // Client identifier and will topic
                    'client_id' => AttributeTypecastBehavior::TYPE_STRING,
                    'will_topic' => AttributeTypecastBehavior::TYPE_STRING,
                    // Will payload (longtext) kept as string
                    'will_payload' => AttributeTypecastBehavior::TYPE_STRING,
                    // Will QoS, retain flag, delay interval
                    'will_qos' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'will_retain' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'will_delay_interval' => AttributeTypecastBehavior::TYPE_INTEGER,
                    // Payload format indicator
                    'payload_format_indicator' => AttributeTypecastBehavior::TYPE_INTEGER,
                    // MQTT v5 string properties
                    'content_type' => AttributeTypecastBehavior::TYPE_STRING,
                    'response_topic' => AttributeTypecastBehavior::TYPE_STRING,
                    // Correlation data (binary) kept as string
                    'correlation_data' => AttributeTypecastBehavior::TYPE_STRING,
                    // DATETIME(6) creation timestamp
                    'created_at' => AttributeTypecastBehavior::TYPE_STRING,
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
