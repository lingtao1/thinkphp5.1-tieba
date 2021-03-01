<?php

namespace app\index\controller;

use think\facade\Config;

use think\Controller;
use think\Request;
use think\Db;

class Search extends Controller
{
    public function index(Request $request)
    {
        $user_id = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('id');
        $array =  $this->getHistory($user_id);
        return json($array);
    }

    public function getSuggest(Request $request)
    {
        $req = $request->get();
        $user_id = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('id');


        $num = 6;
        $res = [];
        $channel = Db::table('channel')
            ->where('name', 'like', $req['keyword'] . '%')
            ->field('name as title')
            ->limit(0, 2)
            ->select();

        $num = $num - count($channel);
        $detail = Db::table('detail')
            ->where('title', 'like', $req['keyword'] . '%')
            ->field('title')
            ->limit(0, $num)
            ->select();
        $res = array_merge($channel, $detail);
        return json($res);
    }

    public function getResultChannels(Request $request)
    {
        $req = $request->get();
        $res = [];
        $user_id = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('id');

        $this->insertHistory($user_id, $req['keyword']);
        $channelInfo = Db::table('channel')
            ->where('name', 'like', $req['keyword'] . '吧')
            ->field('name,image,id')
            ->find();

        if ($channelInfo) {
            $channelInfo['image'] = Config::get('BaseURL') . 'channel-images/' .  $channelInfo['image'];
            $res['channelInfo'] = $channelInfo;
        }

        $channelItem = Db::table('channel')
            ->where('name', 'like', '%' . $req['keyword'] . '%')
            ->where('name', '<>', $req['keyword'] . '吧')
            ->field('name,image')
            ->limit(0, 4)
            ->field('name,image,id')
            ->select();

        $new_channel_item = [];
        foreach ($channelItem as $item) {
            $item['image'] = Config::get('BaseURL') . 'channel-images/' .  $item['image'];
            array_push($new_channel_item, $item);
        }

        if (count($new_channel_item) > 1) {
            $res['channelItem'] = $new_channel_item;
        }
        return json($res);
    }

    public function getResultList(Request $request)
    {
        $req = $request->get();
        $res = [];
        $detailList = Db::table('detail')
            ->join('channel', 'channel.id = detail.parent_id')
            ->join('user', 'user.id = detail.user_id')
            ->whereOr([
                ['detail.title', 'like', '%' . $req['keyword'] . '%'],
                ['detail.content', 'like', '%' . $req['keyword'] . '%'],
            ])
            ->field('channel.id as channel_id,channel.name as channel_name,channel.image as channel_image')
            ->field('detail.id,detail.title,detail.content,detail.images,detail.createtime')
            ->field('user.id as user_id,user.name as user_name,user.image as user_image')
            ->page($req['page'], 3)
            ->select();


        $new_detailList = [];
        foreach ($detailList as $item) {

            $img_array = explode('##', $item['images']);
            $new_img_array = [];
            foreach ($img_array as $img) {
                if ($img) {
                    $img = Config::get('BaseURL') . 'detail-images/' .  $img;
                    array_push($new_img_array, $img);
                }
            }
            $item['images'] = $new_img_array;

            $item['channel_image'] =  Config::get('BaseURL') . 'channel-images/' . $item['channel_image'];

            $item['user_image'] =   $item['user_image'] ? Config::get('BaseURL') . 'user-images/' . $item['user_image'] : '';


            $item['num'] = Db::table('comment')
                ->where('detail_id', $item['id'])
                ->count();
            array_push($new_detailList, $item);
        }

        $res = $new_detailList;

        return json($res);
    }

    public function getHistory(Request $request)
    {
        $history_str = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('history');

        $history_array = strlen($history_str) > 0  ? explode('|', $history_str) : [];
        return json($history_array);
    }

    public function deteleAllHistory(Request $request)
    {
        $history_str = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->update(['history' => '']);
        return 'ok';
    }

    public function deteleOneHistory(Request $request)
    {
        $item = $request->get('value');
        $history_str = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('history');
        $history_array = explode('|', $history_str);
        $key = array_search($item, $history_array);
        array_splice($history_array, $key, 1);

        $new_history_str = implode("|", $history_array);
        Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->update(['history' => $new_history_str]);

        return 'ok';
    }

    public function insertHistory($id, $value)
    {
        $history_str = Db::table('user')
            ->where('id', $id)
            ->value('history');

        $history_array = strlen($history_str) > 0 ? explode("|", $history_str) : [];


        $key = array_search($value, $history_array);

        if ($key === 0 || $key) {
            array_splice($history_array, $key, 1);
        }




        array_unshift($history_array, $value);

        array_splice($history_array, 20);
        $new_history_str = implode("|", $history_array);


        Db::table('user')
            ->where('id', $id)
            ->update(['history' => $new_history_str]);
    }
}
