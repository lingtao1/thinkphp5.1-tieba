<?php

namespace app\index\controller;

use think\facade\Config;

use think\Controller;
use think\Db;
use think\Request;
// use think\facade\Request;
use think\File;

class Detail extends Controller
{
    public function add(Request $request)
    {
        $model = new Token;
        if ($model->checkToken($request->header('Authorization')) !== 'done') {
            return $model->checkToken($request->header('Authorization'));
        }

        $data = $request->post();

        // 获取用户token
        $userToken = $request->header('Authorization');

        //获取用户id
        $data['user_id'] = Db::table('user')->where('token', $userToken)->value('id');



        // 获取上传图片
        $files = $request->file('images');


        // 处理多文件上传
        if ($files) {
            // 准备空数组 待接受待上传文件名
            $files_savename = [];

            // 循环图片数组
            foreach ($files as $file) {
                $info = $file->rule('uniqid')->move('../public/detail-images/');
                array_push($files_savename, $info->getSaveName());
            }

            $files_savename = join('##', $files_savename);
            $data['images'] = $files_savename;
        }
        $newID = Db::name('detail')->insertGetId($data);
        return json($newID);
    }

    public function getDetailList(Request $request)
    {

        $forumId = $request->get('forumId');
        $userToken = $request->header('Authorization');
        $user_id = Db::table('user')
            ->where('token', $userToken)
            ->value('id');

        $data = Db::table('detail')
            ->where('detail.parent_id', $forumId)
            ->join('user', 'detail.user_id=user.id')
            ->order('detail.createtime', 'desc')
            ->field('detail.id,user.name,detail.parent_id,title,content,images,detail.createtime,detail.like,user.image,user.id as user_id')
            ->page($request->get('page'), 5)
            ->select();




        $newData = [];
        foreach ($data as $item) {

            $like_array = $item['like'] ? explode("|", $item['like']) : [];
            $item['like_state'] = in_array($user_id, $like_array);
            // return json($item['like']);
            // return json($like_array);
            $item['like_num'] = count($like_array);

            $item['image'] = Config::get('BaseURL') . 'user-images/' . $item['image'];
            $item['comment_num'] = Db::table('comment')
                ->where('detail_id', $item['id'])
                ->count();


            $item['images'] = $item['images'] ? explode("##", $item['images']) : [];
            $newImages = [];
            foreach ($item['images'] as $key => $image) {

                array_push($newImages, Config::get('BaseURL') . 'detail-images/' . $image);
            }
            $item['images'] = array_slice($newImages, 0, 3);

            array_push($newData, $item);
        }
        return json($newData, 200);
    }

    public function like(Request $request)
    {
        $model = new Token;
        if ($model->checkToken($request->header('Authorization')) !== 'done') {
            return $model->checkToken($request->header('Authorization'));
        }

        $detail_id = $request->get('id');
        $userToken = $request->header('Authorization');
        $user_id = Db::table('user')
            ->where('token', $userToken)
            ->value('id');



        $like_str = Db::table('detail')
            ->where('id', $detail_id)
            ->value('like');

        strlen($like_str) > 0 ? $like_array = explode("|", $like_str) : $like_array = [];
        $exist = in_array($user_id, $like_array);

        if ($exist) {
            $like_array = array_diff($like_array, array($user_id));
        } else {
            array_push($like_array, $user_id);
        }

        $new_like_str = join("|", $like_array);

        Db::table('detail')
            ->where('id', $detail_id)
            ->update(['like' => $new_like_str]);
        return json('ok');
    }

    public function getRecommendList(Request $request)
    {
        $req = $request->get();
        $userToken = $request->header('Authorization');
        $user_id = Db::table('user')
            ->where('token', $userToken)
            ->value('id');

        $data = Db::table('detail')
            ->join('channel', 'detail.parent_id=channel.id')
            ->join('user', 'detail.user_id=user.id')
            ->field('detail.id,detail.title,detail.content,detail.like,detail.createtime,detail.images')
            ->field('channel.id as channel_id,channel.name as channel_name')
            ->field('user.id as user_id,user.name as user_name,user.image as user_image')
            ->order('detail.createtime', 'desc')
            ->page($req['page'], 5)
            ->limit(5)
            ->select();



        $newData = [];
        foreach ($data as $item) {
            $like_array = explode("|", $item['like']);

            $item['like_state'] = in_array($user_id, $like_array);

            $item['like_num'] = count($like_array);

            $item['num'] = Db::table('comment')
                ->where('detail_id', $item['id'])
                ->count();

            $item['user_image'] = Config::get('BaseURL') . 'user-images/' .  $item['user_image'];

            $images = $item['images'] ? explode('##', $item['images']) : [];
            $item['images'] = [];
            foreach ($images  as $img) {
                $img = Config::get('BaseURL') . 'detail-images/' . $img;
                array_push($item['images'], $img);
            }
            array_push($newData, $item);
        }
        return json($newData, 200);
    }

    public function getOneDetail(Request $request)
    {
        $userToken = $request->header('Authorization');
        $user_id = Db::table('user')
            ->where('token', $userToken)
            ->value('id');


        $data = Db::table('detail')
            ->where('detail.id', $request->get('id'))
            ->join('user', 'user.id = detail.user_id')
            ->join('channel', 'channel.id = detail.parent_id')
            ->field('user.id as user_id,user.name,user.image as user_image,detail.title,detail.content,detail.images,detail.createtime,detail.parent_id,detail.id,detail.like,channel.image,channel.name as channel_name')
            ->find();

        if ($user_id) {
            $history = new History;
            $history->history($user_id, $data['parent_id']);
        }


        $newImages = [];
        if ($data['images']) {
            foreach (explode("##", $data['images']) as $item) {

                array_push($newImages, Config::get('BaseURL') . 'detail-images/' . $item);
            }
        }

        $data['images'] = $newImages;

        $data['image'] = Config::get('BaseURL') . 'channel-images/' . $data['image'];

        $data['user_image'] = Config::get('BaseURL') . 'user-images/' . $data['user_image'];

        $data['like_state'] = in_array($user_id, explode("|", $data['like']));

        $data['like_num'] = count(explode("|", $data['like']));

        $data['comment_num'] = Db::table('comment')
            ->where('detail_id', $request->get('id'))
            ->count();

        return json($data);
    }
}
