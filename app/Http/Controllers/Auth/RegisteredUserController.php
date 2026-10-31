<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): Response|JsonResponse
    {
        try {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'photo' => \App\Services\ProfilePhoto::RULE,
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
                'password' => ['required', 'confirmed', Rules\Password::min(12)],
                'role' => 'sometimes|in:user',
                'phone' => 'required|string|min:10|max:30',
                'birthday' => 'required|date|before:today',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->string('password')),
                'role' => 'user',
                'phone' => $request->phone,
                'birthday' => $request->birthday,
            ]);

            event(new Registered($user));

            if (! $request->is('api/*')) {
                Auth::login($user);
                $request->session()->regenerate();
                return response()->noContent();
            }

            $token = $user->createToken('auth-token', ['*'], now()->addHours(8))->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'An error occurred during registration.',
            ], 500);
        }
    }
}
