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
        $user = User::query()
                    ->leftJoin('admin_roles', 'users.admin_role_id', '=', 'admin_roles.id')
                    ->where('users.email', $request->email)
                    ->whereNotNull('admin_roles.admin_role')
                    ->select('users.*', 'admin_roles.admin_role as admin_role')
                    ->first();

        if(empty($user)){
            return withError('The provided credentials are incorrect.', 404);
        }

        if (! Hash::check($request->password, $user->password)) {
            return withError('The provided credentials are incorrect.', 400);
        }

        if($user->status === 0){
            return withError('Your account is inactive. Please contact to admin.', 400);
        }

        if($user->has_two_fa || $user->admin_role === 'super_admin') {
            return withSuccess(new AuthResource($user), 'Two factor authentication is required.');
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
        return withSuccess(message: 'Logged out successfully.');
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
