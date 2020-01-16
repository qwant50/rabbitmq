<?php declare(strict_types=1);

namespace common\components\rabbitMq\components;

interface ProducerInterface
{
    public function setContentType($contentType);

    public function setDeliveryMode($deliveryMode);

    public function setSerializer(callable $serializer);

    public function getSerializer(): callable;

    public function getBasicProperties(): array;

    public function getSafe(): bool;

    public function setSafe(bool $safe): void;

    public function getName(): string;

    public function setName(string $name): void;

    public function getType(): string;

    function setType(string $name): void;

    function publish(
        string $payload,
        string $exchangeName,
        string $routingKey = '',
        array $additionalProperties = [],
        array $headers = null
    );
}
