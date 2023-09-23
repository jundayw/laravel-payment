<?php

namespace Jundayw\LaravelPayment;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Jundayw\LaravelPayment\Adapters\AlipayAdapter;
use Jundayw\LaravelPayment\Adapters\WechatAdapter;
use Jundayw\LaravelPayment\Contracts\Factory;

class PaymentManager implements Factory
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * The configuration repository instance.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected array $customCreators = [];

    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected array $drivers = [];

    /**
     * Create a new manager instance.
     *
     * @param Container $container
     * @return void
     * @throws BindingResolutionException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config    = $container->make('config')->get('payment', []);
    }

    /**
     * Get the default provider name.
     *
     * @return string
     */
    public function getDefaultProvider(): string
    {
        return 'default';
    }

    /**
     * Get a driver instance.
     *
     * @param string $driver
     * @param string|null $provider
     * @return PaymentAdapter
     *
     * @throws InvalidArgumentException
     */
    public function provider($driver, string $provider = null): PaymentAdapter
    {
        if (is_null($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].', static::class
            ));
        }

        if (!array_key_exists($driver, $this->config)) {
            throw new InvalidArgumentException("Payment Driver [$driver] not supported.");
        }

        $provider = $provider ?: $this->getDefaultProvider();

        if (!array_key_exists($provider, $this->config[$driver])) {
            throw new InvalidArgumentException("Payment Provider [$provider] not supported.");
        }

        // If the given driver has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a driver created by this name, we'll just return that instance.
        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver, $provider);
        }

        return call_user_func_array($this->drivers[$driver], [
            $this->config[$driver][$provider],
        ]);
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @param string $provider
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    protected function createDriver(string $driver, string $provider): mixed
    {
        // First, we will determine if a custom driver creator exists for the given driver and
        // if it does not we will check for a creator method for the driver. Custom creator
        // callbacks allow developers to build their own "drivers" easily using Closures.
        if (isset($this->customCreators[$driver])) {
            return $this->customCreators[$driver];
        }

        if (method_exists($this, $driverMethod = 'create' . Str::studly($driver) . 'Driver')) {
            return [$this, $driverMethod];
        }

        throw new InvalidArgumentException("Driver [$driver] not supported.");
    }

    /**
     * create Alipay Driver instance
     *
     * @param array $config
     * @return PaymentAdapter
     */
    public function createAlipayDriver(array $config): PaymentAdapter
    {
        return new AlipayAdapter($config);
    }

    /**
     * create Wechat Driver instance
     *
     * @param array $config
     * @return PaymentAdapter
     */
    public function createWechatDriver(array $config): PaymentAdapter
    {
        return new WechatAdapter($config);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string $driver
     * @param Closure $callback
     * @return $this
     */
    public function extend($driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created "drivers".
     *
     * @return array
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Get the container instance used by the manager.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Set the container instance used by the manager.
     *
     * @param Container $container
     * @return $this
     */
    public function setContainer(Container $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Forget all of the resolved driver instances.
     *
     * @return $this
     */
    public function forgetDrivers(): static
    {
        $this->drivers = [];

        return $this;
    }

}