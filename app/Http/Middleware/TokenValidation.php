<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class TokenValidation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->token;
        if (empty($token)) {
            return withError('Token is required.');
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (empty($accessToken)) {
            return withError('Invalid token.');
        }

        if ($accessToken->expires_at && Carbon::parse($accessToken->expires_at)->isPast()) {
            return withError('Token has expired.', 401);
        }

        $request->merge(['user' => $accessToken->tokenable]);

        return $next($request);
    }
}
