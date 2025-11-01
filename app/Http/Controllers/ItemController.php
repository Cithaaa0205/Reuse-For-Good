<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    /**
     * Get all items with filters
     */
    public function index(Request $request)
    {
        $query = Item::with(['user', 'category', 'images'])
            ->available();

        // Search
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        // Filter by distance
        if ($request->has('distance') && $request->distance !== 'all') {
            $user = auth()->user();
            if ($user && $user->latitude && $user->longitude) {
                $maxDistance = match($request->distance) {
                    '1km' => 1,
                    '5km' => 5,
                    '10km' => 10,
                    '25km' => 25,
                    default => null,
                };

                if ($maxDistance) {
                    $query->withinDistance($user->latitude, $user->longitude, $maxDistance);
                }
            }
        }

        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        
        if ($sortBy === 'distance' && auth()->user() && auth()->user()->latitude) {
            // Already sorted by distance in withinDistance scope
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $items = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    /**
     * Get item detail
     */
    public function show($id)
    {
        $item = Item::with(['user', 'category', 'images', 'requests'])
            ->findOrFail($id);

        // Increment view count
        $item->increment('view_count');

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    /**
     * Upload new item
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'condition' => 'required|in:Seperti Baru,Baik,Cukup Baik',
            'location' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'city' => 'nullable|string',
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120', // 5MB
        ]);

        $item = Item::create([
            'user_id' => auth()->id(),
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'condition' => $validated['condition'],
            'location' => $validated['location'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'city' => $validated['city'] ?? null,
        ]);

        // Upload images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $path = $image->store('items', 'public');
                
                $item->images()->create([
                    'image_path' => $path,
                    'is_primary' => $index === 0,
                    'order' => $index,
                ]);
            }
        }

        $item->load(['images', 'category']);

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil diupload',
            'data' => $item,
        ], 201);
    }

    /**
     * Update item
     */
    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        // Check authorization
        if ($item->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category_id' => 'sometimes|exists:categories,id',
            'condition' => 'sometimes|in:Seperti Baru,Baik,Cukup Baik',
            'location' => 'sometimes|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'city' => 'nullable|string',
            'status' => 'sometimes|in:available,requested,donated',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil diupdate',
            'data' => $item->load(['images', 'category']),
        ]);
    }

    /**
     * Delete item
     */
    public function destroy($id)
    {
        $item = Item::findOrFail($id);

        // Check authorization
        if ($item->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Delete images from storage
        foreach ($item->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item berhasil dihapus',
        ]);
    }

    /**
     * Get user's uploaded items
     */
    public function myItems(Request $request)
    {
        $items = Item::with(['category', 'images', 'requests'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 12));

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
