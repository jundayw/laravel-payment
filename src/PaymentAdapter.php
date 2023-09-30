<?php

namespace Jundayw\LaravelPayment;

use Jundayw\LaravelPayment\Contracts\Adapter;
use Jundayw\LaravelPayment\Requests\PaymentRequest;

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


    abstract public function pay(string $method, PaymentRequest $request, array $mergePayload = []);

    /**
     * 查询
     */
    abstract public function query(PaymentRequest $request, array $mergePayload = []): array;

    /**
     * 关闭
     */
    abstract public function close(PaymentRequest $request, array $mergePayload = []): array;

    /**
     * 交易退款
     */
    abstract public function refund(PaymentRequest $request, array $mergePayload = []): array;

    /**
     * 交易退款查询
     */
    abstract public function refundQuery(PaymentRequest $request): array;

    /**
     * 撤销交易
     */
    abstract public function cancel(PaymentRequest $request, array $mergePayload = []): array;

    /**
     * 通知验证
     */
    abstract public function notify(array $data = []): array|bool;

    /**
     * 通知响应
     */
    abstract public function notifyResponse(): string;
}