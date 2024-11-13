<?php

namespace App\Services;

use App\Models\Caps;
use App\Models\PlatformData;
use App\Traits\CommonTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CapsService
{
    use CommonTrait;

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
        [$startDate, $endDate] = $this->formatStartEndDateWithTimezone($request->start_date, $request->end_date, $request->timezone, defaultTimezone: 'America/New_York');

        $caps = Caps::query();

        $listIds = ! empty($request->list) && is_string($request->list) ? explode(',', $request->list) : [];
        $buyerIds = ! empty($request->buyer) && is_string($request->buyer) ? explode(',', $request->buyer) : [];
        $buyerIntegrationIds = ! empty($request->buyer_integration) && is_string($request->buyer_integration) ? explode(',', $request->buyer_integration) : [];


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
            ->where(function($query) use ($request, $startDate, $endDate) {
                $query->where(function($q) use ($request, $startDate, $endDate) {
                    $q->whereNull('end_date')
                    ->whereBetween('start_date', [$startDate, $endDate]);
                })->orWhere(function($q) use ($request) {
                    $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->whereBetween('end_date', [$request->start_date, $request->end_date]);
                });
            })
            ->join('platform_lists', 'caps_history.list_id', '=', 'platform_lists.id')
            ->join('integrations', 'caps_history.integration_id', '=', 'integrations.id')
            ->join('buyers', 'caps_history.buyer_id', '=', 'buyers.id')
            ->when(! empty($listIds), function($query) use ($listIds) {
                $query->whereIn('caps_history.list_id', $listIds);
            })
            ->when(! empty($buyerIds), function($query) use ($buyerIds) {
                $query->whereIn('caps_history.buyer_id', $buyerIds);
            })
            ->when(! empty($buyerIntegrationIds), function($query) use ($buyerIntegrationIds) {
                $query->whereIn('caps_history.integration_id', $buyerIntegrationIds);
            })
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
        [$startDate, $endDate] = $this->formatStartEndDateWithTimezone($request->start_date, $request->end_date, $request->timezone, defaultTimezone: 'America/New_York');

        $block = explode(':', $request->column_scope);

        $capacities = PlatformData::query();
        $capacities = $capacities
                      ->where('created_at', '>=', $startDate)
                      ->where('created_at', '<=', $endDate)
                      ->where('list_id', $request->list_id)
                      ->where('buyer_integration_id', $request->integration_id)
                      ->where('buyer_id', $request->buyer_id)
                      ->when(! empty($request->column_scope) && $request->column_scope !== 'None' && ! empty($block), function($query) use ($block) {
                            $columnName = $block[0];
                            if (!in_array($columnName, ['affid', 'affm_source_id', 'page_source'])) {
                                $columnName = 'datas->' . $columnName;
                            }
                            return $query->where($columnName, $block[1]);
                      })
                      ->count();


        $capacities = [
            'id' => $request->id,
            'filled' => $capacities
        ];

        return $capacities;
    }

}
