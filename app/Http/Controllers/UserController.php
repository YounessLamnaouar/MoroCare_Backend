<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
                'photo' => \App\Services\ProfilePhoto::RULE,
            'phone' => 'sometimes|nullable|string|min:10|max:30',
            'birthday' => 'sometimes|nullable|date|before:today',
        ]);
        unset($data['photo']);
        \App\Services\ProfilePhoto::save($request, $user);
        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data) {
            $user->update($data);
        });
        return response()->json($user->fresh());
    }

    /**
     * Delete the authenticated user's account.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->tokens()->delete();

        $user->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }
}
