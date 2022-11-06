<?php


namespace App\Http\Controllers;

use App\Models\Orders;
use App\Models\Users;
use Illuminate\Support\Facades\Log;


class OrderController extends Controller
{
    //查询订单数量
    public function getOrderCount($openid)
    {
        $count = app(Orders::class)->getCountByOpenId($openid);
        return json_encode([
            'code' => 200,
            'message' => "success",
            'data' => $count,
        ]);
    }

    //更新订单状态
    public function updateOrderStatus($openid)
    {
        app(\App\Http\Controllers\TaokeController::class)->updateOrder($openid);
    }

    //获取订单
    public function getOrder($openid, $page)
    {
        if ($page == 1) {
            $this->updateOrderStatus($openid);
        }
        $orderPaginate = app(Orders::class)->getAllByPaginateInOpenid($openid);
        $orders = [];

        foreach ($orderPaginate as $o) {
            $img = $o->imageUrl == null || $o->imageUrl == "" ?
                "https://image.baidu.com/search/down?tn=download&word=download&ie=utf8&fr=detail&url=https%3A%2F%2Fgimg2.baidu.com%2Fimage_search%2Fsrc%3Dhttp%253A%252F%252Fpic.51yuansu.com%252Fpic3%252Fcover%252F02%252F19%252F03%252F59af37f562372_610.jpg%26refer%3Dhttp%253A%252F%252Fpic.51yuansu.com%26app%3D2002%26size%3Df9999%2C10000%26q%3Da80%26n%3D0%26g%3D0n%26fmt%3Dauto%3Fsec%3D1656262890%26t%3D6fc0870fe6e89e0d68acadda31b04cce&thumburl=https%3A%2F%2Fimg1.baidu.com%2Fit%2Fu%3D2328686567%2C3113856840%26fm%3D253%26fmt%3Dauto%26app%3D138%26f%3DJPEG%3Fw%3D500%26h%3D500" :
                $o->imageUrl;
            $subtitle = "未知";
            switch ($o->tk_status) {
                case 12:
                    $subtitle = "已付款";
                    break;
                case 13:
                    $subtitle = "已失效（可能被拆单或已退款）";
                    break;
                case 14:
                    $subtitle = "已收货";
                    break;
                case 3:
                    if ($o->tlf_status == 1) {
                        $subtitle = "待结算（次月结算上月订单）";
                    } else {
                        $subtitle = "已结算";
                    }
                    break;
            }
            $order = [
                "extra" => "实付款" . $o->pay_price . "元",
                "title" => "返利" . $o->rebate_pre_fee . "元",
                "thumbnail" => $img,
                "subtitle" => $subtitle,
                "goods" => "订单号" . $o->trade_parent_id . "\n" . $o->item_title,
                "isAd" => false

            ];
            array_push($orders, $order);
        }
        return json_encode([
            'code' => 200,
            'message' => "success",
            'data' => $orders,
        ]);
    }

    public function bindOrder($trade_parent_id, $openid)
    {
        return app(Orders::class)->bindOrder($trade_parent_id, $openid);
    }

}
