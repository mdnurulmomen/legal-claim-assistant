<?php

namespace App\Http\Controllers\Api\Excel;

use App\Http\Controllers\Controller;
use App\Services\ExcelService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Rap2hpoutre\FastExcel\FastExcel;

class ExcelController extends Controller
{
    /**
     * Uploads a CSV file containing lead data and returns the formatted data along with the lead columns.
     *
     * @param Request $request
     * @param ExcelService $excelService
     * @return Response
     * @throws \Throwable
     */
    public function uploadLeadCsv(Request $request, ExcelService $excelService): Response
    {
        $validator = Validator::make($request->all(), [
                            'file' => 'required|file|mimes:xlsx,csv,xls',
                        ]);

        if ($validator->fails()) {
            return withError($validator->errors()->first());
        }

        try {

            $file = $request->file('file')->store('public/import');
            $path = storage_path('app/' . $file);
            $data = (new FastExcel)->import($path);
            $data = $excelService->formatLeadCsvData($data);
            unlink($path);

            return withSuccess([
                'lead_data' => $data,
                'lead_columns' => $excelService->getLeadColumns($data)
            ]);
        } catch (\Throwable $th) {
            unlink($path);
            info($th->getMessage());
            return withError('File Upload Failed');
        }
    }
}
