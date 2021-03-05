<?php

namespace app\index\controller;

use think\Db;
use think\Request;

use app\index\controller\Token;
use Exception;
use think\facade\Config;
use think\facade\Cookie;

class User
{
    public function getFollowList(Request $request)
    {
        $model = new Token;
        if ($model->checkToken($request->header('Authorization')) !== 'done') {
            return $model->checkToken($request->header('Authorization'));
        }

        $uid =  Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('id');

        $data = Db::table('follow')
            ->where('from_uid', $uid)
            ->join('channel', 'channel.id = follow.to_cid')
            ->join('user', 'user.id = follow.from_uid')
            ->field('')
            ->field('')
            ->select();
    }
}
