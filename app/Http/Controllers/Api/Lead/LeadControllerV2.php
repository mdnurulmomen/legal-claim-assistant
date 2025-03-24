<?php

namespace App\Http\Controllers\Api\Lead;

use App\Http\Controllers\Api\Lead\Requests\BulkUpdateLeadRequest;
use App\Http\Controllers\Api\Lead\Resources\LeadLogResource;
use App\Http\Controllers\Controller;
use App\Jobs\RemoveConfigLogs;
use App\Models\Buyer;
use App\Models\DispositionConfigMongo;
use App\Models\DispositionLogMongo;
use App\Services\Lead\PlatformService;
use App\Services\Lead\LeadFilterService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

        $config = DispositionConfigMongo::where('user_id', $userId)->latest('id')->select('id')->first();

        if(empty($config)) {
            return withError('No configuration found');
        }

        $log = DispositionLogMongo::where('disposition_config_id', $config->id)
                ->whereNotNull('updatable_data')
                ->select('id', 'disposition_config_id', 'updatable_data')
                ->latest('id')
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

            $savedLogs = DispositionLogMongo::query()
                            ->select('id', 'platform_data_id', 'updatable_data')
                            ->where('disposition_config_id', $log->disposition_config_id)
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
                            ->lazyById(5000);

                    foreach ($savedLogs->chunk(2000) as $leads) {

                        $formattedLeads = iterator_to_array($platformService->processLeads($leads, $request, $portalLeadIds, $portalExceptedLeadIds, $isAllShowPortal));

                        $request->merge(['leads' => $formattedLeads]);

                        $platformService->formatAndUpdateLeads($request);
                    }

            $oldConfig = DispositionConfigMongo::where('user_id', $userId)->latest('id')->select('id')->first();
            if(! empty($oldConfig)) {
                RemoveConfigLogs::dispatch($oldConfig->id);
            }

            // DB::commit();
        } catch (\Throwable $th) {
            // DB::rollBack();
            return withError('Lead Filled Fields Update Failed.' . $th->getMessage());
        }

        return withSuccess([
                'total_updated' => $savedLogs->count(),
            ],
            'Lead Filled Fields Updated Successfully!'
        );
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

    /**
     * Retrieves a list of lead logs based on the given request filters.
     *
     * @param Request $request
     * @param LeadFilterService $leadFilterService
     * @return Response
     */
    public function leadLogs(Request $request, LeadFilterService $leadFilterService): Response
    {
        try {
            $logs = $leadFilterService->getDispositionLog($request);
            return withSuccessResourceList(LeadLogResource::collection($logs));
        } catch (\Throwable $th) {
            return withError($th->getMessage());
        }
    }

    /**
     * Retrieves statistics of lead dispositions, categorizing them as duplicate or unique.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function logStatistics(Request $request): Response
    {
        $config = DispositionConfigMongo::where('user_id', auth()->id())->latest('id')->select('id')->first();
        if(empty($config)) {
            return withError('No configuration found');
        }

        $statistics = DispositionLogMongo::raw(function($collection) use ($config) {
            return $collection->aggregate([
                ['$match' => ['disposition_config_id' => $config->id]],
                ['$group' => [
                    '_id' => '$is_duplicate',
                    'count' => ['$sum' => 1]
                ]]
            ]);
        });

        $stats = collect($statistics)->pluck('count', '_id');

        return withSuccess([
            'duplicate' => $stats[1] ?? 0,
            'unique' => $stats[0] ?? 0,
        ]);
    }

    /**
     * Retrieves a list of filtered leads from the platform data table, filtered by the
     * given conditions. The conditions must contain at least one of the following
     * filterable fields: {@see \App\Services\Lead\LeadFilterService::getFilterableFields()}.
     * If the conditions do not contain any of the filterable fields, an error will be
     * returned.
     *
     * @param Request $request
     * @param LeadFilterService $leadFilterService
     * @return Response
     */
    public function filteredLeads(Request $request, LeadFilterService $leadFilterService): Response
    {
        $filterableFields = $leadFilterService->getFilterableFields();

        $matchCondition = collect($request->conditions[0] ?? [])
                            ->filter(function($value, $key) {
                                return $key !== 'custom';
                            })
                            ->only($filterableFields)
                            ->count();

        if($matchCondition < 1 && empty($request->conditions[0]['custom'])) {
            return withError('Filter only from ' . implode(', ', $filterableFields) . ' fields.');
        }

        $data = $leadFilterService->getFilterLeadsV2($request);

        if(empty($data)) {
            return withError('No leads found.');
        }

        return withSuccessResourceList(LeadLogResource::collection($data));
    }
}
