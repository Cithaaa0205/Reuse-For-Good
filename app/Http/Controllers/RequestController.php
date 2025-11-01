<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request as ItemRequest;
use App\Models\Item;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    /**
     * Get user's requests (as requester)
     */
    public function index()
    {
        $requests = ItemRequest::with(['item.user', 'item.category', 'item.images'])
            ->where('requester_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Get received requests (as donor)
     */
    public function received()
    {
        $requests = ItemRequest::with(['item.category', 'item.images', 'requester'])
            ->whereHas('item', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Create a new request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'message' => 'nullable|string',
        ]);

        $item = Item::findOrFail($validated['item_id']);

        // Check if item is available
        if ($item->status !== 'available') {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak tersedia',
            ], 400);
        }

        // Check if user already requested this item
        $existingRequest = ItemRequest::where('item_id', $validated['item_id'])
            ->where('requester_id', auth()->id())
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah mengajukan permintaan untuk item ini',
            ], 400);
        }

        // Check if user is not the owner
        if ($item->user_id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak bisa mengajukan permintaan untuk item sendiri',
            ], 400);
        }

        $itemRequest = ItemRequest::create([
            'item_id' => $validated['item_id'],
            'requester_id' => auth()->id(),
            'message' => $validated['message'] ?? null,
        ]);

        $itemRequest->load(['item', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Permintaan berhasil dikirim',
            'data' => $itemRequest,
        ], 201);
    }

    /**
     * Update request status
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:accepted,rejected,completed',
        ]);

        $itemRequest = ItemRequest::with('item')->findOrFail($id);

        // Check authorization (only item owner can update)
        if ($itemRequest->item->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        switch ($validated['status']) {
            case 'accepted':
                $itemRequest->accept();
                $message = 'Permintaan diterima';
                break;
            case 'rejected':
                $itemRequest->reject();
                $message = 'Permintaan ditolak';
                break;
            case 'completed':
                if ($itemRequest->status !== 'accepted') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hanya permintaan yang sudah diterima yang bisa diselesaikan',
                    ], 400);
                }
                $itemRequest->complete();
                $message = 'Donasi selesai';
                break;
        }

        $itemRequest->load(['item', 'requester']);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $itemRequest,
        ]);
    }

    /**
     * Cancel request (by requester)
     */
    public function cancel($id)
    {
        $itemRequest = ItemRequest::findOrFail($id);

        // Check authorization
        if ($itemRequest->requester_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Can only cancel pending requests
        if ($itemRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya permintaan pending yang bisa dibatalkan',
            ], 400);
        }

        $itemRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permintaan dibatalkan',
        ]);
    }
}
