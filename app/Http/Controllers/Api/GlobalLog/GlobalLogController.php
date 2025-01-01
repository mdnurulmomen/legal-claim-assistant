<?php

namespace App\Http\Controllers\Api\GlobalLog;

use App\Helpers\Utility;
use App\Http\Controllers\Controller;
use App\Models\GlobalLog;
use Illuminate\Http\Request;

class GlobalLogController extends Controller
{

    /**
     * Returns the latest log of given type for the current user.
     *
     * @param Request $request
     * @param string $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLog(Request $request, string $type)
    {
        if (! in_array($type, array_keys(Utility::$aliasLogTypes))) {
            abort(400, 'Invalid log type.');
        }

        $log = GlobalLog::query()
                ->where([
                    'loggable_id' => $request->user()->id,
                    'loggable_type' => $type
                ])
                ->latest('id')
                ->first();

        if (empty($log)) {
            return withSuccess(status: 204);
        }

        return withSuccess($log);
    }

}
