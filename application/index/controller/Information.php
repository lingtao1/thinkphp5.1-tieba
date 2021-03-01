<?php

namespace app\index\controller;

use think\facade\Config;
use think\Controller;
use think\Db;
use think\Request;


class  Information
{

    public function getInformationList(Request $request)
    {
        $userToken = $request->header('Authorization');
        $req = $request->get();
        $user = Db::table('user')
            ->where('token', $userToken)
            ->field('id,name')
            ->find();

        $reply = Db::table('reply')
            ->where('reply.to_uid', $user['id'])
            ->where('reply.from_uid', '<>', $user['id'])
            ->join('user', 'user.id = reply.from_uid')
            ->join('comment', 'comment.id=reply.comment_id')
            ->join('detail', 'detail.id=comment.detail_id')
            ->join('channel', 'channel.id = detail.parent_id')
            ->field('user.name as from_uname,user.image as from_image')
            ->field('reply.id,reply.comment_id,reply.content,reply.createtime,reply.isRead,reply_id,reply_type,reply.from_uid')
            ->field('comment.id as comment_id,comment.content as comment_content')
            ->field('detail.id as detail_id,detail.images as detail_images')
            ->field('channel.id as channel_id,channel.name as channel_name')

            ->page($req['page'], 5)
            ->order('reply.createtime', 'desc')
            ->select();
        $res = [];
        foreach ($reply as $item) {
            $item['comment_id'];
            // $item['from_uid'] = $user['id'];
            $item['to_uname'] = $user['name'];
            $item['from_image'] = Config::get('BaseURL') . 'user-images/' . $item['from_image'];
            $item['isRead'] =  $item['isRead'] == 0 ? false : true;
            $item['detail_images'] = $item['detail_images'] ? explode("##", $item['detail_images']) : [];
            if ($item['detail_images']) {
                $item['detail_images'] = array_slice($item['detail_images'], 0, 1)[0];
                $item['detail_images'] = Config::get('BaseURL') . 'detail-images/' . $item['detail_images'];
            }
            array_push($res, $item);
        }
        return json($res);
    }
}
