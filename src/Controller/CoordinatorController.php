<?php

namespace App\Controller;


use Yew\Coordinator\Constants;
use Yew\Coordinator\CoordinatorManager;
use Yew\Coordinator\Timer;
use Yew\Coroutine\Coroutine;
use Yew\Framework\Controller;
use Yew\Plugins\Redis\GetRedis;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;

/**
 * @RestController("coordinator")
 */
class CoordinatorController extends Controller
{
    use GetRedis;

    /**
     * @GetMapping("tick")
     * @return void
     * @throws \Throwable
     */
    public function actionTick()
    {
        $timer = new Timer();
        $id = $timer->tick(1, function (){
            printf("time: %s\n", microtime(true));
        });

        Coroutine::sleep(10);
        $timer->clear($id);
    }
}
