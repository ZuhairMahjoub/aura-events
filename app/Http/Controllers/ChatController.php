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
        $validated = $request->validate([
        'receiver_id' => 'required|exists:providers,id',
    ]);

    $sender = $request->user();

    // جيب الـ provider وبعدين الـ user المرتبط فيه، مش findOrFail مباشرة على User
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
    
}