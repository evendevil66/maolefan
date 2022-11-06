<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //跳过csrf验证
        'wechat',
        '/AppLogin',
        '/AppReg',
        '/SendSms',
        '/validateToken',
        '/getBalance',
        '/receive',
        '/receiveStatus',
        '/unbindAlipay',
        '/bindAlipay',
        '/setCid',
        '/queryTlj',
        '/createTlj',
        '/sendFPCode',
        '/findPassword',
        '/checkLogOff',
        '/logOff',
    ];
}
