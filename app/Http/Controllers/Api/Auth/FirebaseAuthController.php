<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Auth\Requests\CheckCredentialRequest;
use App\Http\Controllers\Api\Auth\Resources\AuthResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class FirebaseAuthController extends Controller
{
    /**
     * Check if the user is logged in using Firebase Auth
     *
     * @param CheckCredentialRequest $request
     * @return Response
     */
    public function checkLogin(CheckCredentialRequest $request): Response
    {
        $user = User::where('phone', $request->phone)->first();

        if(empty($user)){
            return withError('The provided credentials are incorrect.', 404);
        }

        if (! Hash::check($request->password, $user->password)) {
            return withError('The provided credentials are incorrect.', 400);
        }

        if($user->status === 0){
            return withError('Your account is inactive. Please contact to admin.', 400);
        }

        return withSuccess(message: 'Logged in successfully');
    }

    /**
     * Logs in a user using Firebase Auth id_token.
     *
     * @param CheckCredentialRequest $request
     * @return Response
     */
    public function login(CheckCredentialRequest $request): Response
    {
        $idToken = $request->id_token;

        if(empty($idToken)) {
            return withError('The provided credentials are incorrect.', 400);
        }

        $user = User::query()
                    ->leftJoin('admin_roles', 'users.admin_role_id', '=', 'admin_roles.id')
                    ->where('users.phone', $request->phone)
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

        $auth = app('firebase.auth');

        try {
            $verifiedIdToken = $auth->verifyIdToken($idToken);
        } catch (FailedToVerifyToken $e) {
            return withError($e->getMessage(), 400);
        }

        $uid = $verifiedIdToken->claims()->get('sub');
        $fUser = $auth->getUser($uid);

        if($fUser->phoneNumber !== $request->phone) {
            return withError('The provided credentials are incorrect.', 400);
        }

        auth()->login($user);

        $token = $user->createToken('auth_token', ['*'], now()->addDay(1))->plainTextToken;
        $user->access_token = $token;

        return withSuccess(new AuthResource($user), 'Logged in successfully');
    }
}
