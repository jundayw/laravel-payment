<?php

namespace Jundayw\LaravelPayment\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Jundayw\LaravelPayment\Contracts\Factory;
use Jundayw\LaravelPayment\PaymentAdapter;
use Jundayw\LaravelPayment\PaymentManager;

/**
 * @method static PaymentAdapter provider($driver, string $provider = null)
 * @method static PaymentManager extend($driver, Closure $callback)
 * @method static string getDefaultProvider(string $driver)
 */
class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return Factory::class;
    }
}