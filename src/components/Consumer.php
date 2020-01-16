<?php declare(strict_types=1);

namespace common\components\rabbitMq\components;

use PhpAmqpLib\Message\AMQPMessage;

class Consumer extends BaseConsumer
{
    public function onReceive(AMQPMessage $clientMessage, $queue, $callback): void
    {
        try {
            $response = call_user_func($callback, $clientMessage);
        } catch (\Exception $e) {
            echo 'error: ' . $e->getMessage();
        }
        $clientMessage->delivery_info['channel']->basic_ack($clientMessage->delivery_info['delivery_tag']);
    }
}
