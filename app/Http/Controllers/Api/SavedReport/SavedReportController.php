<?php

namespace App\Http\Controllers\Api\SavedReport;

use App\Http\Controllers\Api\SavedReport\Requests\SavedReportRequest;
use App\Http\Controllers\Api\SavedReport\Resources\SavedReportResource;
use App\Http\Controllers\Controller;
use App\Models\SavedReport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SavedReportController extends Controller
{
    public function reportList(Request $request): Response
    {
        $limit = $request->input('limit', 10);

        $savedReports = SavedReport::query()
                            ->whereUserId(auth()->id())
                            ->simplePaginate($limit);

        return withSuccess($savedReports);
    }

    /**
     * Store a new saved report.
     *
     * @param SavedReportRequest $request
     * @return Response
     */
    public function storeReport(SavedReportRequest $request): Response
    {
        try {
            DB::beginTransaction();

            $savedReport = SavedReport::create($request->validated());
            $savedReport->pageSettings()->attach($request->page_setting_ids);

            DB::commit();

            return withSuccess(new SavedReportResource($savedReport), 'Saved Report created successfully');

        } catch (\Throwable $th) {
           DB::rollBack();

           return withError('Failed to create Saved Report', 400);
        }
    }

    /**
     * Retrieves a saved report by its unique identifier.
     *
     * @param Request $request
     * @param string $uid
     * @return Response
     */
    public function showReport(Request $request, string $uid): Response
    {
        $report = SavedReport::where('uid', $uid)->first();
        if(empty($report)){
            return withError('Saved Report not found', 404);
        }

        return withSuccess(new SavedReportResource($report));
    }

    /**
     * Updates a saved report.
     *
     * @param SavedReportRequest $request
     * @param string $uid
     * @return Response
     */
    public function updateReport(SavedReportRequest $request, string $uid): Response
    {
        $report = SavedReport::where('uid', $uid)->first();
        if(empty($report)){
            return withError('Saved Report not found', 404);
        }

        try {
            DB::beginTransaction();

            $report->pageSettings()->sync($request->page_setting_ids);
            $report->update($request->validated());

            DB::commit();
            return withSuccess($report, 'Saved Report updated successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return withError('Failed to update Saved Report', 400);
        }
    }

    /**
     * Deletes a saved report.
     *
     * @param Request $request
     * @param string $uid
     * @return Response
     */
    public function deleteReport(Request $request, string $uid): Response
    {
        $report = SavedReport::where('uid', $uid)->first();
        if(empty($report)){
            return withError('Saved Report not found', 404);
        }

        try {
            DB::beginTransaction();

            $report->pageSettings()->detach();
            $report->delete();

            DB::commit();
            return withSuccess(message: 'Saved Report deleted successfully');

        } catch (\Throwable $th) {
            DB::rollBack();

            return withError('Failed to delete Saved Report', 400);
        }
    }
}
