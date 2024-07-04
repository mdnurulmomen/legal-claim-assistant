<?php

namespace App\Services;

use App\Http\Controllers\Api\Lead\Resources\LeadResource;
use Rap2hpoutre\FastExcel\FastExcel;

class ExcelService
{
    public function formatLeadExportData($leadQuery)
    {
        function leadGenerators($leadQuery) {
            foreach ($leadQuery->cursor() as $lead) {
                yield new LeadResource($lead);
            }
        }

        return (new FastExcel(leadGenerators($leadQuery)))->configureCsv(',', '"', 'UTF-8', false)->download('leads.csv');
    }
}
