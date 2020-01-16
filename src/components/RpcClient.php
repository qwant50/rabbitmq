<?php

namespace common\components\rabbitMq\components;

use common\components\rabbitMq\exceptions\RuntimeException;
use PhpAmqpLib\Message\AMQPMessage;

class RpcClient extends BaseProducer
{
    private $callback_queue;
    private $response;
    private $corr_id;

    public function publish(string $payload, string $exchangeName, string $routingKey = '', array $additionalProperties = [], array $headers = null)
    {
        if ($this->autoDeclare) {
            $this->routing->setChannel($this->getChannel());
            $this->routing->declareAll();
        }
        if ($this->safe && !$this->routing->isExchangeExists($exchangeName)) {
            throw new RuntimeException(
                "Exchange `{$exchangeName}` does not declared in broker (You see this message because safe mode is ON)."
            );
        }

        [$this->callback_queue, ,] = $this->getChannel()->queue_declare('', false, false, true, false);
        $consumer_tag = $this->getChannel()->basic_consume($this->callback_queue, '', false, true, false, false, [$this, 'onResponse']);

        $this->corr_id = uniqid('', true);

        $msg = new AMQPMessage($payload, array_merge($this->getBasicProperties(), $additionalProperties, [
            'correlation_id' => $this->corr_id,
            'reply_to' => $this->callback_queue,
        ]));

        $this->getChannel()->basic_publish($msg, $exchangeName, $routingKey);

        $this->response = null;
        while (!$this->response) {
            $this->getChannel()->wait();
        }
        $this->getChannel()->basic_cancel($consumer_tag);

        return $this->response;
    }

    public function onResponse(AMQPMessage $rep): void
    {
        if ($rep->get('correlation_id') == $this->corr_id) {

            // deserialize message back to initial data type
            if ($rep->has('application_headers') &&
                isset($rep->get('application_headers')->getNativeData()['rabbitmq.compressed'])) {
                $rep->setBody(gzuncompress($rep->getBody()));
            }
            $this->response = $rep->body;
        }
    }
}