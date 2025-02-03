<?php

namespace App\Http\Controllers\Api\Test;

use App\Helpers\Utility;
use App\Http\Controllers\Api\Test\Resources\TestResource;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\PlatformData;
use App\Models\PlatformDataItem;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{

    public function updateIntegration(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', -1);

        $integrations = $request->integrations;

        $leadIdKeys = [
            'leadId', 'LeadId', 'lead_id', 'leadid', 'id', 'request_identifier',
            'confirmation_id', 'successLeadid', 'unique_id', 'TransactionId',
            'intakeId', 'ping_leadToken', 'bid_id', 'transaction_id'
        ];

        $responsePatterns = [
            ["message", "status", "result"],
            ["message", "status", "error"],
            ["message", "status", "errors"],
            ["default_response"],
            ["status", "response"],
            ["message", "error", "success"],
            ["status", "message", "data"],
            ["f"],
            ["Status"]
        ];

        // Process integrations
        $formattedData = collect($integrations)
            ->map(function ($item) use ($leadIdKeys, $responsePatterns) {
                $item['buyer_headers'] = json_decode($item['buyer_headers'], true);

                // Assign `lead_id_key` if missing
                if (empty($item['lead_id_key'])) {
                    $item['lead_id_key'] = collect($leadIdKeys)->first(fn($key) => in_array($key, $item['buyer_headers']));
                }

                // Check if buyer_headers match response patterns
                $sortedBuyerHeaders = $item['buyer_headers'];
                sort($sortedBuyerHeaders);

                $item['is_matched'] = collect($responsePatterns)->contains(function ($pattern) use ($sortedBuyerHeaders) {
                                            sort($pattern);
                                            return empty(array_diff($sortedBuyerHeaders, $pattern));
                                        });

                return $item;
            })
            ->filter(fn($item) => !empty($item['lead_id_key'])) // Ensure `lead_id_key` exists
            ->map(function ($item) {
                unset($item['is_matched'], $item['created_at'], $item['updated_at']);
                $item['buyer_headers'] = json_encode($item['buyer_headers']);
                return $item;
            })
            ->all();

        $upsert = Integration::upsert(
            $formattedData,
            ['id'],
            ['lead_id_key']
        );

        return withSuccess([
            'upsert' => $upsert,
            'count' => count($formattedData)
        ]);
    }

    public function updateDataItem(Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', -1);

        $now = now();
        $limit = ! empty($request->limit) ? $request->limit : 50000;
        $chunkSize = ! empty($request->chunk_size) ? $request->chunk_size : 10000;

        $dataItem = PlatformDataItem::orderBy('platform_data_id')->first();

        $chunks = PlatformData::query()
                    ->whereNotNull('platform_datas.buyer_integration_id')
                    ->whereNotNull('integrations.lead_id_key')
                    ->leftJoin('integrations', 'platform_datas.buyer_integration_id', '=', 'integrations.id')
                    ->select([
                        'platform_datas.id',
                        DB::raw("JSON_UNQUOTE(
                            JSON_EXTRACT(
                                platform_datas.datas,
                                CONCAT('$.', integrations.buyer_unique_id, '_', integrations.lead_id_key)
                            )
                        ) as lead_id")
                    ])
                    ->latest('platform_datas.id')
                    ->when(! empty($dataItem), function($query) use ($dataItem) {
                        return $query->where('platform_datas.id', '<', $dataItem->platform_data_id);
                    })
                    ->limit($limit)
                    ->get()
                    ->chunk($chunkSize);

                    foreach ($chunks as $key => $chunk) {
                        $leads = $chunk->values()
                                ->reject(fn ($item) => in_array($item->lead_id, [null, "null", "", "0", 0, false, " "], true))
                                ->map(function($item) use ($now) {
                                    return [
                                        'platform_data_id' => $item->id,
                                        'field' => 'lead_id',
                                        'value' => $item->lead_id,
                                        'created_at' => $now,
                                        'updated_at' => $now
                                    ];
                                })
                                ->values()
                                ->all();

                        if(empty($leads)) continue;

                        PlatformDataItem::insert($leads);
                    }

        return withSuccess(message: 'Data items updated successfully');
    }
}
