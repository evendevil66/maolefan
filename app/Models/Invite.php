<?php


namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use mysql_xdevapi\Exception;

class Invite extends Model
{
    protected $table = "invite";


    public function getInvite(): \Illuminate\Support\Collection
    {
        return DB::table($this->table)->get();
    }

    public function getInviteByInviterId(string $inviterId): string
    {
        $invite = DB::table($this->table)
            ->where(["inviterId" => $inviterId])
            ->join('users', 'invite.inviteeId', '=', 'users.id')
            ->orderBy('inviteId', 'desc')
            ->get();
        $result = array();
        foreach ($invite as $item) {
            $lastRebateTime = $item->lastRebateTime;
            $state = "活跃用户";
            if ($lastRebateTime == null || $lastRebateTime == "") {
                $state = "新用户";
            } else {
                $date = time();
                $days = round(($date - strtotime($lastRebateTime)) / 3600 / 24);
                if ($days > 40) {
                    $state = "非活跃用户";
                }
            }
            $result[] = [
                "nickname" => $item->nickname,
                "commission" => $item->commission,
                "lastRebateTime" => $item->lastRebateTime,
                "state" => $state
            ];
        }
        return json_encode([
            "code" => 200, "data" => $result
        ]);

    }

    public function addInvite(string $inviterId, string $inviteeId): bool
    {
        $result = DB::table($this->table)->insert([
            "inviterId" => $inviterId,
            "inviteeId" => $inviteeId
        ]);
        return $result > 0;
    }

    public function getInviteById(string $inviterId, string $inviteeId)
    {
        return DB::table($this->table)
            ->where([
                "inviterId" => $inviterId,
                "inviteeId" => $inviteeId
            ])
            ->first();
    }

    public function updateInvite(string $inviterId, string $inviteeId, float $commission, string $lastRebateTime): bool
    {
        $result = DB::table($this->table)
            ->where([
                "inviterId" => $inviterId,
                "inviteeId" => $inviteeId
            ])
            ->update([
                "commission" => $commission,
                "lastRebateTime" => $lastRebateTime
            ]);
        return $result > 0;
    }


}
