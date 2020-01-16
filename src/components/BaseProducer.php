<?php

declare(strict_types=1);

namespace common\components\rabbitMq\components;

/**
 * Service that sends AMQP Messages
 *
 * @package common\components\rabbitMq\components
 */
abstract class BaseProducer extends BaseRabbitMQ implements ProducerInterface
{
    protected $contentType;
    protected $deliveryMode;
    protected $serializer;
    protected $safe;
    protected $name = 'unnamed';
    protected $type;

    /**
     * @param $contentType
     */
    public function setContentType($contentType): void
    {
        $this->contentType = $contentType;
    }

    /**
     * @param $deliveryMode
     */
    public function setDeliveryMode($deliveryMode): void
    {
        $this->deliveryMode = $deliveryMode;
    }

    /**
     * @param callable $serializer
     */
    public function setSerializer(callable $serializer): void
    {
        $this->serializer = $serializer;
    }

    /**
     * @return callable
     */
    public function getSerializer(): callable
    {
        return $this->serializer;
    }

    /**
     * @return array
     */
    public function getBasicProperties(): array
    {
        return [
            'content_type' => $this->contentType,
            'delivery_mode' => $this->deliveryMode,
        ];
    }

    /**
     * @return mixed
     */
    public function getSafe(): bool
    {
        return $this->safe;
    }

    /**
     * @param mixed $safe
     */
    public function setSafe(bool $safe): void
    {
        $this->safe = $safe;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    abstract public function publish(
        string $payload,
        string $exchangeName,
        string $routingKey = '',
        array $additionalProperties = [],
        array $headers = null
    );

}