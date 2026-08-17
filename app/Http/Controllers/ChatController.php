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

        $sender   = $request->user();
        $receiver = \App\Models\User::findOrFail($validated['receiver_id']);

        $sender->loadMissing('providerProfile');
        $receiver->loadMissing('providerProfile');

        $senderId   = $sender->id;
        $receiverId = $receiver->id;

        $providerType = null;

        if ($receiver->isProvider()) {
            $providerType = $receiver->providerProfile?->provider_type;
        } elseif ($sender->isProvider()) {
            $providerType = $sender->providerProfile?->provider_type;
        }

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
}