<?php

namespace App\Modules\Mqtt\Services;

/**
 * Service locator for MQTT broker services.
 *
 * @method static MqttClientService           client()            Client connection management
 * @method static MqttSubscriptionService     subscription()      Subscription tracking
 * @method static MqttSessionService          session()           Session persistence
 * @method static MqttUserService             user()              User records
 * @method static MqttAuthService             auth()              Authentication
 * @method static MqttSaslAuthenticationService sasl()            SASL authentication
 * @method static MqttPublishService          publish()          Publish handling
 * @method static MqttDownlinkService         downlink()          Downlink dispatch
 * @method static MqttMessageService          message()          Message store
 * @method static MqttMessageAckService       ack()              Message acknowledgement
 * @method static MqttRetainedMessageService  retained()         Retained messages
 * @method static MqttOfflineMessageService   offline()          Offline messages
 * @method static MqttOfflineMessageDeliveryService offlineDelivery() Offline message delivery
 * @method static MqttWillMessageService      will()             Will messages
 * @method static MqttWillPropertyService     willProperty()     Will message properties
 */
final class MqttServices
{
    /**
     * @param array<int, mixed> $args
     */
    public static function __callStatic(string $name, array $args): mixed
    {
        $class = __NAMESPACE__ . '\\Mqtt' . ucfirst($name) . 'Service';

        return new $class(...$args);
    }
}
