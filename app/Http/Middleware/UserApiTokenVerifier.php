<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserApiTokenVerifier
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiToken = $request->header('X-API-TOKEN');

        $user = User::where('api_token', $apiToken)->exists();
        if (!$user) {
            return withError('Invalid Token provided!');
        }

        return $next($request);
    }
}
