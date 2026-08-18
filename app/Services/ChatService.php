<?php
namespace App\Services;

class ChatService
{
    protected FirestoreService $firestoreService;

    public function __construct(FirestoreService $firestoreService)
    {
        $this->firestoreService = $firestoreService;
    }

    public function createChatRoom(string $chatId, array $participantIds)
{
    $this->firestoreService->setDocument('chats', $chatId, [
        'participants' => $participantIds,
        'created_at'   => now()->toIso8601String(),
    ]);
}
public function getMessages(string $chatId, int $limit = 30, ?string $cursor = null): array
{
    return $this->firestoreService->getMessages($chatId, $limit, $cursor);
}

}