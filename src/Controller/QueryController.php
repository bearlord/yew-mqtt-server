<?php

namespace App\Controller;


use Doctrine\ORM\Query;
use Yew\Framework\Controller;
use Yew\Plugins\Redis\GetRedis;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;

/**
 * @RestController("query")
 */
class QueryController extends Controller
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
        $one = (new \Yew\Framework\Db\Query())->from("ocs_products")->one();
        //unset($one);

        $m2 = memory_get_usage();

        printf("memory_get_usage: %s - %s = %s KB\n", $m2, $m1, ($m2 - $m1) / 1024);

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
        $one = (new \Yew\Framework\Db\Query())->from("ocs_user")->one();
        //unset($one);

        $m2 = memory_get_usage();

        printf("memory_get_usage: %s - %s = %s KB\n", $m2, $m1, ($m2 - $m1) / 1024);

        return [
            "code" => 0,
            "msg" => "success",
        ];
    }

}
