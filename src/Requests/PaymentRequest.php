<?php

namespace Jundayw\LaravelPayment\Requests;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Jundayw\LaravelPayment\Contracts\Request;

class PaymentRequest implements Request, Arrayable
{
    public string $subject = '';
    public string $outTradeNo = '';
    public int|float $amount = 0.0;
    public string $outRefundNo = '';
    public int|float $refundAmount = 0.0;
    public array $attach = [];
    public string $notifyUrl = '';
    public string $returnUrl = '';
    public string $clientIp = '';
    public string $authCode = '';
    public string $timeExpire = '';
    public string $buyerId = '';

    public function __construct(
        protected array $attributes = [],
    )
    {
        //
    }

    /**
     * @return string
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * @param string $subject
     * @return PaymentRequest
     */
    public function setSubject(string $subject): PaymentRequest
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return string
     */
    public function getOutTradeNo(): string
    {
        return $this->outTradeNo;
    }

    /**
     * @param string $outTradeNo
     * @return PaymentRequest
     */
    public function setOutTradeNo(string $outTradeNo): PaymentRequest
    {
        $this->outTradeNo = $outTradeNo;
        return $this;
    }

    /**
     * @return string
     */
    public function getOutRefundNo(): string
    {
        return $this->outRefundNo;
    }

    /**
     * @param string $outRefundNo
     * @return PaymentRequest
     */
    public function setOutRefundNo(string $outRefundNo): PaymentRequest
    {
        $this->outRefundNo = $outRefundNo;
        return $this;
    }

    /**
     * @param int|float $precision
     * @return float|int
     */
    public function getAmount(int|float $precision = 1): float|int
    {
        return $this->amount * $precision;
    }

    /**
     * @param float|int $amount
     * @return PaymentRequest
     */
    public function setAmount(float|int $amount): PaymentRequest
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @param int|float $precision
     * @return float|int
     */
    public function getRefundAmount(int|float $precision = 1): float|int
    {
        return $this->refundAmount * $precision;
    }

    /**
     * @param float|int $refundAmount
     * @return PaymentRequest
     */
    public function setRefundAmount(float|int $refundAmount): PaymentRequest
    {
        $this->refundAmount = $refundAmount;
        return $this;
    }

    /**
     * @return string
     */
    public function getAttach(): string
    {
        return json_encode($this->attach, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array $attach
     * @return PaymentRequest
     */
    public function setAttach(array $attach): PaymentRequest
    {
        $this->attach = $attach;
        return $this;
    }

    /**
     * @return string
     */
    public function getNotifyUrl(): string
    {
        return $this->notifyUrl;
    }

    /**
     * @param string $notifyUrl
     * @return PaymentRequest
     */
    public function setNotifyUrl(string $notifyUrl): PaymentRequest
    {
        $this->notifyUrl = $notifyUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    /**
     * @param string $returnUrl
     * @return PaymentRequest
     */
    public function setReturnUrl(string $returnUrl): PaymentRequest
    {
        $this->returnUrl = $returnUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getClientIp(): string
    {
        return $this->clientIp ?: $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @param string $clientIp
     * @return PaymentRequest
     */
    public function setClientIp(string $clientIp): PaymentRequest
    {
        $this->clientIp = $clientIp;
        return $this;
    }

    /**
     * @return string
     */
    public function getAuthCode(): string
    {
        return $this->authCode;
    }

    /**
     * @param string $authCode
     * @return PaymentRequest
     */
    public function setAuthCode(string $authCode): PaymentRequest
    {
        $this->authCode = $authCode;
        return $this;
    }

    /**
     * @return Carbon
     */
    public function getTimeExpire(): Carbon
    {
        return $this->timeExpire ? Carbon::parse($this->timeExpire) : Carbon::now()->addHours(45);
    }

    /**
     * @param string $timeExpire
     * @return PaymentRequest
     */
    public function setTimeExpire(string $timeExpire): PaymentRequest
    {
        $this->timeExpire = $timeExpire;
        return $this;
    }

    /**
     * @return string
     */
    public function getBuyerId(): string
    {
        return $this->buyerId;
    }

    /**
     * @param string $buyerId
     * @return PaymentRequest
     */
    public function setBuyerId(string $buyerId): PaymentRequest
    {
        $this->buyerId = $buyerId;
        return $this;
    }

    public function has(string $keys)
    {
        $hasKey = function (array $keys, array $attributes) use (&$hasKey) {
            $key = array_shift($keys);
            if (array_key_exists($key, $attributes)) {
                if (count($keys) == 0) {
                    return true;
                }
                if (is_array($attributes[$key])) {
                    return $hasKey($keys, $attributes[$key]);
                }
            }
            return false;
        };

        return $hasKey(explode('.', $keys), $this->attributes);
    }

    public function get(string $keys, mixed $default = null)
    {
        $getKey = function (array $keys, array $attributes) use (&$getKey, $default) {
            $key = array_shift($keys);
            if (array_key_exists($key, $attributes)) {
                if (count($keys) == 0) {
                    return $attributes[$key];
                }
                if (is_array($attributes[$key])) {
                    return $getKey($keys, $attributes[$key]);
                }
            }
            return $default;
        };

        return $getKey(explode('.', $keys), $this->attributes);
    }

    public function __get(string $name)
    {
        return $this->attributes[$name];
    }

    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}