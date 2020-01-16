<?php
declare(strict_types=1);

namespace common\components\rabbitMq\components;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Service that sends AMQP Messages
 *
 * @package common\components\rabbitMq\components
 */
class Producer extends BaseProducer
{
    /**
     * Publishes the message and merges additional properties with basic properties
     *
     * @param string $payload
     * @param string $exchangeName
     * @param string $routingKey
     * @param array $additionalProperties
     * @param array $headers
     *
     * @throws \RuntimeException
     */
    public function publish(string $payload, string $exchangeName, string $routingKey = '', array $additionalProperties = [], array $headers = null) {
        if ($this->autoDeclare) {
            $this->routing->setChannel($this->getChannel());
            $this->routing->declareAll();
        }
        if ($this->safe && !$this->routing->isExchangeExists($exchangeName)) {
            throw new \RuntimeException(
                "Exchange `{$exchangeName}` does not declared in broker (You see this message because safe mode is ON)."
            );
        }
        $serialized = false;
        if (!is_string($payload)) {
            $payload = call_user_func($this->serializer, $payload);
            $serialized = true;
        }
        $msg = new AMQPMessage($payload, array_merge($this->getBasicProperties(), $additionalProperties));
        if (!empty($headers) || $serialized) {
            if ($serialized) {
                $headers['rabbitmq.serialized'] = 1;
            }
            $headersTable = new AMQPTable($headers);
            $msg->set('application_headers', $headersTable);
        }

        $this->getChannel()->basic_publish($msg, $exchangeName, $routingKey);
    }
}