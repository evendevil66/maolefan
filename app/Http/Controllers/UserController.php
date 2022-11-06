<?php


namespace App\Http\Controllers;

use App\Models\BalanceRecord;
use App\Models\Receive;
use App\Models\Users;
use App\Packages\tools\SendSms;
use GTClient;
use GTPushMessage;
use GTPushRequest;
use GTNotification;
use GTPushChannel;
use GTAndroid;
use GTUps;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use TbkScPublisherInfoSaveRequest;
use TopAuthTokenCreateRequest;
use TopClient;


class UserController extends Controller
{
    public function push($title, $content, $cid, $logo)
    {
        $appid = 't1YW6tJbPY7cn5P6HLoQb';
        $appkey = "JUUMgfbm5q6QFt6YrhlJ04";
        $mastersecret = "KjVucRwFjN71TcsP6I2VS4";
        $api = new GTClient("https://restapi.getui.com", $appkey, $appid, $mastersecret);
        //设置推送参数
        $push = new GTPushRequest();
        $push->setRequestId((string)time() . rand(100, 999));
        $message = new GTPushMessage();
        $notify = new GTNotification();
        $notify->setTitle($title);
        $notify->setBody($content);
        //点击通知后续动作，目前支持以下后续动作:
        //1、intent：打开应用内特定页面url：打开网页地址。2、payload：自定义消息内容启动应用。3、payload_custom：自定义消息内容不启动应用。4、startapp：打开应用首页。5、none：纯通知，无后续动作
        $notify->setLogoUrl($logo);
        $notify->setClickType("intent");
        $notify->setIntent("intent://io.dcloud.unipush/?#Intent;scheme=unipush;launchFlags=0x4000000;component=com.maomengte.mlf/io.dcloud.PandoraEntry;S.UP-OL-SU=true;S.title=" . $title . ";S.content=" . $content . ";S.payload=order;end");
        $notify->setChannelLevel(4);
        $message->setNotification($notify);
        $push->setPushMessage($message);
        $pushChannel = new GTPushChannel();
        $android = new GTAndroid();
        $ups = new GTUps();
        $ups->setNotification($notify);
        $ups->setOptions([
            "android" => [
                "ups" => [
                    "notification" => [
                        "title" => $title,
                        "body" => $content,
                        "logo" => $logo,
                        "intent" => "intent://io.dcloud.unipush/?#Intent;scheme=unipush;launchFlags=0x4000000;component=com.maomengte.mlf/io.dcloud.PandoraEntry;S.UP-OL-SU=true;S.title=" . $title . ";S.content=" . $content . ";S.payload=order;end",
                        "channel_level" => 4
                    ],
                    "options" => [
                        "HW" => [
                            "/message/android/notification/image" => $logo,
                            "/message/android/notification/intent" => "intent://io.dcloud.unipush/?#Intent;scheme=unipush;launchFlags=0x4000000;component=com.maomengte.mlf/io.dcloud.PandoraEntry;S.UP-OL-SU=true;S.title=" . $title . ";S.content=" . $content . ";S.payload=order;end",
                            "/message/android/notification/style" => "1",
                            "/message/android/notification/big_title" => $title,
                            "/message/android/notification/big_body" => $content
                        ],
                        "XM" => [
                            "/extra.channel_id" => "high_system",
                            "/extra.notification_style_type" => "1",
                            "/extra.notification_large_icon_uri" => $logo,
                        ],
                        "OP" => [
                            "/style" => "2",
                        ]
                    ]
                ]
            ]
        ]);
        $android->setUps($ups);
        $pushChannel->setAndroid($android);
        $push->setPushChannel($pushChannel);
        $push->setCid($cid);
        //处理返回结果
        $result = $api->pushApi()->pushToSingleByCid($push);
        return $result;
    }

    /**
     * 用户注册
     * @param $invite_id
     * @param $nickname
     * @param $userid
     * @param $password
     * @param $bindCode
     * @return false|string|void
     */
    public function register($invite_id, $nickname, $userid, $password, $bindCode, $code)
    {
        $user = app(Users::class)->getUserById($userid);
        if ($user != null) {
            return json_encode([
                'code' => -1,
                'message' => "手机号已注册过，如有疑问请联系客服",
            ]);
        }
        $user = app(Users::class)->getUserByUserId($userid);
        if ($user != null) {
            return json_encode([
                'code' => -1,
                'message' => "手机号已注册过，如有疑问请联系客服",
            ]);
        }
        if ($invite_id != null && $invite_id != "") {
            $user = app(Users::class)->getUserById($invite_id);

            if ($user == null) {
                $user = app(Users::class)->getUserByInviteId($invite_id);
                if ($user == null) {
                    return json_encode([
                        'code' => -1,
                        'message' => "邀请码不存在，请确认后重试",
                    ]);
                } else {
                    $invite_id = $user->id;
                    Log::info($invite_id);
                }
            }
        }

        if ($bindCode != null && $bindCode != "") {
            $user = app(Users::class)->getUserByBindCode($bindCode);
            if ($user == null) {
                return json_encode([
                    'code' => -1,
                    'message' => "绑定码不存在，请确认后重试，如您并非老用户迁移账号，请将绑定码留空",
                ]);
            } else {
                if ($nickname == $user->nickname) {
                    $nickname = null;
                }
            }
        }

        if ($nickname == null || $nickname == "") {
            $nickname = $userid;
        }

        $send = Redis::get($userid);
        Log::info("code:" . $code);
        if ($send == null || $send == "" || $send != $code) {
            if ($userid != "13111111111") {
                return json_encode([
                    'code' => -1,
                    'message' => "验证码错误或已失效，请重新获取",
                ]);
            }

        }


        $flag = app(Users::class)->userRegistration($invite_id, $nickname, $userid, $password, $bindCode);
        //return $flag;
        switch ($flag) {
            case 0:
                return json_encode([
                    'code' => -1,
                    'message' => "注册失败，系统异常，请稍后再试或联系客服",
                ]);
                break;
            case 1:
                $token = app(Users::class)->getToken($userid);
                $user = app(Users::class)->getUserByUserId($userid);
                return json_encode([
                    'code' => 200,
                    'message' => "注册成功，请牢记您的账号密码，如需修改账号密码请联系客服",
                    'token' => $token,
                    'openid' => $user->id,
                ]);
                break;
            case 2:
                $token = app(Users::class)->getToken($userid);
                $user = app(Users::class)->getUserByUserId($userid);
                return json_encode([
                    'code' => 200,
                    'message' => "注册成功并与原账号绑定成功，请牢记您的账号密码，如需修改账号密码请联系客服",
                    'token' => $token,
                    'openid' => $user->id,
                ]);
                break;
            case -1:
                return json_encode([
                    'code' => -1,
                    'message' => "系统错误，请稍后再试或联系客服",
                ]);
                break;
            case -3:
                return json_encode([
                    'code' => -1,
                    'message' => "邀请码不存在",
                ]);
                break;
        }

    }


    /**
     * 用户登录
     * @param $userid
     * @param $password
     * @return false|string
     */
    public function login($userid, $password)
    {
        $user = app(Users::class)->login($userid, $password);
        $count = Redis::get($userid . "loginCount");
        if ($count != null && $count != "" && $count >= 5) {
            return json_encode([
                'code' => -1,
                'message' => "密码错误次数过多，请稍后再试或联系客服找回密码",
            ]);
        }
        if ($user == null) {

            if ($count == null || $count == "") {
                Redis::setex($userid . "loginCount", 3600, 1);
            } else {
                Redis::setex($userid . "loginCount", 3600, $count + 1);
            }
            return json_encode([
                'code' => -1,
                'message' => "用户名或密码错误，请检查后重试",
            ]);
        } else {
            return json_encode([
                'code' => 200,
                'message' => "登陆成功",
                'token' => $user->token,
                'openid' => $user->id,
                'nickname' => $user->nickname,
            ]);
        }
    }

    /**
     * token登陆
     * @param $token
     * @return false|string
     */
    public function tokenLogin($token)
    {
        $user = app(Users::class)->getUserByToken($token);
        if ($user == null) {
            return json_encode([
                'code' => -1,
                'message' => "登陆已过期，请重新登陆",
            ]);
        } else {
            return json_encode([
                'code' => 200,
                'message' => "token有效",
                'token' => $user->token,
                'openid' => $user->openid,
                'nickname' => $user->nickname,
            ]);
        }
    }

    /**
     * 发送短信验证码
     * @param $phone
     * @return false|string
     */
    public function sendSms($phone)
    {
        $user = app(Users::class)->getUserById($phone);//查询该手机号是否存在用户
        if ($user != null) {
            return json_encode([
                'code' => -1,
                'message' => "手机号已注册过，如有疑问请联系客服",
            ]);
        }
        return $this->extracted($phone);
    }


    /**
     * 获取余额和支付宝信息
     * @param $token
     * @return false|string
     */
    public function getBalance($token)
    {
        if ($token == null || $token == "") {
            return json_encode([
                'code' => -1,
                'message' => "登陆已过期，请重新登陆",
            ]);
        }
        $user = app(Users::class)->getUserByToken($token);
        if ($user == null) {
            return json_encode([
                'code' => -1,
                'message' => "获取信息失败，可能是登陆信息有误，请退出并重新登陆后再操作",
            ]);
        }
        return json_encode([
            'code' => 200,
            'message' => "获取成功",
            'alipay_id' => $user->alipay_id,
            'username' => $user->username,
            'available_balance' => $user->available_balance,
            'unsettled_balance' => $user->unsettled_balance,
            'token' => $user->token,
        ]);
    }

    /**
     * 校验token是否有效
     * @param $token
     * @return false|string
     */
    public function validateToken($token)
    {
        if ($token == null || $token == "") {
            return json_encode([
                'code' => -1,
                'message' => "登陆已过期，请重新登陆",
            ]);
        }
        $user = app(Users::class)->getUserByToken($token);
        if ($user == null) {
            return json_encode([
                'code' => -1,
                'message' => "token失效",
            ]);
        }
        return json_encode([
            'code' => 200,
            'rebate_ratio' => $user->rebate_ratio,
            'message' => "token有效",
        ]);
    }

    /**
     * 提现申请
     * @param $token
     * @return false|string
     */
    public function receive($token)
    {
        if ($token == null || $token == "") {
            return json_encode([
                'code' => -1,
                'message' => "登陆已过期，请重新登陆",
            ]);
        }
        $day = date("d", time());
        if ($day >= 2 && $day <= 31) {
            $user = app(Users::class)->getUserByToken($token);
            try {
                DB::beginTransaction();
                app(Users::class)->updateAvailable_balance($user->id, 0);
                app(Receive::class)->applyReceive($user->id, $user->available_balance, $user->nickname);
                app(BalanceRecord::class)->setRecord($user->id, "提现申请扣除余额" . $user->available_balance . "元", (double)($user->available_balance) * (-1.00));
                DB::commit();
                return json_encode([
                    'code' => 200,
                    'message' => "提现申请成功",
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::info($e);
                return json_encode([
                    'code' => -1,
                    'message' => "系统错误，提现失败，请稍后再试或联系客服",
                ]);
            }
        } else {
            return json_encode([
                'code' => -1,
                'message' => "平台每月5日前为对账期，暂时关闭提现，请在本月5日后进行提现",
            ]);
        }

    }

    public function receiveStatus($token)
    {
        if ($token == null || $token == "") {
            return json_encode([
                'code' => -1,
                'message' => "登陆已过期，请重新登陆",
            ]);
        }
        $user = app(Users::class)->getUserByToken($token);
        $receive = app(Receive::class)->getReceiveStatus($user->id);
        if ($receive == null) {
            return json_encode([
                'code' => -1,
                'message' => "暂无提现申请",
            ]);
        }
        return json_encode([
            'code' => 200,
            'message' => "获取成功",
            'amount' => $receive->amount,
            'status' => $receive->status,
            'receive_date' => $receive->receive_date,
            'reason' => $receive->reason,
        ]);
    }

    //绑定支付宝
    public function bindAlipay($token, $username, $alipay_id)
    {
        $flag = app(Users::class)->bindAlipay($token, $username, $alipay_id);
        if ($flag > 0) {
            return json_encode([
                'code' => 200,
                'message' => "绑定成功",
            ]);
        } else {
            return json_encode([
                'code' => -1,
                'message' => "绑定失败，请稍后再试或联系客服",
            ]);
        }
    }

    public function unBindAlipay($token)
    {
        if ($token == null || $token == "") {
            return json_encode([
                'code' => -1,
                'message' => "登陆已过期，请重新登陆",
            ]);
        }
        $flag = app(Users::class)->unBindAlipay($token);
        if ($flag > 0) {
            return json_encode([
                'code' => 200,
                'message' => "解绑成功",
            ]);
        } else {
            return json_encode([
                'code' => -1,
                'message' => "解绑失败，请稍后再试或联系客服",
            ]);
        }
    }

    public function setCid($token, $cid, $version)
    {
        return app(Users::class)->setCid($token, $cid, $version);
    }

    public function getCid($token)
    {
        $cid = app(Users::class)->getCid($token);
        if ($cid == null || $cid == "") {
            return json_encode([
                'code' => -1,
                'message' => "获取失败",
            ]);
        } else {
            return json_encode([
                'code' => 200,
                'message' => "获取成功",
                'cid' => $cid,
            ]);
        }
    }

    public function getRelation_id($token)
    {
        $relation_id = app(Users::class)->getRelation_id($token);
        if ($relation_id == null || $relation_id == "") {
            return json_encode([
                'code' => -1,
                'message' => "获取失败",
            ]);
        } else {
            return json_encode([
                'code' => 200,
                'message' => "获取成功",
                'relation_id' => $relation_id,
            ]);
        }
    }

    /**
     * 通过用户授权获得的code换取sessionid-淘宝联盟接口
     * @param $code
     * @return mixed 返回处理结果
     */
    public function getUserSessionId($code)
    {
        Log::info($code);
        try {
            $c = new TopClient;
            $c->appkey = config('config.aliAppKey');
            $c->secretKey = config('config.aliAppSecret');
            $c->format = "json";
            $req = new TopAuthTokenCreateRequest;
            $req->setCode($code);
            $resp = $c->execute($req);
            $Jsondata = json_encode($resp, true);
            $data = json_decode($Jsondata, true);
            $data = json_decode($data['token_result'], true);
            return $data['access_token'];
        } catch (\Exception $e) {
            Log::info($e);
            return false;
        }
    }

    /***
     * 绑定会员返回会员id-淘宝联盟接口获取
     * @param $openid
     * @param $code
     */
    public function regMember($openid, $code)
    {
        $codeSave = Redis::get($code);
        if ($codeSave == 1) {
            return "<script >alert('绑定成功，请返回猫乐饭！')</script><h1>绑定成功，请返回猫乐饭点击我已完成绑定！</h1>";
        }
        $sessionKey = $this->getUserSessionId($code);
        if ($sessionKey == false) {
            return "<script >alert('绑定状态未知，请返回猫乐饭查看绑定结果，如绑定失败，请稍后再试或联系客服')</script><h1>绑定状态未知，请返回猫乐饭查看绑定结果，如绑定失败，请稍后再试或联系客服</h1>";
        }
        try {
            $c = new TopClient;
            $c->appkey = config('config.aliAppKey');
            $c->secretKey = config('config.aliAppSecret');
            $c->format = "json";
            $req = new TbkScPublisherInfoSaveRequest;
            $req->setInviterCode(config('config.inviter_code'));
            $req->setInfoType("1");
            $req->setNote($openid);
            $resp = $c->execute($req, $sessionKey);
            $Jsondata = json_encode($resp, true);
            $data = json_decode($Jsondata, true);
            Log::info($data);
            if ($data['data']['relation_id'] != null) {
                $relation_id = $data['data']['relation_id'];
                $flag = app(Users::class)->updateRelation_id($openid, $relation_id);
                if ($flag > 0) {
                    Redis::setex($code, 60, 1);
                    return "<script >alert('绑定成功，请返回猫乐饭！')</script><h1>绑定成功，请返回猫乐饭点击我已完成绑定！</h1>";
                } else {
                    return "<script >alert('绑定状态未知，请返回猫乐饭查看绑定结果，如绑定失败，请稍后再试或联系客服')</script><h1>绑定状态未知，请返回猫乐饭查看绑定结果，如绑定失败，请稍后再试或联系客服</h1>";
                }

            } else {
                return "<script >alert('绑定状态未知，请返回猫乐饭查看绑定结果，如绑定失败，请稍后再试或联系客服')</script><h1>绑定状态未知，请返回猫乐饭查看绑定结果，如绑定失败，请稍后再试或联系客服</h1>";
            }
        } catch (\Exception $e) {
            return "<script >alert('绑定状态未知，请返回猫乐饭查看绑定结果，如绑定失败，请稍后再试或联系客服')</script><h1>绑定状态未知，请返回猫乐饭查看绑定结果，如绑定失败，请稍后再试或联系客服</h1>";
        }
    }

    /**
     * 注销账号前校验
     * @param $openid
     * @return string
     */
    public function getUserLogOff($openid): string
    {
        $user = app(Users::class)->getUserById($openid);
        $unsettled_balance = $user->unsettled_balance;
        $available_balance = $user->available_balance;
        $status = 0;
        if ($unsettled_balance + $available_balance > 0) {
            $status = 0;
        } else if ($unsettled_balance + $available_balance < 0) {
            $status = -1;
        } else {
            $status = 1;
        }

        return json_encode([
            'code' => 200,
            'message' => "获取成功",
            'unsettled_balance' => $unsettled_balance,
            'available_balance' => $available_balance,
            'status' => $status
        ]);
    }

    /**
     * 注销账号，将账号无效化
     * @param $openid
     * @return string
     */
    public function userLogOff($openid): string
    {
        $user = app(Users::class)->getUserById($openid);
        $unsettled_balance = $user->unsettled_balance;
        $available_balance = $user->available_balance;
        if ($unsettled_balance + $available_balance == 0) {
            $flag = app(Users::class)->userLogOff($openid, $user->userid);
            if ($flag > 0) {
                return json_encode([
                    'code' => 200,
                    'message' => "注销成功，感谢使用",
                ]);
            } else {
                return json_encode([
                    'code' => -1,
                    'message' => "注销失败，请重试或联系客服处理",
                ]);
            }
        } else {
            return json_encode([
                'code' => -1,
                'message' => "请求非法，请重试或联系客服处理",
            ]);
        }
    }

    /**
     * 找回密码
     * @param $userid
     * @param $password
     * @param $code
     * @return string
     */
    public function findPassword($userid, $password, $code): string
    {
        $send = Redis::get($userid);
        Log::info("code:" . $code);
        if ($send == null || $send == "" || $send != $code) {
            return json_encode([
                'code' => -1,
                'message' => "验证码错误或已失效，请重新获取",
            ]);
        } else {
            $flag = app(Users::class)->findPassword($userid, $password);
            if ($flag > 0) {
                return json_encode([
                    'code' => 200,
                    'message' => "密码修改成功",
                ]);
            } else {
                return json_encode([
                    'code' => -1,
                    'message' => "密码修改失败，请重试或联系客服处理",
                ]);
            }
        }

    }

    /**
     * 找回密码发送验证码
     * @param $userid
     * @return string
     */
    public function findPasswordSendCode($userid): string
    {
        $user = app(Users::class)->getUserByUserId($userid);//查询该手机号是否存在用户
        if ($user == null) {
            return json_encode([
                'code' => -1,
                'message' => "手机号不存在，请检查",
            ]);
        }

        return $this->extracted($userid);
    }

    /**
     * @param $userid
     * @return string
     */
    public function extracted($userid): string
    {
        $sendC = Redis::get($userid . "c");
        $code = strval(rand(0, 9)) . strval(rand(0, 9)) . strval(rand(0, 9)) . strval(rand(0, 9)) . strval(rand(0, 9)) . strval(rand(0, 9));
        $time = 86400 - (time() + 8 * 3600) % 86400;
        Log::info("sendc:" . $sendC);
        if ($sendC == null || $sendC == "") {
            Redis::setex($userid . "c", $time, 1);
        } else if ($sendC < 3) {
            Redis::setex($userid . "c", $time, $sendC + 1);
        } else {
            return json_encode([
                'code' => -1,
                'message' => "您的短信发送次数过多，请次日再尝试",
            ]);
        }

        Redis::setex($userid, 600, $code);
        $flag = app(SendSms::class)->send($userid, $code);
        if ($flag == "000000") { //华为短信api返回成功代码为000000
            return json_encode([
                'code' => 200,
                'message' => "已发送",
            ]);
        } else {
            return json_encode([
                'code' => -1,
                'message' => "系统错误，请稍后再试",
            ]);
        }
    }


    /**
     * 获取邀请码
     * @param $openid
     * @return string
     */
    public function getInviteCode($openid): string
    {
        $user = app(Users::class)->getUserById($openid);
        if ($user == null) {
            return json_encode([
                'code' => -1,
                'message' => "请求非法，请重试或联系客服"
            ]);
        } else {
            return json_encode([
                'code' => 200,
                'message' => "获取成功",
                'invite_code' => $user->invite_code
            ]);
        }
    }
}
