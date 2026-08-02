<?php

namespace App\Controller;


use Yew\Coroutine\Coroutine;
use Yew\Framework\Controller;
use Yew\Plugins\CircuitBreaker\Annotation\CircuitBreaker;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;

/**
 * @RestController("circuit-breaker")
 */
class CircuitBreakerController extends Controller
{
    /**
     * @GetMapping("debug")
     * @CircuitBreaker(options={"timeout": 0.3}, failCounter=3, successCounter=2, duration=10, fallback={"App\Controller\CircuitBreakerController", "fallback"}))
     * @ResponseBody()
     * @return array
     */
    public function actionDebug(): array
    {
        Coroutine::sleep(mt_rand(1, 4) / 10);
        return [
            "code" => 200,
            "message" => "success",
            "data" => mt_rand(100, 999)
        ];
    }

    public static function fallback(): array
    {
        return [
            "code" => 50401,
            "message" => "CircuitBreaker",
            "data" => null
        ];
    }
}