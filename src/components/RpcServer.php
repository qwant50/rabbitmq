<?php declare(strict_types=1);

namespace common\components\rabbitMq\components;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RpcServer extends BaseConsumer
{
    public function onReceive(AMQPMessage $clientMessage, $queue, $callback): void
    {
        $headers = null;

        try {
            $response = $callback($clientMessage);
            $this->logger->printConsole(' [x] Response for sending: ');
            $this->logger->printConsole(mb_strimwidth($response, 0, 500, '...'));

            if ($this->compressed) {
                $this->logger->printConsole(' [i] original size: ' . strlen($response));
                $response = gzcompress($response);
                $this->logger->printConsole(' [i] compressed size: ' . strlen($response));
                $headers['rabbitmq.compressed'] = 1;
            }

            $this->sendReply($response, $clientMessage, $headers);
        } catch (\Exception $e) {
            $this->sendReply('error: ' . $e->getMessage(), $clientMessage, $headers);
        }
    }

    protected function sendReply($response, AMQPMessage $clientMessage, $headers)
    {
        $msg = new AMQPMessage($response, ['correlation_id' => $clientMessage->get('correlation_id')]);
        if (!empty($headers)) {
            $headersTable = new AMQPTable($headers);
            $msg->set('application_headers', $headersTable);
        }
        $clientMessage->delivery_info['channel']->basic_publish($msg, '', $clientMessage->get('reply_to'));
        $clientMessage->delivery_info['channel']->basic_ack($clientMessage->delivery_info['delivery_tag']);
    }
}