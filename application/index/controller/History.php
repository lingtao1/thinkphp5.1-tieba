<?php

namespace app\index\controller;


use think\facade\Config;
use think\Controller;
use think\Db;
use think\Request;

class History
{
    public function history($uid, $cid)
    {
        $exist = Db::table('history')
            ->where('uid', $uid)
            ->where('cid', $cid)
            ->find();
        if ($exist) {
            Db::table('history')
                ->where('id', $exist['id'])
                ->update([
                    'updatetime' => time()
                ]);
        } else {
            Db::table('history')
                ->insert([
                    'uid' => $uid,
                    'cid' => $cid,
                    'updatetime' => time()
                ]);
        }
    }

    public function getHistory(Request $request)
    {
        // return  $request->header('Authorization');
        $uid = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('id');

        $data = Db::table('history')
            ->where('uid', $uid)
            ->join('channel', 'channel.id = history.cid')
            ->field('history.id as history_id')
            ->field('channel.id,channel.name,channel.image,channel.synopsis')
            ->order('updatetime', 'desc')
            ->select();

        $res = [];
        foreach ($data as $item) {
            $item['image'] = Config::get('BaseURL') . 'channel-images/' . $item['image'];
            array_push($res, $item);
        }
        return json($res);
    }

    public function deteleHistory(Request $request)
    {
        $id = $request->post('id');
        Db::table('history')->where('id', $id)->delete();
        return json('ok');
    }
}
