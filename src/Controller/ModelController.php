<?php

namespace App\Controller;


use Yew\Framework\Controller;
use Yew\Plugins\Redis\GetRedis;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;

/**
 * //@RestController("model")
 */
class ModelController extends Controller
{
    use GetRedis;

    /**
     * @GetMapping("find")
     * @ResponseBody()
     * @return array
     * @throws \Throwable
     */
    public function actionFind(): array
    {
        $m1 = memory_get_usage();

        $one = \App\Models\Products::find()->one();

        //unset($one);

        $m2 = memory_get_usage();

        printf("%s, memory_get_usage: %s - %s = %s KB\n", __METHOD__, $m2, $m1, ($m2 - $m1) / 1024);

        return [
            "code" => 0,
            "msg" => "success",
        ];
    }

    /**
     * @GetMapping("find2")
     * @ResponseBody()
     * @return array
     * @throws \Throwable
     */
    public function actionFind2(): array
    {
        $m1 = memory_get_usage();

        $one = \App\Models\User::find()->one();

        //unset($one);

        $m2 = memory_get_usage();

        printf("%s, memory_get_usage: %s - %s = %s KB\n", __METHOD__, $m2, $m1, ($m2 - $m1) / 1024);

        return [
            "code" => 0,
            "msg" => "success",
        ];
    }

    /**
     * @GetMapping("find3")
     * @ResponseBody()
     * @return array
     * @throws \Throwable
     */
    public function actionFind3(): array
    {
        $m1 = memory_get_usage();

        $one = \App\Models\User3::find()->one();

        //unset($one);

        $m2 = memory_get_usage();

        printf("%s, memory_get_usage: %s - %s = %s KB\n", __METHOD__, $m2, $m1, ($m2 - $m1) / 1024);

        return [
            "code" => 0,
            "msg" => "success",
        ];
    }

    /**
     * @GetMapping("find4")
     * @ResponseBody()
     * @return array
     * @throws \Throwable
     */
    public function actionFind4(): array
    {
        $m1 = memory_get_usage();

        $one = \App\Models\User4::find()->one();

        //unset($one);

        $m2 = memory_get_usage();

        printf("%s, memory_get_usage: %s - %s = %s KB\n", __METHOD__, $m2, $m1, ($m2 - $m1) / 1024);

        return [
            "code" => 0,
            "msg" => "success",
        ];
    }

}
