<?php

namespace App\Services\Lead;

use App\Models\DispositionConfigMongo;
use App\Models\DispositionLogMongo;
use App\Models\LeadReport;
use App\Models\PlatformData;
use App\Models\PlatformDataItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class LeadFilterService
{

    public function getFilterLeadsV2(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', -1);

        $userId = auth()->id();
        $batchSize = 10000;
        $buffer = [];
        $now = now();

        $requestedConditions = collect($request->conditions ?? []);

        $conditions = $this->formatConditionsData($requestedConditions);
        if(empty($conditions)) {
            abort(400, 'Filter items must not be empty!');
        }

        $groupedData = iterator_to_array($this->groupCsvData($request));

        // $oldConfig = DispositionConfigMongo::where('user_id', $userId)->latest('id')->select('id')->first();
        // if(! empty($oldConfig)) {
        //     RemoveConfigLogs::dispatch($oldConfig->id);
        // }

        $this->saveDataToTemporaryTable($conditions);

        $columns = array_keys($conditions[0] ?? []);
        $fillableKeys = (new PlatformData())->getFillable();
        $selectableKeys = $this->formatSelectableKeys($request, $fillableKeys);
        $isAmountField = in_array('revenue', $request->mapped_headers) || in_array('affiliate_payout', $request->mapped_headers);
        $conditionalKeys = $this->getGroupConditionalKey($columns);

        $groupedKeyCounts = [];

        $config = DispositionConfigMongo::create([
                        'user_id' => $userId,
                        'uid' => (string) str()->uuid()
                    ]);

        $todos = PlatformData::query()
                    ->select($selectableKeys)
                    ->when(in_array('lead_id_1', $columns), function($query) {
                        return $query->addSelect([
                            'custom_lead_id' => PlatformDataItem::whereColumn('platform_datas.id', 'platform_data_items.platform_data_id')
                                                    ->select('platform_data_items.value')
                                                    ->limit(1)
                        ]);
                    })
                    ->when(in_array('buyer_name', $columns), function($query) {
                        return $query->addSelect([
                            'custom_lead_id' => PlatformDataItem::whereColumn('platform_datas.id', 'platform_data_items.platform_data_id')
                                                    ->select('platform_data_items.value')
                                                    ->limit(1)
                        ]);
                    })
                    ->when($isAmountField, function ($query) use($request) {
                        return $query->addSelect([
                            'revenue' => LeadReport::selectRaw('IFNULL(SUM(lead_revenue), 0)')
                                            ->whereColumn('lead_id', 'platform_datas.id')
                                            ->when($request->upload_type === 'retainer_upload', function ($query2) {
                                                return $query2->where('is_retainer', 0);
                                            })
                                            ->limit(1),

                            'affiliate_payout' => LeadReport::selectRaw('IFNULL(SUM(lead_revenue) - SUM(affiliate_payout), 0)')
                                                    ->whereColumn('lead_id', 'platform_datas.id')
                                                    ->when($request->upload_type === 'retainer_upload', function ($query2) {
                                                        return $query2->where('is_retainer', 0);
                                                    })
                                                    ->limit(1)
                        ]);
                    })
                    ->whereExists(function ($query) use ($columns) {
                        $query->select(DB::raw(1))->from('temp_conditions as tc');

                        foreach ($columns as $column) {

                            if (strpos($column, 'lead_id_') === 0) {
                                $query->whereExists(function ($subQuery) use($column) {
                                        $subQuery->select(DB::raw(1))
                                            ->from('platform_data_items as pdi')
                                            ->whereColumn('pdi.value', "tc.$column")
                                            ->whereColumn('platform_datas.id', 'pdi.platform_data_id');
                                    });

                                continue;
                            }

                            if (strpos($column, 'buyer_id_') === 0) {
                                $query->whereColumn("tc.$column", "platform_datas.buyer_id");
                                continue;
                            }

                            if ($column === 'buyer_name') {

                                $query->whereExists(function ($subQuery) use($column) {
                                        $subQuery->select(DB::raw(1))
                                            ->from('buyers')
                                            ->whereColumn('buyers.name', "tc.$column")
                                            ->whereColumn('platform_datas.buyer_id', 'buyers.id');
                                    });

                                continue;
                            }

                            $query->whereColumn("tc.$column", "platform_datas.$column");
                        }
                    })
                    ->lazyById(10000);

                    foreach($todos as $lead) {
                        $keys = array_map(
                            fn($key) => strtolower($lead[$key] ?? ''),
                            $conditionalKeys
                        );

                        $groupedKey = implode('_', array_filter($keys));

                        $groupedKeyCounts[$groupedKey] = ($groupedKeyCounts[$groupedKey] ?? 0) + 1;

                        $buffer[] = [
                            'disposition_config_id' => $config->id,
                            'platform_data_id' => $lead->id,
                            'lead_status' => $lead->lead_status,
                            'data' => $lead->toArray(),
                            'is_duplicate' => $groupedKeyCounts[$groupedKey] > 1 ? true : false,
                            'updatable_data' => $groupedData[$groupedKey] ?? [],
                            'created_at' => $now,
                            'updated_at' => $now
                        ];

                        if (count($buffer) >= $batchSize) {
                            DispositionLogMongo::insert($buffer);
                            $buffer = [];
                        }
                    }

                    if (!empty($buffer)) {
                        DispositionLogMongo::insert($buffer);
                    }

        return $this->getDispositionLog(new Request());
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
        $config = DispositionConfigMongo::where('user_id', auth()->id())
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

        return DispositionLogMongo::query()
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
                    return $query->where('is_duplicate', true);
                })
                ->when(! empty($request->show_type === 'unique'), function($query) {
                    return $query->where('is_duplicate', false);
                })
                ->paginate($limit);
    }

    /**
     * Retrieves a list of conditional keys by filtering out certain columns.
     *
     * @param array $columns The array of column names to process.
     * @return array The filtered array of conditional keys.
     */
    public function getGroupConditionalKey(array $columns)
    {
        $conditionalKeys = collect($columns)->reject(function($item) {
                                return in_array($item, ['lead_id_1', 'buyer_id_1']);
                            })
                            ->values()
                            ->all();

        if(in_array('buyer_id_1', $columns)) {
            $conditionalKeys[] = 'custom_lead_id';
        }

        return $conditionalKeys;
    }

    /**
     * Groups CSV data by specified condition keys and yields grouped lead data.
     *
     * @param Request $request The HTTP request containing CSV data, conditions,
     *                         actual headers, and mapped headers.
     *
     * @yield string $groupKey The unique key representing a group of leads.
     * @yield array $updatableLeads The array of updatable lead data for the group.
     */
    public function groupCsvData(Request $request)
    {
        $actualHeadersObj = collect($request->actual_headers ?? [])->pluck('value', 'model_value')->toArray();
        $conditionKeys = $this->getConditionKeys(($request->conditions[0] ?? []), $actualHeadersObj);
        $mappedHeads = $this->getMappedHeaders(($request->mapped_headers ?? []), $conditionKeys);

        $csvData = $request->csv_data ?? [];

        foreach ($csvData as $item) {

            $keys = array_map(
                fn($key) => strtolower($item[$actualHeadersObj[$key]] ?? ''),
                $conditionKeys
            );

            $groupedKey = implode('_', array_filter($keys));

            $grouped[$groupedKey][] = $item;

            if (count($grouped) > 10000) {
                foreach ($grouped as $groupKey => $leads) {
                    yield $groupKey => $this->extractUpdatableLeads($leads, $actualHeadersObj, $mappedHeads);
                }
                $grouped = [];
            }
        }

        foreach ($grouped as $groupKey => $leads) {
            yield $groupKey => $this->extractUpdatableLeads($leads, $actualHeadersObj, $mappedHeads);
        }
    }

    /**
     * Extracts updatable lead data from an array of grouped leads.
     *
     * @param array $leads The array of grouped leads.
     * @param array $actualHeadersObj The array of actual headers to map.
     * @param array $mappedHeads The array of mapped headers to extract.
     * @return array The array of extracted updatable lead data.
     */
    function extractUpdatableLeads(array $leads, array $actualHeadersObj, array $mappedHeads): array
    {
        $leadData = reset($leads) ?: [];
        $updatableLeads = [];

        foreach ($mappedHeads as $mappedKey) {
            $actualKey = $actualHeadersObj[$mappedKey] ?? null;
            if ($actualKey) {
                $updatableLeads[$mappedKey] = $leadData[$actualKey] ?? '';
            }
        }

        return $updatableLeads;
    }

    /**
     * Gets the mapped headers with the condition keys excluded.
     *
     * @param array $mappedHeads The array of mapped headers.
     * @param array $conditionKeys The array of condition keys to exclude.
     * @return array The array of mapped headers with the condition keys excluded.
     */
    public function getMappedHeaders($mappedHeads, $conditionKeys)
    {
        return collect($mappedHeads)
                    ->reject(function($item) use ($conditionKeys) {
                        return in_array($item, $conditionKeys);
                    })
                    ->values()
                    ->all();
    }

    /**
     * Gets the condition keys by extracting the array keys from the initial columns.
     *
     * @param array $initialColumns The array of initial columns.
     * @param array $actualHeadersObj The array of actual headers to map.
     * @return array The array of condition keys.
     */
    public function getConditionKeys($initialColumns, &$actualHeadersObj = [])
    {
        $formattedKeys = array_keys($initialColumns);

        $custom = $initialColumns['custom'] ?? [];

        if (!empty($custom) && is_array($custom)) {
            foreach ($custom as ['lead_key' => $key]) {
                $formattedKeys[] = $key;
                $actualHeadersObj[$key] = $key;
            }
        }

        if(in_array('custom', $formattedKeys)) {
            unset($formattedKeys[array_search('custom', $formattedKeys)]);
        }

        return $formattedKeys;
    }

    /**
     * Formats the conditions data for saving to the temporary table.
     *
     * @param array $conditions The array of condition objects.
     * @return array The formatted conditions data.
     */
    public function formatConditionsData($conditions) {

        $formattedConditions = $conditions->map(function($item) {
                                    $newItem = $item;

                                    $custom = $newItem['custom'] ?? [];
                                    unset($newItem['custom']);

                                    if(! empty($custom) && is_array($custom)) {
                                        foreach($custom as $key => $item) {
                                            $newKey = $key + 1;
                                            $newItem["buyer_id_$newKey" ]= $item['buyer_id'];
                                            $newItem["lead_id_$newKey" ]= $item['lead_id'];
                                        }
                                    }

                                    return $newItem;
                                })
                                ->all();

        return $formattedConditions;

    }

    /**
     * Save the given conditions data to a temporary table.
     *
     * The table columns are determined by the keys of the first condition.
     * The columns are created with the following types:
     * - buyer_id_*: int
     * - All other columns: VARCHAR(255) COLLATE utf8mb4_unicode_ci
     *
     * @param array $conditions The array of condition objects.
     * @return void
     */
    public function saveDataToTemporaryTable($conditions)
    {
        $firstCondition = $conditions[0] ?? [];
        $columns = array_keys($firstCondition);

        $columnsSql = [];
        foreach ($columns as $column) {
            if (strpos($column, 'buyer_id_') === 0) {
                $columnsSql[] = "$column int";
                continue;
            }

            $columnsSql[] = "$column VARCHAR(255) COLLATE utf8mb4_unicode_ci";
        }
        $columnsSql[] = "INDEX(" . implode("), INDEX(", $columns) . ")";

        DB::statement("CREATE TEMPORARY TABLE temp_conditions (" . implode(', ', $columnsSql) . ")");

        foreach (array_chunk($conditions, 5000) as $chunk) {
            DB::table('temp_conditions')->insert($chunk);
        }
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
     * Retrieves an array of filterable fields that can be used to filter leads.
     *
     * @return array The array of filterable fields.
     */
    public function getFilterableFields()
    {
        return [
            'affm_lead_id',
            'affid',
            'phone',
            'email',
            'buyer_id',
            'lead_status',
            'affm_source_id'
        ];
    }

}
