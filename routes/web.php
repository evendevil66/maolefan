<?php

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers;
use Illuminate\Support\Facades\Request;
use App\Models\Users;
use Illuminate\Support\Facades\Redis;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/adminReg', function () {
    if (Storage::exists("admin.lock")) {
        return "已创建过超级管理员，如忘记密码，请删除站点目录下/storage/app/admin.lock文件后，重新访问本页修改账号密码";
    }
    return view('/admin/reg');
});

//提交新增管理员post请求（初次）
Route::post('/admin/setAdmin', function () {
    $result = app(\App\Models\Admin::class)->setAdmin(Request::post("username"), Request::post("password"));
    if ($result > 0) {
        Storage::disk('local')->put('admin.lock', "taolefan");
        return 1;
    } else {
        return 0;
    }
});

Route::get('/admin/login', function () {
    return view('admin/login');
});


//淘口令中间页
Route::get('/tklzjy', function () {
    $title = Request::get("title");
    $couponInfo = Request::get("couponInfo");
    $maxCommissionRate = Request::get("maxCommissionRate");
    $estimate = Request::get("estimate");
    $tpwd = Request::get("tpwd");
    $image = Request::get("image");
    return view('/tklzjy',
        [
            'title' => $title,
            'couponInfo' => $couponInfo,
            'maxCommissionRate' => $maxCommissionRate,
            'estimate' => $estimate,
            'tpwd' => $tpwd,
            'image' => $image
        ]);
});

//京东中间页
Route::get('/jdzjy', function () {
    $title = Request::get("title");
    $couponInfo = Request::get("couponInfo");
    $maxCommissionRate = Request::get("maxCommissionRate");
    $estimate = Request::get("estimate");
    $url = Request::get("url");
    $image = Request::get("image");
    return view('/jdzjy',
        [
            'title' => $title,
            'couponInfo' => $couponInfo,
            'maxCommissionRate' => $maxCommissionRate,
            'estimate' => $estimate,
            'url' => $url,
            'image' => $image
        ]);
});

//Route::get('/bind/{openid}', [Controllers\UserController::class, 'regMember']);
Route::get('/bind/{openid}', function ($openid) {
    return app(Controllers\UserController::class)->regMember($openid, Request::get("code"));
});

Route::get('/getOrderList', [Controllers\TaokeController::class, 'getOrderList']);
Route::get('/updateOrderAll', [Controllers\TaokeController::class, 'updateOrderAll']);

Route::post('/AppLogin', function () {
    $userid = Request::post("userid");
    $password = Request::post("password");
    return app(\App\Http\Controllers\UserController::class)->login($userid, $password);
});

Route::post('/AppReg', function () {
    $invite_id = Request::post("invite_id");
    $nickname = Request::post("nickname");
    $userid = Request::post("userid");
    $password = Request::post("password");
    //$bindCode = Request::post("bindCode");
    $code = Request::post("code");
    return app(\App\Http\Controllers\UserController::class)->register($invite_id, $nickname, $userid, $password, null, $code);
});

Route::post('/SendSms', function () {
    $phone = Request::post("phone");
    return app(\App\Http\Controllers\UserController::class)->sendSms($phone);
});

Route::get('/getOrder', function () {
    $openid = Request::get("openid");
    $page = Request::get("page");
    return app(\App\Http\Controllers\OrderController::class)->getOrder($openid, $page);
});

Route::get('/getOrderCount', function () {
    $openid = Request::get("openid");
    return app(\App\Http\Controllers\OrderController::class)->getOrderCount($openid);
});

Route::get('/AppBind', function () {
    $tradeid = Request::get("tradeid");
    $openid = Request::get("openid");
    return app(\App\Http\Controllers\OrderController::class)->bindOrder($tradeid, $openid);
});

Route::post('/getBalance', function () {
    $token = Request::post("token");
    return app(\App\Http\Controllers\UserController::class)->getBalance($token);
});

Route::post('/receive', function () {
    $token = Request::post("token");
    return app(\App\Http\Controllers\UserController::class)->receive($token);
});

Route::post('/receiveStatus', function () {
    $token = Request::post("token");
    return app(\App\Http\Controllers\UserController::class)->receiveStatus($token);
});

Route::post('/bindAlipay', function () {
    $token = Request::post("token");
    $username = Request::post("username");
    $alipay_id = Request::post("alipay_id");
    return app(\App\Http\Controllers\UserController::class)->bindAlipay($token, $username, $alipay_id);
});

Route::post('/unbindAlipay', function () {
    $token = Request::post("token");
    return app(\App\Http\Controllers\UserController::class)->unbindAlipay($token);
});

Route::post('/validateToken', function () {
    $token = Request::post("token");
    return app(\App\Http\Controllers\UserController::class)->validateToken($token);
});

Route::post('/setCid', function () {
    $token = Request::post("token");
    $cid = Request::post("cid");
    $version = Request::post("version");
    return app(\App\Http\Controllers\UserController::class)->setCid($token, $cid, $version);
});

Route::any('/getCid', function () {
    $token = Request::post("token");
    return app(\App\Http\Controllers\UserController::class)->getCid($token);
});

Route::any('/getRelation_id', function () {
    $token = Request::get("token");
    return app(\App\Http\Controllers\UserController::class)->getRelation_id($token);
});

Route::get('/AppParse', function () {
    $openid = Request::get("openid");
    $content = Request::get("content");
    $relation = Request::get("relation");
    if ($relation == null || $relation == "") {
        $relation = false;
    }
    return app(\App\Http\Controllers\TaokeController::class)->parseApi($openid, $content, $relation);
});

Route::get('/AppTbParse', function () {
    $openid = Request::get("openid");
    $goodsid = Request::get("goodsid");
    return app(\App\Http\Controllers\TaokeController::class)->parseTbByGoodsId($openid, $goodsid);
});

Route::post('/queryTlj', function () {
    $openid = Request::post("openid");
    $goodsId = Request::post("goodsId");
    return app(\App\Http\Controllers\TaokeController::class)->queryTlj($openid, $goodsId);
});

Route::post('/createTlj', function () {
    $openid = Request::post("openid");
    $token = Request::post("token");
    $goodsId = Request::post("goodsId");
    $title = Request::post("title");
    $estimate = Request::post("estimate");
    return app(\App\Http\Controllers\TaokeController::class)->createTlj($openid, $token, $goodsId, $title, $estimate);
});

Route::get('/getTlj', function () {
    $openid = Request::get("openid");
    return app(\App\Http\Controllers\TljController::class)->getInfoByOpenid($openid);
});


Route::get('/getElmTkl', function () {
    return app(\App\Http\Controllers\TaokeController::class)->getElmTkl();
});

Route::get('/pushTest', function () {
    return app(\App\Http\Controllers\UserController::class)->push("测试成功：【狂欢价】皇氏来思尔大理全脂纯牛奶256g*24盒4整箱批特价生牛乳早餐奶", "测试", "58febe8a5da136c5b39fefd3773bc54c", "https://img11.360buyimg.com/n0/jfs/t1/8249/34/17170/135304/6295dc54E7ef107b7/1fa06e600bdbaf01.jpg");
});

Route::get('/getInvite', function () {
    $inviterId = Request::get("inviterId");
    return app(\App\Models\Invite::class)->getInviteByInviterId($inviterId);
});

Route::post('/sendFPCode', function () {
    $userid = Request::post("phone");
    return app(\App\Http\Controllers\UserController::class)->findPasswordSendCode($userid);
});

Route::post('/findPassword', function () {
    $userid = Request::post("userid");
    $password = Request::post("password");
    $code = Request::post("code");
    return app(\App\Http\Controllers\UserController::class)->findPassword($userid, $password, $code);
});

Route::post('/checkLogOff', function () {
    $openid = Request::post("openid");
    return app(\App\Http\Controllers\UserController::class)->getUserLogOff($openid);
});

Route::post('/logOff', function () {
    $openid = Request::post("openid");
    return app(\App\Http\Controllers\UserController::class)->userLogOff($openid);
});

Route::get('/getInviteCode', function () {
    $openid = Request::get("openid");
    return app(\App\Http\Controllers\UserController::class)->getInviteCode($openid);
});


Route::get('/login', function () {
    $qq = Request::get("qq");
    if ($qq != null && $qq != "") {
        $user = app(Users::class)->getUserById($qq);
        if ($user == null) {
            return view('/login',
                [
                    'error' => true
                ]);
        } else {
            Cookie::queue('qq', $qq, 60 * 24 * 30);
            return view('/welcome',
                [
                    'qq' => $qq
                ]);
        }

    }
    return view('/login');
});

Route::middleware(['CheckQQLogin'])->group(function () {
    Route::get('/', function () {
        $qq = Cookie::get("qq");
        return view('/welcome',
            [
                'qq' => $qq
            ]);
    });

    Route::get('/query', function () {
        $qq = Cookie::get("qq");
        $content = Request::get("content");
        if ($content == null || $content == "") {
            return view('/query',
                [
                    'qq' => $qq,
                    'data' => null
                ]);
        } else {
            $data = app(Controllers\TaokeController::class)->parse($qq, $content);
            return view('/query',
                [
                    'qq' => $qq,
                    'data' => $data
                ]);
        }

    });

    Route::get('/bind', function () {
        $qq = Cookie::get("qq");
        $content = Request::get("content");
        if ($content == null || $content == "") {
            return view('/bind',
                [
                    'qq' => $qq,
                    'data' => null
                ]);
        } else {
            $data = app(\App\Models\Orders::class)->ModifyOpenIdByTradeParentIdAndModifyRebateAmountAccordingToRebateRatio($content, $qq);
            return view('/bind',
                [
                    'qq' => $qq,
                    'data' => $data
                ]);

        }
    });

    Route::get('/loginout', function () {
        Cookie::queue(Cookie::forget('qq'));
        return view('login');
    });
});

Route::middleware(['CheckAdminLogin'])->group(function () {
    Route::get('/setIndustry', function () {
        app(\App\Http\Controllers\WeChatController::class)->setIndustry();
        return "设置模板成功，请前往微信公众号后台获取模板ID，请勿重复访问此页面。";
    });

    Route::get('/admin', function () {
        return view('admin/index');
    });

    //后台主页
    Route::get('/admin/index', function () {
        return view('admin/index');
    });
    //新增管理员小窗口
    Route::get('/admin/admin-add', function () {
        if (Cookie::get('adminId') == 1) {
            return view('admin/admin-add');
        } else {
            return view('admin/error');
        }

    });
    //提交新增管理员post请求
    Route::post('/admin/admin-add', function () {
        $result = app(\App\Models\Admin::class)->addAdmin(Request::post("username"), Request::post("password"));
        if ($result > 0) {
            return 1;
        } else {
            return 0;
        }
    });

    //提交删除管理员get请求
    Route::get('/admin/admin-del', function () {
        if (Cookie::get('adminId') == 1) {
            $result = app(\App\Models\Admin::class)->delAdminById(Request::get("id"));
            if ($result > 0) {
                return 1;
            } else {
                return 0;
            }
        } else {
            return -1;
        }

    });

    //管理员账号密码修改小窗
    Route::get('/admin/admin-edit', function () {
        if (Cookie::get('adminId') == 1 || Cookie::get('adminId') == Request::get('id')) {
            return view('admin/admin-edit', [
                'id' => Request::get('id'),
                'username' => Request::get('username'),
            ]);
        } else {
            return view('admin/error');
        }

    });
    //提交修改管理员账号密码post请求
    Route::post('/admin/admin-edit', function () {
        $result = app(\App\Models\Admin::class)->updateAdminById(Request::post("id"), Request::post("username"), Request::post("password"));
        if ($result > 0) {
            return 1;
        } else {
            return 0;
        }
    });

    //管理员列表
    Route::get('/admin/admin-list', function () {
        $admins = app(\App\Models\Admin::class)->getAll();
        return view('admin/admin-list', [
            'admins' => $admins,
        ]);
    });

    Route::get('/admin/member-list', function () {
        $openid = Request::get("openid");
        $users = null;
        if ($openid != null) {
            $users = app(\App\Models\Users::class)->getAllByPaginate($openid);
        } else {
            $users = app(\App\Models\Users::class)->getAllByPaginate();
        }

        return view('admin/member-list', ['users' => $users]);
    });

    Route::get('/admin/member-edit', function () {
        $id = Request::get("id");
        $rebate_ratio = Request::get("rebate_ratio");
        $special_id = Request::get("special_id");
        return view('admin/member-edit', [
            'id' => $id,
            'rebate_ratio' => $rebate_ratio,
            'special_id' => $special_id
        ]);
    });

    Route::get('/admin/order-list', function () {
        $trade_parent_id = Request::get("trade_parent_id");
        $start = Request::get("start");
        $end = Request::get("end");
        $tk_status = Request::get("tk_status");
        $orders = app(\App\Models\Orders::class)->getAllByPaginate($trade_parent_id, $start, $end, $tk_status);
        return view('admin/order-list', [
            'orders' => $orders,
            'trade_parent_id' => $trade_parent_id,
            'start' => $start,
            'end' => $end,
            'tk_status' => $tk_status
        ]);
    });

    Route::get('/admin/receive', function () {
        $openid = Request::get("openid");
        $status = Request::get("status");
        $receives = app(\App\Models\Receive::class)->getAllByPaginate($openid, $status);
        $alipays = app(\App\Models\Users::class)->getAlipayTraversalInReceive($receives);
        return view('admin/receive', [
            'receives' => $receives,
            'openid' => $openid,
            'status' => $status,
            'alipays' => $alipays
        ]);
    });

    Route::get('/admin/receivePass', function () {
        $id = Request::get("id");
        return app(\App\Models\Receive::class)->receivePass($id);
    });

    Route::get('/admin/receiveRefuse', function () {
        $id = Request::get("id");
        $reason = Request::get("reason");

        return app(\App\Models\Receive::class)->receiveRefuse($id, $reason);
    });

    Route::get('/admin/welcome', function () {
        $orderCount = app(\App\Models\Orders::class)->getOrderCountAndFee();
        $receiveCount = app(\App\Models\Receive::class)->getReceiveCountOfTheDay();
        if ($orderCount != null && count($orderCount) > 0) {
            return view('admin/welcome', [
                'count' => $orderCount[0]->count,
                'pub_share_pre_fee' => $orderCount[0]->pub_share_pre_fee,
                'rebate_pre_fee' => $orderCount[0]->rebate_pre_fee,
                'receiveCount' => $receiveCount[0]->count
            ]);
        } else {
            return view('admin/welcome', [
                'count' => 0,
                'pub_share_pre_fee' => 0,
                'rebate_pre_fee' => 0,
                'receiveCount' => $receiveCount[0]->count
            ]);

        }
    });

    Route::post('/admin/modifyUser', function () {
        $result = app(\App\Models\Users::class)->modifyUserById(Request::post("id"), Request::post("rebate_ratio"), Request::post("special_id"));
        if ($result > 0) {
            return 1;
        } else {
            return 0;
        }
    });

});

Route::get('/admin/loginout', function () {
    Cookie::queue(Cookie::forget('username'));
    return view('admin/login');
});

Route::post('/admin/getAdmin', function () {
    $admin = app(\App\Models\Admin::class)->getAdmin(Request::post("username"), Request::post("password"));
    if ($admin != null) {
        return response('1')->cookie('username', $admin->username, 14400)->cookie('adminId', $admin->id, 14400);
    } else {
        return 0;
    }
});
