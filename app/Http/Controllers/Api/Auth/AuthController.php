<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Auth\Requests\LoginRequest;
use App\Http\Controllers\Api\Auth\Resources\AuthResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Logs in a user.
     *
     * @param LoginRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function login(LoginRequest $request): Response
    {
        $user = User::where('email', $request->email)->where('role', 'admin')->first();
        if(empty($user)){
            return withError('The provided credentials are incorrect.', 404);
        }

        if (! Hash::check($request->password, $user->password)) {
            return withError('The provided credentials are incorrect.', 400);
        }

        auth()->login($user);

        $token = $user->createToken('auth_token', ['*'], now()->addWeeks(1))->plainTextToken;
        $user->access_token = $token;

        return withSuccess(new AuthResource($user), 'Logged in successfully');
    }

    /**
     * Logout the user by deleting their current access token.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();
        return withSuccess(['message' => 'Logged out successfully']);
    }

    /**
     * Retrieves the authenticated user's information.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function verifyToken(Request $request): Response
    {
        $user = $request->user();
        $user->access_token = $request->api_token;
        return withSuccess(new AuthResource($request->user()));
    }
}
