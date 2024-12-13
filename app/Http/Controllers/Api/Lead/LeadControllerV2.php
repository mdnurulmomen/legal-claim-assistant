<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Api\Lead\Requests\BulkUpdateLeadRequest;
use App\Http\Controllers\Controller;
use App\Services\Lead\PlatformService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LeadControllerV2 extends Controller
{

    /**
     * Bulk update filled fields of leads in the database.
     *
     * @param \App\Http\Controllers\Api\Lead\Requests\BulkUpdateLeadRequest $request
     * @param \App\Services\Lead\PlatformService $platformService
     *
     * @return \Illuminate\Http\Response
     */
    public function bulkUpdateLeads(BulkUpdateLeadRequest $request, PlatformService $platformService): Response
    {
        try {
            DB::beginTransaction();
            $platformService->formatAndUpdateLeads($request);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return withError('Lead Filled Fields Update Failed.' . $th->getMessage());
        }

        return withSuccess(message: 'Lead Filled Fields Updated Successfully!');
    }
}
