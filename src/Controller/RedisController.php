<?php

namespace App\Controller;


use Yew\Coroutine\Coroutine;
use Yew\Framework\Controller;
use Yew\Plugins\Redis\GetRedis;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;

/**
 * @RestController("redis")
 */
class RedisController extends Controller
{
    use GetRedis;

    /**
     * @GetMapping("cache")
     * @return void
     * @throws \Throwable
     */
    public function actionCache()
    {
        printf("getDbNum:%d\n", $this->redis()->getDbNum());
        $this->redis()->set('a', 100);
        var_dump($this->redis()->get('a'));
    }

    /**
     * @GetMapping("loop")
     * @ResponseBody(value="application/json")
     * @return array
     * @throws \Throwable
     */
    public function loop(): array
    {
        $id = 100;
        for ($i = 0; $i < 5; $i++) {
            goWithContext(function () use ($i, $id) {
                $this->redis()->set("loop-id-{$i}", mt_rand(1000, 9999));
                Coroutine::sleep(0.2);
            });
        }

        $value = null;
        return [
            "code" => 200,
            "message" => "success",
            "data" => [
                "value" => $value
            ]
        ];
    }
}
