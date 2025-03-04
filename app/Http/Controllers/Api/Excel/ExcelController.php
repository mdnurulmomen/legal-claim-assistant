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
        set_time_limit(0);
        ini_set('memory_limit', -1);

        $validator = Validator::make($request->all(), [
            'file' => 'required|file'
        ]);

        if ($validator->fails()) {
            return withError($validator->errors()->first());
        }

        $originalFile = $request->file('file');
        $extension = $originalFile->getClientOriginalExtension();
        $allowedExtensions = ['csv', 'xls', 'xlsx'];

        if (!in_array($extension, $allowedExtensions)) {
            return withError('The file must be a CSV, XLS, or XLSX.');
        }

        try {
            $file = $originalFile->storeAs('public/import', time() . '.' . $extension);
            $path = storage_path('app/' . $file);

            if (!file_exists($path)) {
                return withError('Uploaded file could not be found.');
            }

            $data = (new FastExcel)->import($path);

            $data = $excelService->formatLeadCsvData($data);

            if (file_exists($path)) {
                unlink($path);
            }

            return withSuccess([
                'lead_data' => $data,
                'lead_columns' => $excelService->getLeadColumns($data)
            ]);

        } catch (\Throwable $th) {

            if (isset($path) && file_exists($path)) {
                unlink($path);
            }

            info($th->getMessage());
            return withError('File Upload Failed');
        }
    }
}
