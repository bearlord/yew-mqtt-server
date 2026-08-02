<?php

namespace App\Plugins\MQTTClient;

use Yew\Core\Context\Context;
use Yew\Core\Plugin\AbstractPlugin;
use Yew\Coroutine\Server\Server;

class MQTTClientPlugin extends AbstractPlugin
{
    const PROCESS_NAME = "mqttClient";
    const PROCESS_GROUP_NAME = "HelperGroup";

    public function getName(): string
    {
        return 'MQTTClientPlugin';
    }

    public function beforeServerStart(Context $context)
    {
        printf("beforeServerStart\n");

        Server::$instance->addProcess(self::PROCESS_NAME, MQTTClientProcess::class, self::PROCESS_GROUP_NAME);
        //Add scheduled process
        for ($i = 0; $i < $this->scheduledConfig->getTaskProcessCount(); $i++) {
            Server::$instance->addProcess("scheduled-$i", ScheduledProcess::class, ScheduledTask::GROUP_NAME);
        }
    }

    public function beforeProcessStart(Context $context)
    {
        printf("beforeProcessStart\n");
    }
}