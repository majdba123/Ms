<?php

namespace App\Http\Controllers;

use App\Models\chat;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\chat1;

class ChatController extends Controller
{
    public function sendMessage(Request $request ,$receiver_id)
    {

        $message = chat::create([
            'sender_id' => auth()->id(),
            'receiver_id' => $receiver_id,
            'message' => $request->message,
        ]);

        broadcast(new chat1($message))->toOthers();

        return response()->json(['status' => 'Message sent successfully!']);
    }



    public function getMessagesFromSender($sender_id)
    {
        $receiver_id = auth()->id(); // المستخدم الموثق هو المستقبل
        $messages = chat::where('sender_id', $sender_id)
            ->where('receiver_id', $receiver_id)
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['message', 'sender_id','created_at','is_read']); // تقسيم النتائج إلى صفحات (10 رسائل لكل صفحة)

        return response()->json($messages);
    }



    public function getUnreadMessages()
    {
        $receiver_id = auth()->id(); // المستخدم الموثق هو المستقبل

        // جلب الرسائل غير المقروءة
        $unreadMessages = chat::where('receiver_id', $receiver_id)
            ->where('is_read', false) // شرط الرسائل غير المقروءة
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['message', 'sender_id','created_at','is_read']); // تقسيم النتائج إلى صفحات (10 رسائل لكل صفحة)


        // التحقق إذا لم توجد رسائل غير مقروءة
        if ($unreadMessages->isEmpty()) {
            return response()->json(['message' => 'No unread messages found.']);
        }

        // إعادة الرسائل غير المقروءة مع معرّف المرسل
        return response()->json($unreadMessages);
    }



    public function markMessagesAsRead($sender_id)
    {
        $receiver_id = auth()->id(); // المستخدم الموثق هو المستقبل

        // تحديث حالة الرسائل غير المقروءة
        $updatedRows = chat::where('sender_id', $sender_id)
            ->where('receiver_id', $receiver_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // استجابة
        if ($updatedRows > 0) {
            return response()->json(['message' => 'Messages marked as read.']);
        }

        return response()->json(['message' => 'No unread messages found to mark as read.']);
    }



    public function getInteractedUsers()
    {
        $userId = auth()->id();

        // الحصول على المستخدمين الذين تفاعل معهم المستخدم الحالي
        $interactedUsers = chat::where(function ($query) use ($userId) {
            $query->where('sender_id', $userId)
                ->orWhere('receiver_id', $userId);
        })
        ->selectRaw('
            CASE
                WHEN sender_id = ? THEN receiver_id
                ELSE sender_id
            END as other_user_id,
            MAX(created_at) as last_message_at
        ', [$userId])
        ->groupBy('other_user_id')
        ->orderBy('last_message_at', 'desc')
        ->get();

        $userIds = $interactedUsers->pluck('other_user_id')->toArray();

        // الحصول على معلومات المستخدمين وعدد الرسائل غير المقروءة لكل منهم
        $users = User::whereIn('id', $userIds)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        $result = $interactedUsers->map(function ($item) use ($users, $userId) {
            $user = $users->get($item->other_user_id);

            // حساب عدد الرسائل غير المقروءة لهذا المستخدم
            $unreadCount = chat::where('sender_id', $item->other_user_id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'last_message_at' => $item->last_message_at,
                'unread_count' => $unreadCount,
            ];
        });

        return response()->json($result);
    }


    public function getMessagesByReceiver($receiver_id)
    {
        $sender_id = auth()->id();
        $messages = chat::where(function ($query) use ($sender_id, $receiver_id) {
            $query->where('sender_id', $sender_id)
                  ->where('receiver_id', $receiver_id);
        })->orWhere(function ($query) use ($sender_id, $receiver_id) {
            $query->where('sender_id', $receiver_id)
                  ->where('receiver_id', $sender_id);
        })
        ->orderBy('created_at', 'asc')
        ->paginate(10, ['id', 'message', 'sender_id', 'receiver_id', 'is_read', 'created_at']);

        if ($messages->isEmpty()) {
            return response()->json(['message' => 'No messages found with this receiver.']);
        }

        return response()->json($messages);
    }

      public function getConversation($user_id)
    {
        $authUserId = auth()->id();
        $conversation = chat::where(function ($query) use ($authUserId, $user_id) {
                $query->where('sender_id', $authUserId)
                    ->where('receiver_id', $user_id);
            })
            ->orWhere(function ($query) use ($authUserId, $user_id) {
                $query->where('sender_id', $user_id)
                    ->where('receiver_id', $authUserId);
            })
            ->orderBy('created_at', 'asc')
            ->get(['id', 'message', 'sender_id', 'receiver_id', 'is_read', 'created_at']);

        // حساب عدد الرسائل غير المقروءة
        $unreadCount = chat::where('sender_id', $user_id)
            ->where('receiver_id', $authUserId)
            ->where('is_read', false)
            ->count();

        if ($conversation->isEmpty()) {
            return response()->json([
                'message' => 'No conversation found with this user.',
                'unread_count' => 0
            ]);
        }

        return response()->json([
            'messages' => $conversation,
            'unread_count' => $unreadCount
        ]);
    }
}
