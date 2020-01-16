<?php declare(strict_types=1);

namespace common\components\rabbitMq\commands;

use common\components\rabbitMq\components\Consumer;
use common\components\rabbitMq\components\ProducerInterface;
use common\components\rabbitMq\components\Routing;
use ErrorException;
use ReflectionException;
use yii\base\Action;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class RabbitMqController extends Controller
{
    public $memoryLimit = 0;
    public $messagesLimit = 0;
    public $debug = false;
    public $withoutSignals = false;

    /**
     * @var $routing Routing
     */
    protected $routing;

    protected $options = [
        'm' => 'messagesLimit',
        'l' => 'memoryLimit',
        'd' => 'debug',
        'w' => 'withoutSignals',
    ];

    /**
     * @param string $actionID
     *
     * @return array
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), array_values($this->options));
    }

    /**
     * @return array
     */
    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), $this->options);
    }

    /**
     * @param Action $event
     *
     * @return bool
     */
    public function beforeAction($event): bool
    {
        if (defined('AMQP_WITHOUT_SIGNALS') === false) {
            define('AMQP_WITHOUT_SIGNALS', $this->withoutSignals);
        }
        if (defined('AMQP_DEBUG') === false) {
            if ($this->debug === 'false') {
                $this->debug = false;
            }
            define('AMQP_DEBUG', (bool)$this->debug);
        }
        return parent::beforeAction($event);
    }

    /**
     * Publish a message from STDIN to the queue
     *
     * echo '{"params":{"facilityId":1013,"dateStart":"2019-03-07 03:59:00","dateEnd":"2019-04-07 03:59:00"}}' | /usr/bin/php /var/www/html/thgyii2/yii rabbitmq/publish producer_name exchange_name routing_key
     *
     * @param        $producerName
     * @param        $exchangeName
     * @param string $routingKey
     *
     * @return int
     */
    public function actionPublish(string $producerName, string $exchangeName, string $routingKey = ''): int
    {
        try {
            /** @var $producer ProducerInterface */
            $producer = \Yii::$app->rabbitMq->getProducer($producerName);
        } catch (ReflectionException $e) {
            $this->stderr(Console::ansiFormat("Producer `{$producerName}` doesn't exist: {$e->getMessage()}\n",
                [Console::FG_RED]));

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (posix_isatty(STDIN)) {
            $this->stderr(Console::ansiFormat("Please pipe in some data in order to send it.\n", [Console::FG_RED]));

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $data = '';
        while (!feof(STDIN)) {
            $data .= fread(STDIN, 8192);
        }

        $this->stdout(" [x] " . ucfirst($producer->getType()) . " producer is started\n" , Console::FG_YELLOW);
        $this->stdout(" [x] To exit press CTRL+C\n", Console::FG_YELLOW);
        $this->stdout(" [x] Sent message:\n", Console::FG_YELLOW);
        $this->stdout(" [x] $data\n", Console::FG_GREY);

        $response = $producer->publish($data, $exchangeName, $routingKey);

        $this->stdout(" [x] Message was successfully published.\n", Console::FG_GREEN);

        if ($producer->getType() === 'rpc')
        {
            $this->stdout(" [x] Received response message:\n", Console::FG_GREEN);
            $this->stdout(" [+] Size: " . strlen($response) . " \n", Console::FG_GREEN);
            $this->stdout(" [+] " . mb_strimwidth($response, 0, 500, '...') . " \n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * Run a consumer
     *
     * @param string $name Consumer name
     * @return int
     * @throws ErrorException
     *
     * @example /usr/bin/php /var/www/html/thgyii2/yii rabbitmq/consume CONSUMER_NAME
     */
    public function actionConsume(string $name): int
    {
        try {
            /** @var $consumer Consumer */
            $consumer = \Yii::$app->rabbitMq->getConsumer($name);
        } catch (ReflectionException $e) {
            $this->stderr(Console::ansiFormat("Consumer `{$name}` doesn't exist: {$e->getMessage()}\n",
                [Console::FG_RED]));

            return self::EXIT_CODE_ERROR;
        }

        $this->validateConsumerOptions($consumer);
        if ((null !== $this->memoryLimit) && ctype_digit((string)$this->memoryLimit) && ($this->memoryLimit > 0))
        {
            $consumer->setMemoryLimit($this->memoryLimit);
        }

        $this->stdout(" [*] Waiting for messages. To exit press CTRL+C\n", Console::FG_YELLOW);
        $this->stdout(" [x] Awaiting RPC requests\n", Console::FG_YELLOW);
        $consumer->consume();

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Validate options passed by user
     *
     * @param Consumer $consumer
     */
    private function validateConsumerOptions($consumer)
    {
        if (!AMQP_WITHOUT_SIGNALS && extension_loaded('pcntl'))
        {
            if (!function_exists('pcntl_signal'))
            {
                throw new \BadFunctionCallException(
                    "Function 'pcntl_signal' is referenced in the php.ini 'disable_functions' and can't be called."
                );
            }
            pcntl_signal(SIGTERM, [$consumer, 'stopDaemon']);
            pcntl_signal(SIGINT, [$consumer, 'stopDaemon']);
            pcntl_signal(SIGHUP, [$consumer, 'restartDaemon']);

            $this->stdout(" [i] Signals installed:\n", Console::FG_YELLOW);
            $this->stdout(" [i] SIGTERM, SIGINT - stopDaemon\n", Console::FG_YELLOW);
            $this->stdout(" [i] SIGHUP          - restartDaemon\n", Console::FG_YELLOW);
        }
        $this->messagesLimit = (int)$this->messagesLimit;
        $this->memoryLimit   = (int)$this->memoryLimit;
        if (!is_numeric($this->messagesLimit) || 0 > $this->messagesLimit)
        {
            throw new \InvalidArgumentException('The -m option should be null or greater than 0');
        }
        if (!is_numeric($this->memoryLimit) || 0 > $this->memoryLimit)
        {
            throw new \InvalidArgumentException('The -l option should be null or greater than 0');
        }
    }
}

