# 安装方法

命令行下, 执行 composer 命令安装:

````
composer require jundayw/laravel-render-provider
````

authentication package that is simple and enjoyable to use.

# 对象方法

```php
$message = new PaymentRequest();

$message->setSubject('测试456');
$message->setAmount(0.01);
$message->setOutTradeNo('' . time());
$message->setAttach([
    'id' => 1,
]);

$message->setBuyerId('mhfhvf8808@sandbox.com');
$message->setBuyerId('2088722004475227');
$message->setAuthCode('130842561143513043');

return Payment::provider('wechat')->pay('wap', $message);
```