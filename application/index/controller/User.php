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
    public function index()
    {
        return 'hello wordK';
    }

    public function profile(Request $request)
    {
        // 用户个人信息
        $model = new Token;
        // return json($request->header('Authorization'));
        if ($request->header('Authorization') == 'null') {
            return response()->code(200);
        }
        if ($model->checkToken($request->header('Authorization')) != 'ok') {
            return $model->checkToken($request->header('Authorization'));
        }

        $data = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->field('id,name,image,sex')
            ->find();
        $data['image'] = Config::get('BaseURL') . 'user-images/' . $data['image'];
        return json($data);
    }

    public function getUserInfo(Request $request)
    {
        // 用户个人信息
        $id = $request->get('id');

        $data = Db::table('user')
            ->where('id', $id)
            ->field('id,name,image,sex')
            ->find();
        $data['image'] = $data['image'] ? Config::get('BaseURL') . 'user-images/' . $data['image'] : [];

        $data['channel_num'] = Db::table('follow')
            ->where('from_uid', $data['id'])
            ->count();

        $data['follow_num'] = Db::table('attention')
            ->where('from_uid', $data['id'])
            ->count();


        $data['fans_num'] = Db::table('attention')
            ->where('from_uid', $data['id'])
            ->count();
        return json($data);
    }

    public function login(Request $request)
    {
        // return json($request->header('Authorization'));

        // 获取用户信息
        $data = Db::table('user')
            ->where('name', $request->post('username'))
            ->find();
        if ($data) {
            // 用户存在

            // 判断密码是否正确
            if ($data['password'] !== $request->post('password')) {
                $res = [];
                $res['message'] = '密码错误';
                return json($res, 401);
            }
        } else {
            // 用户不存在


            // 创建新用户
            $this->createUser($request->post());
        }
        // 设置token
        $model = new Token;
        $token = $model->setToken($request->post('username'));
        // 返回所需用户数据
        $user['token'] = $token;
        return json($user, 200);
    }

    public function createUser($userInfo)
    {
        $data['name'] = $userInfo['username'];
        $data['password'] = $userInfo['password'];
        Db::table('user')->insert($data);
    }

    public function getUserinfoEdit(Request $request)
    {
        $userToken = $request->header('Authorization');

        $res = Db::table('user')
            ->where('token', $userToken)
            ->field('image,sex')
            ->find();
        $res['image'] = Config::get('BaseURL') . 'user-images/' . $res['image'];
        return json($res);
    }

    public function channgeUserSex(Request $request)
    {
        $req = $request->post();

        $req['sex'];
        Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->update(['sex' => $req['sex']]);
        return json(null, 200);
    }

    public function updateUserImage(Request $request)
    {

        $file = $request->file('image');
        if ($file) {
            $imageInfo = $file->rule('uniqid')->move('../public/user-images/');
            $data['image'] = $imageInfo->getSaveName();
        }

        $image = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('image');

        if ($image != 'detault.jpg' && file_exists('../public/user-images/' . $image)) {
            unlink('../public/user-images/' . $image);
        }

        Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->update(['image' => $imageInfo->getSaveName()]);

        return json('ok');
    }

    public function getUserPost(Request $request)
    {
        $req = $request->get();
        $uid = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('id');

        $data = Db::table('detail')
            ->where('user_id', $req['id'])
            ->field('detail.id,parent_id as cid,title,like,content,images,detail.createtime')
            ->join('channel', 'channel.id = detail.parent_id')
            ->field('channel.name as channel_name')
            ->page($req['page'], 3)
            ->select();

        $res = [];
        foreach ($data as $item) {

            $item['images'] = $item['images'] ? explode('##', $item['images']) : [];
            $like = $item['like'] ? explode('|', $item['like']) : [];

            $item['like_num'] =  count($like);
            $item['like'] = in_array($uid, $like);
            $item['comment_num'] = Db::table('comment')
                ->where('detail_id', $item['id'])
                ->count();
            $new_images = [];
            foreach ($item['images'] as $img) {
                $img =  Config::get('BaseURL') . 'detail-images/' .  $img;
                array_push($new_images, $img);
            }
            $item['images'] = $new_images;
            array_push($res, $item);
        }
        return json($res);
    }
}
