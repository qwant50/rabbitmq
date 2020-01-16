<?php declare(strict_types=1);

namespace common\components\rabbitMq\components;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;

abstract class BaseRabbitMQ
{
    /** @var AbstractConnection $conn */
    protected $conn;

    protected $autoDeclare;

    /** @var  AMQPChannel $ch*/
    protected $ch;

    /**
     * @var $logger Logger
     */
    protected $logger;

    /**
     * @var $routing Routing
     */
    protected $routing;

    /**
     * @param AbstractConnection $conn
     * @param Routing $routing
     * @param Logger $logger
     * @param bool $autoDeclare
     */
    public function __construct(AbstractConnection $conn, Routing $routing, Logger $logger, bool $autoDeclare)
    {
        $this->conn = $conn;
        $this->routing = $routing;
        $this->logger = $logger;
        $this->autoDeclare = $autoDeclare;
        if ($conn->connectOnConstruct()) {
            $this->getChannel();
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if ($this->ch) {
            try {
                $this->ch->close();
            } catch (\Exception $e) {
                // ignore on shutdown
            }
        }
        if ($this->conn && $this->conn->isConnected()) {
            try {
                $this->conn->close();
            } catch (\Exception $e) {
                // ignore on shutdown
            }
        }
    }

    public function renew()
    {
        if (!$this->conn->isConnected()) {
            return;
        }
        $this->conn->reconnect();
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel
    {
        if (empty($this->ch) || null === $this->ch->getChannelId()) {
            $this->ch = $this->conn->channel();
        }

        return $this->ch;
    }
}
