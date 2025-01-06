<?php

namespace App\Services;

use App\Helpers\Utility;
use App\Http\Controllers\Api\Lead\Resources\ExcelLeadResource;
use App\Http\Controllers\Api\Lead\Resources\LeadResource;
use App\Http\Controllers\Api\Reporting\Resources\ReportingResource;
use App\Library\Services\CountryFuzzyMatcher;
use App\Models\PlatformData;
use App\Traits\FormatterTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelService
{
    use FormatterTrait;

    /**
     * Formats the lead export data and returns it as a CSV file or a string.
     *
     * @param Builder $leadQuery
     * @return string|StreamedResponse
     */
    public function formatLeadExportData(Request $request, Builder $leadQuery): string|StreamedResponse
    {
        $from = (int) $request->from;
        $to = (int) $request->to;

        $skip = $from - ($from === 1 ? 1 : 0);
        $take = $to - $skip;

        $columns = $request->columns ? json_decode($request->columns, true) : [];
        if(empty($columns)) {
            abort(400, 'Columns are required to export data.');
        }

        [$tableColumns, $amountColumns] = $this->formatExportColumns($columns);

        $leadReport = function ($column) {
            return "(
                SELECT SUM(lead_reports.{$column})
                FROM lead_reports
                WHERE lead_reports.lead_id = platform_datas.id
                LIMIT 1
            )";
        };

        $leadQuery = $leadQuery
                        ->select($tableColumns)
                        ->when(! empty($amountColumns), function ($query) use ($amountColumns, $leadReport) {
                            foreach ($amountColumns as $column) {
                                $query->addSelect(DB::raw($leadReport($this->convertKeyToColumn($column)) . " AS {$column}"));
                            }
                        })
                        ->selectRaw("platform_datas.created_at")
                        ->skip($skip)
                        ->take($take);

        function leadGenerators($leadQuery) {
            foreach ($leadQuery->cursor() as $lead) {
                yield new ExcelLeadResource($lead);
            }
        }

        $fileName = 'Lead Export - ' . $this->formatDateTime(now(), 'M j Y g:i:s a', timezone: config('app.timezone')) . '.csv';

        $exportLead = (new FastExcel(leadGenerators($leadQuery)))
                        ->configureCsv(',', '"', 'UTF-8', false)
                        ->download($fileName);


        (new GlobalLogService())
            ->saveLogs(
                [
                    'loggable_type' => Utility::$aliasLogTypes['lead_export'],
                    'loggable_id' => $request->user ? $request->user->id : null,
                ],
                [ 'data' => $columns ]
            );

        return $exportLead;
    }

    /**
     * Format the given columns into two arrays: table columns and amount columns.
     *
     * @param array $columns
     *
     * @return array
     */
    public function formatExportColumns(array $columns): array
    {
        $fillable = (new PlatformData())->getFillable();

        $specialColumns = [
            'buyer_name' => 'buyers.name as buyer_name',
            'affiliate_name' => 'users.name as affiliate_name',
            'list_name' => 'platform_lists.name as list_name',
            'buyer_integration' => 'integrations.name as buyer_integration',
        ];

        $amountColumns = [];
        $tableColumns = ['platform_datas.id'];

        foreach ($columns as $column) {
            if (in_array($column, ['revenue', 'profit', 'affiliate_payout', 'affiliate_margin'], true)) {
                $amountColumns[] = $column;
            } elseif (in_array($column, $fillable, true)) {
                $tableColumns[] = "platform_datas.{$column}";
            } elseif (isset($specialColumns[$column])) {
                $tableColumns[] = $specialColumns[$column];
            } else {
                $tableColumns[] = "platform_datas.datas->{$column} as {$column}";
            }
        }

        return [$tableColumns, $amountColumns];
    }

    /**
     * Convert a key to the corresponding column name in the database.
     *
     * @param string $key
     *
     * @return string
     */
    public function convertKeyToColumn(string $key): string
    {
        return match($key) {
            'revenue' => 'lead_revenue',
            'profit' => 'lead_profit',
            'affiliate_payout' => 'affiliate_payout',
            'affiliate_margin' => 'affiliate_margin',
            default => $key
        };
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
            $country = isset($item['country']) ? $item['country'] : null;

            return collect($item)->mapWithKeys(function ($value, $key) use ($country) {
                $slugKey = str()->slug($key, '_');
                $newValue = $value;

                if(in_array($slugKey, ['phone', 'mobile', 'phone_number', 'mobile_number', 'mobile_no', 'phone_no', 'number'])) {
                    $newValue = (new CountryFuzzyMatcher())->formatPhoneNumber($value, $country);
                }

                return [$slugKey => $newValue];
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


    /**
     * Exports report data based on the given request parameters and query.
     *
     * @param Request $request
     * @param QueryBuilder $reportQuery
     * @return StreamedResponse
     */
    public function exportReportData(Request $request, QueryBuilder $reportQuery): StreamedResponse
    {
        $from = (int) $request->from;
        $to = (int) $request->to;

        $skip = $from - ($from === 1 ? 1 : 0);
        $take = $to - $skip;

        $columns = $request->columns ? json_decode($request->columns, true) : [];
        if(empty($columns)) {
            abort(400, 'Columns are required to export data.');
        }

        $reportQuery = $reportQuery->skip($skip)->take($take);

        function reportGenerator($reportQuery) {
            foreach ($reportQuery->cursor() as $lead) {
                yield new ReportingResource($lead);
            }
        }

        $fileName = 'Report Export - ' . $this->formatDateTime(now(), 'M j Y g:i:s a', timezone: config('app.timezone')) . '.csv';

        $exportLead = (new FastExcel(reportGenerator($reportQuery)))
                        ->configureCsv(',', '"', 'UTF-8', false)
                        ->download($fileName);


        (new GlobalLogService())
            ->saveLogs(
                [
                    'loggable_type' => Utility::$aliasLogTypes['report_export'],
                    'loggable_id' => $request->user ? $request->user->id : null,
                ],
                [ 'data' => $columns ]
            );

        return $exportLead;
    }
}
