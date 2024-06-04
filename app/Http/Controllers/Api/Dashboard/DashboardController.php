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
            ->select(DB::raw('DATE_FORMAT(created_at, "%a, %d") as day, COUNT(CASE WHEN is_posted = 1 THEN 1 END) as posted, COUNT(CASE WHEN buyer_id IS NOT NULL and lead_id IS NOT NULL THEN 1 END) as accepted, SUM(lead_revenue) as revenue, SUM(lead_profit) as profit'))
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

    /**
     * Get top buyers for dashboard
     *
     * @return Response
     */
    public function getTopBuyers(): Response
    {
        $topBuyers = DB::table('lead_reports')
            ->join('buyers', 'lead_reports.buyer_id', '=', 'buyers.id')
            ->select('buyers.name', 'buyers.company_name as companyName', DB::raw('count(case when lead_id is not null then 1 end) as totalConvertedLeads, sum(lead_revenue) as totalRevenue'))
            ->whereBetween('lead_reports.created_at', [now()->startOfMonth(), now()->endOfDay()])
            ->groupBy('buyers.id')
            ->orderBy('totalRevenue', 'desc')
            ->limit(5)
            ->get();

        return withSuccess($topBuyers);
    }

    /**
     * Get top affiliates for dashboard
     *
     * @return Response
     */
    public function getTopAffiliates(): Response
    {
        $topAffiliates = DB::table('lead_reports')
            ->join('users', 'lead_reports.affiliate_id', '=', 'users.id')
            ->leftJoin('affiliates', 'lead_reports.affiliate_id', '=', 'affiliates.user_id')
            ->select('users.name', 'affiliates.company_name as companyName', DB::raw('count(case when is_posted = 1 then 1 end) as totalPosted, count(case when buyer_id is not null and lead_id is not null then 1 end) as totalConvertedLeads, sum(affiliate_payout) as totalEarned'))
            ->whereBetween('lead_reports.created_at', [now()->startOfMonth(), now()->endOfDay()])
            ->groupBy('affiliates.id')
            ->orderBy('totalEarned', 'desc')
            ->limit(5)
            ->get();

        $topAffiliates = collect($topAffiliates)->map(function ($item) {
            return [
                'name' => $item->name,
                'companyName' => $item->companyName,
                'totalPosted' => $item->totalPosted,
                'totalConvertedLeads' => $item->totalConvertedLeads,
                'AR' => $item->totalPosted > 0 ? $item->totalConvertedLeads / $item->totalPosted * 100 : 0,
                'totalEarned' => $item->totalEarned,
            ];
        });


        return withSuccess($topAffiliates);
    }

    /**
     * Get top lists for dashboard
     *
     * @return Response
     */
    public function getTopLists(): Response
    {
        $topLists = DB::table('lead_reports')
            ->join('platform_lists', 'lead_reports.list_id', '=', 'platform_lists.id')
            ->select('platform_lists.name', DB::raw('count(case when is_posted = 1 then 1 end) as totalPosted, count(case when buyer_id is not null and lead_id is not null then 1 end) as totalConvertedLeads, count(case when sold_type = "CPL" then 1 end) as totalCPLConvertedLeads, sum(lead_revenue) as totalRevenue, sum(lead_profit) as totalProfit'))
            ->whereBetween('lead_reports.created_at', [now()->startOfMonth(), now()->endOfDay()])
            ->groupBy('platform_lists.id')
            ->orderBy('totalRevenue', 'desc')
            ->limit(5)
            ->get();

        $topLists = collect($topLists)->map(function ($item) {
            return [
                'name' => $item->name,
                'totalPosted' => $item->totalPosted,
                'totalConvertedLeads' => $item->totalConvertedLeads,
                'AR' => $item->totalPosted > 0 ? $item->totalConvertedLeads / $item->totalPosted * 100 : 0,
                'AR_CPL' => $item->totalPosted > 0 ? $item->totalCPLConvertedLeads / $item->totalPosted * 100 : 0,
                'totalRevenue' => $item->totalRevenue,
                'totalProfit' => $item->totalProfit,
            ];
        });

        return withSuccess($topLists);
    }

}
