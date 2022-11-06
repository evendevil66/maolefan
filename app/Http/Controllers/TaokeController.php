<?php


namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\Tlj;
use App\Models\Users;
use App\Models\Orders;
use App\Models\BalanceRecord;
use App\Packages\tools\Tools;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use TopClient;
use TbkItemInfoGetRequest;
use TopAuthTokenCreateRequest;
use TbkScPublisherInfoSaveRequest;
use TbkOrderDetailsGetRequest;
use Illuminate\Support\Facades\Redis;


class TaokeController extends Controller
{
    /**
     * 发起get请求
     * @param $url
     * @param $method
     * @param int $post_data
     * @return bool|string
     */
    public function curlGet($url, $method, $post_data = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//绕过ssl验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);

            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        } elseif ($method == 'get') {
            curl_setopt($ch, CURLOPT_HEADER, 0);
        }
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    /**
     * 参数加密
     * @param $data
     * @return string
     */
    function makeSign($data)
    {
        $appSecret = config('config.dtkAppSecret');
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {

            $str .= '&' . $k . '=' . $v;
        }
        $str = trim($str, '&');
        $sign = strtoupper(md5($str . '&key=' . $appSecret));
        return $sign;
    }

    /***
     * 解析并调用转链函数
     * @param $id /传入提交转链用户的信息
     * @param $content /传入用户的完整消息
     * @return array|string /返回转链后的文本信息
     */
    public function parse($id, $content): array|string
    {
        $user = app(Users::class)->getUserById($id);
        $rate = $user->rebate_ratio * 0.01;
        $dataArr = $this->dtkParse($content);//调用大淘客淘宝转链接口
        $status = $dataArr['code']; //获取转链接口status
        switch ($status) {
            case "0":
                $title = $dataArr['data']['originInfo']['title'];
                $price = $dataArr['data']['originInfo']['price'];
                $goodsid = $dataArr['data']['itemId'];
                $image = $dataArr['data']['originInfo']['image'];
                $dataArr = $this->privilegeLink($goodsid);
                return $this->formatDataByTb($rate, $dataArr, $title, $price, $image); //调用淘宝的转链格式化数据
            case "-1":
                return "系统错误，请稍后再试";
            case "20002":
            case "200002":
            case "200001":
            case "20001":
            case "200003":
            case "200004":
                if (strlen($content) > 10) {
                    return $this->jdParse($content, $rate);//调用京东转链
                } else {
                    return "查询失败，可能无饭粒";
                }
            case "25003":
                return "查询失败，可能无饭粒";
            default:
                return "系统错误，请稍后再试";
        }

    }

    /**
     * 通过淘宝商品id转链
     * @param $openid
     * @param $goodsId
     * @return false|string
     */
    public function parseTbByGoodsId($openid, $goodsId): bool|string
    {
        $user = app(Users::class)->getUserById($openid);
        $rate = $user->rebate_ratio * 0.01;
        $dataArr = null;
        if ($user->relation_id == null || $user->relation_id == "") {
            $dataArr = $this->privilegeLink($goodsId);
        } else {
            $dataArr = $this->privilegeLinkByRelation_id($goodsId, $user->relation_id);
        }
        $data = $this->formatDataByTbApi($user, $rate, $dataArr, $goodsId, null, null, null);
        if ($data == 0) {
            return json_encode(['code' => 0, 'message' => '获取饭粒失败，可能活动已结束，请稍后再试或联系客服']);
        } else if ($data == -1) {
            return json_encode(['code' => -1, 'message' => '获取饭粒失败，可能活动已结束，请稍后再试或联系客服']);
        } else {
            return json_encode([
                'code' => 200,
                'message' => 'success',
                'data' => $data
            ]);
        }
    }

    /**
     * 解析并调用转链函数
     * @param $id
     * @param $content
     * @return false|string
     */
    public function parseApi($id, $content, $relation = true)
    {
        //if($id != "1"){
        //    return json_encode(['code'=>-1,'message'=>'系统正在结账，请稍后进行查询']);
        //}
        //Log::info("relation:".$relation);
        $user = app(Users::class)->getUserById($id);
        $rate = $user->rebate_ratio * 0.01;
        $relation_id = $user->relation_id;
        //Log::info("content:".str_replace(PHP_EOL,"",$content));
        $dataArr = $this->dtkParse(str_replace(PHP_EOL, "", $content));//调用大淘客淘宝转链接口
        Log::info(json_encode($dataArr));
        //return json_encode(['code'=>-2,'message'=>$dataArr['code']]);
        $status = $dataArr['code']; //获取转链接口status
        //Log::info("status:".$status);
        Log::info($status);
        if ($status == "0" && $dataArr['data'] == null) {
            $status = 400;
        } else if ($status == "0" && $dataArr['data']["itemId"] == "") {
            $status = 400;
        }

        switch ($status) {
            case "0":
                $title = $dataArr['data']['originInfo']['title'];
                $price = $dataArr['data']['originInfo']['price'];
                $goodsid = $dataArr['data']['itemId'];
                $image = $dataArr['data']['originInfo']['image'];
                $relationFlag = false;
                if ($relation == "true") {
                    $relationFlag = true;
                    $dataArr = $this->privilegeLinkByRelation_id($goodsid, $relation_id);
                } else {
                    $dataArr = $this->privilegeLink($goodsid);
                }
                $data = $this->formatDataByTbApi($user, $rate, $dataArr, $goodsid, $title, $price, $image, $relationFlag);
                if ($data == 0) {
                    return json_encode(['code' => 0, 'message' => '商品无饭粒']);
                } else if ($data == -1) {
                    Log::info(json_encode($dataArr));
                    return json_encode(['code' => -1, 'message' => '系统错误，请稍后再试或联系客服0']);
                } else {
                    return json_encode([
                        'code' => 200,
                        'message' => 'success',
                        'data' => $data
                    ]);
                }
                break;
            case "20002":
            case "200002":
            case "200001":
            case "20001":
            case "200003":
            case "200004":
            case "400":
                if (strlen($content) > 10) {
                    $data = $this->jdParseApi($content, $rate, $user);
                    switch ($data) {
                        case 0:
                            return json_encode(['code' => 0, 'message' => '查询失败，可能无饭粒']);
                        case -1:
                            return json_encode(['code' => -1, 'message' => '系统错误，请稍后再试或联系客服1']);
                        case -2:
                            return json_encode(['code' => -2, 'message' => '转链非口令或无返利']);
                        default:
                            return json_encode([
                                'code' => 200,
                                'message' => 'success',
                                'data' => $data,

                            ]);
                    }
                } else {
                    return json_encode(['code' => 0, 'message' => '查询失败，可能无饭粒']);
                }
            case "25003":
                return json_encode(['code' => 0, 'message' => '查询失败，可能无饭粒']);
            case "-1":
            default:
                return json_encode(['code' => -1, 'message' => '系统错误，请稍后再试或联系客服2']);
        }

    }

    /**
     * 格式化商品返利信息并返回
     * @param $user
     * @param $goodsid
     * @param $rate
     * @param $dataArr
     * @return string
     */
    public function formatDataByTbApi($user, $rate, $dataArr, $goodsid, $title, $price, $image, $relationFlag = true)
    {
        if ($dataArr['code'] == '0') {
            $couponInfo = "商品无优惠券";
            $amount = "0";
            $startFee = "0";
            if ($dataArr['data']['couponInfo'] != null) {
                $couponInfo = $dataArr['data']['couponInfo']; //优惠券信息
                $start = (strpos($couponInfo, "元"));
                $ci = mb_substr($couponInfo, $start);
                //return $ci;
                $end = (strpos($ci, "元"));
                $amount = mb_substr($ci, 0, $end);
                $end = (strpos($couponInfo, "元减"));
                $startFee = mb_substr($couponInfo, 1, $end - 3);
                //return $startFee;
            }
            //$tpwd = $dataArr['data']['tpwd']; //淘口令
            //$kuaiZhanUrl = $dataArr['data']['kuaiZhanUrl']; //快站链接
            $estimate = $price >= $startFee ? $price - $amount : $price; //预估付款金额
            $tpwd = $dataArr['data']['longTpwd']; //长淘口令
            $maxCommissionRate = $dataArr['data']['maxCommissionRate'] == "" || null ? $dataArr['data']['minCommissionRate'] : $dataArr['data']['maxCommissionRate']; //佣金比例
            $kuaiZhanUrl = $dataArr['data']['kuaiZhanUrl']; //商品的快站链接
            $itemUrl = $dataArr['data']['shortUrl']; //商品的淘宝链接
            $message = "";
            $openid = Redis::get($title);

            if ($relationFlag) {
                $message = "注意：你正在进行渠道返利，使用此次链接下单不限淘宝号均可自动跟单，可用于分享朋友下单。";
            } else if ($openid != null && $openid != "" && $openid != $user->id) {
                Redis::setex($title, 1800, "repeat");
                $message = "当前商品有多个用户在下单，为防止跟单错误，自动跟单已关闭，请下单两分钟后到个人中心绑定订单";
            } else {
                Redis::setex($title, 900, $user->id);
                $message = "您在15分钟内下单将自动绑定，如超时或未自动绑定，请您到个人中心绑定订单";
            }

            Redis::setex($title . "tljid", 900, $user->id);
            Redis::setex($title . "tljprice", 900, round(($estimate * $rate * ($maxCommissionRate / 100)), 2));


            $data = [
                'title' => $title,
                'price' => $price,
                'couponInfo' => $couponInfo,
                'tpwd' => $tpwd,
                'maxCommissionRate' => round($maxCommissionRate * $rate, 2),
                'estimate' => round(($estimate * $rate * ($maxCommissionRate / 100)), 2),
                'rate' => $rate,
                'image' => $image,
                'message' => $message,
                'itemUrl' => $itemUrl,
                'goodsId' => $goodsid
            ];
            //$content = $couponInfo.'，返现比例'.round($maxCommissionRate* $rate,2).'%，预计付款'.$estimate.'，预计返现'.round(($estimate * $rate * ($maxCommissionRate / 100)), 2).'，点击查看下单';
            return $data;
        } else if ($dataArr['code'] == '10006') {
            return 0;
        } else {
            return -1;
        }
    }

    /**
     * 查询用户是否生成过相同淘礼金
     * @param $openid
     * @param $goodsId
     * @return string
     */
    public function queryTlj($openid, $goodsId): string
    {
        Log::info($goodsId);
        Log::info($openid);
        $goods = explode('-', $goodsId);
        $tlj = app(Tlj::class)->getInfoByOpenidAndGoodsId($openid, $goods[1]);
        //return json_encode(['code'=>1,'message'=>'今日已生成过该商品的淘口令']);
        if ($tlj == null) {
            return json_encode(['code' => 0, 'message' => '今日未生成过该商品的淘口令']);
        } else {
            return json_encode(['code' => 1, 'message' => '今日已生成过该商品的淘口令']);
        }
    }

    /**
     * 生成淘礼金链接
     * @param $openid
     * @param $token
     * @param $goodsId
     * @param $title
     * @param $estimate
     * @return string
     */
    public function createTlj($openid, $token, $goodsId, $title, $estimate): string
    {
        date_default_timezone_set("Asia/Shanghai");
        if ($estimate > 10) {
            return json_encode(['code' => -1, 'message' => '当前单日直返金额不超过10元，该笔订单请使用普通返利，谢谢']);
        }
        if ($estimate < 1) {
            return json_encode(['code' => -1, 'message' => '金额低于1元暂时无法生成']);
        }
        $user = app(Users::class)->getUserById($openid);
        if ($user == null || $user->token != $token) {
            return json_encode(['code' => -1, 'message' => '系统错误，请重试或重新登录后再操作直返功能']);
        }
        $tljid = Redis::get($title . "tljid");
        $tljprice = Redis::get($title . "tljprice");
        if ($tljid == null || $tljprice == null || $tljid != $openid || $tljprice != $estimate) {
            return json_encode(['code' => -1, 'message' => '请求非法，请重新转链后再尝试生成直返']);
        }
        if ($user->available_balance < 0) {
            return json_encode(['code' => -1, 'message' => '您的可提现余额为负数，可能是直返金额出错导致多返，请联系客服充值或使用普通返利']);
        }
        $startTime = (date("Y-m-d", time())) . " 00:00:00";
        $endTime = (date("Y-m-d", time())) . " 23:59:59";
        $amount = Redis::get($openid . "amount");//redis查询当天直减金额，如超过10元则返回使用普通返利
        if (($amount == null && $amount == "") || $amount + $estimate <= 10) {
            $host = "https://openapi.dataoke.com/api/dels/taobao/kit/create-tlj";
            $data = [
                'appKey' => config('config.dtkAppKey'),
                'version' => '1.0.0',
                'alimamaAppKey' => config('config.aliAppKey'),
                'alimamaAppSecret' => config('config.aliAppSecret'),
                'name' => "猫乐饭转链",
                'itemId' => $goodsId,
                'perFace' => $estimate,
                'totalNum' => 1,
                'winNumLimit' => 1,
                'sendStartTime' => $startTime,
                'sendEndTime' => $endTime,
                'useEndTimeMode' => "2",
                "useStartTime" => $startTime,
                "userEndTime" => $endTime
            ];
            $data['sign'] = $this->makeSign($data);
            $url = $host . '?' . http_build_query($data);
            $output = $this->curlGet($url, 'get');
            $data = json_decode($output, true);
            //判断是否成功，并进行格式化处理
            Log::info(json_encode($data));
            if ($data["code"] == 0) {
                $tljTitle = Redis::get($title . "tlj");
                if ($tljTitle == null || $tljTitle == "") {//成功，则将商品加入排除库一次当天有效，获取订单时，如商品存在于排除库，则记录为直返
                    Redis::setex($title . "tlj", strtotime('23:59:59') - time(), 1);
                } else {
                    Redis::setex($title . "tlj", strtotime('23:59:59') - time(), $tljTitle + 1);
                }
                if ($amount == null && $amount == "") {
                    Redis::setex($openid . "amount", strtotime('23:59:59') - time(), $estimate);
                } else {
                    Redis::setex($openid . "amount", strtotime('23:59:59') - time(), $amount + $estimate);
                }
                app(Tlj::class)->saveTlj($openid, $goodsId, $title, $estimate, $data["data"]["rightsId"], $data["data"]["sendUrl"], $data["data"]["longTpwd"]);
                return json_encode(['code' => 1, 'message' => '生成成功', 'longTpwd' => $data["data"]["longTpwd"], 'sendUrl' => $data["data"]["sendUrl"]]);
            } else {
                return json_encode(['code' => -1, 'message' => '直返生成失败，可能是商品不支持或达到官方上限，请使用普通返利。']);
            }
        } else {
            return json_encode(['code' => -1, 'message' => '当前单日直返金额不超过10元，您今日已直返' . $amount . '元，请使用普通返利，或次日再试']);
        }
    }


    /**
     * 京东商品转链
     * @param $url
     * @param $rate
     * @return string
     */
    public function jdParseApi($url, $rate, $user)
    {
        $skuId = $this->getJdSku($url);
        if (!$skuId) {
            return -2;
        }
        $dataArr = $this->getJdDetails($skuId, $rate, $user);
        if (!$dataArr) {
            return -1;
        }

        $title = $dataArr["skuName"];
        $price = $dataArr["originPrice"];
        $actualPrice = $dataArr["actualPrice"];
        $couponInfo1 = $dataArr["couponAmount"];
        $couponInfo2 = $dataArr["couponConditions"];
        $commissionShare = $dataArr["commissionShare"];
        $url = $this->getJdUrl($url);
        $couponInfo = "商品无优惠券";
        if ($couponInfo1 != "-1" && $couponInfo1 != "0") {
            $couponInfo = $couponInfo2 . "减" . $couponInfo1 . "元优惠券";
            $price = $price > $couponInfo1 ? $price - $couponInfo1 : $price;
        }
        $commissionShare = round($commissionShare * $rate, 2);
        $estimate = round(($price * ($commissionShare / 100)), 2);
        $image = $dataArr["picMain"];
        if (!$url) {
            $url = $this->getJdUrl($skuId);
        }
        if (!$url) {
            return -1;
        }
        $message = "";
        $openid = Redis::get($title);
        if ($openid != null && $openid != "" && $openid != $user->id) {
            Redis::setex($title, 1800, "repeat");
            $message = "当前商品有多个用户在下单，为防止跟单错误，自动跟单已关闭，请下单后2分钟复制您的订单号发送到给我进行绑定";
        } else {
            Redis::setex($title, 900, $user->id);
            $message = "您在15分钟内下单将自动绑定，如超时或未自动绑定，请您到个人中心绑定订单";
        }
        $data = [
            'title' => $title,
            'price' => $price,
            'couponInfo' => $couponInfo,
            'url' => $url,
            'maxCommissionRate' => $commissionShare,
            'estimate' => $estimate,
            'rate' => $rate,
            'image' => $image,
            'message' => $message,
        ];
        return $data;

    }

    /**
     * 京东商品转链
     * @param $url
     * @param $rate
     * @return string
     */
    public function jdParse($url, $rate)
    {
        $skuId = $this->getJdSku($url);
        if (!$skuId) {
            return "查询失败，可能无饭粒";
        }
        $dataArr = $this->getJdDetails($skuId, $rate);
        if (!$dataArr) {
            return "查询失败，可能无饭粒";
        }

        $title = $dataArr["skuName"];
        $price = $dataArr["originPrice"];
        $actualPrice = $dataArr["actualPrice"];
        $couponInfo1 = $dataArr["couponAmount"];
        $couponInfo2 = $dataArr["couponConditions"];
        $commissionShare = $dataArr["commissionShare"];
        $url = $this->getJdUrl($url);
        $couponInfo = "无优惠券";
        if ($couponInfo1 != (-1)) {
            $couponInfo = "满" . (int)$couponInfo2 . "减" . $couponInfo1;
            $price = $price > $couponInfo1 ? $price - $couponInfo1 : $price;
        }
        $commissionShare = round($commissionShare * $rate, 2);
        $estimate = round(($price * ($commissionShare / 100)), 2);
        $image = $dataArr["picMain"];
        if (!$url) {
            $url = $this->getJdUrl($skuId);
        }
        if (!$url) {
            return "查询失败，可能无饭粒";
        }
        $url = '/jdzjy?title=' . $title . '&url=' . $url . '&couponInfo=' . $couponInfo . '&maxCommissionRate=' . $commissionShare . '&rate=' . $rate . '&estimate=' . $estimate . '&image=' . $image;
        $title = app(Tools::class)->replace_specialChar($title);
        $title = mb_substr($title, 0, 10);
        $data = [
            'title' => $title,
            'image' => $image,
            'couponInfo' => $couponInfo,
            'maxCommissionRate' => $commissionShare,
            'price' => $price,
            'estimate' => $estimate,
            'url' => $url,
        ];
        return $data;

    }

    /**
     * 通过京东商品url或名称获取skuId
     * @param $url
     * @return false|mixed
     */
    public function getJdSku($url)
    {
        $host = "https://openapi.dataoke.com/api/dels/jd/kit/parseUrl";
        $data = [
            'appKey' => config('config.dtkAppKey'),
            'version' => '1.0.0',
            'url' => $url
        ];
        $data['sign'] = $this->makeSign($data);
        $url = $host . '?' . http_build_query($data);
        $output = $this->curlGet($url, 'get');
        $dataArr = json_decode($output, true);//将返回数据转为数组
        if (isset($dataArr["data"])) {
            return $dataArr["data"]["skuId"];
        } else {
            return false;
        }

    }

    /**
     * 通过skuId获取京东商品信息
     * @param $skuId
     * @param $rate
     * @return false|string
     */
    public function getJdDetails($skuId, $rate)
    {
        $host = "https://openapi.dataoke.com/api/dels/jd/goods/get-details";
        $data = [
            'appKey' => config('config.dtkAppKey'),
            'version' => '1.0.0',
            'skuIds' => $skuId
        ];
        $data['sign'] = $this->makeSign($data);
        $url = $host . '?' . http_build_query($data);
        $output = $this->curlGet($url, 'get');
        $dataArr = json_decode($output, true);
        if (isset($dataArr["data"][0])) {
            return $dataArr["data"][0];
        } else {
            return false;
        }
    }

    /**
     * 通过京东url或skuId获取转链后的链接
     * @param $url
     * @return false|mixed
     */
    public function getJdUrl($url)
    {
        $host = "https://openapi.dataoke.com/api/dels/jd/kit/promotion-union-convert";
        $data = [
            'appKey' => config('config.dtkAppKey'),
            'version' => '1.0.0',
            'unionId' => config('config.unionId'),
            'materialId' => $url
        ];
        $data['sign'] = $this->makeSign($data);
        $url = $host . '?' . http_build_query($data);
        $output = $this->curlGet($url, 'get');
        $dataArr = json_decode($output, true);//将返回数据转为数组
        if (($dataArr["code"]) == 0) {
            return $dataArr["data"]["shortUrl"];
        } else {
            return false;
        }
    }

    /**
     * 调用大淘客淘宝转链接口转换链接-淘宝大淘客接口
     * @param $content
     * @return mixed
     */
    public function dtkParse($content)
    {
        $host = "https://openapi.dataoke.com/api/tb-service/parse-content";
        $data = [
            'appKey' => config('config.dtkAppKey'),
            'version' => '1.0.0',
            'content' => $content
        ];
        $data['sign'] = $this->makeSign($data);
        $url = $host . '?' . http_build_query($data);
        //var_dump($url);
        //处理大淘客解析请求url
        $output = $this->curlGet($url, 'get');
        //调用统一请求函数
        $data = json_decode($output, true);
        return $data;
    }

    /**
     * 格式化商品返利信息并返回
     * @param $user
     * @param $goodsid
     * @param $rate
     * @param $dataArr
     * @return string
     */
    public function formatDataByTb($rate, $dataArr, $title, $price, $image)
    {
        if ($dataArr['code'] == '0') {
            //$tbArr = $this->aliParse($goodsid);
            //$title = $tbArr['results']['n_tbk_item'][0]['title']; //商品标题
            //$price = $tbArr['results']['n_tbk_item'][0]['zk_final_price']; //商品价格
            $couponInfo = "无优惠券";
            $amount = "0";
            $startFee = "0";
            if ($dataArr['data']['couponInfo'] != null) {
                $couponInfo = $dataArr['data']['couponInfo']; //优惠券信息
                $start = (strpos($couponInfo, "元"));
                $ci = mb_substr($couponInfo, $start);
                //return $ci;
                $end = (strpos($ci, "元"));
                $amount = mb_substr($ci, 0, $end);
                $end = (strpos($couponInfo, "元减"));
                $startFee = mb_substr($couponInfo, 1, $end - 3);
                //return $startFee;

            }
            $couponInfo = "满" . $startFee . "减" . $amount;
            $tpwd = $dataArr['data']['tpwd']; //淘口令
            $kuaiZhanUrl = $dataArr['data']['kuaiZhanUrl']; //快站链接
            $estimate = $price >= $startFee ? $price - $amount : $price; //预估付款金额
            $maxCommissionRate = $dataArr['data']['maxCommissionRate'] == "" || null ? $dataArr['data']['minCommissionRate'] : $dataArr['data']['maxCommissionRate']; //佣金比例
            $kuaiZhanUrl = $dataArr['data']['kuaiZhanUrl']; //商品的快站链接
            $url = '/tklzjy?title=' . $title . '&tpwd=' . $tpwd . '&couponInfo=' . $couponInfo . '&maxCommissionRate=' . round($maxCommissionRate * $rate, 2) . '&rate=' . $rate . '&estimate=' . round(($estimate * $rate * ($maxCommissionRate / 100)), 2) . '&image=' . $image;
            $title = app(Tools::class)->replace_specialChar($title);
            $title = mb_substr($title, 0, 10);
            $data = [
                'title' => $title,
                'image' => $image,
                'couponInfo' => $couponInfo,
                'maxCommissionRate' => round($maxCommissionRate * $rate, 2),
                'price' => $price,
                'estimate' => round(($estimate * $rate * ($maxCommissionRate / 100)), 2),
                'url' => $url,
            ];
            return $data;

        } else if ($dataArr['code'] == '10006') {
            return "查询失败，可能无饭粒";
        } else {
            return "系统错误，请稍后再试";
        }
    }

    /**
     * 未绑定会员id的用户通过商品id获取链接信息-淘宝大淘客接口
     * @param $goodsid 传入预转链的商品id
     */
    public function privilegeLink($goodsid)
    {
        $host = "https://openapi.dataoke.com/api/tb-service/get-privilege-link";
        $data = [
            'appKey' => config('config.dtkAppKey'),
            'version' => '1.3.1',
            'goodsId' => $goodsid,
            'pid' => config('config.pubpid')
        ];
        $data['sign'] = $this->makeSign($data);
        $url = $host . '?' . http_build_query($data);
        //var_dump($url);
        //处理大淘客解析请求url
        $output = $this->curlGet($url, 'get');
        $data = json_decode($output, true);//将返回数据转为数组
        return $data;
    }

    /**
     * 绑定渠道id的用户通过商品id获取链接信息-淘宝大淘客接口
     * @param $goodsid 传入预转链的商品id
     */
    public function privilegeLinkByRelation_id($goodsid, $relation_id)
    {
        $host = "https://openapi.dataoke.com/api/tb-service/get-privilege-link";
        $data = [
            'appKey' => config('config.dtkAppKey'),
            'version' => '1.3.1',
            'goodsId' => $goodsid,
            'pid' => config('config.specialpid'),
            'channelId' => $relation_id
        ];
        $data['sign'] = $this->makeSign($data);
        $url = $host . '?' . http_build_query($data);
        //var_dump($url);
        //处理大淘客解析请求url
        $output = $this->curlGet($url, 'get');
        $data = json_decode($output, true);//将返回数据转为数组
        return $data;
    }


    /**
     * 获取饿了么推广淘口令信息
     * @return string
     */
    public function getElmTkl()
    {
        try {
            $host = "https://openapi.dataoke.com/api/tb-service/activity-link";
            $data = [
                'appKey' => config('config.dtkAppKey'),
                'version' => '1.0.0',
                'promotionSceneId' => "20150318020002597", //饿了么会场固定编号
                'pid' => config('config.pubpid')
            ];
            $data['sign'] = $this->makeSign($data);
            $url = $host . '?' . http_build_query($data);
            //var_dump($url);
            //处理大淘客解析请求url
            $output = $this->curlGet($url, 'get');
            $data = json_decode($output, true);//将返回数据转为数组
            $url = $data['data']['click_url'];
            $Tpwd = $data['data']['longTpwd'];
            return json_encode([
                'code' => 200,
                'message' => 'success',
                'data' => $url,
                'Tpwd' => $Tpwd
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'code' => -1,
                'message' => 'error',
            ]);
        }

    }

    /**
     * 查询用户1个月内订单并重新获取状态
     * @param $openid
     * @return int
     */
    public function updateOrder($openid)
    {
        $orders = app(Orders::class)->getAllWithinOneMonthByOpenid($openid);//查询最近一个月订单
        if ($orders == null || !isset($orders[0])) {
            //如果订单信息为空，则表示最近一个月无订单
            return 0;
        }
        $user = app(Users::class)->getUserById($orders[0]->openid);//获取订单中的用户信息
        date_default_timezone_set("Asia/Shanghai");//设置当前时区为Shanghai
        foreach ($orders as $order) {
            //循环处理每个订单
            if ($order->tk_status == 13 || $order->tlf_status == 2 || $order->tlf_status == -1) {
                //如果订单状态为13或tlf状态为-1，则已退款
                //如果tlf状态为2则已结算
                //此类情况跳过订单
                continue;
            }
            $flag = true;
            $pageNo = 1;//设置默认页面
            while ($flag && strlen($order->trade_parent_id) > 13) {
                //订单号大于13位则作为淘宝订单处理
                $host = "https://openapi.dataoke.com/api/tb-service/get-order-details";
                $orderScene = 1;
                if ($order->relation_id == null || trim($order->relation_id) == "") {
                    $orderScene = 1;
                } else {
                    $orderScene = 2;
                }
                $endTime = date("Y-m-d H:i:s", strtotime($order->tk_paid_time) + 60);
                $startTime = date("Y-m-d H:i:s", strtotime($order->tk_paid_time) - 60);
                $data = [
                    'appKey' => config('config.dtkAppKey'),
                    'version' => '1.0.0',
                    'queryType' => 2,//按照付款时间查询
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                    'pageNo' => $pageNo,
                    'orderScene' => $orderScene,
                    'pageSize' => 100
                ];
                $data['sign'] = $this->makeSign($data);
                $url = $host . '?' . http_build_query($data);
                $output = $this->curlGet($url, 'get');
                $data = json_decode($output, true);//将返回数据转为数组
                if (isset($data['data']['has_next']) && json_encode($data['data']['has_next']) == 'false') {
                    //如果has_next=false，则表示无下一页，设置flag为false则此次执行后不再进入循环
                    $flag = false;
                } else {
                    $pageNo++;
                } //如不包含下一页，则本次执行结束后终止循环
                if (isset($data['data']['results']['publisher_order_dto'])) {
                    //判断是否存在数据以免报错
                    $publisher_order_dto = $data['data']['results']['publisher_order_dto'];
                    if (isset($publisher_order_dto[0])) {
                        //二次判断是否存在数据
                        for ($i = 0; $i < sizeof($publisher_order_dto); $i++) {
                            //存在数据则循环读取数据
                            $trade_parent_id = $publisher_order_dto[$i]['trade_parent_id']; //订单号
                            $item_title = $publisher_order_dto[$i]['item_title']; //商品名称
                            if ($trade_parent_id != $order->trade_parent_id || $item_title != $order->item_title) {
                                continue;
                            }
                            $tk_status = $publisher_order_dto[$i]['tk_status'];//订单状态
                            $refund_tag = $publisher_order_dto[$i]['refund_tag'];
                            $tk_earning_time = null;

                            if ($tk_status == 13 || $refund_tag == 1) {
                                //已退款，处理扣除金额
                                try {
                                    DB::beginTransaction();
                                    app(Orders::class)->changeStatusAndEarningTimeById($order->id, 13, $tk_earning_time, $refund_tag);
                                    app(BalanceRecord::class)->setRecord($order->openid, "订单" . $trade_parent_id . "退款扣除返利" . $order->rebate_pre_fee, ($order->rebate_pre_fee) * (-1));
                                    app(Users::class)->updateUnsettled_balance($order->openid, $user->unsettled_balance - $order->rebate_pre_fee);
                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                }
                            } else if ($tk_status != $order->tk_status) {
                                //判断订单数据与系统内是否一致，不一致则进行更新处理
                                try {
                                    if ($tk_status == 3) {
                                        //状态为3则为平台已结算
                                        $tk_earning_time = $publisher_order_dto[$i]['tk_earning_time'];//结算时间
                                        if ($user->invite_id != null && $user->invite_id != "" && config('config.invite') == 1) {
                                            if ($user->invitation_reward == 1) {
                                                //判断用户是否为被邀请，并且未结算邀请奖励
                                                $nickname = $user->nickname == null ? "未设置昵称" : $user->nickname;//获取用户的昵称
                                                $invite = app(Invite::class)->getInviteById($user->invite_id, $user->id);//获取邀请数据
                                                $inviteUser = app(Users::class)->getUserById($user->invite_id);
                                                app(Invite::class)->updateInvite($user->invite_id, $user->id, $invite->commission + config('config.invite_rewards'), $tk_earning_time);//将邀请奖励添加进信息表中
                                                app(BalanceRecord::class)->setRecord($user->invite_id, "邀请好友" . $nickname . "首次下单获得奖励" . config('config.invite_rewards') . "元", config('config.invite_rewards'));//储存余额变动数据
                                                app(Users::class)->updateAvailable_balance($user->invite_id, $inviteUser->available_balance + config('config.invite_rewards'));
                                                app(Users::class)->updateInvitationReward($user->id);//设置用户奖励邀请状态
                                            }
                                        }
                                    }
                                    //处理变更状态
                                    app(Orders::class)->changeStatusAndEarningTimeById($order->id, $tk_status, $tk_earning_time);
                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                }

                            }
                            $flag = false;
                            break;
                        }
                    } else {
                        if (!isset($publisher_order_dto['trade_parent_id'])) {
                            break;
                        }
                        $trade_parent_id = $publisher_order_dto['trade_parent_id']; //订单号
                        $item_title = $publisher_order_dto['item_title']; //商品名称
                        if ($trade_parent_id != $order->trade_parent_id || $item_title != $order->item_title) {
                            continue;
                        }
                        $tk_status = $publisher_order_dto['tk_status'];//订单状态
                        $refund_tag = $publisher_order_dto['refund_tag'];
                        $tk_earning_time = null;
                        if ($tk_status == 13 || $refund_tag == 1) {
                            //已退款，处理扣除金额
                            try {
                                $user = app(Users::class)->getUserById($order->openid);
                                DB::beginTransaction();
                                app(Orders::class)->changeStatusAndEarningTimeById($order->id, 13, $tk_earning_time);
                                app(BalanceRecord::class)->setRecord($order->openid, "订单" . $trade_parent_id . "退款扣除返利" . $order->rebate_pre_fee, ($order->rebate_pre_fee) * (-1));
                                app(Users::class)->updateUnsettled_balance($order->openid, $user->unsettled_balance - $order->rebate_pre_fee);
                                DB::commit();
                            } catch (\Exception $e) {
                                DB::rollBack();
                            }
                        } else if ($tk_status != $order->tk_status) {
                            try {
                                if ($tk_status == 3) {
                                    $tk_earning_time = $publisher_order_dto['tk_earning_time'];
                                    if ($user->invite_id != null && $user->invite_id != "" && config('config.invite') == 1) {
                                        if ($user->invitation_reward == 1) {
                                            $nickname = $user->nickname == null ? "未设置昵称" : $user->nickname;
                                            $invite = app(Invite::class)->getInviteById($user->invite_id, $user->id);
                                            $inviteUser = app(Users::class)->getUserById($user->invite_id);
                                            app(Invite::class)->updateInvite($user->invite_id, $user->id, $invite->commission + config('config.invite_rewards'), $tk_earning_time);
                                            app(BalanceRecord::class)->setRecord($user->invite_id, "邀请好友" . $nickname . "首次下单获得奖励" . config('config.invite_rewards') . "元", config('config.invite_rewards'));
                                            app(Users::class)->updateAvailable_balance($user->invite_id, $inviteUser->available_balance + config('config.invite_rewards'));
                                            app(Users::class)->updateInvitationReward($user->id);//设置用户奖励邀请状态
                                        }
                                    }
                                }
                                app(Orders::class)->changeStatusAndEarningTimeById($order->id, $tk_status, $tk_earning_time);
                                DB::commit();
                            } catch (\Exception $e) {
                                DB::rollBack();
                            }

                        }
                        $flag = false;
                        break;
                    }
                }
            }

            $flag = true;
            $pageNo = 1;
            while ($flag && strlen($order->trade_parent_id) < 17) {
                $host = "https://openapi.dataoke.com/api/dels/jd/order/get-official-order-list";
                $data = [
                    'appKey' => config('config.dtkAppKey'),
                    'version' => '1.0.0',
                    'key' => config('config.jdApiKey'),
                    'startTime' => date("Y-m-d H:i:s", strtotime($order->tk_paid_time) - 60),
                    'endTime' => date("Y-m-d H:i:s", strtotime($order->tk_paid_time) + 60),
                    'type' => 1,
                    'pageNo' => $pageNo,
                ];
                $data['sign'] = $this->makeSign($data);
                $url = $host . '?' . http_build_query($data);
                $output = $this->curlGet($url, 'get');
                $dataArr = json_decode($output, true);//将返回数据转为数组
                if ($dataArr["data"] != null) {
                    foreach ($dataArr["data"] as $data) {
                        $trade_parent_id = $data["orderId"];
                        $item_title = $data['skuName']; //商品名称
                        if ($trade_parent_id != $order->trade_parent_id || $item_title != $order->item_title) {
                            continue;
                        }
                        $tk_status = $data["validCode"];
                        $finishTime = null;

                        switch ($tk_status) {
                            case 15:
                                break;
                            case 16:
                                $tk_status = 12;
                                break;
                            case 17:
                                $tk_status = 3;
                                break;
                            default:
                                try {
                                    $user = app(Users::class)->getUserById($order->openid);
                                    DB::beginTransaction();
                                    app(Orders::class)->changeStatusAndEarningTimeById($order->id, 13, $finishTime);
                                    app(BalanceRecord::class)->setRecord($order->openid, "订单" . $trade_parent_id . "退款扣除返利" . $order->rebate_pre_fee, ($order->rebate_pre_fee) * (-1));
                                    app(Users::class)->updateUnsettled_balance($order->openid, $user->unsettled_balance - $order->rebate_pre_fee);
                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                }
                                $tk_status = 13;
                                break;
                        }
                        if ($tk_status != 13 && $tk_status != $order->tk_status) {
                            try {
                                if ($tk_status == 3) {
                                    $finishTime = $data["finishTime"];
                                    if ($user->invite_id != null && $user->invite_id != "") {
                                        if ($user->invitation_reward == 1) {
                                            $nickname = $user->nickname == null ? "未设置昵称" : $user->nickname;
                                            $invite = app(Invite::class)->getInviteById($user->invite_id, $user->id);
                                            $inviteUser = app(Users::class)->getUserById($user->invite_id);
                                            app(Invite::class)->updateInvite($user->invite_id, $user->id, $invite->commission + config('config.invite_rewards'), $finishTime);
                                            app(BalanceRecord::class)->setRecord($user->invite_id, "邀请好友" . $nickname . "首次下单获得奖励" . config('config.invite_rewards') . "元", config('config.invite_rewards'));
                                            app(Users::class)->updateAvailable_balance($user->invite_id, $inviteUser->available_balance + config('config.invite_rewards'));
                                            app(Users::class)->updateInvitationReward($user->id);//设置用户奖励邀请状态
                                        }
                                    }
                                }
                                app(Orders::class)->changeStatusAndEarningTimeById($order->id, $tk_status, $finishTime);
                                DB::commit();
                            } catch (\Exception $e) {
                                DB::rollBack();
                            }
                        }
                    }
                    $pageNo++;
                } else {
                    $flag = false;
                }
            }
        }
    }

    /**
     * 获取订单信息
     * @return string
     */
    public function getOrderList()
    {
        $str = $this->getOrderListByTaobao();
        $str = $str . "\n" . $this->getOrderListByJd();
        return $str;
    }

    /**
     * 获取并存储订单信息-淘宝大淘客接口
     */
    public function getOrderListByTaobao()
    {
        $count = 0;
        $timeQuantum = 600;  //默认1分30秒用于冗余以免漏单
        date_default_timezone_set("Asia/Shanghai");//设置当前时区为Shanghai
        $host = "https://openapi.dataoke.com/api/tb-service/get-order-details";
        //开始处理包含渠道管理ID的订单
        $flag = true;
        $pageNo = 1;
        while ($flag) {
            $endTime = date("Y-m-d H:i:s", time());
            $startTime = date("Y-m-d H:i:s", time() - $timeQuantum);
            //$startTime="2022-11-01 22:00:00";
            //$endTime="2022-11-01 23:00:00";
            $data = [
                'appKey' => config('config.dtkAppKey'),
                'version' => '1.0.0',
                'queryType' => 2,//按照付款时间查询
                'startTime' => $startTime,
                'endTime' => $endTime,
                'pageNo' => $pageNo,
                'orderScene' => 2,
                'pageSize' => 100
            ];
            $data['sign'] = $this->makeSign($data);
            $url = $host . '?' . http_build_query($data);
            $output = $this->curlGet($url, 'get');
            $data = json_decode($output, true);//将返回数据转为数组
            if (isset($data['data']['has_next']) && json_encode($data['data']['has_next']) == 'false') {
                $flag = false;
            } else {
                $pageNo++;
            } //如不包含下一页，则本次执行结束后终止循环
            if (isset($data['data']['results']['publisher_order_dto'])) {
                $publisher_order_dto = $data['data']['results']['publisher_order_dto'];
                if (isset($publisher_order_dto[0])) {
                    for ($i = 0; $i < sizeof($publisher_order_dto); $i++) {
                        $count++;
                        //$testStr=$testStr."检测到第".($i+1)."个订单\n";
                        $trade_parent_id = $publisher_order_dto[$i]['trade_parent_id']; //订单号
                        $item_title = $publisher_order_dto[$i]['item_title'];//商品名称
                        $tk_paid_time = $publisher_order_dto[$i]['tk_paid_time'];//付款时间
                        $tk_status = $publisher_order_dto[$i]['tk_status'];//订单状态
                        $alipay_total_price = $publisher_order_dto[$i]['alipay_total_price'];//付款金额
                        $pub_share_pre_fee = $publisher_order_dto[$i]['pub_share_pre_fee'];//付款预估收入
                        $tk_commission_pre_fee_for_media_platform = $publisher_order_dto[$i]['tk_commission_pre_fee_for_media_platform'];//预估内容专项服务费
                        $rebate_pre_fee = 0; //预估返利金额
                        $relation_id = $publisher_order_dto[$i]['relation_id'];
                        $item_img = $publisher_order_dto[$i]['item_img'];//商品图片
                        app(Orders::class)->saveOrder($trade_parent_id, $item_title, $tk_paid_time, $tk_status, $alipay_total_price, $pub_share_pre_fee, $tk_commission_pre_fee_for_media_platform, $rebate_pre_fee, $relation_id, $item_img);

                        //$testStr=$testStr."订单ID".$trade_parent_id."\n付款时间".$tk_paid_time."\n商品标题".$item_title."\n付款金额".$alipay_total_price."\n预估佣金".$pub_share_pre_fee."\n会员ID".$special_id."\n\n";
                    }
                } else {
                    if (!isset($publisher_order_dto['trade_parent_id'])) {
                        break;
                    }
                    $count++;
                    //$testStr=$testStr."检测到第".($i+1)."个订单\n";
                    $trade_parent_id = $publisher_order_dto['trade_parent_id']; //订单号
                    $item_title = $publisher_order_dto['item_title'];//商品名称
                    $tk_paid_time = $publisher_order_dto['tk_paid_time'];//付款时间
                    $tk_status = $publisher_order_dto['tk_status'];//订单状态
                    $alipay_total_price = $publisher_order_dto['alipay_total_price'];//付款金额
                    $pub_share_pre_fee = $publisher_order_dto['pub_share_pre_fee'];//付款预估收入
                    $tk_commission_pre_fee_for_media_platform = $publisher_order_dto['tk_commission_pre_fee_for_media_platform'];//预估内容专项服务费
                    $rebate_pre_fee = 0; //预估返利金额
                    $relation_id = $publisher_order_dto['relation_id'];
                    $item_img = $publisher_order_dto['item_img'];//商品图片
                    app(Orders::class)->saveOrder($trade_parent_id, $item_title, $tk_paid_time, $tk_status, $alipay_total_price, $pub_share_pre_fee, $tk_commission_pre_fee_for_media_platform, $rebate_pre_fee, $relation_id, $item_img);
                }
            }

        }

        $flag = true;
        //开始处理未包含会员运营ID的普通订单
        $pageNo = 1;
        while ($flag) {
            $endTime = date("Y-m-d H:i:s", time());
            $startTime = date("Y-m-d H:i:s", time() - $timeQuantum);
            //$startTime="2022-11-03 22:00:00";
            //$endTime="2022-11-04 00:00:00";
            $data = [
                'appKey' => config('config.dtkAppKey'),
                'version' => '1.0.0',
                'queryType' => 2,//按照付款时间查询
                'startTime' => $startTime,
                'endTime' => $endTime,
                'pageNo' => $pageNo,
                'orderScene' => 1,
                'pageSize' => 100
            ];
            $data['sign'] = $this->makeSign($data);
            $url = $host . '?' . http_build_query($data);
            $output = $this->curlGet($url, 'get');
            $data = json_decode($output, true);//将返回数据转为数组
            //return json_encode($data);
            if (isset($data['data']['has_next']) && json_encode($data['data']['has_next']) == 'false') {
                $flag = false;
            } else {
                $pageNo++;
            } //如不包含下一页，则本次执行结束后终止循环
            if (isset($data['data']['results']['publisher_order_dto'])) {
                $publisher_order_dto = $data['data']['results']['publisher_order_dto'];
                if (isset($publisher_order_dto[0])) {
                    for ($i = 0; $i < sizeof($publisher_order_dto); $i++) {
                        $count++;
                        //$testStr=$testStr."检测到第".($i+1)."个订单\n";
                        $trade_parent_id = $publisher_order_dto[$i]['trade_parent_id']; //订单号
                        $item_title = $publisher_order_dto[$i]['item_title'];//商品名称
                        $tk_paid_time = $publisher_order_dto[$i]['tk_paid_time'];//付款时间
                        $tk_status = $publisher_order_dto[$i]['tk_status'];//订单状态
                        $alipay_total_price = $publisher_order_dto[$i]['alipay_total_price'];//付款金额
                        $pub_share_pre_fee = $publisher_order_dto[$i]['pub_share_pre_fee'];//付款预估收入
                        $tk_commission_pre_fee_for_media_platform = $publisher_order_dto[$i]['tk_commission_pre_fee_for_media_platform'];//预估内容专项服务费
                        $rebate_pre_fee = 0; //预估返利金额
                        $item_img = $publisher_order_dto[$i]['item_img'];//商品图片
                        app(Orders::class)->saveOrder($trade_parent_id, $item_title, $tk_paid_time, $tk_status, $alipay_total_price, $pub_share_pre_fee, $tk_commission_pre_fee_for_media_platform, $rebate_pre_fee, -1, $item_img);

                        //$testStr=$testStr."订单ID".$trade_parent_id."\n付款时间".$tk_paid_time."\n商品标题".$item_title."\n付款金额".$alipay_total_price."\预估佣金".$pub_share_pre_fee."\n\n";*/
                    }
                    //return $testStr;
                } else {
                    if (!isset($publisher_order_dto['trade_parent_id'])) {
                        break;
                    }
                    $count++;
                    //$testStr=$testStr."检测到第".($i+1)."个订单\n";
                    $trade_parent_id = $publisher_order_dto['trade_parent_id']; //订单号
                    $item_title = $publisher_order_dto['item_title'];//商品名称
                    $tk_paid_time = $publisher_order_dto['tk_paid_time'];//付款时间
                    $tk_status = $publisher_order_dto['tk_status'];//订单状态
                    $alipay_total_price = $publisher_order_dto['alipay_total_price'];//付款金额
                    $pub_share_pre_fee = $publisher_order_dto['pub_share_pre_fee'];//付款预估收入
                    $tk_commission_pre_fee_for_media_platform = $publisher_order_dto['tk_commission_pre_fee_for_media_platform'];//预估内容专项服务费
                    $rebate_pre_fee = 0; //预估返利金额
                    $item_img = $publisher_order_dto['item_img'];//商品图片
                    app(Orders::class)->saveOrder($trade_parent_id, $item_title, $tk_paid_time, $tk_status, $alipay_total_price, $pub_share_pre_fee, $tk_commission_pre_fee_for_media_platform, $rebate_pre_fee, -1, $item_img);
                }
            }


        }
        return "成功处理订单数量：" . $count;

    }

    /**
     * 获取并存储订单信息-京东联盟
     */
    public function getOrderListByJd()
    {
        $count = 0;
        $timeQuantum = 600;  //默认1分30秒用于冗余以免漏单
        date_default_timezone_set("Asia/Shanghai");//设置当前时区为Shanghai
        $flag = true;
        $pageNo = 1;
        $host = "https://openapi.dataoke.com/api/dels/jd/order/get-official-order-list";
        while (true) {
            $data = [
                'appKey' => config('config.dtkAppKey'),
                'version' => '1.0.0',
                'key' => config('config.jdApiKey'),
                'startTime' => date("Y-m-d H:i:s", time() - $timeQuantum),
                'endTime' => (date("Y-m-d H:i:s", time())),
                'type' => 3,
                'pageNo' => $pageNo,
            ];
            $data['sign'] = $this->makeSign($data);
            $url = $host . '?' . http_build_query($data);
            $output = $this->curlGet($url, 'get');
            $dataArr = json_decode($output, true);//将返回数据转为数组
            if ($dataArr["data"] != null) {
                foreach ($dataArr["data"] as $data) {
                    $trade_parent_id = $data["orderId"];
                    $item_title = $data["skuName"];
                    $tk_paid_time = $data["orderTime"];
                    $tk_status = $data["validCode"];
                    switch ($tk_status) {
                        case 15:
                            break;
                        case 16:
                            $tk_status = 12;
                            break;
                        case 17:
                            $tk_status = 3;
                            break;
                        default:
                            $tk_status = 13;
                    }
                    $alipay_total_price = $data["estimateCosPrice"];
                    $pub_share_pre_fee = $data["estimateFee"];
                    $tk_commission_pre_fee_for_media_platform = 0;
                    $rebate_pre_fee = 0;
                    $imageUrl = $data["goodsInfo"]["imageUrl"];
                    app(Orders::class)->saveOrder($trade_parent_id, $item_title, $tk_paid_time, $tk_status, $alipay_total_price, $pub_share_pre_fee, $tk_commission_pre_fee_for_media_platform, $rebate_pre_fee, -1, $imageUrl);
                    $count++;
                }
                $pageNo++;
            } else {
                $flag = false;
            }
            return "成功处理订单数量：" . $count;
        }

    }

    /**
     * 获取上个月全部订单（建议每月执行多次）
     */
    public function updateOrderAll(): string
    {
        $tb = $this->updateOrderTb();
        sleep(1);
        $jd = $this->updateOrderJd();
        return "淘宝" . $tb . "----京东" . $jd;
    }

    /**
     * 获取上个月全部订单并更新结算状态-淘宝（建议每月执行多次）
     * @return int
     */
    public function updateOrderTb()
    {
        $count = 0;
        $orders = app(Orders::class)->getAllWithinLastMonth();

        if ($orders == null || sizeof($orders) == 0) {
            return "上月无订单";
        }
        date_default_timezone_set("Asia/Shanghai");//设置当前时区为Shanghai
        $host = "https://openapi.dataoke.com/api/tb-service/get-order-details";
        foreach ($orders as $order) {
            usleep(100000);
            //如果订单状态为已退款或已结算，则跳过
            if ($order->tk_status == 13 || $order->tlf_status == 2 || $order->tlf_status == -1) {
                continue;
            }
            //如果订单号不足17位，则非淘宝订单，跳过
            if (strlen($order->trade_parent_id) < 17) {
                continue;
            }
            //如果order中不含openid，则订单未被绑定，跳过
            if ($order->openid == "" || $order->openid == null) {
                continue;
            }

            /*if($order->openid == "13833436421"){
                return json_encode($order);
            }*/


            $flag = true;
            $pageNo = 1;
            while ($flag) {
                $endTime = date("Y-m-d H:i:s", strtotime($order->tk_paid_time) + 60);
                $startTime = date("Y-m-d H:i:s", strtotime($order->tk_paid_time) - 60);
                //如果订单包含运营id，则按运营订单查询。否则按常规订单查询
                if ($order->relation_id == null || trim($order->relation_id) == "" || $order->relation_id == "-1") {
                    $orderScene = 1;
                } else {
                    $orderScene = 2;
                }
                $data = [
                    'appKey' => config('config.dtkAppKey'),
                    'version' => '1.0.0',
                    'queryType' => 2,//按照付款时间查询
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                    'pageNo' => $pageNo,
                    'orderScene' => $orderScene,
                    'pageSize' => 100
                ];
                $data['sign'] = $this->makeSign($data);
                $url = $host . '?' . http_build_query($data);
                $output = $this->curlGet($url, 'get');
                $data = json_decode($output, true);//将返回数据转为数组
                if (isset($data['data']['results'])) {
                    if (isset($data['data']['has_next']) && json_encode($data['data']['has_next']) == 'false') {
                        $flag = false;
                    } else {
                        $pageNo++;
                    } //如不包含下一页，则本次执行结束后终止循环
                    if (isset($data['data']['results']['publisher_order_dto']) && isset($data['data']['results']['publisher_order_dto'][0])) {
                        $publisher_order_dto = $data['data']['results']['publisher_order_dto'];
                        for ($i = 0; $i < sizeof($publisher_order_dto); $i++) {
                            sleep(0.1);
                            $trade_parent_id = $publisher_order_dto[$i]['trade_parent_id']; //订单号
                            $item_title = $publisher_order_dto[$i]['item_title']; //商品名称
                            if ($trade_parent_id != $order->trade_parent_id || $item_title != $order->item_title) {
                                continue;
                            }
                            if ($order->openid == null || $order->openid == "") {
                                continue;
                            }

                            $tk_status = $publisher_order_dto[$i]['tk_status'];//订单状态
                            $refund_tag = $publisher_order_dto[$i]['refund_tag'];
                            $tk_earning_time = null;

                            $user = app(Users::class)->getUserById($order->openid);

                            if ($user == null) {
                                continue;
                            }


                            //如果status为13为已退款，refund_tag为1则已维权，扣除返利处理。
                            if ($tk_status == 13 || $refund_tag == 1) {
                                //已退款，处理扣除金额
                                try {
                                    DB::beginTransaction();
                                    app(Orders::class)->changeStatusAndEarningTimeById($order->id, 13, $tk_earning_time);
                                    app(BalanceRecord::class)->setRecord($order->openid, "订单" . $trade_parent_id . "退款扣除返利" . $order->rebate_pre_fee, ($order->rebate_pre_fee) * (-1));
                                    app(Users::class)->updateUnsettled_balance($order->openid, $user->unsettled_balance - $order->rebate_pre_fee);
                                    DB::commit();
                                    $count++;
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    return $e;
                                }
                            } else if ($tk_status == 3) {
                                //如果订单状态为淘宝已结算（3），且站点未结算（！=2）
                                try {
                                    DB::beginTransaction();
                                    $tk_earning_time = $publisher_order_dto[$i]['tk_earning_time'];
                                    //获取淘宝结算时间
                                    $month = date("m", time());
                                    if ($month == 1) {
                                        $month = 12;
                                    } else {
                                        $month = (int)$month - 1;
                                    }
                                    $lastMonth = $month == 1 ? 12 : $month - 1;
                                    //获取上月及上上月 月份
                                    if ($month == date('m', strtotime($tk_earning_time)) || $lastMonth == date('m', strtotime($tk_earning_time))) {
                                        //return date('m', strtotime($tk_earning_time)) . "1......" . $trade_parent_id;
                                        //判断如果结算时间为上月或上上月，处理结算。
                                        app(Users::class)->updateUnsettled_balance($order->openid, $user->unsettled_balance - $order->rebate_pre_fee);
                                        app(Users::class)->updateAvailable_balance($order->openid, $user->available_balance + $order->rebate_pre_fee);
                                        app(Orders::class)->changeTlfStatus($trade_parent_id, 2);

                                        //如果用户为被邀请，则更新邀请人的可用余额
                                        if ($user->invite_id != null && $user->invite_id != "" && config('config.invite') == 1) {
                                            $invite_user = app(Users::class)->getUserById($user->invite_id);
                                            app(Users::class)->updateAvailable_balance($invite_user->id, $invite_user->available_balance + ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01));
                                            $nickname = $user->nickname == null ? "未设置昵称" : $user->nickname;
                                            $invite = app(Invite::class)->getInviteById($user->invite_id, $user->id);
                                            app(Invite::class)->updateInvite($user->invite_id, $user->id, $invite->commission + ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01), $tk_earning_time);
                                            app(BalanceRecord::class)->setRecord($user->invite_id, "好友" . $nickname . "下单获得提成" . ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01) . "元", ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01));
                                        }

                                    }
                                    //处理变更状态
                                    if ($order->tk_status != 3) {
                                        //如果订单状态并非已结算或退款，且发生变化，处理变更。
                                        app(Orders::class)->changeStatusAndEarningTimeById($order->id, $tk_status, $tk_earning_time);
                                    }
                                    DB::commit();
                                    $count++;
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    return $e;
                                }
                            }
                            $flag = false;
                            break;
                        }
                    } else {
                        if (!isset($publisher_order_dto['trade_parent_id'])) {
                            break;
                        }
                        $trade_parent_id = $publisher_order_dto['trade_parent_id']; //订单号
                        $item_title = $publisher_order_dto['item_title']; //商品名称
                        if ($trade_parent_id != $order->trade_parent_id || $item_title != $order->item_title) {
                            continue;
                        }
                        $tk_status = $publisher_order_dto['tk_status'];//订单状态
                        $tk_earning_time = null;
                        $refund_tag = $publisher_order_dto['refund_tag'];
                        $user = app(Users::class)->getUserById($order->openid);

                        //如果status为13为已退款，refund_tag为1则已维权，扣除返利处理。
                        if ($tk_status == 13 || $refund_tag == 1) {
                            //已退款，处理扣除金额
                            try {
                                DB::beginTransaction();
                                app(Orders::class)->changeStatusAndEarningTimeById($order->id, 13, $tk_earning_time);
                                app(BalanceRecord::class)->setRecord($order->openid, "订单" . $trade_parent_id . "退款扣除返利" . $order->rebate_pre_fee, ($order->rebate_pre_fee) * (-1));
                                app(Users::class)->updateUnsettled_balance($order->openid, $user->unsettled_balance - $order->rebate_pre_fee);
                                DB::commit();
                                sleep(0.3);
                                $count++;
                            } catch (\Exception $e) {
                                DB::rollBack();
                                return $e;
                            }
                        } else if ($tk_status == 3) {
                            //如果订单状态为淘宝已结算（3），且站点未结算（！=2）
                            try {
                                DB::beginTransaction();
                                $tk_earning_time = $publisher_order_dto['tk_earning_time'];
                                //获取淘宝结算时间
                                $month = date("m", time());
                                if ($month == 1) {
                                    $month = 12;
                                } else {
                                    $month = (int)$month - 1;
                                }
                                $lastMonth = $month == 1 ? 12 : $month - 1;
                                //获取上月及上上月 月份
                                if ($month == date('m', strtotime($tk_earning_time)) || $lastMonth == date('m', strtotime($tk_earning_time))) {
                                    //判断如果结算时间为上月或上上月，处理结算。
                                    app(Users::class)->updateUnsettled_balance($order->openid, $user->unsettled_balance - $order->rebate_pre_fee);
                                    app(Users::class)->updateAvailable_balance($order->openid, $user->available_balance + $order->rebate_pre_fee);
                                    app(Orders::class)->changeTlfStatus($trade_parent_id, 2);
                                    //如果用户为被邀请，则更新邀请人的可用余额
                                    if ($user->invite_id != null && $user->invite_id != "" && config('config.invite') == 1) {
                                        $invite_user = app(Users::class)->getUserById($user->invite_id);
                                        app(Users::class)->updateAvailable_balance($invite_user->id, $invite_user->available_balance + ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01));
                                        $nickname = $user->nickname == null ? "未设置昵称" : $user->nickname;
                                        $invite = app(Invite::class)->getInviteById($user->invite_id, $user->id);
                                        app(Invite::class)->updateInvite($user->invite_id, $user->id, $invite->commission + ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01), $tk_earning_time);
                                        app(BalanceRecord::class)->setRecord($user->invite_id, "好友" . $nickname . "下单获得提成" . ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01) . "元", ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01));
                                    }
                                }
                                //处理变更状态
                                if ($order->tk_status != 3) {
                                    //如果订单状态并非已结算或退款，且发生变化，处理变更。
                                    app(Orders::class)->changeStatusAndEarningTimeById($order->id, $tk_status, $tk_earning_time);
                                }
                                DB::commit();
                                sleep(0.3);
                                $count++;
                            } catch (\Exception $e) {
                                DB::rollBack();
                                return $e;
                            }

                        }
                        $flag = false;
                        break;
                    }
                }
                $flag = false;
            }
        }
        return "处理成功" . $count . "条订单";
    }

    /**
     * 获取上个月全部订单并更新结算状态-京东（建议每月执行多次）
     * @return int
     */
    public function updateOrderJd()
    {
        $count = 0;
        $orders = app(Orders::class)->getAllWithinLastMonth();
        $host = "https://openapi.dataoke.com/api/dels/jd/order/get-official-order-list";
        if ($orders == null || sizeof($orders) == 0) {
            return "上月无订单";
        }
        date_default_timezone_set("Asia/Shanghai");//设置当前时区为Shanghai
        foreach ($orders as $order) {
            //如果订单已退款或已结算，跳过
            if ($order->tk_status == 13 || $order->tlf_status == 2 || $order->tlf_status == -1) {
                continue;
            }
            //如果订单变化大于13位，则非京东订单，跳过
            if (strlen($order->trade_parent_id) > 13) {
                continue;
            }
            //如果order中不含openid，则订单未被绑定，跳过
            if ($order->openid == "" || $order->openid == null) {
                continue;
            }
            $flag = true;
            $pageNo = 1;
            while ($flag) {
                $data = [
                    'appKey' => config('config.dtkAppKey'),
                    'version' => '1.0.0',
                    'key' => config('config.jdApiKey'),
                    'startTime' => date("Y-m-d H:i:s", strtotime($order->tk_paid_time) - 60),
                    'endTime' => date("Y-m-d H:i:s", strtotime($order->tk_paid_time) + 60),
                    'type' => 1,
                    'pageNo' => $pageNo,
                ];
                $data['sign'] = $this->makeSign($data);
                $url = $host . '?' . http_build_query($data);
                $output = $this->curlGet($url, 'get');
                $dataArr = json_decode($output, true);//将返回数据转为数组
                if ($dataArr["data"] != null) {
                    foreach ($dataArr["data"] as $data) {
                        $trade_parent_id = $data["orderId"];
                        $item_title = $data['skuName']; //商品名称
                        //如果订单号与订单名称无法匹配，跳过
                        if ($trade_parent_id != $order->trade_parent_id || $item_title != $order->item_title) {
                            continue;
                        }
                        $tk_status = $data["validCode"];
                        $finishTime = null;
                        switch ($tk_status) {
                            case 15:
                                break;
                            case 16:
                                $tk_status = 12;
                                break;
                            case 17:
                                //京东状态码17则表示已确认收货
                                $tk_status = 3;
                                $user = app(Users::class)->getUserById($order->openid);
                                try {
                                    DB::beginTransaction();
                                    $tk_earning_time = $data['finishTime'];
                                    $month = date("m", time());
                                    if ($month == 1) {
                                        $month = 12;
                                    } else {
                                        $month = (int)$month - 1;
                                    }
                                    $lastMonth = $month == 1 ? 12 : $month - 1;
                                    if ($month == date('m', strtotime($tk_earning_time)) || $lastMonth == date('m', strtotime($tk_earning_time))) {
                                        app(Users::class)->updateUnsettled_balance($order->openid, $user->unsettled_balance - $order->rebate_pre_fee);
                                        app(Users::class)->updateAvailable_balance($order->openid, $user->available_balance + $order->rebate_pre_fee);
                                        app(Orders::class)->changeTlfStatus($trade_parent_id, 2);
                                        //如果用户为被邀请，则更新邀请人的可用余额
                                        if ($user->invite_id != null && $user->invite_id != "" && config('config.invite') == 1) {
                                            $invite_user = app(Users::class)->getUserById($user->invite_id);
                                            app(Users::class)->updateAvailable_balance($invite_user->id, $invite_user->available_balance + ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01));
                                            $nickname = $user->nickname == null ? "未设置昵称" : $user->nickname;
                                            $invite = app(Invite::class)->getInviteById($user->invite_id, $user->id);
                                            app(Invite::class)->updateInvite($user->invite_id, $user->id, $invite->commission + ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01), $tk_earning_time);
                                            app(BalanceRecord::class)->setRecord($user->invite_id, "好友" . $nickname . "下单获得提成" . ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01) . "元", ($order->rebate_pre_fee * config('config.invite_ratio') * 0.01));
                                        }
                                    }
                                    //处理变更状态
                                    if ($order->tk_status != 3) {
                                        app(Orders::class)->changeStatusAndEarningTimeById($order->id, $tk_status, $tk_earning_time);
                                    }
                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                    return $e;
                                }
                                break;
                            default:
                                try {
                                    $user = app(Users::class)->getUserById($order->openid);
                                    DB::beginTransaction();
                                    app(Orders::class)->changeStatusAndEarningTimeById($order->id, 13, $finishTime);
                                    app(BalanceRecord::class)->setRecord($order->openid, "订单" . $trade_parent_id . "退款扣除返利" . $order->rebate_pre_fee, ($order->rebate_pre_fee) * (-1));
                                    app(Users::class)->updateUnsettled_balance($order->openid, $user->unsettled_balance - $order->rebate_pre_fee);
                                    DB::commit();
                                } catch (\Exception $e) {
                                    DB::rollBack();
                                }
                                $tk_status = 13;
                                break;
                        }
                        if ($tk_status != 13 && $tk_status != $order->tk_status) {
                            if ($tk_status == 3) {
                                $finishTime = $data["finishTime"];
                            }
                            app(Orders::class)->changeStatusAndEarningTimeById($order->id, $tk_status, $finishTime);
                        }
                    }
                    $pageNo++;
                } else {
                    $flag = false;
                }
            }
        }
        return "处理成功" . $count . "条订单";
    }


}
