<?php


namespace App\Models;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use mysql_xdevapi\Exception;

class Users extends Model
{
    protected $table = "users";

    /**
     * 获取所有用户
     * @return \Illuminate\Support\Collection 返回用户对象
     */
    public function getAll()
    {
        return DB::table($this->table)->get();
    }

    /**
     * 通过openid获取用户信息
     * @param $openid 微信openid
     * @return \Illuminate\Support\Collection 返回用户对象或NULL
     */
    public function getUserById($openid)
    {
        return DB::table($this->table)->where('id', $openid)->first();
    }

    /**
     * 通过userid获取用户信息
     * @param $userid
     * @return Model|Builder|object|null
     */
    public function getUserByUserId($userid)
    {
        return DB::table($this->table)->where('userid', $userid)->first();
    }

    /**
     * 通过会员运营id获取用户信息
     * @param $special_id 会员运营id
     * @return \Illuminate\Support\Collection 返回用户对象或NULL
     */
    public function getUserByRelationId($relation_id)
    {
        return DB::table($this->table)->where('relation_id', $relation_id)->first();
    }

    /**
     * 注册账号
     * @param $invite_id
     * @param $nickname
     * @param $userid
     * @param $password
     * @param $bindCode
     * @return int
     */
    public function userRegistration($invite_id, $nickname, $userid, $password, $bindCode): string
    {
        $openid = $userid;
        $password = password_hash($password, PASSWORD_DEFAULT);
        $token = md5($userid . time());
        if ($invite_id != null && $invite_id != "") {
            $user = $this->getUserById($invite_id);
            Log::info($invite_id);
            if ($user == null) {
                return -3;
            }
        }
        $str = "";
        while (true) {
            $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            mt_srand(10000000 * (double)microtime());
            for ($i = 0, $str = '', $lc = strlen($chars) - 1; $i < 6; $i++) {
                $str .= $chars[mt_rand(0, $lc)];
            }
            $user = $this->getUserByInviteId($str);
            if ($user == null) {
                break;
            }
        }

        if ($bindCode == null || $bindCode == "") {
            return $flag = DB::table($this->table)
                ->insert([
                    'id' => $openid,
                    'nickname' => $nickname,
                    'invite_id' => $invite_id,
                    'invitation_reward' => $invite_id != null ? 1 : 0,
                    'rebate_ratio' => config('config.default_rebate_ratio'),
                    'userid' => $userid,
                    'password' => $password,
                    'token' => $token,
                    'invite_code' => $str
                ]);
        } else {
            return $this->userTransfer($bindCode, $nickname, $userid, $password, $token);
        }
    }

    /**
     * 绑定账号
     * @param $bindCode
     * @param $nickname
     * @param $userid
     * @param $password
     * @param $token
     * @return string
     */
    public function userTransfer($bindCode, $nickname, $userid, $password, $token)
    {
        $flag = -1;
        if ($nickname != null) {
            $flag = DB::table($this->table)
                ->where('bind_code', $bindCode)
                ->update([
                    'nickname' => $nickname,
                    'userid' => $userid,
                    'password' => $password,
                    'token' => $token
                ]);
        } else {
            $flag = DB::table($this->table)
                ->where('bind_code', $bindCode)
                ->update([
                    'userid' => $userid,
                    'password' => $password,
                    'token' => $token
                ]);
        }

        if ($flag) {
            $this->deleteBindCode($bindCode);
            return 2;
        } else {
            return -1;
        }
    }

    //删除绑定码
    public function deleteBindCode($bindCode)
    {
        return DB::table($this->table)
            ->where('bind_code', $bindCode)
            ->update([
                'bind_code' => null
            ]);
    }

    /**
     * 用户登陆
     * @param $userid
     * @param $password
     * @return Builder|Model|object|null
     */
    public function login($userid, $password)
    {
        try {
            $user = $this->getUserByUserId($userid);
            if ($user != null) {
                $flag = password_verify($password, $user->password);
                if ($flag) {
                    $this->setToken($user->userid);
                    return $this->getUserByUserId($userid);
                }
            }
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }

    /**
     * 获取token
     * @param $userid
     * @return mixed|null
     */
    public function getToken($userid)
    {
        $user = $this->getUserByUserId($userid);
        if ($user != null) {
            return $user->token;
        }
        return null;
    }

    /**
     * 设置token
     * @param $userid
     * @return bool
     */
    public function setToken($userid): bool
    {
        $user = $this->getUserByUserId($userid);
        if ($user != null) {
            $token = md5($userid . time());
            Log::info($userid . time() . "\n" . $token);
            $flag = DB::table($this->table)
                ->where('userid', $userid)
                ->update([
                    'token' => $token
                ]);
            if ($flag) {
                return true;
            }
        }
        return false;
    }

    /**
     * 通过token获取用户信息
     * @param $token
     * @return Model|Builder|object|null
     */
    public function getUserByToken($token)
    {
        return DB::table($this->table)->where('token', $token)->first();
    }


    /**
     * @param $bindCode
     * @return Model|Builder|object|null
     */
    public function getUserByBindCode($bindCode)
    {
        return DB::table($this->table)->where('bind_code', $bindCode)->first();
    }


    /**
     * 用户信息补全更新 通过Request获取参数
     * $openid 微信openid
     * $nickname 用户填写的昵称
     * $username ～姓名
     * $alipay_id ～支付宝账号
     * @return int 如执行成功返回1
     */
    public function userUpdate()
    {
        $openid = Request::post("openid");
        $nickname = Request::post("nickname");
        $username = Request::post("username");
        $alipay_id = Request::post("alipay_id");
        return DB::table($this->table)
            ->where('id', $openid)
            ->update([
                'nickname' => $nickname,
                'username' => $username,
                'alipay_id' => $alipay_id
            ]);
    }

    public function updateNickname($openid, $nickname)
    {
        return DB::table($this->table)
            ->where('id', $openid)
            ->update([
                'nickname' => $nickname
            ]);
    }

    /**
     * 更新用户的粉丝运营id
     * @param $openid 微信openid
     * @param $special_id 粉丝运营id
     * @return int 如执行成功返回1
     */
    public function updateSpecial_id($openid, $special_id)
    {
        try {
            return DB::table($this->table)
                ->where('id', $openid)
                ->update([
                    'special_id' => $special_id
                ]);
        } catch (Exception $e) {
            return 0;
        }

    }

    /**
     * 更新用户的渠道id
     * @param $openid 微信openid
     * @param $special_id 粉丝运营id
     * @return int 如执行成功返回1
     */
    public function updateRelation_id($openid, $relation_id)
    {
        try {
            return DB::table($this->table)
                ->where('id', $openid)
                ->update([
                    'relation_id' => $relation_id
                ]);
        } catch (Exception $e) {
            return 0;
        }

    }

    /**
     * 更新未结算金额
     * @param $openid
     * @param $unsettled_balance
     * @return int
     */
    public function updateUnsettled_balance($openid, $unsettled_balance)
    {
        return DB::table($this->table)
            ->where('id', $openid)
            ->update([
                'unsettled_balance' => $unsettled_balance
            ]);

    }

    /**
     * 更新可用金额
     * @param $openid
     * @param $available_balance
     * @return int
     */
    public function updateAvailable_balance($openid, $available_balance)
    {
        return DB::table($this->table)
            ->where('id', $openid)
            ->update([
                'available_balance' => $available_balance
            ]);

    }

    /**
     * 分页查询用户信息
     * @param null $openid 筛选openid或昵称精准查询
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator 分页查询对象
     */
    public function getAllByPaginate($openid = null)
    {
        if ($openid == null) {
            //判断是否传入筛选条件，如未传入则全量分页查询
            return DB::table('users')->paginate(10);
        } else {
            return DB::table('users')
                ->where('id', $openid)
                ->orWhere('nickname', 'like', "%" . $openid . "%")
                ->paginate(5);
            //分别对openid和nickname执行条件筛选，openid为精准，nickname为模糊
        }

    }

    /**
     * 根据openid修改返利比例和运营id
     * @param $id openid
     * @param $rebate_ratio 返利比例
     * @param $special_id 用户运营id
     * @return int 修改成功返回1否则0
     */
    public function modifyUserById($id, $rebate_ratio, $special_id)
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->update([
                'rebate_ratio' => $rebate_ratio,
                'special_id' => $special_id,
            ]);
    }

    /**
     * 根据openid修改返利比例
     * @param $id openid
     * @param $rebate_ratio 返利比例
     * @return int 修改成功返回1否则0
     */
    public function modifyRebateRatioById($id, $rebate_ratio)
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->update([
                'rebate_ratio' => $rebate_ratio
            ]);
    }

    public function getAlipayTraversalInReceive($receives)
    {
        $alipays = array();
        foreach ($receives as $receive) {
            $openid = $receive->openid;
            $user = $this->getUserById($openid);
            $alipay = array();
            if ($user == null) {
                $alipay["username"] = "账户已迁移";
                $alipay["alipay_id"] = "账户已迁移";
            } else {
                $alipay["username"] = $user->username;
                $alipay["alipay_id"] = $user->alipay_id;
            }
            array_push($alipays, $alipay);
        }
        return $alipays;
    }

    //通过邀请id查询邀请的用户数量
    public function getUserCountByInviteId($invite_id)
    {
        return DB::table($this->table)
            ->where('invite_id', $invite_id)
            ->count();
    }

    //通过邀请id查询邀请的用户invitation_reward为0的数量
    public function getUserCountByInviteIdAndInvitationReward($invite_id)
    {
        return DB::table($this->table)
            ->where('invite_id', $invite_id)
            ->where('invitation_reward', 0)
            ->count();
    }

    //修改用户的invitation_reward为0
    public function updateInvitationReward($openid)
    {
        return DB::table($this->table)
            ->where('id', $openid)
            ->update(['invitation_reward' => 0]);
    }

    //绑定支付宝
    public function bindAlipay($token, $username, $alipay_id)
    {
        return DB::table($this->table)
            ->where('token', $token)
            ->update([
                'alipay_id' => $alipay_id,
                'username' => $username
            ]);
    }

    /**
     * 解绑支付宝
     * @param $token
     * @return int
     */
    public function unbindAlipay($token)
    {
        return DB::table($this->table)
            ->where('token', $token)
            ->update([
                'alipay_id' => null,
                'username' => null
            ]);
    }

    public function setCid($token, $cid, $version)
    {
        return DB::table($this->table)
            ->where('token', $token)
            ->update([
                'cid' => $cid,
                'version' => $version
            ]);
    }

    public function getCid($token)
    {
        $user = DB::table($this->table)->where('token', $token)->first();
        try {
            if ($user->cid == null) {
                return null;
            }
            return $user->cid;
        } catch (\Exception $e) {
            return null;
        }


    }

    public function getRelation_id($token)
    {
        $user = DB::table($this->table)->where('token', $token)->first();
        try {
            if ($user->relation_id == null) {
                return null;
            }
            return $user->relation_id;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function userLogOff($openid, $userid)
    {
        return DB::table($this->table)
            ->where('id', $openid)
            ->update([
                'userid' => $userid . "-" . time() . "已注销"
            ]);
    }

    public function findPassword($userid, $password)
    {
        $password = password_hash($password, PASSWORD_DEFAULT);
        return DB::table($this->table)
            ->where('id', $userid)
            ->update([
                'password' => $password
            ]);
    }

    public function getUserByInviteId($invite_code)
    {
        return DB::table($this->table)->where('invite_code', $invite_code)->first();
    }

}
