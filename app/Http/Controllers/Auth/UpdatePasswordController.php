<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class UpdatePasswordController extends Controller
{
    /**
     * Handle an incoming password update request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::min(12)],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $user = $request->user();
            $user->update(['password' => Hash::make($request->password), 'must_change_password' => false]);
            $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();
        });

        return response()->json(['message' => 'Password updated successfully']);
    }
}
