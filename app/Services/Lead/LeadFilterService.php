<?php

namespace App\Services\Lead;

// use App\Models\DispositionConfigMongo;
// use App\Models\DispositionLogMongo;
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
        $batchSize = 5000;
        $buffer = [];
        $now = now();

        $conditions = $this->formatConditionsData(collect($request->conditions ?? []));
        if(empty($conditions)) {
            abort(400, 'Filter items must not be empty!');
        }

        $firstCondition = array_keys($conditions[0] ?? []);
        $this->saveDataToTemporaryTable($conditions);

        $fillableKeys = (new PlatformData())->getFillable();
        $selectableKeys = $this->formatSelectableKeys($request, $fillableKeys);

        // $config = DispositionConfigMongo::create([
        //                 'user_id' => $userId,
        //                 'uid' => (string) str()->uuid()
        //             ]);

        $config = collect();

        $todos = PlatformData::query()
                    ->select($selectableKeys)
                    ->whereExists(function ($query) use ($firstCondition) {
                        $query->select(DB::raw(1))->from('temp_conditions as tc');

                        foreach ($firstCondition as $column) {

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

                            $query->whereColumn("tc.$column", "platform_datas.$column");
                        }

                            // ->whereColumn('tc.buyer_id', 'platform_datas.buyer_id')
                            // ->whereColumn('tc.phone', 'platform_datas.phone')
                            // ->whereExists(function ($subQuery) {
                            //     $subQuery->select(DB::raw(1))
                            //         ->from('platform_data_items as pdi')
                            //         ->whereColumn('pdi.value', 'tc.lead_id')
                            //         ->whereColumn('platform_datas.id', 'pdi.platform_data_id');
                            // });
                    })
                    ->select('platform_datas.*')
                    ->lazyById(5000)
                    ->each(function ($lead) use (&$buffer, $batchSize, $config, $now) {
                        // Transform or process the data
                        $buffer[] = [
                            'disposition_config_id' => $config->id ?? null,
                            'platform_data_id' => $lead->id,
                            'lead_status' => $lead->lead_status,
                            'data' => [],
                            'is_duplicate' => false,
                            'updatable_data' => [],
                            'created_at' => $now,
                            'updated_at' => $now
                        ];

                        if (count($buffer) >= $batchSize) {
                            // DispositionLogMongo::insert($buffer);
                            $buffer = [];
                        }
                    });

                    if (!empty($buffer)) {
                        // DispositionLogMongo::insert($buffer);
                    }

        return [
            'todos_count' => $todos->count(),
            // 'total' => DispositionLogMongo::count(),
            // 'data' => DispositionLogMongo::limit(40)->get()
        ];
    }

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

        // DispositionLogMongo::truncate();
        // DispositionConfigMongo::truncate();

        DB::statement("CREATE TEMPORARY TABLE temp_conditions (" . implode(', ', $columnsSql) . ")");

        foreach (array_chunk($conditions, 10000) as $chunk) {
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

}
