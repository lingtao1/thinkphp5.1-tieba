<?php

namespace app\index\controller;

use think\facade\Config;
use think\Controller;
use think\Db;
use think\Request;
// use think\facade\Request;
use think\File;

class Comment extends Controller
{
    public function add(Request $request)
    {
        $model = new Token;
        if ($model->checkToken($request->header('Authorization')) !== 'done') {
            return $model->checkToken($request->header('Authorization'));
        }

        $data = $request->post();
        $userToken = $request->header('Authorization');
        $data['user_id'] = Db::table('user')
            ->where('token', $userToken)
            ->value('id');

        $id = Db::table('comment')
            ->insertGetId($data);

        $res = Db::table('comment')
            ->where('id', $id)
            ->find();
        $user = Db::table('user')
            ->where('id', $res['user_id'])
            ->field('name,image')
            ->find();
        $res['image'] = Config::get('BaseURL') . 'user-images/' . $user['image'];
        $res['name'] = $user['name'];
        $res['like_num'] = 0;
        $res['reply'] = [];
        return json($res);
    }

    public function comment(Request $request)
    {
        $req = $request->get();
        $userToken = $request->header('Authorization');
        $userID = Db::table('user')
            ->where('token', $userToken)
            ->value('id');



        $query = ['detail_id' => $req['id']];

        if ($req['isAll'] == 'false') {
            $PublisherID = Db::table('comment')
                ->join('detail', 'detail.id = comment.detail_id')
                ->where(['detail_id' => $req['id']])
                ->value('detail.user_id');
            $query['user_id'] = $PublisherID;
        }

        $data = Db::table('comment')
            ->where($query)
            ->join('user', 'user.id = comment.user_id')
            ->field('user.name,user.image,comment.id,comment.user_id,comment.content,comment.createtime,comment.like')
            ->order('comment.createtime', $req['order'])
            ->page($req['page'], 5)
            ->select();

        $newdata = [];
        foreach ($data as $key => $item) {
            $like_array = $item['like'] ? explode("|", $item['like']) : [];
            $item['like'] = in_array($userID, $like_array);
            $item['like_num'] = count($like_array);

            $item['reply'] = Db::table('reply')
                ->where('reply.comment_id', $item['id'])
                ->field('reply.reply_id,reply_type,reply.content,reply.from_uid,to_uid')
                ->limit(0, 2)
                ->select();

            $item['reply_num'] = Db::table('reply')
                ->where('reply.comment_id', $item['id'])
                ->count();

            $item['image'] = Config::get('BaseURL') . 'user-images/' . $item['image'];

            $new_reply = [];
            foreach ($item['reply'] as $key => $reply) {
                $reply['from_uid'];
                $reply['to_uid'];
                $reply['from_uname'] = Db::table('user')
                    ->where('id', $reply['from_uid'])
                    ->value('name');

                $reply['to_uname'] = Db::table('user')
                    ->where('id', $reply['to_uid'])
                    ->value('name');

                array_push($new_reply, $reply);
            }
            $item['reply'] = $new_reply;

            array_push($newdata, $item);
        }
        return json($newdata);
    }

    public function like(Request $request)
    {
        $model = new Token;
        if ($model->checkToken($request->header('Authorization')) !== 'done') {
            return $model->checkToken($request->header('Authorization'));
        }

        $req = $request->post();
        $userToken = $request->header('Authorization');
        $userID = Db::table('user')
            ->where('token', $userToken)
            ->value('id');


        $like_str = Db::table('comment')
            ->where('id', $req['parent_id'])
            ->value('like');

        strlen($like_str) > 0 ? $like_array = explode("|", $like_str) : $like_array = [];
        $exist = in_array($userID, $like_array);

        if ($exist) {
            $like_array = array_diff($like_array, array($userID));
        } else {
            array_push($like_array, $userID);
        }



        $new_like_str = join("|", $like_array);
        Db::table('comment')
            ->where('id', $req['parent_id'])
            ->update(['like' => $new_like_str]);
        return json('ok');
    }

    public function reply(Request $request)
    {
        $model = new Token;
        if ($model->checkToken($request->header('Authorization')) !== 'done') {
            return $model->checkToken($request->header('Authorization'));
        }

        $req = $request->post();
        $req['comment_id'];
        $req['reply_id'];
        $req['reply_type'];
        $req['content'];
        $req['to_uid'];
        $req['from_uid'] = Db::table('user')
            ->where('token', $request->header('Authorization'))
            ->value('id');

        $id = Db::table('reply')
            ->insertGetId($req);

        $res = Db::table('reply')
            ->where('id', $id)
            ->find();

        $from_user_info = Db::table('user')
            ->where('id', $res['from_uid'])
            ->field('name,image')
            ->find();

        $res['to_uname'] = Db::table('user')
            ->where('id', $res['to_uid'])
            ->value('name');

        $res['image'] = Config::get('BaseURL') . 'user-images/' . $from_user_info['image'];
        $res['name'] =  $from_user_info['name'];
        return json($res);
    }

    public function getOneComment(Request $request)
    {
        $req = $request->get();
        $userToken = $request->header('Authorization');

        $userID = Db::table('user')
            ->where('token', $userToken)
            ->value('id');

        $res = Db::table('comment')
            ->join('user', 'user.id = comment.user_id')
            ->where('comment.id', $req['id'])
            ->field('user.id as user_id,user.name as user_name,user.image')
            ->field('comment.id,comment.detail_id,comment.content,comment.like,comment.createtime')
            ->find();

        $res['reply_num'] = Db::table('reply')
            ->where('reply.comment_id', $req['id'])
            ->count();

        $res['like'] = $res['like'] ? explode('|', $res['like']) : [];
        $res['like_num'] = count($res['like']);
        $res['like'] = in_array($userID, $res['like']);

        $res['image'] = $res['image'] ? Config::get('BaseURL') . 'user-images/' . $res['image'] : '';
        return json($res);
    }

    public function getReplyList(Request $request)
    {
        $req = $request->get();
        $req['id'];
        $req['page'];

        $res = Db::table('reply')
            ->where('comment_id', $req['id'])
            ->join('user', 'user.id = reply.from_uid')
            ->field('user.name,user.image')
            ->field('reply.id,reply.comment_id,reply.reply_id,reply.reply_type,reply.content,reply.createtime,reply.from_uid,reply.to_uid')
            ->page($req['page'], 5)
            ->order('reply.createtime', 'asc')
            ->select();



        $new_reply = [];
        foreach ($res as  $item) {
            $item['to_uid'];
            $item['image']  = Config::get('BaseURL') . 'user-images/' . $item['image'];
            $item['to_uname'] = Db::table('user')
                ->where('id', $item['to_uid'])
                ->value('name');

            array_push($new_reply, $item);
        }
        $res = $new_reply;

        return json($res);
    }
}
