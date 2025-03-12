<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\Auth\Requests\LoginRequest;
use App\Http\Controllers\Api\Auth\Resources\AuthResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AppAuthenticatorController extends Controller
{
    /**
     * Generate QR code for two factor authentication.
     *
     * @param  LoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate2FA(LoginRequest $request)
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

        $data = [
            'qr_code' => "",
            'secret' => "",
            'is_new_qr' => false
        ];

        if (empty($user->google2fa_secret)) {

            $google2fa = app('pragmarx.google2fa');

            $user->google2fa_secret = $google2fa->generateSecretKey();
            $user->save();

            $qrCodeUrl = $google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $user->google2fa_secret
            );

            $data['qr_code'] = $this->generateQrCode($qrCodeUrl);
            $data['secret'] = $user->google2fa_secret;
            $data['is_new_qr'] = true;
        }

        return withSuccess($data);
    }

    /**
     * Generate a QR Code image data URI using Bacon QR Code.
     *
     * @param string $qrCodeUrl
     * @return string
     */
    private function generateQrCode(string $qrCodeUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($qrCodeUrl);

        // Convert SVG to Data URI
        $dataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);

        return $dataUri;
    }

    /**
     * Verify 2FA for a user.
     *
     * @param LoginRequest $request
     * @return Response
     */
    public function verify2FA(LoginRequest $request)
    {

        $validator = Validator::make($request->all(), [
                            'otp' => 'required|string',
                        ]);

        if ($validator->fails()) {
            return withError($validator->errors()->first());
        }

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

        $google2fa = app('pragmarx.google2fa');

        if ($google2fa->verifyKey($user->google2fa_secret, $request->otp)) {
            auth()->login($user);

            $token = $user->createToken('auth_token', ['*'], now()->addDay(1))->plainTextToken;
            $user->access_token = $token;

            return withSuccess(new AuthResource($user), 'Logged in successfully');
        }

        return withError('Invalid Verification Code!');
    }

    /**
     * Generate a QR Code image data URI using Bacon QR Code. If the user has never had a QR code, generate a new one.
     *
     * @param Request $request
     * @return Response
     */
    public function generateQr(Request $request)
    {
        $user = User::find(auth()->id());
        if(empty($user)) {
            return withError('User not found', 404);
        }

        $data = [
            'qr_code' => "",
            'secret' => "",
            'is_new_qr' => false
        ];

        $google2fa = app('pragmarx.google2fa');

        if(empty($user->google2fa_secret) && empty($request->is_generate_new)) {
            return withSuccess($data);
        }

        if(! empty($user->google2fa_secret) && empty($request->is_generate_new)) {
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $user->google2fa_secret
            );

            $data['qr_code'] = $this->generateQrCode($qrCodeUrl);
            $data['secret'] = $user->google2fa_secret;

            return withSuccess($data);
        }

        $user->google2fa_secret = $google2fa->generateSecretKey();
        $user->save();

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $user->google2fa_secret
        );

        $data['qr_code'] = $this->generateQrCode($qrCodeUrl);
        $data['secret'] = $user->google2fa_secret;

        $data['is_new_qr'] = true;

        return withSuccess($data);
    }
}
