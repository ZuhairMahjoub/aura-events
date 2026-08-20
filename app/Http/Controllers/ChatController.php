<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Models\ChatRoom;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function initializeChat(Request $request)
    {
        // 1. إعادة الشرط الصارم: يجب أن يكون الـ ID موجوداً في جدول providers (فريلانسر أو شركة)
        $validated = $request->validate([
            'receiver_id' => 'required|exists:providers,id',
        ]);

        $sender = $request->user();

        // 2. جلب الفريلانسر/الشركة من جدول providers مع المستخدم المرتبط به
        $providerProfile = \App\Models\Provider::with('user.providerProfile')
            ->findOrFail($validated['receiver_id']);

        $receiver = $providerProfile->user;

        if (!$receiver) {
            abort(404, 'No user associated with this provider.');
        }

        $sender->loadMissing('providerProfile');

        $senderId   = $sender->id;
        $receiverId = $receiver->id;

        $providerType = $providerProfile->provider_type
            ?? $sender->providerProfile?->provider_type;

        return DB::transaction(function () use ($senderId, $receiverId, $providerType) {

            $existingRoom = ChatRoom::whereHas('participants', fn($q) => $q->where('user_id', $senderId))
                ->whereHas('participants', fn($q) => $q->where('user_id', $receiverId))
                ->first();

            if ($existingRoom) {
                return response()->json([
                    'status'        => 'success',
                    'chat_id'       => $existingRoom->firebase_chat_id,
                    'provider_type' => $providerType,
                ]);
            }

            $firebaseChatId = (string) Str::ulid();

            $chatRoom = ChatRoom::create([
                'firebase_chat_id' => $firebaseChatId,
            ]);

            $chatRoom->participants()->attach([$senderId, $receiverId]);

            $this->chatService->createChatRoom($firebaseChatId, [$senderId, $receiverId]);

            return response()->json([
                'status'        => 'success',
                'chat_id'       => $firebaseChatId,
                'provider_type' => $providerType,
            ]);
        });
    }

    public function getMessages(Request $request, string $firebaseChatId)
    {
        $validated = $request->validate([
            'limit'  => 'sometimes|integer|min:1|max:100',
            'cursor' => 'sometimes|string', // آخر message id من الصفحة السابقة
        ]);

        $userId = $request->user()->id;

        // ✅ التحقق الحاسم: المستخدم لازم يكون participant، وإلا 403
        $chatRoom = ChatRoom::where('firebase_chat_id', $firebaseChatId)
            ->whereHas('participants', fn($q) => $q->where('user_id', $userId))
            ->first();

        if (!$chatRoom) {
            return response()->json([
                'status'  => 'error',
                'message' => 'غير مصرح بالوصول لهذه المحادثة.',
            ], 403);
        }

        $messages = $this->chatService->getMessages(
            $firebaseChatId,
            $validated['limit'] ?? 30,
            $validated['cursor'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'data'   => [
                'messages'    => $messages,
                'next_cursor' => count($messages) > 0 ? end($messages)['id'] : null,
            ],
        ]);
    }

    // 💡 أبقينا على هذه الدالة لأنها ضرورية لكي تظهر أسماء المستخدمين والفريلانسرز في واجهة الرياكت
    public function getChatUsersInfo(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'string'
        ]);

        // جلب الأسماء فقط للأشخاص المطلوبين لتخفيف الضغط على الداتابيز
        $users = \App\Models\User::whereIn('id', $request->ids)
            ->get(['id', 'first_name', 'last_name']);

        $data = $users->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name
            ];
        });

        return response()->json($data);
    }
}