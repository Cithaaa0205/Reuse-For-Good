<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show()
    {
        $user = auth()->user()->load(['items', 'favorites']);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile berhasil diupdate',
            'data' => $user,
        ]);
    }

    /**
     * Switch user role
     */
    public function switchRole(Request $request)
    {
        $validated = $request->validate([
            'role' => 'required|in:donor,recipient',
        ]);

        $user = auth()->user();
        $user->update(['current_role' => $validated['role']]);

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diubah',
            'data' => $user,
        ]);
    }

    /**
     * Update user location
     */
    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'city' => 'nullable|string',
        ]);

        $user = auth()->user();
        $user->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'city' => $validated['city'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lokasi berhasil diupdate',
            'data' => $user,
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini salah',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah',
        ]);
    }
}
