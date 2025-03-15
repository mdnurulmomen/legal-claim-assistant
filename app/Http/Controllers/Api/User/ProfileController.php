<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use stdClass;

class ProfileController extends Controller
{

    public function basicInfo(Request $request): Response
    {
        $basicInfo = [
            'my_info' => new stdClass()
        ];

        if(! auth('sanctum')->check()) {
            return withSuccess($basicInfo);
        }

        $auth = auth('sanctum')->user();
        $adminRole = $auth->adminRole;

        $role = $adminRole ? $adminRole->admin_role : '';

        $basicInfo['my_info'] = [
            'logo' => $auth->logo ? url(Storage::url($auth->logo)) : '',
            'has_two_fa' => $role === 'super_admin' ? true : $auth->has_two_fa,
            'admin_role' => $role
        ];

        return withSuccess($basicInfo);
    }

}
