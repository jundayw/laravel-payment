<?php

namespace Jundayw\LaravelPayment;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;
use Jundayw\LaravelPayment\Contracts\Factory;

class PaymentServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Factory::class, function ($app) {
            return new PaymentManager($app);
        });

        $this->app->alias(
            PaymentManager::class, Factory::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/payment.php' => $this->app->configPath('payment.php'),
            ], 'laravel-payment');
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('payment');
        }
        $this->mergeConfigFrom(__DIR__ . '/../config/payment.php', 'payment');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [Factory::class];
    }

}