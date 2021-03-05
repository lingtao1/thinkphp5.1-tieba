<?php

namespace app\index\controller;

use think\Db;

use think\Request;
use think\Response;

class Token
{
    public function checkToken($token)
    {
        $data = Db::table('user')
            ->where('token', $token)
            ->find();

        if (time() - $data['token_time_out'] > 0 || $token !== $data['token']) {
            return json('用户登录状态已过期', 403);
            exit;
        }




        if (!$token) {
            return  json('用户未登录', 401);
            exit;
        }


        return 'done';
    }

    public function setToken($name)
    {
        Db::table('user')
            ->where('name', $name)
            ->update([
                'token' => $this->makeToken(),
                'token_time_out' => time() + 604800
            ]);

        $data = Db::table('user')
            ->where('name', $name)
            ->value('token');
        return $data;
    }

    public function makeToken()
    {

        $str = md5(uniqid(md5(microtime(true)), true)); //生成一个不会重复的字符串
        $str = sha1($str); //加密
        return $str;
    }
}
