<?php

namespace App\Controller;

use DI\Annotation\Inject;
use Yew\Coroutine\Server\Server;
use Yew\Framework\Controller;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;

/**
 * @RestController()
 */
class IndexController extends Controller
{
    /**
     * @GetMapping("/hello")
     * @ResponseBody("application/json;charset=UTF-8")
     */
    public function hello(): array
    {
        //printf("hello\n");
        //return $this->blade->render("app::welcome");

	    var_dump([
			Server::$instance->getProcessTable()->get("actor-0"),
	    ]);

		$topicTable = DIGet("topicTable");
		var_dump($topicTable->get("hello"));

        return [
            "code" => 200,
            "message" => "success",
            "data" => null
        ];
    }
}












