<?php

namespace App\Services;

use App\Http\Controllers\Api\Lead\Resources\LeadResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelService
{
    /**
     * Formats the lead export data and returns it as a CSV file or a string.
     *
     * @param Builder $leadQuery
     * @return string|StreamedResponse
     */
    public function formatLeadExportData(Builder $leadQuery): string | StreamedResponse
    {
        function leadGenerators($leadQuery) {
            foreach ($leadQuery->cursor() as $lead) {
                yield new LeadResource($lead);
            }
        }

        return (new FastExcel(leadGenerators($leadQuery)))
                    ->configureCsv(',', '"', 'UTF-8', false)
                    ->download('leads.csv');
    }

    /**
     * Formats the lead CSV data by converting keys to slugs.
     *
     * @param Collection $data
     * @return array
     */
    public function formatLeadCsvData(Collection $data): array
    {
        return $data->map(function ($item) {
            return collect($item)->mapWithKeys(function ($value, $key) {
                return [str()->slug($key, '_') => $value];
            })->all();
        })->all();
    }

    /**
     * A function to extract unique lead columns from the given data array.
     *
     * @param array $data
     * @return array
     */
    public function getLeadColumns(array $data): array
    {
        $allKeys = collect($data)->reduce(function ($carry, $item) {
            return array_merge($carry, array_keys($item));
        }, []);

        $uniqueKeys = collect($allKeys)->unique()->values()->map(function ($item) {
            return [
                'value' => $item,
                'label' => ucwords(str_replace('_', ' ', $item)),
                'model_value' => $item,
                'options' => [],
                'is_filled' => false
            ];
        })
        ->all();

        return $uniqueKeys;
    }
}
