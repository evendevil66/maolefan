<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Log;

class Tlj extends Model
{
    use HasFactory;

    protected $table = "tlj";

    public function getInfoByOpenidAndGoodsId($openid, $goodsId): object|null
    {
        date_default_timezone_set("Asia/Shanghai");
        return DB::table($this->table)
            ->where([
                'openid' => $openid
            ])
            ->where('goodsId', "like", "%" . $goodsId)
            ->where('createTime', ">=", date("Y-m-d", time()))
            ->first();
    }


    public function saveTlj($openid, $goodsId, $title, $estimate, $rightsId, $sendUrl, $longTpwd): bool
    {
        date_default_timezone_set("Asia/Shanghai");
        return DB::table($this->table)
            ->insert([
                'openid' => $openid,
                'goodsId' => $goodsId,
                'title' => $title,
                'estimate' => $estimate,
                'rightsId' => $rightsId,
                'sendUrl' => $sendUrl,
                'longTpwd' => $longTpwd,
                'createTime' => date("Y-m-d H:i:s", time())
            ]);
    }

    public function getInfoByOpenid($openid): object|null
    {
        date_default_timezone_set("Asia/Shanghai");
        return DB::table($this->table)
            ->where([
                'openid' => $openid
            ])
            ->where('createTime', ">=", date("Y-m-d", time()))
            ->get();
    }
}
