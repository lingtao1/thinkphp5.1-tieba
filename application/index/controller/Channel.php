<?php

namespace app\index\controller;

use think\facade\Config;
use think\Controller;
use think\Db;
use think\Request;
// use think\facade\Request;
use think\File;

class Channel extends Controller
{

    public function add(Request $request)
    {
        $model = new Token;
        if ($model->checkToken($request->header('Authorization')) !== 'done') {
            return $model->checkToken($request->header('Authorization'));
        }

        $data = $request->post();
        $exist = Db::table('channel')
            ->where('name', $data['name'] . '吧')
            ->find();

        if ($exist) {
            return json('已存在此吧', 401);
        }

        $file = $request->file('image');
        if ($file) {
            $imageInfo = $file->rule('uniqid')->move('../public/channel-images/');
            $data['image'] = $imageInfo->getSaveName();
        }

        if (!$file) {
            $data['image'] = 'no-avatar.jpg';
        }
        $data['create_username'] = $data['create-username'];
        $data['name'] = $data['name'] . '吧';
        unset($data['create-username']);

        Db::table('channel')
            ->insert($data);

        return 'ok';
    }

    public function getFollowChannel(Request $request)
    {
        // $model = new Token;
        // if ($model->checkToken($request->header('Authorization')) != 'ok') {
        //     return $model->checkToken($request->header('Authorization'));
        // }

        $uid = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('id');

        $data = Db::table('follow')
            ->where('follow.from_uid', $uid)
            ->join('channel', 'follow.to_cid=channel.id')
            ->select();


        $newdata = [];
        foreach ($data as $value) {
            $value['image'] = Config::get('BaseURL') . 'channel-images/' . $value['image'];
            array_push($newdata, $value);
        }

        return json($newdata);
    }

    public function info(Request $request)
    {


        $data = DB::table('channel')
            ->where('id', $request->get('id'))
            ->field('id,name,image,synopsis')
            ->find();


        if ($request->header('Authorization')) {

            $history = new History;

            $uid = Db::table('user')
                ->where('token', $request->header('Authorization'))
                ->value('id');

            $isFollow = Db::table('follow')
                ->where('from_uid', $uid)
                ->where('to_cid', $request->get('id'))
                ->find();

            $data['follow_num'] = Db::table('follow')
                ->where('to_cid', $data['id'])
                ->count();

            $data['isFollow'] = $isFollow ? true : false;
            $history->history($uid, $request->get('id'));
        } else {
            $data['isFollow'] = false;
        }


        $data['image'] = Config::get('BaseURL') . 'channel-images/' . $data['image'];
        return json($data, 200);
    }

    public function followChannel(Request $request)
    {
        $model = new Token;
        if ($model->checkToken($request->header('Authorization')) !== 'done') {
            return $model->checkToken($request->header('Authorization'));
        }

        $uid = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('id');
        $cid = $request->post('id');

        $exist = Db::table('follow')
            ->where('from_uid', $uid)
            ->where('to_cid', $cid)
            ->find();
        if ($exist) {
            Db::table('follow')
                ->where('from_uid', $uid)
                ->where('to_cid', $cid)
                ->delete();
        } else {
            Db::table('follow')
                ->insert([
                    'from_uid' => $uid,
                    'to_cid' =>  $cid,
                ]);
        }
    }
}
