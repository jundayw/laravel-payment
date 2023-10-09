<?php

namespace Jundayw\LaravelPayment\Adapters;

use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Config;
use Exception;
use Jundayw\LaravelPayment\PaymentAdapter;
use InvalidArgumentException;
use Jundayw\LaravelPayment\Requests\PaymentRequest;

class AlipayAdapter extends PaymentAdapter
{
    public function getProvider(): Factory
    {
        $options              = new Config();
        $options->protocol    = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        // $options->gatewayHost = 'openapi-sandbox.dl.alipaydev.com';
        $options->signType = 'RSA2';

        $options->appId = $this->config['app_id'];

        // 为避免私钥随源码泄露，推荐从文件中读取私钥字符串而不是写入源码中
        $options->merchantPrivateKey = file_get_contents($this->config['private_key']);

        // 密钥模式
        if ($this->config['alipay_public_key']) {
            //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
            $options->alipayPublicKey = file_get_contents($this->config['alipay_public_key']);
        }

        // 证书模式
        if ($this->config['app_cert_path'] && $this->config['alipay_public_cert_path'] && $this->config['root_cert_path']) {
            $options->merchantCertPath   = $this->config['app_cert_path'];
            $options->alipayCertPath     = $this->config['alipay_public_cert_path'];
            $options->alipayRootCertPath = $this->config['root_cert_path'];
        }

        //可设置异步通知接收服务地址（可选）
        $options->notifyUrl = $this->config['notify_url'];

        return Factory::setOptions($options);
    }

    public function pay(string $method, PaymentRequest $request, array $mergePayload = [])
    {
        if (method_exists($this, $method) && in_array($method, ['web', 'wap', 'app', 'pos', 'scan', 'mini', 'transfer'])) {
            return call_user_func_array([$this, $method], [$request, $mergePayload]);
        }
        throw new InvalidArgumentException(sprintf(
            '[%s] not supported [%s].', static::class, $method
        ));
    }

    protected function web(PaymentRequest $request, array $mergePayload = []): string
    {
        return $this->getProvider()
            ->payment()
            ->page()
            ->optional('time_expire', $request->getTimeExpire()->toDateTimeString())
            ->optional('passback_params', $request->getAttach())
            ->pay(
                $request->getSubject(),
                $request->getOutTradeNo(),
                $request->getAmount(),
                $request->getReturnUrl()
            )
            ->body;
    }

    protected function wap(PaymentRequest $request, array $mergePayload = []): string
    {
        return $this->getProvider()
            ->payment()
            ->wap()
            ->optional('time_expire', $request->getTimeExpire()->toDateTimeString())
            ->optional('passback_params', $request->getAttach())
            ->pay(
                $request->getSubject(),
                $request->getOutTradeNo(),
                $request->getAmount(),
                $request->getReturnUrl(),
                $request->getReturnUrl()
            )
            ->body;
    }

    protected function app(PaymentRequest $request, array $mergePayload = []): string
    {
        return $this->getProvider()
            ->payment()
            ->app()
            ->optional('time_expire', $request->getTimeExpire()->toDateTimeString())
            ->optional('passback_params', $request->getAttach())
            ->pay(
                $request->getSubject(),
                $request->getOutTradeNo(),
                $request->getAmount()
            )
            ->body;
    }

    /**
     * 付款码
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    protected function pos(PaymentRequest $request, array $mergePayload = []): array
    {
        $response = $this->getProvider()
            ->payment()
            ->faceToFace()
            ->optional('time_expire', $request->getTimeExpire()->toDateTimeString())
            ->optional('passback_params', $request->getAttach())
            ->pay(
                $request->getSubject(),
                $request->getOutTradeNo(),
                $request->getAmount(),
                $request->getAuthCode()
            );
        if ($response->code == '10000') {
            return json_decode($response->httpBody, true);
        }
        throw new Exception($response->subMsg);
    }

    /**
     * 扫码
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    protected function scan(PaymentRequest $request, array $mergePayload = []): array
    {
        $response = $this->getProvider()
            ->payment()
            ->faceToFace()
            ->optional('time_expire', $request->getTimeExpire()->toDateTimeString())
            ->optional('passback_params', $request->getAttach())
            ->precreate(
                $request->getSubject(),
                $request->getOutTradeNo(),
                $request->getAmount()
            );
        if ($response->code == '10000') {
            return json_decode($response->httpBody, true);
        }
        throw new Exception($response->subMsg);
    }

    /**
     * 小程序支付
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    protected function mini(PaymentRequest $request, array $mergePayload = []): array
    {
        $response = $this->getProvider()
            ->payment()
            ->common()
            ->optional('time_expire', $request->getTimeExpire()->toDateTimeString())
            ->optional('passback_params', $request->getAttach())
            ->create(
                $request->getSubject(),
                $request->getOutTradeNo(),
                $request->getAmount(),
                $request->getBuyerId()
            );
        if ($response->code == '10000') {
            return json_decode($response->httpBody, true);
        }
        throw new Exception($response->subMsg);
    }

    /**
     * 转账
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    protected function transfer(PaymentRequest $request, array $mergePayload = []): array
    {
        $response = $this->getProvider()
            ->util()
            ->generic()
            ->execute('alipay.fund.trans.uni.transfer', [], [
                'out_biz_no' => $request->getOutTradeNo(),
                'trans_amount' => $request->getAmount(),
                'biz_scene' => 'DIRECT_TRANSFER',
                'product_code' => 'TRANS_ACCOUNT_NO_PWD',
                'order_title' => $request->getSubject(),
                'payee_info' => [
                    'identity' => $request->getBuyerId(),
                    'identity_type' => 'ALIPAY_USER_ID',
                ],
            ]);
        if ($response->code == '10000') {
            return json_decode($response->httpBody, true);
        }
        throw new Exception($response->subMsg);
    }

    /**
     * 查询
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    public function query(PaymentRequest $request, array $mergePayload = []): array
    {
        $response = $this->getProvider()
            ->payment()
            ->common()
            ->query($request->getOutTradeNo());
        if ($response->code == '10000') {
            return json_decode($response->httpBody, true);
        }
        throw new Exception($response->subMsg);
    }

    /**
     * 关闭
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    public function close(PaymentRequest $request, array $mergePayload = []): array
    {
        $response = $this->getProvider()
            ->payment()
            ->common()
            ->close($request->getOutTradeNo());
        if ($response->code == '10000') {
            return json_decode($response->httpBody, true);
        }
        throw new Exception($response->subMsg);
    }

    /**
     * 交易退款
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    public function refund(PaymentRequest $request, array $mergePayload = []): array
    {
        $response = $this->getProvider()
            ->payment()
            ->common()
            ->refund($request->getOutTradeNo(), $request->getRefundAmount());
        if ($response->code == '10000') {
            return json_decode($response->httpBody, true);
        }
        throw new Exception($response->subMsg);
    }

    /**
     * 交易退款查询
     *
     * @param PaymentRequest $request
     * @return array
     * @throws Exception
     */
    public function refundQuery(PaymentRequest $request): array
    {
        $response = $this->getProvider()
            ->payment()
            ->common()
            ->queryRefund($request->getOutTradeNo(), $request->getOutRefundNo());
        if ($response->code == '10000') {
            return json_decode($response->httpBody, true);
        }
        throw new Exception($response->subMsg);
    }

    /**
     * 撤销交易
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    public function cancel(PaymentRequest $request, array $mergePayload = []): array
    {
        $response = $this->getProvider()
            ->payment()
            ->common()
            ->cancel($request->getOutTradeNo());
        if ($response->code == '10000') {
            return json_decode($response->httpBody, true);
        }
        throw new Exception($response->subMsg);
    }

    /**
     * 通知验证
     *
     * @param array $data
     * @return array|bool
     */
    public function notify(array $data = []): array|bool
    {
        return $this->getProvider()
            ->payment()
            ->common()
            ->verifyNotify($data) ? $data : false;
    }

    /**
     * 通知响应
     *
     * @return string
     */
    public function notifyResponse(): string
    {
        return 'success';
    }

}