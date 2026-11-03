<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiPrivacyHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        // Immutable UUID image URLs contain only intentionally public profile/product images.
        if (! $request->is('api/product-images/*', 'api/profile-photos/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
