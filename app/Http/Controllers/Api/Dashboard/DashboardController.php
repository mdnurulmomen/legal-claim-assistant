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

    /**
     * Get performance statistics for dashboard
     *
     * @return Response
     */
    public function getPerformanceStats(): Response
    {
        $performanceStats = DB::table('lead_reports')
            ->select(
                DB::raw('count(case when sold_type = "CPL" then 1 end) as acceptanceRateCPL'),
                DB::raw('count(case when buyer_id is not null and lead_id is not null  then 1 end) as acceptanceRate'),
                DB::raw('avg(lead_revenue) as avarageRevenue'),
                DB::raw('avg(lead_profit) as avarageProfit')
            )
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfDay()])
            ->first();

        return withSuccess($performanceStats);
    }

    /**
     * Get performance graph for dashboard
     *
     * @return Response
     */
    public function getGraph(): Response
    {
        $daysInMonth = DB::table('lead_reports')
        ->select(DB::raw('DAY(LAST_DAY(CURDATE())) as days_in_month'))
        ->first()
        ->days_in_month;

        $interval = ceil($daysInMonth / 6);

        $performanceGraphData = DB::table('lead_reports')
            ->select(DB::raw('DATE_FORMAT(created_at, "%a, %d") as day, COUNT(*) as posted, COUNT(CASE WHEN buyer_id IS NOT NULL and lead_id IS NOT NULL THEN 1 END) as accepted, SUM(lead_revenue) as revenue, SUM(lead_profit) as profit'))
            ->whereRaw('DAY(created_at) % ' . $interval . ' = 1')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->groupBy('day')
            ->orderBy('created_at', 'asc')
            ->get();

        $postedGraph = collect($performanceGraphData)->map(function ($item) {
            return [
                'x' => $item->day,
                'y' => $item->posted,
                'revenue' => $item->revenue,
                'profit' => $item->profit,
            ];
        });

        $acceptedGraph = collect($performanceGraphData)->map(function ($item) {
            return [
                'x' => $item->day,
                'y' => $item->accepted,
                'revenue' => $item->revenue,
                'profit' => $item->profit,
            ];
        });

        return withSuccess([
            'postedGraph' => $postedGraph,
            'acceptedGraph' => $acceptedGraph,
        ]);
    }

}
