<?php

namespace App\Controller;

use App\Actors\ManActor;
use App\Actors\WomanActor;
use Yew\Core\DI\DI;
use Yew\Core\Server\Server;
use Yew\Coroutine\Coroutine;
use Yew\Framework\Controller;
use Yew\Plugins\Actor\Actor;
use Yew\Plugins\Actor\Exception\ActorException;
use Yew\Plugins\Actor\ActorManager;
use Yew\Plugins\Actor\ActorMessage;
use Yew\Plugins\Actor\Multicast\GetMulticast;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\RequestMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;

/**
 * //@RestController("actor")
 */
class ActorController extends Controller
{
    use GetMulticast;

    /**
     * 创建角色
     *
     * @RequestMapping("create1")
     * @ResponseBody
     * @return array
     */
    public function actionCreate1(): array
    {
        var_dump(__METHOD__);
        Actor::create(WomanActor::class, 'lucy', [
            'money' => 10001
        ]);

//        Actor::create(WomanActor::class, 'lily', [
//            'money' => 20002
//        ]);
//
//        Actor::create(ManActor::class, 'lilei', [
//            'money' => 30
//        ]);
//
//        Actor::create(ManActor::class, 'han', [
//            'money' => 30
//        ]);

        return [
            'code' => 200,
            'message' => 'success'
        ];
    }

    /**
     * @RequestMapping("delete")
     * @return void
     * @throws ActorException
     */
    public function actionDelete()
    {
        $name = $this->request->input('name');

        $actor = Actor::getProxy($name);
        if (!empty($actor)) {
            $actor->destroy();
        }

    }

    /**
     * @RequestMapping("borrow-money")
     * @return void
     * @throws \Throwable
     */
    public function actionBorrowMoney()
    {
        $lucy = Actor::getProxy('lucy', false);
        $lilei = Actor::getProxy('lilei', false);

        $money1 = 1;

        $m1 = $lucy->outMoney($money1);
        $lilei->inMoney($m1);

        Server::$instance->getLog()->debug(sprintf("lilei余额：%f", $lilei->getMoney()));
        $this->response->withStatus(200)->end();
    }

    /**
     * @RequestMapping("info")
     * @ResponseBody()
     * @return array
     * @throws \Exception
     */
    public function actionInfo(): array
    {
        $name = $this->request->input('name');
        if (empty($name)) {
            $name = 'lucy';
        }
        $actorInfo = ActorManager::getInstance()->getActorInfo($name);

        return [
            $actorInfo->getProcess()->getProcessName(),
            $actorInfo->getProcess()->getProcessId(),
            date("Y-m-d H:i:s", $actorInfo->getCreateTime()),
            $actorInfo->getClassName() . ":" . $actorInfo->getName()
        ];
    }

    /**
     * @RequestMapping("data")
     * @ResponseBody()
     * @return array
     */
    public function actionData(): array
    {
        $name = $this->request->input('name');
        $actor = Actor::getProxy($name, false);
        $money = $actor->getMoney();

        return [
            'money' => $money
        ];
    }

    /**
     * @RequestMapping("send")
     * @return void
     * @throws \Exception
     */
    public function actionSend()
    {
        $lilei = Actor::getProxy('lilei');
        $lilei->sendMessageToActor(new ActorMessage('晚上看电影?？', time(), 'lilei', 'lucy'), 'lucy');
        $lilei->sendMessageToActor(new ActorMessage('晚上看电影?？', time(), 'lilei', 'lily'), 'lily');
    }

    /**
     * @RequestMapping("subscribe")
     * @return void
     * @throws \Exception
     */
    public function actionSubscribe()
    {
        $channel = 'welcome';
        $channel2 = 'welcome2';
        $this->subscribe($channel, 'lucy');
        $this->subscribe($channel, 'lily');
        $this->subscribe($channel, 'lilei');
        $this->subscribe($channel2, 'lucy');
        $this->subscribe($channel2, 'lilei');

        //10秒后，lucy取消订阅 channel
        addTimerAfter(10 * 1000, function () use ($channel, $channel2) {
            $this->unsubscribe($channel, 'lucy');
        });
        //20秒后，lilei取消所有的订阅
        addTimerAfter(20 * 1000, function () use ($channel, $channel2) {
            $this->unsubscribeAll('lilei');
        });
        //30秒后，删除 channel
        addTimerAfter(30 * 1000, function () use ($channel, $channel2) {
            $this->deleteChannel($channel);
        });
    }

    /**
     * @RequestMapping("publish")
     * @return void
     */
    public function actionPublish()
    {
        $channel = 'welcome';
        $channel2 = 'welcome2';
        $this->actorPublish($channel, "欢迎~~~");
        $this->actorPublish($channel2, "逛街去~~~");
    }


    /**
     * @RequestMapping("subscribe2")
     * @ResponseBody
     * @return array
     */
    public function actionSubscribe2()
    {
        $channel = 'device/+/temperature';
        $channel2 = 'device/#';
        $channel3 = 'device/3/temperature';

        $luly = Actor::getProxy('lucy');
        $lily = Actor::getProxy('lily');
        $lilei = Actor::getProxy('lilei');

        try {
            $luly->subscribe($channel);
            $lily->subscribe($channel2);
            $lilei->subscribe($channel3);
        } catch (\Exception $exception) {
            var_dump($exception);
        }
        return [
            'code' => 200,
            'message' => 'success'
        ];
    }

    /**
     * 发布
     *
     * @RequestMapping("publish2")
     * @return void
     */
    public function actionPublish2()
    {
        $channel = 'device/1/temperature';
        $channel2 = 'device/2/humidity';
        $channel3 = 'device/3/temperature';

        $han = Actor::getProxy('han');

        $han->publish($channel, "10");
        $han->publish($channel2, "20");
        $han->publish($channel3, "30");
    }


    /**
     * 创建角色
     *
     * @RequestMapping("create2")
     * @ResponseBody
     * @return array
     */
    public function actionCreate2()
    {
        $lilei = Actor::create(ManActor::class, 'lilei', [
            'money' => 30
        ]);

        $moneys = [];
        for ($i = 1; $i < 2; $i++) {
            $moneys["money" . $i] = mt_rand(1000, 9999);
        }

        $lilei->setData($moneys);

        $lilei->saveToDb();

//        $lilei->saveContext();

        return [
            'code' => 200,
            'message' => 'success',
            'data' => new \stdClass()
        ];
    }

    /**
     * @RequestMapping("get-data2")
     * @ResponseBody
     * @return array
     * @throws ActorException
     */
    public function actionGetData2()
    {
        $lilei = Actor::getProxy('lilei');
        $data = $lilei->getData();
        return [
            'code' => 200,
            'message' => 'success',
            'data' => $data
        ];
    }


    /**
     * 创建角色
     *
     * @RequestMapping("create3")
     * @ResponseBody
     * @return array
     */
    public function actionCreate3(): array
    {
        for ($i = 1; $i <= 1000; $i++) {
            Actor::create(ManActor::class, 'lilei_' . $i, [
                'money' => mt_rand(100, 9999)
            ]);
        }
        return [
            'code' => 200,
            'message' => 'success'
        ];
    }

    /**
     * 订阅
     *
     * @RequestMapping("subscribe3")
     * @ResponseBody
     * @return array
     * @throws ActorException
     */
    public function actionSubscribe3(): array
    {
        for ($i = 1; $i <= 1000; $i++) {
            $actor = Actor::getProxy('lilei_' . $i);
            $actor->subscribe("channel101");
        }

        return [
            'code' => 200,
            'message' => 'success'
        ];
    }


    /**
     * 发布
     *
     * @RequestMapping("publish3")
     * @ResponseBody
     * @return array
     * @throws ActorException
     */
    public function actionPublish3(): array
    {
        $lilei = Actor::getProxy('lilei_1', true);

        $lilei->publish("channel101", "hello" .date("Y-m-d H:i:s"));


        return [
            'code' => 200,
            'message' => 'success'
        ];
    }

    /**
     * @GetMapping("checkAndCreate")
     * @return void
     * @throws \Exception
     */
    public function checkAndCreate()
    {
        $times = 1;
        while ($times <= 20) {
            $mem1 = memory_get_usage();
            $time1 = microtime(true);
            $playerIds = [1, 2];
            foreach ($playerIds as $playerId)
            {
                $playerName = "woman-" . $playerId;

                if (ActorManager::getInstance()->hasActor($playerName)) {
                    echo $playerName . "存在" . "\n";

                    $actor = Actor::getProxy($playerName);
                    $actor->destroy();
                }

                $newPlayer = Actor::create(WomanActor::class, $playerName, [
                    'money' => 10001
                ]);
                Coroutine::sleep(1);
            }
            $mem2 = memory_get_usage();
            $time2 = microtime(true);
            printf("times: %d, memory: %f, time: %f\n", $times, $mem2 - $mem1, $time2 - $time1);

            $times++;
        }

    }
}
