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
    
}