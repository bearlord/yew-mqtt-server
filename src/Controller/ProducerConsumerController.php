<?php

namespace App\Controller;

use App\Amqp\Producers\DeviceRechargeProducer;
use DI\Annotation\Inject;
use Yew\Framework\Controller;
use Yew\Plugins\Amqp\Consumer;
use Yew\Plugins\Amqp\Producer;
use Yew\Plugins\AnnotationsScan\ScanClass;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\Annotation\RestController;
use Yew\Plugins\Route\Annotation\GetMapping;
use Yew\Core\Server\Server;

/**
 * @RestController("producer-consumer")
 */
class ProducerConsumerController extends Controller
{

    /**
     * @Inject
     * @var Producer
     */
    protected Producer $producer;

    /**
     * @var Consumer
     */
    protected Consumer $consumer;

    /**
     * @GetMapping("producer");
     * @ResponseBody()
     * @throws \Throwable
     */
    public function actionProduce()
    {
        $this->producer->produce(new DeviceRechargeProducer(mt_rand()));

        Server::$instance->getLog()->debug("publish");

//        for ($i = 1; $i <= 1; $i++) {
//            $this->producer->produce(new DeviceRechargeProducer($i));
//        }
//        $this->response->withContent("success")->end();

    }

    /**
     * @GetMapping("test")
     */
    public function actionTest()
    {
        /** @var ScanClass $scanClass */
        $scanClass = DIget(ScanClass::class);

        var_dump($scanClass->getCachedReader());

        $producer = new DeviceRechargeProducer(1);

        $reflect = new \ReflectionClass($producer);
//
//        var_dump(new \ReflectionClass($producer));
//
        $r = $scanClass->getClassAndInterfaceAnnotation($reflect, \ESD\Plugins\Amqp\Annotation\Producer::class);


        var_dump($r);
    }

    /**
     * @GetMapping("test2")
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function actionTest2()
    {
        //Scan annotation
        $scanClass = Server::$instance->getContainer()->get(ScanClass::class);
        $reflectionClasses = $scanClass->findClassesByAnn(\ESD\Plugins\Amqp\Annotation\Consumer::class);
        var_dump($reflectionClasses);
        foreach ($reflectionClasses as $reflectionClass) {
            $annotation = $scanClass->reflectionClasses($reflectionClass, \ESD\Plugins\Amqp\Annotation\Consumer::class);
            var_dump($annotation);

        }
    }
}