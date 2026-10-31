<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response|JsonResponse
    {
        try {
            $request->authenticate();

            if (! $request->is('api/*')) {
                $request->session()->regenerate();
                return response()->noContent();
            }

            $user = Auth::guard('web')->user();
            $token = $user->createToken('auth-token', ['*'], now()->addHours(8))->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Email or Password incorrect !',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'An error occurred during authentication.',
            ], 500);
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        if ($request->is('api/*')) {
            $request->user()->currentAccessToken()?->delete();
            return response()->noContent();
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
