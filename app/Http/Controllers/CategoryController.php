<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get all categories
     */
    public function index()
    {
        $categories = Category::withCount(['items' => function ($query) {
            $query->where('status', 'available');
        }])->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get category by slug
     */
    public function show($slug)
    {
        $category = Category::where('slug', $slug)
            ->withCount(['items' => function ($query) {
                $query->where('status', 'available');
            }])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }
}
