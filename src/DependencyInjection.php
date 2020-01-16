<?php declare(strict_types=1);

namespace qwant50\rabbitMq;

use qwant50\rabbitMq\components\{AbstractConnectionFactory,
    Consumer,
    ConsumerInterface,
    Producer,
    RpcClient,
    Logger,
    Routing,
    RpcServer};
use qwant50\rabbitMq\commands\RabbitMqController;
use qwant50\rabbitMq\exceptions\InvalidConfigException;
use PhpAmqpLib\Connection\AbstractConnection;

class DependencyInjection
{
    private const CONNECTION_SERVICE_NAME = 'rabbit_mq.connection.%s';
    private const CONSUMER_SERVICE_NAME = 'rabbit_mq.consumer.%s';
    private const PRODUCER_SERVICE_NAME = 'rabbit_mq.producer.%s';
    private const ROUTING_SERVICE_NAME = 'rabbit_mq.routing';
    private const LOGGER_SERVICE_NAME = 'rabbit_mq.logger';

    private const DEFAULT_CONNECTION_NAME = 'default';
    private const EXTENSION_CONTROLLER_ALIAS = 'rabbitmq';

    /**
     * @var $logger Logger
     */
    private $logger;
    protected $isLoaded = false;

    /**
     * Configuration auto-loading
     * @param Configuration $config
     */
    public function registerAll($config): void
    {
        $this->registerLogger($config);
        $this->registerConnections($config);
        $this->registerRouting($config);
        $this->registerProducers($config);
        $this->registerConsumers($config);
        $this->addControllers($config);
    }

    /**
     * Register logger service
     * @param $config
     */
    private function registerLogger($config)
    {
        \Yii::$container->setSingleton(self::LOGGER_SERVICE_NAME, ['class' => Logger::class, 'options' => $config->logger]);
    }

    /**
     * Register connections in service container
     * @param Configuration $config
     */
    protected function registerConnections(Configuration $config)
    {
        foreach ($config->connections as $options) {
            $serviceAlias = sprintf(self::CONNECTION_SERVICE_NAME, $options['name']);
            \Yii::$container->setSingleton($serviceAlias, function () use ($options) {
                $factory = new AbstractConnectionFactory($options['type'], $options);
                return $factory->createConnection();
            });
        }
    }

    /**
     * Register routing in service container
     * @param Configuration $config
     */
    protected function registerRouting(Configuration $config)
    {
        \Yii::$container->setSingleton(self::ROUTING_SERVICE_NAME, function ($container, $params) use ($config) {
            $routing = new Routing($params['conn']);
            \Yii::$container->invoke([$routing, 'setQueues'], [$config->queues]);
            \Yii::$container->invoke([$routing, 'setExchanges'], [$config->exchanges]);
            \Yii::$container->invoke([$routing, 'setBindings'], [$config->bindings]);

            return $routing;
        });
    }

    /**
     * Register producers in service container
     * @param Configuration $config
     */
    protected function registerProducers(Configuration $config)
    {
        $autoDeclare = $config->auto_declare;
        foreach ($config->producers as $options) {
            $serviceAlias = sprintf(self::PRODUCER_SERVICE_NAME, $options['name']);
            \Yii::$container->setSingleton($serviceAlias, function () use ($options, $autoDeclare) {
                /**
                 * @var $connection AbstractConnection
                 */
                $connection = \Yii::$container->get(sprintf(self::CONNECTION_SERVICE_NAME, $options['connection']));
                /**
                 * @var $routing Routing
                 */
                $routing = \Yii::$container->get(self::ROUTING_SERVICE_NAME, ['conn' => $connection]);
                /**
                 * @var $logger Logger
                 */
                $logger = \Yii::$container->get(self::LOGGER_SERVICE_NAME);
                if ($options['type'] === 'rpc') {
                    $producer = new RpcClient($connection, $routing, $logger, $autoDeclare);
                } else {
                    $producer = new Producer($connection, $routing, $logger, $autoDeclare);
                }
                \Yii::$container->invoke([$producer, 'setName'], [$options['name']]);
                \Yii::$container->invoke([$producer, 'setContentType'], [$options['content_type']]);
                \Yii::$container->invoke([$producer, 'setDeliveryMode'], [$options['delivery_mode']]);
                \Yii::$container->invoke([$producer, 'setSafe'], [$options['safe']]);
                \Yii::$container->invoke([$producer, 'setSerializer'], [$options['serializer']]);
                \Yii::$container->invoke([$producer, 'setType'], [$options['type']]);

                return $producer;
            });
        }
    }

    /**
     * Register consumers(one instance per one or multiple queues) in service container
     * @param Configuration $config
     */
    protected function registerConsumers(Configuration $config)
    {
        $autoDeclare = $config->auto_declare;
        foreach ($config->consumers as $options) {
            $serviceAlias = sprintf(self::CONSUMER_SERVICE_NAME, $options['name']);
            \Yii::$container->setSingleton($serviceAlias, function () use ($options, $autoDeclare) {
                /**
                 * @var $connection AbstractConnection
                 */
                $connection = \Yii::$container->get(sprintf(self::CONNECTION_SERVICE_NAME, $options['connection']));
                /**
                 * @var $routing Routing
                 */
                $routing = \Yii::$container->get(self::ROUTING_SERVICE_NAME, ['conn' => $connection]);
                /**
                 * @var $logger Logger
                 */
                $logger = \Yii::$container->get(self::LOGGER_SERVICE_NAME);
                if ($options['type'] === 'rpc') {
                    $consumer = new RpcServer($connection, $routing, $logger, $autoDeclare);
                } else {
                    $consumer = new Consumer($connection, $routing, $logger, $autoDeclare);
                }
                $queues = [];
                foreach ($options['callbacks'] as $queueName => $callback) {
                    $callbackClass = $this->getCallbackClass($callback);
                    $queues[$queueName] = [$callbackClass, 'execute'];
                }
                \Yii::$container->invoke([$consumer, 'setName'], [$options['name']]);
                \Yii::$container->invoke([$consumer, 'setQueues'], [$queues]);
                \Yii::$container->invoke([$consumer, 'setQos'], [$options['qos']]);
                \Yii::$container->invoke([$consumer, 'setIdleTimeout'], [$options['idle_timeout']]);
                \Yii::$container->invoke([$consumer, 'setIdleTimeoutExitCode'], [$options['idle_timeout_exit_code']]);
                \Yii::$container->invoke([$consumer, 'setProceedOnException'], [$options['proceed_on_exception']]);
                \Yii::$container->invoke([$consumer, 'setDeserializer'], [$options['deserializer']]);
                \Yii::$container->invoke([$consumer, 'setCompressed'], [$options['compressed']]);

                return $consumer;
            });
        }
    }

    /**
     * Callback can be passed as class name or alias in service container
     * @param string $callbackName
     * @return ConsumerInterface
     * @throws InvalidConfigException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\di\NotInstantiableException
     */
    private function getCallbackClass(string $callbackName) : ConsumerInterface
    {
        if (!class_exists($callbackName)) {
            $callbackClass = \Yii::$container->get($callbackName);
        } else {
            $callbackClass = new $callbackName();
        }
        if (!($callbackClass instanceof ConsumerInterface)) {
            throw new InvalidConfigException("{$callbackName} should implement ConsumerInterface.");
        }

        return $callbackClass;
    }

    /**
     * Auto-configure console controller classes
     */
    private function addControllers(Configuration $config)
    {
        \Yii::$app->controllerMap[self::EXTENSION_CONTROLLER_ALIAS] = RabbitMqController::class;
    }
}
