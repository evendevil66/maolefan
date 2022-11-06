<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tlj;

class TljController extends Controller
{
    public function getInfoByOpenid($openid): string
    {
        $tlj = app(Tlj::class)->getInfoByOpenid($openid);
        $data = [];
        foreach ($tlj as $t) {
            $d = [
                "title" => $t->title,
                "estimate" => $t->estimate,
                "longTpwd" => $t->longTpwd,

            ];
            array_push($data, $d);
        }
        return json_encode([
            'code' => 200,
            'message' => "success",
            'data' => $data,
        ]);
    }
}
