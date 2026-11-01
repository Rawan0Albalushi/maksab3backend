<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use App\CentralLogics\Helpers;
use App\Models\Conversation;
use App\Models\UserInfo;
use App\Models\Message;
use App\Models\User;
use App\Models\DeliveryMan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    private const USER_INFO_RELATIONS = ['user', 'vendor.stores', 'delivery_man'];

    public function list(Request $request)
    {
        $conversations = Conversation::with([
            'sender' => fn ($query) => $query->with(self::USER_INFO_RELATIONS),
            'receiver' => fn ($query) => $query->with(self::USER_INFO_RELATIONS),
            'last_message',
        ])->WhereUserType('admin');
        if($request->query('key')) {
            $key = explode(' ', $request->input('key'));
            $conversations = $conversations->where(function($qu)use($key){
                    $qu->whereHas('sender',function($query)use($key){
                    foreach ($key as $value) {
                        $query->where('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%");
                    }
                })
                ->orWhereHas('receiver',function($query1)use($key){
                    foreach ($key as $value) {
                        $query1->where('f_name', 'like', "%{$value}%")->orWhere('l_name', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%");
                    }
                });
            });
        }
        if ($request->boolean('unread')) {
            $this->scopeUnread($conversations);
        }

        $conversations = $conversations->orderBy('last_message_time', 'DESC')
        ->paginate(8);

        if ($request->ajax()) {
            $view = view('admin-views.messages.data',compact('conversations'))->render();

            // has_more lets the rail stop paginating instead of walking past
            // the last page forever.
            return response()->json([
                'html' => $view,
                'has_more' => $conversations->hasMorePages(),
                'total' => $conversations->total(),
            ]);
        }

        $total_conversations = Conversation::whereUserType('admin')->count();
        $unread_conversations = $this->scopeUnread(Conversation::whereUserType('admin'))->count();

        return view('admin-views.messages.index', compact('conversations', 'total_conversations', 'unread_conversations'));
    }

    /**
     * Conversations whose newest message came from the other side and is still
     * unread — the same rule the badge in the rail uses, so the "Unread" filter
     * and the counters never disagree with what a row shows.
     */
    private function scopeUnread($query)
    {
        return $query->where('unread_message_count', '>', 0)
            ->whereHas('last_message', function ($builder) {
                $builder->whereColumn('messages.sender_id', DB::raw(
                    "CASE WHEN conversations.sender_type = 'admin' THEN conversations.receiver_id ELSE conversations.sender_id END"
                ));
            });
    }

    public function view($conversation_id,$user_id)
    {
        $conversation = Conversation::with(['last_message', 'receiver', 'sender'])->find($conversation_id);

        if (! $conversation) {
            Toastr::warning(translate('No data found'));

            return back();
        }

        $lastmessage = $conversation->last_message;
        if($lastmessage && $lastmessage->sender_id == $user_id ) {
            $conversation->unread_message_count = 0;
            $conversation->save();
        }
        Message::where(['conversation_id' => $conversation->id])->where('sender_id',$user_id)->update(['is_seen' => 1]);
        $convs = Message::with('order')->where(['conversation_id' => $conversation_id])->get();
        $receiver = UserInfo::with(self::USER_INFO_RELATIONS)->find($user_id);

        if (! $receiver) {
            return response()->json(['errors' => [['code' => 'user', 'message' => translate('No data found')]]], 404);
        }

        $user = $receiver;
        return response()->json([
            'view' => view('admin-views.messages.partials._conversations', compact('convs', 'user', 'receiver'))->render()
        ]);
    }

    public function store(Request $request, $user_id)
    {

        if ($request->has('images')) {
            $image_name=[];
            foreach($request->images as $key=>$img)
            {
                $name = Helpers::upload('conversation/', 'png', $img);
                array_push($image_name,['img'=>$name, 'storage'=> Helpers::getDisk()]);
            }
        } else {
            $image_name = null;
            $validator = Validator::make($request->all(), [
                'reply' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => Helpers::error_processor($validator)]);
            }
        }

        $admin = auth('admin')->user();
        $sender = UserInfo::where('admin_id',$admin->id)->first();
        if(!$sender){
            $sender = new UserInfo();
            $sender->admin_id = $admin->id;
            $sender->f_name = $admin->f_name;
            $sender->l_name = $admin->l_name;
            $sender->phone = $admin->phone;
            $sender->email = $admin->email;
            $sender->image = $admin->image;
            $sender->save();
        }

        if($request->user_type == 'deliveryman'){
            $user = DeliveryMan::withoutGlobalScope('delivery_only')->find($user_id);
            $fcm_token=$user->fcm_token;
            $receiver = UserInfo::where('deliveryman_id', $user->id)->first();
        }else{
            $user = User::find($user_id);
            $fcm_token=$user->cm_firebase_token;
            $receiver = UserInfo::where('user_id', $user->id)->first();
        }
        if(!$receiver){
            $receiver = new UserInfo();
            $receiver->user_id = $user->id;
            $receiver->f_name = $user->f_name;
            $receiver->l_name = $user->l_name;
            $receiver->phone = $user->phone;
            $receiver->email = $user->email;
            $receiver->image = $user->image;
            $receiver->save();
        }

        $conversation = Conversation::whereConversation($receiver->id,0)->first();


        if(!$conversation){
            $conversation = new Conversation;
            $conversation->sender_id = 0;
            $conversation->sender_type = 'admin';
            $conversation->receiver_id = $receiver->id;
            $conversation->receiver_type = $receiver->user_type == 'customer' ? 'user' : 'delivery_man';
            $conversation->last_message_time = Carbon::now()->toDateTimeString();
            $conversation->save();

            $conversation= Conversation::find($conversation->id);
        }

        $message = new Message();
        $message->conversation_id = $conversation->id;
        $message->sender_id = $sender->id;
        $message->message = $request->reply;
        if($image_name && count($image_name)>0){
            $message->file = json_encode($image_name, JSON_UNESCAPED_SLASHES);
        }
        try {
            if($message->save())
            $conversation->unread_message_count = $conversation->unread_message_count? $conversation->unread_message_count+1:1;
            $conversation->last_message_id=$message->id;
            $conversation->last_message_time = Carbon::now()->toDateTimeString();
            $conversation->save();
            {
                $data = NotificationMessages::chatMessage(translate('messages.Message from admin'), $message, ['conversation_id' => $conversation->id, 'sender_type' => 'admin']);
                SendNotification::sendToDevice($fcm_token, $data);
            }

        } catch (\Exception $e) {
            Log::error('admin.conversation_controller.store_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }

        $convs = Message::with('order')->where(['conversation_id' => $conversation->id])->get();
        $receiver->loadMissing(self::USER_INFO_RELATIONS);
        $user = $receiver;
        return response()->json([
            'view' => view('admin-views.messages.partials._conversations', compact('convs', 'user', 'receiver'))->render()
        ]);
    }
}
