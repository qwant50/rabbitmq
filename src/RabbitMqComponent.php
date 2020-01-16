<?php

namespace qwant50\rabbitMq;

use yii\base\Component;

class RabbitMqComponent extends Component
{
    private $areDependenciesRegistered = false;

    private $config;

    public function init()
    {
        $this->config = new Configuration();
    }

    public function publish()
    {

    }

    public function consume()
    {

    }
}