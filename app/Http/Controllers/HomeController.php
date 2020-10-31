<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\PusherOps;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    use PusherOps;
    public function index()
    {
        $posts = Post::with('comments.user','user','likes')
            ->orderByDesc('created_at')->get();
        return view('home',compact('posts'));
    }

    public function createPost(Request $request)
    {
        $validator = \Validator::make($request->all(),[
            'text'=>'sometimes',
            'image'=>'sometimes|image'
        ]);

        if ($validator->fails()) return response()->json([
            'status'=>400,
            'error'=>$validator->errors()->first()
        ]);

        if (!$request->image && !$request->text)return response()->json([
            'status'=>400,
            'error'=>'Must Select Image Or Write Text First'
        ]);

        $inputs = $request->all();
        if ($request->image){
            $inputs['image'] = \Storage::putFile('public/posts',$request->image);
            $inputs['image'] = str_replace('public/','',$inputs['image']);
        }
        $post = auth()->user()->posts()->create($inputs);
        $html = view('post',compact('post'))->render();
        $this->push('post',['data'=>$html,'user_id'=>auth()->id()]);
        return response()->json([
            'status'=>200
        ]);
    }

    public function postComment(Request $request)
    {
        $data = $request->only('text');
        $data['user_id'] = auth()->id();
        $comment = Post::find($request->post_id)->comments()->create($data);
        $pusherData['html'] = view('comment',compact('comment'))->render();
        $pusherData['user_id'] = $comment->post->user_id;
        $pusherData['post_id'] = $comment->post_id;
        $this->push('comment',$pusherData);
        return response()->json(['status'=>true]);
    }

    public function pressLike(Request $request)
    {
        $post = Post::find($request->post_id);
        if($post->likes->contains('user_id',auth()->id())){

            $post->likes()->where('user_id',auth()->id())->delete();
        }else{
            $post->likes()->create(['user_id'=>auth()->id()]);
        }
        $count = $post->likes()->count();
        $pusherData['post_id'] = $post->id;
        $pusherData['count'] = $count;
        $this->push('likes',$pusherData);
        return response()->json(['likes'=>$count]);
    }

    public function profile($id = null)
    {
        $posts = auth()->user()->posts()->with('likes','comments.user')
            ->orderByDesc('created_at')->get();
        if ($id)
        $user = User::find($id);
        else $user = auth()->user();
        return view('profile',compact('posts','user'));
    }
}
