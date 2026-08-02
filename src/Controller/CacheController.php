<?php

namespace App\Controller;

use Yew\Framework\Controller;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;
use Yew\Yew;

/**
 * @RestController("cache")
 */
class CacheController extends Controller
{

    /**
     * @GetMapping("cache-set")
     * @ResponseBody()
     * @return array
     */
    public function actionCacheSet(): array
    {
        $key = "name";

        Yew::$app->cache->set($key, "张三丰");

        $value = Yew::$app->cache->get($key);

        var_dump($value);

        return [$value];
    }




}