<?php

namespace App\Packages\tools;

use Illuminate\Support\Facades\Log;

class SendSms
{
    public function send($phone, $code)
    {
        //必填,请参考"开发准备"获取如下数据,替换为实际值
        $url = 'https://smsapi.cn-north-4.myhuaweicloud.com:443/sms/batchSendSms/v1'; //APP接入地址(在控制台"应用管理"页面获取)+接口访问URI
        $APP_KEY = '***'; //APP_Key
        $APP_SECRET = '***'; //APP_Secret
        $sender = '***'; //国内短信签名通道号或国际/港澳台短信通道号
        $TEMPLATE_ID = '***'; //模板ID
        //条件必填,国内短信关注,当templateId指定的模板类型为通用模板时生效且必填,必须是已审核通过的,与模板类型一致的签名名称
        //国际/港澳台短信不用关注该参数
        $signature = '***'; //签名名称
        //必填,全局号码格式(包含国家码),示例:+86151****6789,多个号码之间用英文逗号分隔
        $receiver = "+86" . $phone; //短信接收人号码
        //选填,短信状态报告接收地址,推荐使用域名,为空或者不填表示不接收状态报告
        $statusCallback = '';

        /**
         * 选填,使用无变量模板时请赋空值 $TEMPLATE_PARAS = '';
         * 单变量模板示例:模板内容为"您的验证码是${1}"时,$TEMPLATE_PARAS可填写为'["369751"]'
         * 双变量模板示例:模板内容为"您有${1}件快递请到${2}领取"时,$TEMPLATE_PARAS可填写为'["3","人民公园正门"]'
         * 模板中的每个变量都必须赋值，且取值不能为空
         * 查看更多模板和变量规范:产品介绍>模板和变量规范
         * @var string $TEMPLATE_PARAS
         */

        $TEMPLATE_PARAS = '["' . $code . '"]'; //模板变量，此处以单变量验证码短信为例，请客户自行生成6位验证码，并定义为字符串类型，以杜绝首位0丢失的问题（例如：002569变成了2569）。
        //请求Headers
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: WSSE realm="SDP",profile="UsernameToken",type="Appkey"',
            'X-WSSE: ' . $this->buildWsseHeader($APP_KEY, $APP_SECRET)
        ];
        //请求Body
        $data = http_build_query([
            'from' => $sender,
            'to' => $receiver,
            'templateId' => $TEMPLATE_ID,
            'templateParas' => $TEMPLATE_PARAS,
            'statusCallback' => $statusCallback,
            //'signature' => $signature //使用国内短信通用模板时,必须填写签名名称
        ]);

        $context_options = [
            'http' => ['method' => 'POST', 'header' => $headers, 'content' => $data, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false] //为防止因HTTPS证书认证失败造成API调用失败，需要先忽略证书信任问题
        ];
        $response = file_get_contents($url, false, stream_context_create($context_options));
        Log::info($response);
        $response = json_decode($response);

        return $response->code;


    }

    /**
     * 构造X-WSSE参数值
     * @param string $appKey
     * @param string $appSecret
     * @return string
     */
    function buildWsseHeader(string $appKey, string $appSecret)
    {
        date_default_timezone_set('Asia/Shanghai');
        $now = date('Y-m-d\TH:i:s\Z'); //Created
        $nonce = uniqid(); //Nonce
        $base64 = base64_encode(hash('sha256', ($nonce . $now . $appSecret))); //PasswordDigest
        return sprintf("UsernameToken Username=\"%s\",PasswordDigest=\"%s\",Nonce=\"%s\",Created=\"%s\"",
            $appKey, $base64, $nonce, $now);
    }

}
