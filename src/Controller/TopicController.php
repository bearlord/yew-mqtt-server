<?php

namespace App\Controller;

use Yew\Framework\Controller;
use Yew\Plugins\Pack\ClientData;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\RequestMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;
use Yew\Plugins\Route\Annotation\WsController;
use Yew\Plugins\Topic\GetTopic;
use Yew\Plugins\Uid\GetUid;

/**
 * @WsController("topic")
 */
class TopicController extends Controller
{
	use GetTopic;

	use GetUid;


    /**
     * @RequestMapping("bind-uid")
     * @ResponseBody()
     * @return array
     */
    public function actionBindUid(): array
    {
        $uid = 2000;

        $fd = $this->clientData->getFd();
        $this->bindUid($fd, $uid);

        return [
            "code" => 200,
            "message" => "success",
            "data" => null
        ];
    }

	/**
	 * @RequestMapping("add-sub-1000")
	 * @ResponseBody()
	 * @return array
	 */
	public function actionAddSub1000(): array
	{
		$uid = 1000;

		$fd = $this->clientData->getFd();
		$this->bindUid($fd, $uid);

		$this->addSubscription("device/1000/info", $uid);

		$this->addSubscription("broadcast/#", $uid);

		return [
			"code" => 200,
			"message" => "success",
			"data" => null
		];
	}

    /**
     * @RequestMapping("add-sub-2000")
     * @ResponseBody()
     * @return array
     */
    public function actionAddSub2000(): array
    {
        $uid = 2000;

        $fd = $this->clientData->getFd();
        $this->bindUid($fd, $uid);

        $this->addSubscription("device/2000/info", $uid);

        $this->addSubscription("broadcast/#", $uid);

        return [
            "code" => 200,
            "message" => "success",
            "data" => null
        ];
    }

	/**
	 * @RequestMapping("publish")
	 * @ResponseBody()
	 * @return array
	 */
	public function actionPublish(): array
	{
		$this->publish("device/1000/info", "device 1000 info");
		$this->publish("device/2000/info", "device 2000 info");

		$this->publish("broadcast/1234", "broadcast 1234");

		return [
			"code" => 200,
			"message" => "success",
			"data" => null
		];
	}
}