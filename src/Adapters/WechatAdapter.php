<?php

namespace Jundayw\LaravelPayment\Adapters;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Jundayw\LaravelPayment\PaymentAdapter;
use Jundayw\LaravelPayment\Requests\PaymentRequest;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WeChatPay\Util\PemUtil;

class WechatAdapter extends PaymentAdapter
{
    public function getProvider(): BuilderChainable
    {
        // 商户号
        $merchantId = $this->getConfig('mch_id');
        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyFilePath = file_get_contents($this->getConfig('merchant_private_key_path'));
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);
        // 「商户API证书」的「证书序列号」
        $merchantCertificateSerial = $this->getConfig('merchant_certificate_serial');
        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
        $platformCertificateFilePath = file_get_contents($this->getConfig('platform_certificate_path'));
        // 从「微信支付平台证书」中获取「证书序列号」
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);
        // 构造一个 APIv3 客户端实例
        return Builder::factory([
            'mchid' => $merchantId,
            'privateKey' => $merchantPrivateKeyInstance,
            'serial' => $merchantCertificateSerial,
            'certs' => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);
    }

    /**
     * @throws Exception
     */
    public function pay(string $method, PaymentRequest $request, array $mergePayload = [])
    {
        if (method_exists($this, $method) && in_array($method, ['wap', 'app', 'pos', 'scan', 'mp', 'mini', 'transfer'])) {
            try {
                return call_user_func_array([$this, $method], [$request, $mergePayload]);
            } catch (Exception $exception) {
                dd($exception);
                throw new Exception($exception->getMessage());
            }
        }
        throw new InvalidArgumentException(sprintf(
            '[%s] not supported [%s].', static::class, $method
        ));
    }

    /**
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    protected function wap(PaymentRequest $request, array $mergePayload = []): array
    {
        $data   = [
            'json' => [
                "appid" => $this->getConfig('app_id'),
                "mchid" => $this->getConfig('mch_id'),
                "description" => $request->getSubject(),
                "out_trade_no" => $request->getOutTradeNo(),
                "notify_url" => $this->getConfig('notify_url'),
                "time_expire" => $request->getTimeExpire()->toRfc3339String(),
                "attach" => $request->getAttach(),
                "amount" => [
                    "total" => intval($request->getAmount(100)),
                ],
                "scene_info" => [
                    "payer_client_ip" => $request->getClientIp(),
                    "h5_info" => [
                        'type' => 'Wap',
                    ],
                ],
            ],
        ];
        $result = $this->getProvider()
            ->chain('/v3/pay/transactions/h5')
            ->post($data);
        if ($result->getStatusCode() != 200) {
            throw new Exception('请求微信H5预下单异常');
        }
        return json_decode($result->getBody()->getContents(), true);
    }

    /**
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    protected function app(PaymentRequest $request, array $mergePayload = []): array
    {
        $data   = [
            'json' => [
                "appid" => $this->getConfig('app_id'),
                "mchid" => $this->getConfig('mch_id'),
                "description" => $request->getSubject(),
                "out_trade_no" => $request->getOutTradeNo(),
                "time_expire" => $request->getTimeExpire()->toRfc3339String(),
                "attach" => $request->getAttach(),
                "notify_url" => $this->getConfig('notify_url'),
                "amount" => [
                    "total" => intval($request->getAmount(100)),
                ],
            ],
        ];
        $result = $this->getProvider()
            ->chain('/v3/pay/transactions/app')
            ->post($data);
        if ($result->getStatusCode() != 200) {
            throw new Exception('请求微信APP预下单异常');
        }
        $result = $result->getBody()->getContents();

        $result     = json_decode($result->getBody()->getContents(), true);
        $payData    = [
            "appId" => $this->getConfig('app_id'),
            "timeStamp" => (string)time(),
            "nonceStr" => md5(uniqid(microtime(true), true)),
            "package" => "prepay_id={$result["prepay_id"]}",
        ];
        $payDataStr = "";
        foreach ($payData as $key => $value) {
            $payDataStr .= "{$value}\n";
        }
        $privateKey           = file_get_contents($this->config['merchant_private_key_path']);
        $privateKeyResource   = openssl_pkey_get_private($privateKey);
        $sign                 = openssl_sign(
            $payDataStr,
            $signature,
            $privateKeyResource,
            OPENSSL_ALGO_SHA256) ? base64_encode($signature) : '';
        $payData["sign"]      = $sign;
        $payData["partnerid"] = $this->getConfig('mch_id');
        return $payData;
    }

    /**
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    public function pos(PaymentRequest $request, array $mergePayload = []): array
    {
        $data         = [
            'appid' => $this->getConfig('app_id'),
            'mch_id' => $this->getConfig('mch_id'),
            'nonce_str' => md5(uniqid(microtime(true), true)),
            'body' => $request->getSubject(),
            'out_trade_no' => $request->getOutTradeNo(),
            "time_expire" => $request->getTimeExpire()->toRfc3339String(),
            "attach" => $request->getAttach(),
            'total_fee' => intval($request->getAmount(100)),
            'spbill_create_ip' => $request->getClientIp(),
            'auth_code' => $request->getAuthCode(),
        ];
        $data['sign'] = $this->wxSign($data);
        $xml          = $this->array2Xml($data);
        $result       = Http::send('POST', 'https://api.mch.weixin.qq.com/pay/micropay', [
            "body" => $xml,
        ]);
        if ($result->status() != 200) {
            throw new Exception('请求微信付款码支付异常');
        }
        return $this->xml2Array($result->body());
    }

    /**
     *
     * 扫码支付
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    public function scan(PaymentRequest $request, array $mergePayload = []): array
    {
        $data   = [
            'json' => [
                "appid" => $this->getConfig('app_id'),
                "mchid" => $this->getConfig('mch_id'),
                "description" => $request->getSubject(),
                "out_trade_no" => $request->getOutTradeNo(),
                "time_expire" => $request->getTimeExpire()->toRfc3339String(),
                "attach" => $request->getAttach(),
                "notify_url" => $this->getConfig('notify_url'),
                "amount" => [
                    "total" => intval($request->getAmount(100)),
                ],
            ],
        ];
        $result = $this->getProvider()
            ->chain('/v3/pay/transactions/native')
            ->post($data);
        if ($result->getStatusCode() != 200) {
            throw new Exception('请求微信扫码预下单异常');
        }
        return json_decode($result->getBody()->getContents(), true);
    }

    /**
     * 公众号支付
     *
     * @param PaymentRequest $request
     * @param array $mergePayload
     * @return array
     * @throws Exception
     */
    public function mp(PaymentRequest $request, array $mergePayload = []): array
    {
        $data   = [
            'json' => [
                "appid" => $this->getConfig('mp_app_id'),
                "mchid" => $this->getConfig('mch_id'),
                "description" => $request->getSubject(),
                "out_trade_no" => $request->getOutTradeNo(),
                "time_expire" => $request->getTimeExpire()->toRfc3339String(),
                "attach" => $request->getAttach(),
                "notify_url" => $this->getConfig('notify_url'),
                "amount" => [
                    "total" => intval($request->getAmount(100)),
                ],
                "payer" => [
                    "openid" => $request->getBuyerId(),
                ],
            ],
        ];
        $result = $this->getProvider()
            ->chain('/v3/pay/transactions/jsapi')
            ->post($data);

        if ($result->getStatusCode() != 200) {
            throw new Exception('请求微信小程序预下单异常');
        }

        $result     = json_decode($result->getBody()->getContents(), true);
        $payData    = [
            "appId" => $this->getConfig('app_id'),
            "timeStamp" => (string)time(),
            "nonceStr" => md5(uniqid(microtime(true), true)),
            "package" => "prepay_id={$result["prepay_id"]}",
        ];
        $payDataStr = "";
        foreach ($payData as $key => $value) {
            $payDataStr .= "{$value}\n";
        }
        $privateKey          = file_get_contents($this->config['merchant_private_key_path']);
        $privateKeyResource  = openssl_pkey_get_private($privateKey);
        $sign                = openssl_sign(
            $payDataStr,
            $signature,
            $privateKeyResource,
            OPENSSL_ALGO_SHA256) ? base64_encode($signature) : '';
        $payData["signType"] = "RSA";
        $payData["paySign"]  = $sign;
        return $payData;
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
        $data   = [
            'json' => [
                "appid" => $this->getConfig('mini_app_id'),
                "mchid" => $this->getConfig('mch_id'),
                "description" => $request->getSubject(),
                "out_trade_no" => $request->getOutTradeNo(),
                "time_expire" => $request->getTimeExpire()->toRfc3339String(),
                "attach" => $request->getAttach(),
                "notify_url" => $this->getConfig('notify_url'),
                "amount" => [
                    "total" => intval($request->getAmount(100)),
                ],
                "payer" => [
                    "openid" => $request->getBuyerId(),
                ],
            ],
        ];
        $result = $this->getProvider()
            ->chain('/v3/pay/transactions/jsapi')
            ->post($data);

        if ($result->getStatusCode() != 200) {
            throw new Exception('请求微信小程序预下单异常');
        }

        $result     = json_decode($result->getBody()->getContents(), true);
        $payData    = [
            "appId" => $this->getConfig('app_id'),
            "timeStamp" => (string)time(),
            "nonceStr" => md5(uniqid(microtime(true), true)),
            "package" => "prepay_id={$result["prepay_id"]}",
        ];
        $payDataStr = "";
        foreach ($payData as $key => $value) {
            $payDataStr .= "{$value}\n";
        }
        $privateKey          = file_get_contents($this->config['merchant_private_key_path']);
        $privateKeyResource  = openssl_pkey_get_private($privateKey);
        $sign                = openssl_sign(
            $payDataStr,
            $signature,
            $privateKeyResource,
            OPENSSL_ALGO_SHA256) ? base64_encode($signature) : '';
        $payData["signType"] = "RSA";
        $payData["paySign"]  = $sign;
        return $payData;
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
        $data   = [
            "json" => [
                'appid' => $this->getConfig('app_id'),
                'out_batch_no' => $request->getOutTradeNo(), // 商户系统内部的商家批次单号
                'batch_name' => $request->getSubject(),
                'batch_remark' => $request->getSubject(),
                'total_amount' => intval($request->getAmount(100)),
                'total_num' => 1, //一个转账批次单最多发起一千笔转账。
                'transfer_detail_list' => [
                    [
                        "out_detail_no" => $request->getOutTradeNo(),//商户系统内部区分转账批次单下不同转账明细单的唯一标识
                        "transfer_amount" => intval($request->getAmount(100)),
                        "transfer_remark" => $request->getSubject(),
                        "openid" => $request->getBuyerId(),
                    ],
                ],
            ],
        ];
        $result = $this->getProvider()
            ->chain("/v3/transfer/batches")
            ->post($data);
        if ($result->getStatusCode() != 200) {
            throw new Exception('请求微信转账异常');
        }
        return json_decode($result->getBody()->getContents(), true);
    }

    public function query(PaymentRequest $request, array $mergePayload = []): array
    {
        try {
            $result = $this->getProvider()
                ->chain("/v3/pay/transactions/out-trade-no/{$request->getOutTradeNo()}?mchid={$this->getConfig('mch_id')}")
                ->get();
            if ($result->getStatusCode() != 200) {
                throw new Exception('请求微信订单号查询异常');
            }
            return json_decode($result->getBody()->getContents(), true);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    public function close(PaymentRequest $request, array $mergePayload = []): array
    {
        try {
            $data   = [
                "json" => [
                    "mchid" => $this->getConfig('mch_id'),
                ],
            ];
            $result = $this->getProvider()
                ->chain("/v3/pay/transactions/out-trade-no/{$request->getOutTradeNo()}/close")
                ->post($data);
            if ($result->getStatusCode() != 204) {
                throw new Exception('请求微信关闭订单异常');
            }
            return json_decode($result->getBody()->getContents(), true);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    public function refund(PaymentRequest $request, array $mergePayload = []): array
    {
        try {
            $data   = [
                'json' => [
                    "out_trade_no" => $request->getTradeCode(),                   // 原支付交易对应的商户订单号，与transaction_id二选一
                    "out_refund_no" => $request->getOutRefundNo(),                // 商户系统内部的退款单号
                    "notify_url" => $this->getConfig('notify_url'),               // 商户系统内部的退款单号
                    "amount" => [
                        "refund" => intval($request->getRefundAmount(100)), // 退款金额
                        "total" => intval($request->getTotalAmount(100)),   // 原支付交易的订单总金额
                        "currency" => "CNY",
                    ],
                ],
            ];
            $result = $this->getProvider()
                ->chain('/v3/refund/domestic/refunds')
                ->post($data);
            if ($result->getStatusCode() != 200) {
                throw new Exception('请求微信退款异常');
            }
            return json_decode($result->getBody()->getContents(), true);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    public function refundQuery(PaymentRequest $request): array
    {
        try {
            $result = $this->getProvider()
                ->chain("/v3/refund/domestic/refunds/{$request->getOutRefundNo()}")
                ->get();
            if ($result->getStatusCode() != 200) {
                throw new Exception('请求微信退款查询异常');
            }
            return json_decode($result->getBody()->getContents(), true);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    public function cancel(PaymentRequest $request, array $mergePayload = [])
    {
        throw new Exception('微信不支持此功能');
    }

    public function notify(array $data = []): array|bool
    {
        $inWechatpaySignature      = $data['Wechatpay-Signature'];
        $inWechatpayTimestamp      = $data['Wechatpay-Timestamp'];
        $inWechatpaySerial         = $data['Wechatpay-Serial'];
        $inWechatpayNonce          = $data['Wechatpay-Nonce'];
        $inBody                    = file_get_contents('php://input');
        $platformPublicKeyInstance = Rsa::from(file_get_contents($this->getConfig('platform_certificate_path')), Rsa::KEY_TYPE_PUBLIC);
        $timeOffsetStatus          = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus            = Rsa::verify(
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            $platformPublicKeyInstance
        );
        if ($timeOffsetStatus && $verifiedStatus) {
            // 转换通知的JSON文本消息为PHP Array数组
            $inBodyArray = (array)json_decode($inBody, true);
            // 使用PHP7的数据解构语法，从Array中解构并赋值变量
            ['resource' => [
                'ciphertext' => $ciphertext,
                'nonce' => $nonce,
                'associated_data' => $aad,
            ]] = $inBodyArray;
            // 加密文本消息解密
            $inBodyResource = AesGcm::decrypt($ciphertext, $this->getConfig('api_v3_key'), $nonce, $aad);
            // 把解密后的文本转换为PHP Array数组
            return json_decode($inBodyResource, true);
        }
        return false;
    }

    public function notifyResponse(string $code = 'SUCCESS', string $message = ''): string
    {
        return json_encode([
            'code' => $code,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
    }

    protected function wxSign(array $arr): string
    {
        ksort($arr);
        $str = '';
        foreach ($arr as $k => $v) {
            if ($str == '') {
                $str = $k . '=' . $v;
            } else {
                $str .= '&' . $k . '=' . $v;
            }
        }
        // 拼接上key值
        $string = $str . '&key=' . $this->getConfig('api_v2_key');

        // 加密生成支付签名
        return strtoupper(md5($string));
    }

    protected function array2Xml(array $arr): string
    {
        ksort($arr);//将数组进行升序排列
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }

    protected function xml2Array(string $xml): array
    {
        if (!$xml) {
            return [];
        }
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    }

}