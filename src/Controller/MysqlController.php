<?php

namespace App\Controller;


use Yew\Coroutine\Coroutine;
use Yew\Framework\Controller;
use Yew\Framework\Db\Query;
use Yew\Plugins\Redis\GetRedis;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;
use Yew\Yew;

/**
 * @RestController("mysql")
 */
class MysqlController extends Controller
{
    use GetRedis;

    /**
     * @GetMapping("version")
     * @ResponseBody()
     * @return array
     * @throws \Throwable
     */
    public function actionVersion(): array
    {
        $row =  Yew::$app->getDb()->createCommand("SELECT VERSION()")->queryScalar();

        return (array)$row;
    }

}
