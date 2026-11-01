<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
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
    public function list(Request $request)
    {
        $vendor = Helpers::get_vendor_data();

        // toBase()->value('id') rather than first(): only the id and an existence check are
        // used here, but hydrating the UserInfo ran its HasStorage global scope — and the
        // conversations' receiver eager-load hydrates that same row again, so the storages
        // query fired twice. toBase() keeps the where clauses and drops the hydration;
        // Eloquent's own value() still hydrates internally, so it is not enough.
        $vendor_user_info_id = UserInfo::where('vendor_id',$vendor->id)->toBase()->value('id');

        if (! $vendor_user_info_id) {
            $conversations = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 8);
            $total_conversations = 0;
            $unread_conversations = 0;

            if ($request->ajax()) {
                return response()->json([
                    'html' => view('vendor-views.messages.data', compact('conversations'))->render(),
                    'has_more' => false,
                    'total' => 0,
                ]);
            }

            return view('vendor-views.messages.index', compact('conversations', 'total_conversations', 'unread_conversations'));
        }

        $conversations = Conversation::with([
            'sender' => fn ($query) => $query->whereKeyNot($vendor_user_info_id),
            'receiver' => fn ($query) => $query->whereKeyNot($vendor_user_info_id),
            'sender.user',
            'receiver.user',
            'last_message',
        ])->WhereUser($vendor_user_info_id);
        if($request->query('key')) {
            $key = explode(' ', $request->input('key'));
            $conversations = $conversations->where(function($qu)use($key){
                $qu->whereHas('sender',function($query)use($key){
                    foreach ($key as $value) {
                        $query->where('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%");
                    }
                })
                ->orWhereHas('receiver',function($query1)use($key){
                    foreach ($key as $value) {
                        $query1->where('f_name', 'like', "%{$value}%")
                        ->orWhere('l_name', 'like', "%{$value}%")
                        ->orWhere('phone', 'like', "%{$value}%");
                    }
                });
            });
        }
        if ($request->boolean('unread')) {
            $this->scopeUnread($conversations, $vendor_user_info_id);
        }

        $conversations = $conversations->orderBy('last_message_time', 'DESC')
        ->latest()
        ->paginate(8);

        $this->attachOwnSide($conversations->getCollection(), $vendor_user_info_id);

        if ($request->ajax()) {
            $view = view('vendor-views.messages.data',compact('conversations'))->render();

            return response()->json([
                'html' => $view,
                'has_more' => $conversations->hasMorePages(),
                'total' => $conversations->total(),
            ]);
        }

        $total_conversations = ($request->query('key') || $request->boolean('unread'))
            ? Conversation::WhereUser($vendor_user_info_id)->count()
            : $conversations->total();
        $unread_conversations = $this->scopeUnread(Conversation::WhereUser($vendor_user_info_id), $vendor_user_info_id)->count();

        return view('vendor-views.messages.index', compact('conversations', 'total_conversations', 'unread_conversations'));
    }

    private function attachOwnSide($conversations, $vendorUserInfoId): void
    {
        $self = null;

        foreach ($conversations as $conversation) {
            foreach (['sender', 'receiver'] as $side) {
                if ((int) $conversation->{$side.'_id'} !== (int) $vendorUserInfoId) {
                    continue;
                }

                $self ??= UserInfo::withoutEagerLoads()->find($vendorUserInfoId);

                $conversation->setRelation($side, $self);
            }
        }
    }

    private function scopeUnread($query, $vendor_user_info_id)
    {
        $vendor_user_info_id = (int) $vendor_user_info_id;

        return $query->where('unread_message_count', '>', 0)
            ->whereHas('last_message', function ($builder) use ($vendor_user_info_id) {
                $builder->whereColumn('messages.sender_id', DB::raw(
                    "CASE WHEN conversations.sender_id = {$vendor_user_info_id} THEN conversations.receiver_id ELSE conversations.sender_id END"
                ));
            });
    }

    public function view($conversation_id,$user_id)
    {
        $vendor = Helpers::get_vendor_data();
        $vendorUserInfoId = UserInfo::where('vendor_id', $vendor?->id)->toBase()->value('id');

        if (! $vendorUserInfoId) {
            abort(404);
        }

        $conversation = Conversation::with(['last_message', 'receiver', 'sender'])
            ->WhereUser($vendorUserInfoId)
            ->find($conversation_id);

        if (! $conversation) {
            abort(404);
        }

        $lastmessage = $conversation->last_message;
        if($lastmessage && $lastmessage->sender_id == $user_id ) {
            $conversation->unread_message_count = 0;
            $conversation->save();
        }
        Message::where(['conversation_id' => $conversation->id])->where('sender_id',$user_id)->update(['is_seen' => 1]);
        $convs = Message::with('order')->where(['conversation_id' => $conversation_id])->get();
        // Re-fetching the same row was redundant: $conversation is that row and the only
        // change since — unread_message_count — was written through this instance. Only
        // ->receiver and ->sender are read below, and both resolve identically.
        $receiver = $conversation->receiver;
        $sender = $conversation->sender;
        $vendor = UserInfo::find($vendorUserInfoId);

        if($receiver?->user_id){
            $user = User::withStorage()->find($receiver->user_id);
            $user_type = 'user';
        }elseif($receiver?->deliveryman_id){
            $user = DeliveryMan::withStorage()->find($receiver->deliveryman_id);
            $user_type = 'delivery_man';
        }elseif($sender?->user_id){
            $user = User::withStorage()->find($sender->user_id);
            $user_type = 'user';
        }else{
            $user = $sender?->deliveryman_id ? DeliveryMan::withStorage()->find($sender->deliveryman_id) : null;
            $user_type = 'delivery_man';
        }

        if (! $user || ! $vendor) {
            abort(404);
        }

        return response()->json([
            'view' => view('vendor-views.messages.partials._conversations', compact('convs', 'user', 'receiver','sender','user_type','vendor'))->render()
        ]);
    }

    public function store(Request $request, $user_id, $user_type)
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

        $vendor = Helpers::get_vendor_data();
        $sender = UserInfo::where('vendor_id',$vendor->id)->first();
        if(!$sender){
            $sender = new UserInfo();
            $sender->vendor_id = $vendor->id;
            $sender->f_name = $vendor->stores[0]->name;
            $sender->l_name = '';
            $sender->phone = $vendor->phone;
            $sender->email = $vendor->email;
            $sender->image = $vendor->stores[0]->logo;
            $sender->save();
        }

        if($user_type == 'user'){

            $user = User::find($user_id);
            $fcm_token=$user->cm_firebase_token;
            $receiver = UserInfo::where('user_id', $user->id)->first();
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

        }elseif($user_type == 'delivery_man'){
            // The store's own delivery men, or one who has carried this store's orders -- not any
            // delivery man on the platform by id, whose profile the reply view then rendered.
            $dm = DeliveryMan::where(function ($query) {
                $query->where('store_id', Helpers::get_store_id())
                    ->orWhereHas('orders', fn ($orders) => $orders->where('store_id', Helpers::get_store_id()));
            })->findOrFail($user_id);
            $fcm_token=$dm->fcm_token;
            $receiver = UserInfo::where('deliveryman_id', $dm->id)->first();
            if(!$receiver){
                $receiver = new UserInfo();
                $receiver->deliveryman_id = $dm->id;
                $receiver->f_name = $dm->f_name;
                $receiver->l_name = $dm->l_name;
                $receiver->phone = $dm->phone;
                $receiver->email = $dm->email;
                $receiver->image = $dm->image;
                $receiver->save();
            }
            $user = DeliveryMan::find($user_id);
        }



        $conversation = Conversation::WhereConversation($sender->id,$receiver->id)->first();


        if(!$conversation){
            $conversation = new Conversation;
            $conversation->sender_id = $sender->id;
            $conversation->sender_type = 'vendor';
            $conversation->receiver_id = $receiver->id;
            $conversation->receiver_type = $user_type;
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
                $data = NotificationMessages::chatMessage(translate('messages.Message from')." ".$sender->f_name, $message, ['conversation_id' => $conversation->id, 'sender_type' => 'vendor']);
                SendNotification::sendToDevice($fcm_token, $data);
            }

        } catch (\Exception $e) {
            Log::error('vendor.conversation_controller.store_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }
        $vendor = UserInfo::where('vendor_id',$vendor->id)->first();
        $convs = Message::with('order')->where(['conversation_id' => $conversation->id])->get();
        return response()->json([
            'view' => view('vendor-views.messages.partials._conversations', compact('convs', 'user', 'receiver','user_type','vendor'))->render()
        ]);
    }
}
