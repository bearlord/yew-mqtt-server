<?php


namespace App\Controller;


use Yew\Coroutine\Coroutine;
use Yew\Coroutine\Server\Server;
use Yew\Framework\Controller;
use Yew\Plugins\Pack\GetBoostSend;
use Yew\Plugins\Redis\GetRedis;
use Yew\Plugins\Route\Annotation\RequestMapping;
use Yew\Plugins\Route\Annotation\TcpController;
use Yew\Plugins\Route\Annotation\UdpController;

/**
 * @UdpController(portNames={"udp"})
 * Class StreamController
 *
 * @package App\Controller
 */
class UdpStreamController extends Controller
{

    use GetBoostSend;
    use GetRedis;

//    public function beforeAction($action)
//    {
//        printf("before\n");
//        return parent::beforeAction($action);
//    }
//
//    public function afterAction($action, $result)
//    {
//        printf("after\n");
//        return parent::afterAction($action, $result);
//    }

    /**
     * @RequestMapping("onConnect")
     * @return void
     */
    public function actionOnConnect($fd, $reactorId)
    {
        Server::$instance->getLog()->critical("on Connect!");
    }

    /**
     * @RequestMapping("onClose")
     * @return void
     */
    public function actionOnClose()
    {
        Server::$instance->getLog()->critical("on Close!");
    }


    /**
     * @RequestMapping("onReceive")
     * @return bool
     * @throws \Exception
     */
    public function actionOnTcpReceive(): bool
    {
        Server::$instance->getLog()->error(sprintf("99810--cid: %d, memory_get_usage: %d\n", Coroutine::getCid(), memory_get_usage()));



        $fd = $this->clientData->getFd();
        $data = $this->clientData->getData();


        //Server::$instance->getLog()->error(sprintf("2--cid: %d, memory_get_usage: %d", \ESD\Coroutine\Coroutine::getCid(), memory_get_usage()));

        //数据长度
        $dataLength = strlen($data);




        return true;
    }
}
