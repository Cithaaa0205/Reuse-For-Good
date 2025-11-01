<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Get user's chats
     */
    public function index()
    {
        $userId = auth()->id();
        
        $chats = Chat::with(['item', 'user1', 'user2', 'lastMessage'])
            ->where('user1_id', $userId)
            ->orWhere('user2_id', $userId)
            ->get()
            ->map(function ($chat) use ($userId) {
                $otherUser = $chat->getOtherUser($userId);
                return [
                    'id' => $chat->id,
                    'item' => $chat->item,
                    'other_user' => $otherUser,
                    'last_message' => $chat->lastMessage,
                    'unread_count' => $chat->unreadCount($userId),
                    'created_at' => $chat->created_at,
                    'updated_at' => $chat->updated_at,
                ];
            })
            ->sortByDesc('updated_at')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $chats,
        ]);
    }

    /**
     * Get or create a chat
     */
    public function getOrCreate(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'nullable|exists:items,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = auth()->id();
        $otherUserId = $validated['user_id'];

        if ($userId === $otherUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak bisa chat dengan diri sendiri',
            ], 400);
        }

        // Order user IDs to maintain consistency
        $user1Id = min($userId, $otherUserId);
        $user2Id = max($userId, $otherUserId);

        $chat = Chat::firstOrCreate(
            [
                'item_id' => $validated['item_id'] ?? null,
                'user1_id' => $user1Id,
                'user2_id' => $user2Id,
            ]
        );

        $chat->load(['item', 'user1', 'user2']);

        return response()->json([
            'success' => true,
            'data' => $chat,
        ]);
    }

    /**
     * Get chat messages
     */
    public function messages($chatId)
    {
        $chat = Chat::findOrFail($chatId);
        
        $userId = auth()->id();

        // Check authorization
        if ($chat->user1_id !== $userId && $chat->user2_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $messages = $chat->messages()
            ->with('sender')
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark messages as read
        $chat->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request, $chatId)
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $chat = Chat::findOrFail($chatId);
        $userId = auth()->id();

        // Check authorization
        if ($chat->user1_id !== $userId && $chat->user2_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $message = Message::create([
            'chat_id' => $chatId,
            'sender_id' => $userId,
            'message' => $validated['message'],
        ]);

        // Update chat timestamp
        $chat->touch();

        $message->load('sender');

        return response()->json([
            'success' => true,
            'data' => $message,
        ], 201);
    }

    /**
     * Delete a chat
     */
    public function destroy($chatId)
    {
        $chat = Chat::findOrFail($chatId);
        $userId = auth()->id();

        // Check authorization
        if ($chat->user1_id !== $userId && $chat->user2_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $chat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chat berhasil dihapus',
        ]);
    }
}
