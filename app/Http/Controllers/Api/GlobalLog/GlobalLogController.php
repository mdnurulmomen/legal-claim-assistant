<?php

namespace App\Http\Controllers\Api\GlobalLog;

use App\Helpers\Utility;
use App\Http\Controllers\Api\GlobalLog\Resources\GlobalLogResource;
use App\Http\Controllers\Controller;
use App\Models\GlobalLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GlobalLogController extends Controller
{

    /**
     * Returns the latest log of given type for the current user.
     *
     * @param Request $request
     * @param string $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLog(Request $request, string $type): Response
    {
        if (! in_array($type, array_keys(Utility::$aliasLogTypes))) {
            return withError('Invalid Log Type!');
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

    /**
     * Returns a list of global logs of given type.
     *
     * @param Request $request
     * @param string $type
     * @return \Illuminate\Http\JsonResponse
     */
    public function logList(Request $request, string $type, int $loggableId): Response
    {
        if (! in_array($type, array_keys(Utility::$aliasLogTypes))) {
            return withError('Invalid Log Type!');
        }

        $limit = $request->input('limit', 10);

        $logs = GlobalLog::query()
                    ->where([
                        'loggable_id' => $loggableId,
                        'loggable_type' => $type
                    ])
                    ->select(
                        'id',
                        'loggable_id',
                        'loggable_type',
                        'data'
                    )
                    ->get();

        return withSuccess(GlobalLogResource::collection($logs));
    }

    /**
     * Delete a global log based on the provided ID.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function deleteLog(Request $request, int $id): Response
    {
        $log = GlobalLog::find($id);
        if (empty($log)) {
            return withError('Log not found');
        }

        $log->delete();
        return withSuccess(message: 'Log deleted successfully');
    }

}
