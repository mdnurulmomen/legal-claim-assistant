<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        if(empty($accessToken)){
            return withError('Invalid token.');
        }

        $user = $accessToken->tokenable;

        if (empty($user)) {
            return withError(message: 'User not found.');
        }

        $request->merge(['authenticated_user' => $user]);

        return $next($request);
    }
}
