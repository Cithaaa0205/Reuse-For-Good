<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Get user's favorite items
     */
    public function index()
    {
        $favorites = auth()->user()
            ->favorites()
            ->with(['user', 'category', 'images'])
            ->available()
            ->orderBy('favorites.created_at', 'desc')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data' => $favorites,
        ]);
    }

    /**
     * Add item to favorites
     */
    public function store($itemId)
    {
        $item = Item::findOrFail($itemId);
        
        $user = auth()->user();

        // Check if already favorited
        if ($user->favorites()->where('item_id', $itemId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Item sudah ada di favorites',
            ], 400);
        }

        $user->favorites()->attach($itemId);

        return response()->json([
            'success' => true,
            'message' => 'Item ditambahkan ke favorites',
        ]);
    }

    /**
     * Remove item from favorites
     */
    public function destroy($itemId)
    {
        $user = auth()->user();

        if (!$user->favorites()->where('item_id', $itemId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ada di favorites',
            ], 404);
        }

        $user->favorites()->detach($itemId);

        return response()->json([
            'success' => true,
            'message' => 'Item dihapus dari favorites',
        ]);
    }

    /**
     * Check if item is favorited
     */
    public function check($itemId)
    {
        $isFavorited = auth()->user()
            ->favorites()
            ->where('item_id', $itemId)
            ->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'is_favorited' => $isFavorited,
            ],
        ]);
    }
}
