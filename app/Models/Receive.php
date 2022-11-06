<?php


namespace App\Models;

use App\Http\Controllers\CQSocketController;
use App\Http\Controllers\WeChatController;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use mysql_xdevapi\Exception;

class Receive extends Model
{
    protected $table = "receive";


    /**
     * 获取当天的提现订单数量
     * @return array 返回数组中仅包含一条数据，其中该数据中的count为当日当单数量
     */
    public function getReceiveCountOfTheDay()
    {
        $sql = "SELECT count(*) AS count FROM `receive` WHERE to_days(`receive_date`) = to_days(now())";
        return DB::select($sql);
    }

    /**
     * 分页查询提现记录
     * @param null $openid 筛选条件openid
     * @param null $status 筛选条件提现状态
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllByPaginate($openid = null, $status = null)
    {
        if ($openid == null && $status == null) {
            //判断是否有筛选条件，如所有筛选条件均为null，则直接查询所有
            return DB::table($this->table)->orderBy('id', 'desc')->paginate(10);
        } else {
            return DB::table($this->table)
                ->where('openid', 'like', trim($openid) == "" ? "%" : $openid)
                ->where('status', 'like', trim($status) == "" ? "%" : $status)
                ->orderBy('id', 'desc')
                ->paginate(10);
        }

    }

    /**
     * 根据提现id通过提现申请
     * @param $id
     * @return int
     */
    public function receivePass($id)
    {
        try {
            date_default_timezone_set("Asia/Shanghai");
            $receive = DB::table($this->table)->where('id', $id)->first();
            DB::beginTransaction();
            DB::table($this->table)
                ->where('id', $id)
                ->update([
                    'status' => 1,
                    'process_time' => date('Y-m-d H:i:s', time())
                ]);
            DB::commit();
            //app(WeChatController::class)->sendReceiveTemplateMessage($receive->openid,"您的提现申请已受理完成",$receive->amount,"提现成功");
            $message = "您的提现申请已受理完成,提现金额为" . $receive->amount . "元";
            return 1;
        } catch (Exception $e) {
            DB::rollBack();
        }
    }

    /**
     * 根据提现id拒绝提现申请
     * @param $id
     * @param $reason 拒绝原因
     * @return int
     */
    public function receiveRefuse($id, $reason)
    {
        $receive = DB::table($this->table)->where('id', $id)->first();
        $user = app(Users::class)->getUserById($receive->openid);
        try {
            date_default_timezone_set("Asia/Shanghai");
            $time = date('Y-m-d H:i:s', time());
            DB::beginTransaction();
            app(Users::class)->updateAvailable_balance($receive->openid, $user->available_balance + $receive->amount);
            app(BalanceRecord::class)->setRecord($user->id, "提现被拒绝返还" . $receive->amount . "元", $receive->amount);
            DB::table($this->table)
                ->where('id', $id)
                ->update([
                    'status' => -1,
                    'process_time' => $time,
                    'reason' => $reason
                ]);
            DB::commit();
            //app(WeChatController::class)->sendReceiveTemplateMessage($receive->openid,"您的提现申请被拒绝，余额已返还",$receive->amount,$reason);
            $message = "您的提现申请被拒绝，余额已返还\n拒绝原因：" . $reason;
            return 1;
        } catch (Exception $e) {
            DB::rollBack();
            return 0;
        }
    }

    /**
     * 获取最近一次提现记录
     * @param $openid
     * @return Model|\Illuminate\Database\Query\Builder|object
     */
    public function getReceiveStatus($openid)
    {
        return DB::table($this->table)
            ->where("openid", $openid)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * 申请提现
     * @param $openid
     * @param $amount
     * @param $nickname
     * @return void
     */
    public function applyReceive($openid, $amount, $nickname)
    {
        date_default_timezone_set("Asia/Shanghai");//设置当前时区为Shanghai
        DB::table($this->table)
            ->insert([
                'openid' => $openid,
                'amount' => $amount,
                'nickname' => $nickname,
                'receive_date' => date('Y-m-d H:i:s', time())
            ]);
    }


}
