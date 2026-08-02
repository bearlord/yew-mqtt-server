<?php

namespace App\Modules\Mqtt\Controllers;

use App\Modules\Mqtt\Services\MqttClientService;
use App\Modules\Mqtt\Services\MqttConnectService;
use App\Modules\Mqtt\Services\MqttEnhancedAuthService;
use App\Modules\Mqtt\Services\MqttUserService;
use App\Modules\Mqtt\Services\MqttMessageAckService;
use App\Modules\Mqtt\Services\MqttPublishService;
use App\Modules\Mqtt\Services\MqttSubscriptionService;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Framework\Controller;
use Yew\Mqtt\Hex\ReasonCode;
use Yew\Mqtt\Tools\ProtocolLevel;
use Yew\Plugins\Connection\GetConnection;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\Route\Annotation\RequestMapping;
use Yew\Plugins\Route\Annotation\WsController;
use Yew\Plugins\Uid\GetUid;

/**
 * @WsController("mqtt-websocket")
 */
class MqttWebsocketController extends Controller
{

    use GetBoostSend;
    use GetLogger;
    use GetUid;
    use GetConnection;

    public function __construct(
        private readonly MqttUserService $userService,
    )
    {
    }

    /**
     * Per-connection CONNECT context for an MQTT 5 enhanced (SASL) auth exchange, keyed by fd.
     *
     * @RequestMapping("connect")
     */
    public function actionConnect(): void
    {
        $fd         = $this->clientData->getFd();
        $clientData = $this->clientData->getData();
        $ipAddress  = $this->clientData->getClientInfo()->getRemoteIp();

        // All CONNECT handling (validation, plain/SASL auth, session setup) is
        // delegated to the connect service; the controller only adapts the request.
        (new MqttConnectService($this->userService))->connectProcess($fd, $clientData, $ipAddress);
    }

    /**
     * @RequestMapping("auth")
     */
    public function actionAuth(): void
    {
        $fd         = $this->clientData->getFd();
        $clientData = $this->clientData->getData();

        $clientId = $clientData['client_id'] ?? null;
        if (empty($clientId)) {
            Server::$instance->closeFd($fd);
            return;
        }

        $code     = (int)($clientData['data']['code'] ?? ReasonCode::CONTINUE_AUTHENTICATION);
        $authData = $clientData['data']['authentication_data'] ?? '';
        $reAuth   = ($code === ReasonCode::RE_AUTHENTICATE);

        // Re-auth has no stored CONNECT context; assemble a minimal one so the
        // session is kept rather than re-created on success (handled by the service).
        $reAuthContext = null;
        if ($reAuth) {
            $reAuthMethod = $clientData['data']['authentication_method'] ?? '';
            if ($reAuthMethod === '') {
                Server::$instance->closeFd($fd);
                return;
            }
            $reAuthContext = [
                'method' => $reAuthMethod,
                'username' => $clientData['data']['username'] ?? null,
                'protocol_level' => $clientData['protocol_level'] ?? ProtocolLevel::PROTOCOL_LEVEL_V5,
                'ip_address' => $this->clientData->getClientInfo()->getRemoteIp(),
            ];
        }

        (new MqttEnhancedAuthService($this->userService))->advanceEnhancedAuth($fd, $clientId, (string)$authData, $reAuth, $reAuthContext);
    }

    /**
     * @RequestMapping("disconnect")
     */
    public function actionDisconnect(): void
    {
        $fd         = $this->clientData->getFd();
        $clientData = $this->clientData->getData();

        (new MqttClientService())->disconnectProcess($fd, $clientData);
    }

    /**
     * @RequestMapping("pingreq")
     */
    public function actionPingreq(): void
    {
        $fd         = $this->clientData->getFd();
        $clientData = $this->clientData->getData();

        $clientId = $clientData['client_id'];

        (new MqttClientService())->pingreqProcess($clientId, $fd);
    }


    /**
     * @RequestMapping("subscribe")
     */
    public function actionSubscribe(): void
    {
        $fd         = $this->clientData->getFd();
        $clientData = $this->clientData->getData();

        // The whole decoded frame is handed to the service; it extracts the
        // individual fields and rejects invalid subscribes by closing the connection.
        (new MqttSubscriptionService())->subscribeProcess($fd, $clientData);
    }

    /**
     * @RequestMapping("unsubscribe")
     */
    public function actionUnsubscribe(): void
    {
        $fd         = $this->clientData->getFd();
        $clientData = $this->clientData->getData();

        (new MqttSubscriptionService())->unsubscribeProcess($fd, $clientData);
    }

    /**
     * @RequestMapping("publish")
     */
    public function actionPublish(): void
    {
        $fd         = $this->clientData->getFd();
        $clientData = $this->clientData->getData();

        // The whole decoded frame is handed to the service; it extracts the
        // individual fields (protocol_level, client_id, qos, retain, message_id…)
        // and rejects invalid publishes by closing the connection.
        (new MqttPublishService())->publishProcess($fd, $clientData);
    }

    /**
     * @RequestMapping("pubrec")
     */
    public function actionPubrec(): void
    {
        $fd         = $this->clientData->getFd();
        $clientData = $this->clientData->getData();
        $clientId   = $clientData['client_id'];
        $messageId  = $clientData["data"]['message_id'] ?? null;

        if (empty($messageId)) {
            Server::$instance->closeFd($fd);
            return;
        }

        $mqttPublishService = new MqttPublishService();
        $hasDownAck         = (new MqttMessageAckService())->exists(
            MqttMessageAckService::DIRECTION_DOWN,
            $clientId,
            $messageId
        );

        if ($hasDownAck) {
            $mqttPublishService->pubrecDownProcess($fd, $clientData);
        } else {
            $mqttPublishService->pubrecProcess($fd, $clientData);
        }
    }

    /**
     * @RequestMapping("pubrel")
     */
    public function actionPubrel(): void
    {
        $fd = $this->clientData->getFd();
        $clientData = $this->clientData->getData();

        (new MqttPublishService())->pubrelProcess($fd, $clientData);
    }

    /**
     * @RequestMapping("pubcomp")
     */
    public function actionPubcomp(): void
    {
        $fd = $this->clientData->getFd();
        $clientData = $this->clientData->getData();

        // The whole decoded frame is handed to the service; it extracts the
        // individual fields and rejects invalid pubcomp by closing the connection.
        (new MqttPublishService())->pubcompProcess($fd, $clientData);
    }

    /**
     * @RequestMapping("puback")
     */
    public function actionPuback(): void
    {
        $fd = $this->clientData->getFd();
        $clientData = $this->clientData->getData();

        (new MqttPublishService())->pubackDownProcess($fd, $clientData);
    }
}