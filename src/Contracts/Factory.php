<?php

namespace Jundayw\LaravelPayment\Contracts;

use Closure;

interface Factory
{
    public function provider($driver, string $provider = null);

    public function extend($driver, Closure $callback);
}