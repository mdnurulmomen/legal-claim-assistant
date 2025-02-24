<?php

namespace App\Services\Lead;

use App\Http\Controllers\Api\Lead\Resources\LeadLogResource;
use App\Jobs\GlobalPostBackTriggerJob;
use App\Jobs\RemoveConfigLogs;
use App\Models\DispositionConfig;
use App\Models\DispositionLog;
use App\Models\LeadLog;
use App\Models\LeadReport;
use App\Models\PlatformData;
use App\Traits\FormatterTrait;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformService
{
    use FormatterTrait;
    /**
     * Retrieves a list of filtered leads from the platform data table.
     *
     * The request should contain the following parameters:
     *
     * @param Request $request
     */
    public function getFilteredLeads(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', -1);

        $leadIds = [];
        $fillableKeys = (new PlatformData())->getFillable();
        $selectableKeys = $this->formatSelectableKeys($request, $fillableKeys);
        $conditions = $this->formatConditions($request, $fillableKeys, $leadIds);

        $isAmountField = in_array('revenue', $request->mapped_headers) || in_array('affiliate_payout', $request->mapped_headers);
        $userId = auth()->id();

        $csvData = $request->csv_data ?? [];
        $actualHeaders = $request->actual_headers ?? [];

        $leadKeys = [];
        $requestedConditions = collect($request->input('conditions', []));
        $firstCondition = $requestedConditions[0];
        $custom = $firstCondition['custom'] ?? [];
        unset($firstCondition['custom']);

        $conditionKeys = array_keys($firstCondition);

        $newKeyValueConditions = [];

        foreach($conditionKeys as $conditionKey) {
            if(! in_array($conditionKey, $fillableKeys)) continue;

            $newKeyValueConditions[$conditionKey] = $requestedConditions->pluck($conditionKey)
                                                        ->unique()->values()->all();
        }

        info('new key Conditions', $newKeyValueConditions);

        foreach($custom as $item) {
            $conditionKeys[] = $item['lead_key'];
            $leadKeys[$item['lead_key']] = 'custom_lead_id';
        }

        $mappedHeads = collect($request->mapped_headers ?? [])
                        ->reject(function($item) use ($conditionKeys) {
                            return in_array($item, $conditionKeys);
                        })
                        ->values()
                        ->all();

        $actualHeadersObj = collect($actualHeaders)->pluck('value', 'model_value')->toArray();


        $csvLeads = iterator_to_array($this->groupCsvData($csvData, $conditionKeys, $actualHeadersObj, $mappedHeads));

        $groupedKeyCounts = [];
        $now = now();

        // $oldConfig = DispositionConfig::where('user_id', $userId)->latest('id')->select('id')->first();
        // if(! empty($oldConfig)) {
        //     RemoveConfigLogs::dispatch($oldConfig->id);
        // }

        // abort(400, 'Disposition config not found !');

        try {

            // DB::beginTransaction();

            $config = DispositionConfig::create([
                'user_id' => $userId,
                'uid' => str()->uuid()
            ]);

            $leads = PlatformData::query()
                    ->select($selectableKeys)
                    // ->whereIn('lead_status', ['Pending', 'Returned', 'Disqualified', 'Sent Agreement', 'Agreement Signed', 'Retained'])
                    ->when(! empty($leadIds), function ($query) use ($leadIds) {
                        return $query->leftJoin('platform_data_items as pdi', 'platform_datas.id', '=', 'pdi.platform_data_id')
                                    ->selectRaw("
                                        pdi.value as custom_lead_id
                                    ");
                    })
                    ->when($isAmountField, function ($query) {
                        return $query->addSelect([
                            'revenue' => LeadReport::selectRaw('IFNULL(SUM(lead_revenue), 0)')
                                            ->whereColumn('lead_id', 'platform_datas.id')
                                            ->where('is_retainer', 0)
                                            ->limit(1),

                            'affiliate_payout' => LeadReport::selectRaw('IFNULL(SUM(lead_revenue) - SUM(affiliate_payout), 0)')
                                                    ->whereColumn('lead_id', 'platform_datas.id')
                                                    ->where('is_retainer', 0)
                                                    ->limit(1)
                        ]);
                    })
                    ->when(! empty($newKeyValueConditions), function($query) use ($newKeyValueConditions) {
                        foreach($newKeyValueConditions as $key => $values) {
                            $query->whereIn($key, $values);
                        }
                    })
                    // ->when(! empty($conditions), function ($query) use ($conditions) {
                    //     foreach ($conditions as $index => $condition) {

                    //         if(empty($condition)) continue;

                    //         $method = $this->getConditionMethod($index);

                    //         $query->$method(function ($query2) use ($condition) {
                    //             foreach ($condition as $childIndex => $item) {

                    //                 if($item['type'] === 'primary') {
                    //                     $query2->where($item['key'], $item['operator'], $item['value']);
                    //                 }

                    //                 if($item['type'] === 'json') {
                    //                     $query2->whereRaw(
                    //                         sprintf(
                    //                             'LOWER(JSON_UNQUOTE(JSON_EXTRACT(platform_datas.datas, "$.%s"))) %s ?',
                    //                             $item['key'],
                    //                             $item['operator']
                    //                         ),
                    //                         [$item['value']]
                    //                     );
                    //                 }

                    //                 if($item['type'] === 'custom') {
                    //                     $query2->where(function($query3) use($item) {

                    //                         $query3->where('platform_datas.buyer_id', $item['buyer_id'])
                    //                            ->when(! empty($item['lead_id']), function($query4) use($item) {

                    //                                 $leadId = $item['lead_id'];

                    //                                 $query4->where('pdi.value', $leadId);
                    //                            });
                    //                     });
                    //                 }
                    //             }
                    //         });

                    //     };
                    // })
                    ->orderBy('platform_datas.id')
                    ->lazyById(2000);

                    info($leads->count());

                    // ->chunk(1000, function($leads) use ($config, $conditionKeys, $csvLeads, $leadKeys, &$groupedKeyCounts, $now) {

                        // foreach ($leads as $lead) {
                        //     $groupedKey = strtolower(implode('_', array_map(fn($key) => $lead[$leadKeys[$key] ?? $key] ?? '', $conditionKeys)));

                        //     $groupedKeyCounts[$groupedKey] = ($groupedKeyCounts[$groupedKey] ?? 0) + 1;
                        // }

                        // $processLeads = function () use ($leads, $config, $csvLeads, $leadKeys, $conditionKeys, $groupedKeyCounts, $now) {
                        //     foreach ($leads as $lead) {
                        //         $groupedKey = strtolower(implode('_', array_map(fn($key) => $lead[$leadKeys[$key] ?? $key] ?? '', $conditionKeys)));

                        //         yield [
                        //             'disposition_config_id' => $config->id,
                        //             'platform_data_id' => $lead['id'],
                        //             'lead_status' => $lead['lead_status'],
                        //             'data' => json_encode($lead),
                        //             'is_duplicate' => $groupedKeyCounts[$groupedKey] > 1 ? 1 : 0, // Correctly detects duplicates
                        //             'updatable_data' => json_encode($csvLeads[$groupedKey] ?? []),
                        //             'created_at' => $now,
                        //             'updated_at' => $now
                        //         ];
                        //     }
                        // };

                        // $processLeads = iterator_to_array($processLeads());

                        // info('count ' . count($processLeads));

                    //     DispositionLog::insert(iterator_to_array($processLeads()));

                    // });

            // DB::commit();
        } catch (\Throwable $th) {
            // DB::rollBack();
            throw $th;
        }

        return $this->getDispositionLog(new Request());
    }

    function groupCsvData(iterable $csvData, array $conditionKeys, array $actualHeadersObj, array $mappedHeads): Generator
    {
        $grouped = [];

        foreach ($csvData as $item) {
            $groupedKey = "";
            foreach ($conditionKeys as $index => $key) {
                $actualKey = $actualHeadersObj[$key] ?? null;
                if (empty($actualKey)) continue;
                $groupedKey .= ($index > 0 ? "_" : "") . strtolower($item[$actualKey]);
            }

            $grouped[$groupedKey][] = $item;
        }

        foreach ($grouped as $groupKey => $leads) {
            $leadData = $leads[0] ?? null;
            if (empty($leadData)) continue;

            $updatableLeads = [];

            foreach ($mappedHeads as $mappedKey) {
                $actualKey = $actualHeadersObj[$mappedKey] ?? null;
                if (empty($actualKey)) continue;

                $updatableLeads[$mappedKey] = $leadData[$actualKey] ?? '';
            }

            yield $groupKey => $updatableLeads;
        }
    }

    /**
     * Retrieves a paginated list of disposition logs for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function getDispositionLog(Request $request)
    {
        $config = DispositionConfig::where('user_id', auth()->id())
                        ->latest('id')
                        ->select('id')
                        ->first();

        if(empty($config)) {
            abort(400, 'Disposition config not found !');
        }

        $limit = $request->input('limit', 20);
        $searchText = strtolower($request->input('search_txt'));
        $leadStatus = $request->input('lead_status');
        $leadIds = ! empty($request->lead_ids) ? json_decode($request->lead_ids, true) : [];
        $exceptedIds = ! empty($request->excepted_ids) ? json_decode($request->excepted_ids, true) : [];

        return DispositionLog::query()
                ->where('disposition_config_id', $config->id)
                ->when(! empty($searchText) && empty($request->is_updatable_only), function ($query) use ($searchText) {
                    return $query->whereRaw('LOWER(data) like ?', ["%{$searchText}%"]);
                })
                ->when(! empty($searchText) && ! empty($request->is_updatable_only), function ($query) use ($searchText) {
                    return $query->whereRaw('LOWER(updatable_data) like ?', ["%{$searchText}%"]);
                })
                ->when(! empty($leadStatus), function ($query) use ($leadStatus) {
                    return $query->where('lead_status', $leadStatus);
                })
                ->when(! empty($leadIds), function($query) use ($leadIds) {
                    return $query->whereIn('platform_data_id', $leadIds);
                })
                ->when(! empty($exceptedIds), function($query) use ($exceptedIds) {
                    return $query->whereNotIn('platform_data_id', $exceptedIds);
                })
                ->when(! empty($request->show_type === 'duplicate'), function($query) {
                    return $query->where('is_duplicate', 1);
                })
                ->when(! empty($request->show_type === 'unique'), function($query) {
                    return $query->where('is_duplicate', 0);
                })
                ->paginate($limit);
    }

    /**
     * Generate a SQL case statement for generating a custom_lead_id based on values in the $leadIds array.
     *
     * @param array $leadIds
     * @return array
     */
    public function generateLeadIdCaseStatement(array $leadIds): array
    {
        $sql = sprintf(
            "CASE %s END as custom_lead_id",
            collect($leadIds)
                ->map(function ($value) {
                    $escapedValue = str_replace("'", "''", $value);
                    $returnValue = ! is_string($value) && is_numeric($value) ? $escapedValue : "'" . $escapedValue . "'";

                    return sprintf(
                        "WHEN datas REGEXP ? THEN %s",
                        $returnValue
                    );
                })
                ->implode(' ')
        );

        $bindings = collect($leadIds)
            ->map(function ($value) {
                $escapedValue = str_replace("'", "''", $value);
                return ! is_string($value) && is_numeric($value)
                    ? ':[[:space:]]*' . $escapedValue . '[,}]'
                    : ':[[:space:]]*"' . $escapedValue . '"[,}]';
            })
            ->all();

        return [
            'sql' => $sql,
            'bindings' => $bindings
        ];
    }

    /**
     * Formats the selectable keys for querying the platform data.
     *
     * @param Request $request
     * @param array $fillableKeys
     * @return array
     */
    public function formatSelectableKeys(Request $request, array $fillableKeys): array
    {
        $excludedHeaders = ['revenue', 'affiliate_payout'];

        $columns = collect($request->mapped_headers)
                    ->reject(fn($header) => in_array($header, $excludedHeaders, true)) // More readable & efficient
                    ->map(fn($header) => in_array($header, $fillableKeys, true) ? "platform_datas.$header" : "platform_datas.datas->{$header} as {$header}")
                    ->push('platform_datas.id', 'platform_datas.lead_status', 'platform_datas.buyer_id')
                    ->toArray();

        return $columns;
    }

    /**
     * Formats the conditions for querying the platform data.
     *
     * @param Request $request
     * @param array $fillableKeys
     * @return array
     */
    public function formatConditions(Request $request, $fillableKeys, &$leadIds): array
    {
        $formattedLeads = [];
        $rules = $request->rules;

        foreach($request->conditions as $condition)
        {
            $childConditions = [];

            foreach($condition as $key => $value)
            {
                if(empty($value)) continue;

                if(is_array($value)) {
                    foreach($value as $custom) {

                        $leadId = $custom['lead_id'] ?? null;
                        if(empty($leadId)) continue;

                        $childConditions[] = [
                            'type' => 'custom',
                            'buyer_id' => $custom['buyer_id'],
                            'lead_id' => $leadId
                        ];

                        if($leadId) $leadIds[] = $leadId;
                    }
                    continue;
                }

                $rule = $rules[$key] ?? 'equals';

                if(in_array($key, $fillableKeys)){

                    $formattedCondition = $this->convertConditionToSql([
                        'column' => $key,
                        'value' => $value,
                        'rule' => $rule
                    ]);

                    $formattedCondition['type'] = 'primary';

                    $childConditions[] = $formattedCondition;

                    continue;
                }

                $formattedCondition = $this->convertConditionToSql([
                    'column' => preg_replace('/[^a-zA-Z0-9_.]/', '', $key),
                    'value' => strtolower($value),
                    'rule' => $rule
                ]);

                $formattedCondition['type'] = 'json';
                $childConditions[] = $formattedCondition;
            }

            $formattedLeads[] = $childConditions;
        }

        return $formattedLeads;
    }

    /**
     * Converts a condition array to a SQL condition array.
     *
     * @param array $conditions The condition array to be converted. It should have the following keys:
     *                         - 'rule': The rule of the condition.
     *                         - 'column': The column of the condition.
     *                         - 'value': The value of the condition.
     * @return array
     */
    public function convertConditionToSql(array $conditions): array
    {
        $condition = match($conditions['rule']){
            'contains' => $this->makeCondition($conditions['column'], 'like', '%' . $conditions['value'] . '%'),
            'does_not_contain' => $this->makeCondition($conditions['column'], 'not like', '%' . $conditions['value'] . '%'),
            'begins_with' => $this->makeCondition($conditions['column'], 'like', $conditions['value'] . '%'),
            'does_not_begin_with' => $this->makeCondition($conditions['column'], 'not like', $conditions['value'] . '%'),
            'greater_than' => $this->makeCondition($conditions['column'], '>', $conditions['value']),
            'less_than' => $this->makeCondition($conditions['column'], '<', $conditions['value']),
            'equals' => $this->makeCondition($conditions['column'], '=', $conditions['value']),
            'not_equals' => $this->makeCondition($conditions['column'], '!=', $conditions['value']),
            'equals_any' => $this->makeCondition($conditions['column'], '=', $conditions['value']),
            'exists' => $this->makeCondition($conditions['column'], 'exists'),
            'does_not_exist' => $this->makeCondition($conditions['column'], 'does_not_exist'),
            default => []
        };
        return $condition;
    }

    /**
     * Creates a condition array with the given column, operator, and optional value.
     *
     * @param string $column
     * @param string $operator
     * @param string|int|null $value
     * @return array
     */
    public function makeCondition(string $column, string $operator, string | int | null | array $value = null)
    {
        return [
            'key' => $column,
            'operator' => $operator,
            'value' => $value
        ];
    }

    /**
     * Generate a generator of lead data with updated fields
     *
     * @param Collection $filledData
     * @param array $leads
     * @param array $fillable
     *
     * @return Generator
     */
    private function generateLeadData(Collection $filledData, array $leads, array $fillable, array &$leadAdditionalData): Generator
    {
        $excludedFields = ['id', 'revenue', 'affiliate_payout', 'retained_date'];

        $groupFilledData = $filledData->groupBy('id')->toArray();
        $formattedFillable = collect($fillable)->reject(fn($item) => in_array($item, ['retained_date']))->all();

        foreach ($leads as &$lead) {

            $newLead = $groupFilledData[$lead['id']][0] ?? null;
            if(empty($newLead)) continue;

            $data = $lead['datas'] ?? [];

            foreach($newLead as $key => $value) {

                if(in_array($key, $formattedFillable)) {
                    $lead[$key] = $value;
                    continue;
                }

                if(in_array($key, $excludedFields)) continue;

                if(is_array($data)) {
                    $data[$key] = $value;
                }
            }

            $lead['datas'] = json_encode($data);

            yield $lead;

            if($isRetainer = $newLead['is_show_portal'] ?? null) {
                $lead['is_retainer'] = $isRetainer;
            }

            if($payout = $newLead['affiliate_payout'] ?? 0) {
                $lead['payout'] = $payout;
            }

            if($retainedDate = $newLead['retained_date'] ?? null) {
                $lead['new_retained_date'] = $retainedDate;
            }

            $leadAdditionalData[] = $lead;
        }

    }

    /**
     * Returns the condition method based on the given index.
     *
     * @param int $index
     * @return string
     */
    public function getConditionMethod(int $index, bool $isJson = false): string
    {
        if($isJson) {
            return $index == 0 ? 'whereRaw' : 'orWhereRaw';
        }
        return $index == 0 ? 'where' : 'orWhere';
    }

    /**
     * Formats the given leads and updates the given fields in the platform data table.
     *
     * The request should contain the following parameters:
     *
     * - `leads`: An array of objects where each object contains the following keys:
     *   - `id`: The ID of the lead to be updated.
     *   - `$fillable`: The fields in the platform data table that can be updated.
     *
     * @param Request $request
     * @return void
     */
    public function formatAndUpdateLeads(Request $request): void
    {
        $filledData = collect($request->leads);
        if(empty($filledData)) {
            throw new \Exception('No data provided');
        }

        $fillable = (new PlatformData())->getFillable();
        $fields = collect($filledData[0])->keys();

        $selectableFields = $fields->filter(fn($field) => in_array($field, $fillable))->toArray();

        $isAmountField = in_array('revenue', $fields->toArray());

        if(in_array('affiliate_payout', $fields->toArray())) {
            $selectableFields[] = 'payout';
            $isAmountField = true;
        }

        $leads = PlatformData::query()
                    ->select('id', 'datas', ... $selectableFields)
                    ->whereIn('id', $filledData->pluck('id'))
                    ->when(! empty($isAmountField), function($query) {
                        return $query->addSelect([
                            'affiliate_id',
                            'list_id',
                            'buyer_id',
                            'affid',
                            'buyer_integration_id',
                            'affiliate_specs_id',
                            'affm_source_id',
                            'created_at',
                            'is_retainer',
                            'lead_status',
                            'retained_date',
                            'sold_type'
                        ]);
                    })
                    ->get();

        if(empty($leads)) {
            throw new \Exception('No leads found');
        }

        $leadAdditionalData = [];

        $allLeadData = iterator_to_array($this->generateLeadData($filledData, $leads->toArray(), $fillable, $leadAdditionalData), false);

        if(empty($allLeadData)) {
            throw new \Exception('No leads found');
        }

        $platformUpdatableField = collect($selectableFields)
                                    ->filter(fn($field) => ! in_array($field, ['revenue', 'affiliate_payout', 'retained_date']))
                                    ->push('datas')
                                    ->toArray();

        PlatformData::upsert(
            $allLeadData,
            ['id'],
            $platformUpdatableField
        );

        $leadData = collect($leadAdditionalData);
        $leadGroup = $leadData->groupBy('id')->all();
        $leadIds = $leadData->pluck('id')->all();

        $this->updateRelevantLeadReports($fields, $leadData, $leadIds, $request->upload_type);
        $this->updateRelevantLeadLog($fields, $leadGroup, $leadIds, $fillable);
    }

    /**
     * Updates the lead reports in the database based on the provided updated leads data.
     *
     * @param Collection $fields
     * @param Collection $leadGroup
     * @param array $leadIds
     *
     * @return void
     */
    public function updateRelevantLeadReports(Collection $fields, Collection $leadData, array $leadIds, string $uploadType): void
    {
        $updatableReportFields = $fields
                                    ->filter(fn($item) => in_array($item, ['affid', 'revenue', 'affiliate_payout']))
                                    ->map(fn($item) => $item === 'revenue' ? 'lead_revenue' : $item)
                                    ->values();

        $isRevenuePayoutField = $updatableReportFields->contains(fn($field) => in_array($field, ['revenue', 'affiliate_payout']));

        if(empty($updatableReportFields)) return;

        $leadReports = LeadReport::whereIn('lead_id', $leadIds)
                            ->select(
                                'id',
                                'lead_id',
                                'affiliate_id',
                                'list_id',
                                'buyer_id',
                                'affid',
                                'buyer_integration_id',
                                'affiliate_specs_id',
                                'affm_source_id',
                                'is_retainer',
                                'lead_revenue',
                                'affiliate_payout',
                                'lead_profit',
                                'affiliate_margin',
                                'profit_margin',
                                'sold_type',
                                'created_at',
                                'updated_at'
                            )
                            ->orderBy('lead_id', 'asc')
                            ->get();

        $leadRevenuePayouts = [];

        $leadReportData = iterator_to_array(
            $this->generateLeadReportDataV2(
                $leadReports->toArray(),
                $leadData->toArray(),
                $leadRevenuePayouts,
                $updatableReportFields->toArray(),
                $uploadType
            ),
            false
        );

        if(count($leadReportData) > 0) {
            $leadReportData = collect($leadReportData);

            $existingData = $leadReportData->filter(fn($item) => array_key_exists('id', $item))->all();

            if(count($existingData) > 0) {
                LeadReport::upsert(
                    $leadReportData->filter(fn($item) => array_key_exists('id', $item))->all(),
                    ['id'],
                    ['lead_revenue', 'affiliate_payout', 'lead_profit', 'affiliate_margin', 'profit_margin', 'created_at', 'updated_at', 'affid', 'sold_type']
                );
            }

            $newReports = $leadReportData->filter(fn($item) => ! array_key_exists('id', $item))->all();

            if(count($newReports) > 0) {
                LeadReport::insert($newReports);
            }

            if($isRevenuePayoutField && $uploadType === 'retainer_upload') {
                GlobalPostBackTriggerJob::dispatch([
                    'type' => 'bulk_retainer',
                    'lead_reports' => $leadReportData->toArray()
                ]);
            }
        }

        if(count($leadRevenuePayouts) > 0) {

            $uploadableFields = ['payout', 'revenue'];

            if($uploadType === 'retainer_upload') {
                $uploadableFields = ['payout', 'revenue', 'is_retainer', 'lead_status', 'retained_date'];
            }

            PlatformData::upsert(
                $leadRevenuePayouts,
                ['id'],
                $uploadableFields
            );
        }
    }

    /**
     * Updates the lead logs for a collection of leads.
     *
     * @param array $updatableFields
     * @param array $leadGroup
     * @param array $leadIds
     * @param array $fillable
     *
     * @return void
     */
    public function updateRelevantLeadLog(Collection $fields, array $leadGroup, array $leadIds, array $fillable): void
    {
        $logs = LeadLog::whereIn('lead_id', $leadIds)
                    ->select('id', 'lead_id', 'log_data')
                    ->get();

        $logDataGenerator = $this->generatorLeadLogs($logs, $leadGroup, $fields, $fillable);
        $logData = iterator_to_array($logDataGenerator, false);

        if(empty($logData)) return;

        LeadLog::upsert(
            $logData,
            ['id'],
            ['log_data']
        );
    }

    /**
     * Generator to generate lead log data from the given $logs and $leadGroup data.
     * This will iterate over the lead logs and update the original payload of the log data
     * with the updatable fields from the lead group data.
     *
     * @param \Illuminate\Database\Eloquent\Collection $logs
     * @param array $leads
     * @param array $updatableFields
     * @param array $fillable
     *
     * @yield array
     */
    private function generatorLeadLogs($logs, array $leads, Collection $fields, array $fillable): Generator
    {
        $fields = $fields->reject(fn($item) => in_array($item, ['id']))->all();

        foreach($logs as $log) {
            $lead = $leads[$log->lead_id][0] ?? null;
            if(empty($lead)) continue;

            $logData = $log->log_data;
            $originalPayload = $logData && $logData['original_payload'] ? $logData['original_payload'] : null;
            if(empty($originalPayload)) continue;

            $data = is_string($lead['datas']) ? json_decode($lead['datas'], true) : $lead['datas'];

            foreach($fields as $key){
                if(! array_key_exists($key, $originalPayload)) continue;

                if(in_array($key, $fillable)) {
                    $originalPayload[$key] = $lead[$key];
                }

                if(array_key_exists($key, $data)) {
                    $originalPayload[$key] = $data[$key];
                }
            }

            $logData['original_payload'] = $originalPayload;

            yield [
                'id' => $log->id,
                'log_data' => json_encode($logData)
            ];
        }
    }

    /**
     * Generator to generate lead report data from the given $leadReports and $leadData data.
     * This will also update the $leadRevenuePayouts array with the revenue and payout data.
     *
     * @param array $leadReports
     * @param array $leadData
     * @param array $updatableReportFields
     * @param array $leadRevenuePayouts
     * @param array $oldRetainerIds
     * @param string $uploadType
     * @return Generator
     */
    private function generateLeadReportDataV2(
        array $leadReports,
        array $leadData,
        &$leadRevenuePayouts,
        array $updatableReportFields,
        string $uploadType
    ): Generator
    {
        $revenue = 0;
        $payout = 0;

        $updatableReportFields = collect($updatableReportFields);
        $generalUpdatableFields = $updatableReportFields->filter(fn($field) => ! in_array($field, ['lead_revenue', 'affiliate_payout']))->all();
        $revenuePayoutFields = $updatableReportFields->filter(fn($field) => in_array($field, ['lead_revenue', 'affiliate_payout']))->all();

        if(empty($generalUpdatableFields) && empty($revenuePayoutFields)) return;

        $leadReports = collect($leadReports)->groupBy('lead_id')->all();

        foreach($leadData as $lead) {

            $reports = $leadReports[$lead['id']] ?? [];

            $oldRetainer = null;
            $evenlyDistributedRevenue = 0;
            $evenlyDistributedPayout = 0;
            $reportsCount = count($reports);

            if(! empty($revenuePayoutFields) && $uploadType === 'disposition_upload') {

                $leadRevenue = (float) ($lead['revenue'] ?? 0);
                $leadPayout = (float) ($lead['payout'] ?? 0);

                $evenlyDistributedRevenue = $reportsCount ? $leadRevenue / $reportsCount : $leadRevenue;
                $evenlyDistributedPayout = $reportsCount ? $leadPayout / $reportsCount : $leadPayout;
            }

            foreach($reports as $report) {

                if(! empty($revenuePayoutFields)) {

                    if($uploadType === 'disposition_upload') {
                        $report['lead_revenue'] = $evenlyDistributedRevenue;
                        $report['affiliate_payout'] = $evenlyDistributedPayout;
                    }

                    if(! empty($report['is_retainer']) && $uploadType === 'retainer_upload') {
                        $oldRetainer = $report;
                        continue;
                    }

                    $this->sumRevenuePayout($revenue, $payout, $report);
                }

                foreach ($generalUpdatableFields as $field) {
                    $report[$field] = $lead[$field];
                }

                yield $report;
            }

            $isRevenuePayoutFieldsNotEmpty = !empty($revenuePayoutFields);
            $isRetainerUpload = $uploadType === 'retainer_upload';
            $isDispositionUploadWithNoReports = $uploadType === 'disposition_upload' && $reportsCount < 1;
            $newRetainer = null;

            if($isRevenuePayoutFieldsNotEmpty && ($isRetainerUpload || $isDispositionUploadWithNoReports)) {

                $newRetainer = $this->getFormData($lead, $oldRetainer, $uploadType, $evenlyDistributedRevenue, $evenlyDistributedPayout);

                $this->sumRevenuePayout($revenue, $payout, $newRetainer);

                $leadRevenuePayouts[] = $this->getRevenuePayout($lead, $revenue, $payout, $newRetainer, $uploadType);
                $this->resetRevenuePayout($revenue, $payout);

                yield $newRetainer;
            }
        }
    }

    /**
     * Resets the initial keys (isRetainer, revenue, payout) to their default values.
     *
     * @param bool $isRetainer
     * @param float $revenue
     * @param float $payout
     */
    private function resetRevenuePayout(&$revenue, &$payout)
    {
        $revenue = 0;
        $payout = 0;
    }

    /**
     * Sums the revenue and payout from the given report to the given variables.
     *
     * @param float $revenue
     * @param float $payout
     * @param array $report
     */
    private function sumRevenuePayout(&$revenue, &$payout, $report): void
    {
        $revenue += (float) $report['lead_revenue'];
        $payout += (float) $report['affiliate_payout'];
    }

    /**
     * Returns an array containing the lead ID, revenue and payout.
     * The payout is calculated by subtracting the affiliate payout from the revenue.
     *
     * @param array $lead
     * @param float $revenue
     * @param float $affiliatePayout
     * @return array
     */
    private function getRevenuePayout($lead, $revenue, $payout, $leadReport = null, string $uploadType = null): array
    {
        $data = [
            'id' => $lead['id'],
            'revenue' => $revenue,
            'payout' => $revenue - $payout,
            'is_retainer' => $lead['is_retainer'],
            'lead_status' => 'Retained',
            'retained_date' => $leadReport['created_at'] ?? null
        ];

        if($uploadType === 'disposition_upload') {
            $data['is_retainer'] = $lead['is_retainer'];
            $data['lead_status'] = $lead['lead_status'];
            $data['retained_date'] = $lead['retained_date'];
        }

        return $data;
    }

    /**
    * Retrieves the form data for creating a new lead report from the given lead data and any revenue/payout data.
    *
    * @param PlatformData $lead
    * @param array $leadReportData
    * @return array
    */
    public function getFormData(array $lead, ?array $oldRetainer = null, string $uploadType = null, float $evenlyDistributedRevenue = 0, float $evenlyDistributedPayout = 0): array
    {
        $newDate = now();

        if($uploadType === 'retainer_upload') {
            $newDate = Carbon::parse($lead['new_retained_date'])->midDay();
            $createdAt = Carbon::parse($lead['created_at']) ?? null;

            if($createdAt && ($createdAt->greaterThan($newDate))){
                $newDate = $createdAt->addHour();
            }
        }

        $formData = [
            'lead_id' => $lead['id'] ?? null,
            'affiliate_id' => $lead['affiliate_id'] ?? null,
            'list_id' => $lead['list_id'] ?? null,
            'buyer_id' => $lead['buyer_id'] ?? null,
            'affid' => $lead['affid'] ?? null,
            'buyer_integration_id' => $lead['buyer_integration_id'] ?? null,
            'affiliate_specs_id' => $lead['affiliate_specs_id'] ?? null,
            'affm_source_id' => $lead['affm_source_id'] ?? null,
            'is_retainer' => true,
            'lead_revenue' => $lead['revenue'] ?? 0,
            'affiliate_payout' => $lead['payout'] ?? 0,
            'lead_profit' => 0,
            'affiliate_margin' => 0,
            'profit_margin' => 0,
            'sold_type' => $lead['sold_type'] ?? null,
            'created_at' => $newDate,
            'updated_at' => $newDate
        ];

        if($oldRetainer && $uploadType === 'retainer_upload') {
            $formData['id'] = $oldRetainer['id'];
        }

        if($uploadType === 'disposition_upload') {
            $formData['is_retainer'] = (bool) ($lead['is_retainer'] ?? 0);
            $formData['lead_revenue'] = $evenlyDistributedRevenue;
            $formData['affiliate_payout'] = $evenlyDistributedPayout;
        }

        $reportData = $this->calculateRevenuePayout((float) $formData['lead_revenue'], (float) $formData['affiliate_payout']);

        return array_merge($formData, $reportData);
    }

    /**
     * Calculates the revenue, profit, affiliate margin and profit margin given the revenue and affiliate payout.
     *
     * @param float|int $revenue
     * @param float|int $affiliatePayout
     * @return array
     */
    public function calculateRevenuePayout( float | int $revenue = 0, float | int $affiliatePayout): array
    {
        $profit = $revenue - $affiliatePayout;
        $affiliateMargin = $revenue ? (($affiliatePayout / $revenue) * 100) : 0;
        $profitMargin = $revenue ? (($profit / $revenue) * 100) : 0;

        return [
            'lead_profit' => $profit,
            'affiliate_margin' => $affiliateMargin,
            'profit_margin' => $profitMargin
        ];
    }

    public function processLeads(iterable $leads, Request $request, array $portalLeadIds, array $portalExceptedLeadIds, bool $isAllShowPortal): iterable
    {
        foreach ($leads as $item) {

            $item['id'] = (string) $item['id'];

            if ($request->upload_type === 'disposition_upload') {
                yield $item;
                continue;
            }

            $item['is_show_portal'] = $isAllShowPortal;

            if (!empty($portalLeadIds)) {
                $item['is_show_portal'] = in_array($item['id'], $portalLeadIds);
            }

            if (!empty($portalExceptedLeadIds)) {
                $item['is_show_portal'] = !in_array($item['id'], $portalExceptedLeadIds);
            }

            yield $item;
        }
    }
}
