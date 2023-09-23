<?php

return [
    'alipay' => [
        'default' => [
            // 必填-支付宝分配的 app_id
            'app_id' => env('ALIPAY_APP_ID', '202100410260****'),
            // 必填-应用私钥 路径
            'private_key' => env('ALIPAY_PRIVATE_KEY', storage_path('payment/alipay_sandbox/merchantPrivateKey.txt')),

            // 必填-支付宝公钥 路径
            'alipay_public_key' => env('ALIPAY_PUBLIC_KEY', storage_path('payment/alipay_sandbox/alipayPublicKey.txt')),

            // 必填-应用公钥证书 路径
            'app_cert_path' => env('ALIPAY_APP_CERT_PATH', storage_path('payment/alipay_sandbox/appCertPublicKey_2021004102600102.crt')),
            // 必填-支付宝公钥证书 路径
            'alipay_public_cert_path' => env('ALIPAY_PUBLIC_CERT_PATH', storage_path('payment/alipay_sandbox/alipayCertPublicKey_RSA2.crt')),
            // 必填-支付宝根证书 路径
            'root_cert_path' => env('ALIPAY_ROOT_CERT_PATH', storage_path('payment/alipay_sandbox/alipayRootCert.crt')),
            'return_url' => env('ALIPAY_RETURN_URL', 'http://localhost/'),
            'notify_url' => env('ALIPAY_NOTIFY_URL', 'http://localhost/'),
        ],
    ],
    'wechat' => [
        'default' => [
            // 必填-商户号
            'mch_id' => env('WECHAT_MCH_ID', '190000****'),
            'api_v2_key' => env('WECHAT_API_V2_KEY', ''),
            'api_v3_key' => env('WECHAT_API_V3_KEY', ''),
            // 必填-商户API证书私钥 路径
            'merchant_private_key_path' => env('WECHAT_MERCHANT_PRIVATE_KEY_PATH', storage_path('payment/wechatpay/apiclient_key.pem')),
            // 必填-商户API证书序列号
            'merchant_certificate_serial' => env('WECHAT_MERCHANT_CERTIFICATE_SERIAL', '3775B6A45ACD588826D15E583A95F5DD********'),
            // 必填-微信支付平台证书
            'platform_certificate_path' => env('WECHAT_PLATFORM_CERTIFICATE_PATH', storage_path('payment/wechatpay/cert.pem')),
            // 必填
            'notify_url' => env('WECHAT_NOTIFY_URL', 'http://localhost/'),
            // 选填-公众号 的 app_id
            'mp_app_id' => env('WECHAT_MP_APP_ID', ''),
            // 选填-小程序 的 app_id
            'mini_app_id' => env('WECHAT_MINI_APP_ID', ''),
            // 选填-app 的 app_id
            'app_id' => env('WECHAT_APP_ID', ''),
        ],
    ],
];
