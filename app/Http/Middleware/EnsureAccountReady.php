<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAccountReady
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('sanctum');
        if ($user && ! $user->is_active) return response()->json(['message' => 'Account deactivated.'], 403);
        if ($user?->must_change_password && ! $request->is('api/user', 'api/user/password', 'api/logout')) {
            return response()->json(['message' => 'Change your temporary password before continuing.', 'code' => 'password_change_required'], 403);
        }
        return $next($request);
    }
}
