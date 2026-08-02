<?php

namespace App\Plugins\Pack\PackTool;

use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Core\Server\Config\PortConfig;
use Yew\Core\Server\Server;
use Yew\Framework\Helpers\Json;
use Yew\Plugins\Pack\ClientData;
use Yew\Plugins\Pack\PackTool\IPack;

class WsJsonPack implements IPack
{
    use GetLogger;

    /**
     * @param $data
     * @param PortConfig $portConfig
     * @param string|null $topic
     * @return string
     */
    public function pack($data, PortConfig $portConfig, ?string $topic = null): string
    {
        return Json::encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param int $fd
     * @param string $data
     * @param PortConfig $portConfig
     * @return ClientData
     */
    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData
    {
        $value = Json::decode($data, true);

        if (empty($value)) {
            $this->warn('json unPack 失败');
            return null;
        }
        if (empty($value['action'])) {
            $this->warn('参数错误');
            return null;
        }

        return new ClientData($fd, $portConfig->getBaseType(), $value['action'], $value);
    }

    public function encode($buffer): string
    {
        return $buffer;
    }

    public function decode($buffer)
    {
        return $buffer;
    }

    /**
     * @throws \Exception
     */
    public static function changePortConfig(PortConfig $portConfig)
    {
        return;
    }
}