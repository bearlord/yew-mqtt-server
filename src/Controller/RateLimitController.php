<?php

namespace App\Controller;


use Yew\Framework\Controller;
use Yew\Plugins\RateLimit\Annotation\RateLimit;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;

/**
 * //@RestController("rate-limit")
 */
class RateLimitController extends Controller
{
    /**
     * @GetMapping("debug")
     * //@RateLimit(create=1, consume=1, waitTimeout=1, limitCallback={"\App\Controller\RateLimitController", "limitCallback"})
     *
     * @RateLimit(create=1, consume=1, waitTimeout=1)
     * @ResponseBody()
     * @return array
     */
    public function actionDebug(): array
    {
//        var_dump([
//            microtime(true),
//            __METHOD__
//        ]);
        return [
            "code" => 200,
            "message" => "success",
            "data" => null
        ];
    }

    public static function limitCallback(): array
    {
        var_dump([
            microtime(true),
            __METHOD__
        ]);

        return [
            "code" => 4901,
            "message" => "rale limit",
            "data" => null
        ];
    }
}
