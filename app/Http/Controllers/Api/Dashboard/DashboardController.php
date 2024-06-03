<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class DashboardController extends Controller
{
    /**
     * Get total statistics for dashboard
     *
     * @return Response
     */
    public function totalStats(): Response
    {
        $totalStats = DB::table('lead_reports')
            ->select(
                DB::raw('count(case when is_posted = 1 then 1 end) as totalLeads'),
                DB::raw('count(case when buyer_id is not null and lead_id is not null  then 1 end) as totalConvertedLeads'),
                DB::raw('sum(lead_revenue) as totalRevenue'),
                DB::raw('sum(lead_profit) as totalProfit')
            )
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfDay()])
            ->first();

        return withSuccess($totalStats);
    }

}
