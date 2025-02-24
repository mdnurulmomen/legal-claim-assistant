<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Api\Lead\Requests\BulkUpdateLeadRequest;
use App\Http\Controllers\Api\Lead\Resources\LeadLogResource;
use App\Http\Controllers\Controller;
use App\Jobs\RemoveConfigLogs;
use App\Models\Buyer;
use App\Models\DispositionConfig;
use App\Models\DispositionLog;
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
    public function bulkUpdateLeads(BulkUpdateLeadRequest $request, PlatformService $platformService)
    {
        set_time_limit(0);
        ini_set('memory_limit', -1);

        $userId = auth()->id();

        $log = DispositionLog::query()
                ->leftJoin('disposition_configs as dc', 'dc.id', '=', 'disposition_logs.disposition_config_id')
                ->where('dc.user_id', $userId)
                ->whereNotNull('disposition_logs.updatable_data')
                ->select('disposition_logs.id', 'dc.id as config_id', 'disposition_logs.updatable_data')
                ->latest('dc.id')
                ->first();

        if(empty($log)) {
            return withError('No log found');
        }

        $keys = array_keys($log->updatable_data);

        if(! empty(array_diff($request->filled_headers, $keys))) {
            return withError('Invalid headers');
        }

        $headers = collect($request->filled_headers)
                    ->map(fn($item) => "updatable_data->{$item} as {$item}")
                    ->push('platform_data_id as id')
                    ->values()
                    ->all();

        $leadIds = isset($request->selection_type['lead_ids']) ? json_decode($request->selection_type['lead_ids'], true) : [];
        $exceptedLeadIds = isset($request->selection_type['excepted_ids']) ? json_decode($request->selection_type['excepted_ids'], true) : [];

        $portalLeadIds = isset($request->portal_selection['checked_ids']) ? json_decode($request->portal_selection['checked_ids'], true) : [];
        $portalExceptedLeadIds = isset($request->portal_selection['excepted_ids']) ? json_decode($request->portal_selection['excepted_ids'], true) : [];
        $isAllShowPortal = isset($request->portal_selection['is_checked_all']) ? (bool) $request->portal_selection['is_checked_all'] : false;

        try {
            // DB::beginTransaction();

            DispositionLog::query()
                ->where('disposition_config_id', $log->config_id)
                ->select($headers)
                ->when(! empty($leadIds), function ($query) use ($leadIds) {
                    return $query->whereIn('platform_data_id', $leadIds);
                })
                ->when(! empty($exceptedLeadIds), function ($query) use ($exceptedLeadIds) {
                    return $query->whereNotIn('platform_data_id', $exceptedLeadIds);
                })
                ->when(! empty($request->show_type === 'duplicate'), function($query) {
                    return $query->where('is_duplicate', 1);
                })
                ->when(! empty($request->show_type === 'unique'), function($query) {
                    return $query->where('is_duplicate', 0);
                })
                ->chunk(1000, function ($leads) use ($request, $platformService, $portalLeadIds, $portalExceptedLeadIds, $isAllShowPortal) {

                    $formattedLeads = iterator_to_array($platformService->processLeads($leads, $request, $portalLeadIds, $portalExceptedLeadIds, $isAllShowPortal));

                    $request->merge(['leads' => $formattedLeads]);
                    $platformService->formatAndUpdateLeads($request);
                });

            $oldConfig = DispositionConfig::where('user_id', $userId)->latest('id')->select('id')->first();
            if(! empty($oldConfig)) {
                RemoveConfigLogs::dispatch($oldConfig->id);
            }

            // DB::commit();
        } catch (\Throwable $th) {
            // DB::rollBack();
            return withError('Lead Filled Fields Update Failed.' . $th->getMessage());
        }

        return withSuccess(message: 'Lead Filled Fields Updated Successfully!');
    }

    /**
     * Retrieve a list of all buyers.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function buyerList(Request $request)
    {
        $limit = $request->per_page ?? 50;

        $buyers = Buyer::query()
                        ->select('id as value', 'name as actual_name')
                        ->when(! empty($request->is_custom), function ($query) {
                            return $query->selectRaw("CONCAT(name , ': Lead ID') as label");
                        }, function ($query) {
                            return $query->selectRaw('name as label');
                        })
                        ->when(! empty($request->search), function ($query) use ($request) {
                            return $query->where('name', 'like', "%{$request->search}%");
                        })
                        ->when(! empty($request->is_paginated), function($query) use ($limit) {
                            return $query->paginate($limit);
                        }, function ($query) {
                            return $query->get();
                        });

        return withSuccess($buyers);
    }

    public function leadLogs(Request $request, PlatformService $platformService): Response
    {
        set_time_limit(0);
        ini_set('memory_limit', -1);

        try {
            $logs = $platformService->getDispositionLog($request);
            return withSuccessResourceList(LeadLogResource::collection($logs));
        } catch (\Throwable $th) {
            return withError($th->getMessage());
        }
    }

    public function logStatistics(Request $request)
    {
        $config = DispositionConfig::where('user_id', auth()->id())->latest('id')->select('id')->first();
        if(empty($config)) {
            return withError('No configuration found');
        }

        $statistics = DispositionLog::where('disposition_config_id', $config->id)
                        ->selectRaw('is_duplicate, COUNT(*) as count')
                        ->groupBy('is_duplicate')
                        ->pluck('count', 'is_duplicate');

        return withSuccess([
            'duplicate' => $statistics[1] ?? 0,
            'unique' => $statistics[0] ?? 0,
        ]);
    }
}
