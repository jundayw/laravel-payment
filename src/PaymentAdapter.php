<?php

namespace Jundayw\LaravelPayment;

use Jundayw\LaravelPayment\Contracts\Adapter;

abstract class PaymentAdapter implements Adapter
{
    public function __construct(
        protected array $config,
    )
    {
        //
    }

    abstract public function getProvider();

    public function getConfig($key)
    {
        if (array_key_exists($key, $this->config)) {
            return $this->config[$key];
        }
        return null;
    }
}