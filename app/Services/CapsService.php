<?php

namespace App\Services;

use App\Models\Caps;
use App\Models\PlatformData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CapsService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     *
     * @param Request $request
     * @return Builder
     */
    public function getCapsList(Request $request): Builder
    {

        $caps = Caps::query();

        $caps = $caps->selectRaw('
        caps_history.id,
        platform_lists.name as list_name,
        integrations.buyer_unique_id as integration_name,
        buyers.name as buyer_name,
        column_scope,
        caps_history.list_id,
        caps_history.integration_id,
        caps_history.buyer_id,
        SUM(IF(end_date IS NULL,
            cap_amount * IF(duration = "daily",
                DATEDIFF(?, start_date) + 1,
                TIMESTAMPDIFF(WEEK,
                    DATE_SUB(start_date, INTERVAL WEEKDAY(start_date) DAY),
                    DATE_ADD(?, INTERVAL (6 - WEEKDAY(?)) DAY)
                ) + 1
            ), cap_amount
        )) AS capacity', [$request->end_date, $request->end_date, $request->end_date])
    ->where(function($query) use ($request) {
        $query->where(function($q) use ($request) {
            $q->whereNull('end_date')
            ->whereBetween('start_date', [$request->start_date, $request->end_date]);
        })->orWhere(function($q) use ($request) {
            $q->whereBetween('start_date', [$request->start_date, $request->end_date])
            ->whereBetween('end_date', [$request->start_date, $request->end_date]);
        });
    })
    ->join('platform_lists', 'caps_history.list_id', '=', 'platform_lists.id')
    ->join('integrations', 'caps_history.integration_id', '=', 'integrations.id')
    ->join('buyers', 'caps_history.buyer_id', '=', 'buyers.id')
    ->groupBy(['caps_history.list_id', 'integration_id', 'caps_history.buyer_id', 'column_scope']);

    return $caps;

    }

    /**
     *
     * @param Request $request The request object containing the group by parameter.
     * @return array The formatted group by array.
     */
    public function getCapacities(Request $request): array
    {
        $capacities = PlatformData::query();
        $capacities = $capacities
                      ->where('created_at', '>=', $request->start_date)
                      ->where('created_at', '<=', $request->end_date)
                      ->where('list_id', $request->list_id)
                      ->where('buyer_integration_id', $request->integration_id)
                      ->where('buyer_id', $request->buyer_id)
                      ->where(function($query) use ($request) {
                            if ($request->column_scope !== 'None') {
                                $block = explode(':', $request->column_scope);
                                $columnName = $block[0];
                                if (!in_array($columnName, ['affid', 'affm_source_id', 'page_source'])) {
                                    $columnName = 'data->' . $columnName;
                                }
                                $query->where($columnName, $block[1]);
                            }
                      })->count();


        $capacities = [
            'id' => $request->id,
            'filled' => $capacities
        ];

        return $capacities;
    }

}
