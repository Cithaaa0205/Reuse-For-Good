<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * Get nearby items
     */
    public function nearby(Request $request)
    {
        $user = auth()->user();

        if (!$user->latitude || !$user->longitude) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi user tidak tersedia',
            ], 400);
        }

        $items = Item::with(['user', 'category', 'images'])
            ->available()
            ->withinDistance($user->latitude, $user->longitude, 25) // 25km radius
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Get new items
     */
    public function new(Request $request)
    {
        $items = Item::with(['user', 'category', 'images'])
            ->available()
            ->orderBy('created_at', 'desc')
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Get personalized recommendations
     */
    public function forYou(Request $request)
    {
        $user = auth()->user();

        // Get categories from favorited items
        $favoritedCategories = $user->favorites()
            ->pluck('category_id')
            ->unique()
            ->toArray();

        $query = Item::with(['user', 'category', 'images'])
            ->available();

        // If user has favorited items, prioritize those categories
        if (!empty($favoritedCategories)) {
            $query->whereIn('category_id', $favoritedCategories);
        }

        // Sort by distance if location available
        if ($user->latitude && $user->longitude) {
            $query->withinDistance($user->latitude, $user->longitude, 25);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $items = $query->limit($request->get('limit', 10))->get();

        // If not enough items, fill with popular items
        if ($items->count() < 10) {
            $additionalItems = Item::with(['user', 'category', 'images'])
                ->available()
                ->whereNotIn('id', $items->pluck('id'))
                ->orderBy('view_count', 'desc')
                ->limit(10 - $items->count())
                ->get();

            $items = $items->merge($additionalItems);
        }

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Get popular items
     */
    public function popular(Request $request)
    {
        $items = Item::with(['user', 'category', 'images'])
            ->available()
            ->orderBy('view_count', 'desc')
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
