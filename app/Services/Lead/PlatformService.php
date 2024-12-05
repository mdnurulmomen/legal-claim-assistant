<?php

namespace App\Services\Lead;

use App\Jobs\GlobalPostBackTriggerJob;
use App\Models\LeadLog;
use App\Models\LeadReport;
use App\Models\PlatformData;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PlatformService
{
    /**
     * Retrieves a list of filtered leads from the platform data table.
     *
     * The request should contain the following parameters:
     *
     * @param Request $request
     * @return Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getFilteredLeads(Request $request)
    {
        $fillableKeys = (new PlatformData())->getFillable();
        $selectableKeys = $this->formatSelectableKeys($request, $fillableKeys);
        $conditions = $this->formatConditions($request, $fillableKeys);

        $leads = PlatformData::query()
                    ->select($selectableKeys)
                    ->when(! empty($conditions), function ($query) use ($conditions) {
                        foreach ($conditions as $index => $condition) {

                            $method = $this->getConditionMethod($index);

                            $query->$method(function ($query2) use ($condition) {
                                foreach ($condition as $item) {

                                    if(empty($item['isJson'])) {
                                        $query2->where($item['key'], $item['value']);
                                        continue;
                                    }
                                    $query2->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(datas, "$.' . $item['key'] . '"))) = ?', [$item['value']]);
                                }
                            });

                        };
                    })
                    ->get();

        return $leads;
    }

    /**
     * Formats the selectable keys for querying the platform data.
     *
     * @param Request $request
     * @param array $fillableKeys
     * @return array
     */
    public function formatSelectableKeys(Request $request, array $fillableKeys): array
    {
        $columns = array_map(function ($header) use ($fillableKeys) {
            return in_array($header, $fillableKeys) ? $header : "datas->{$header} as {$header}";
        }, $request->mapped_headers);

        return array_merge(['id'], $columns);
    }

    /**
     * Formats the conditions for querying the platform data.
     *
     * @param Request $request
     * @param array $fillableKeys
     * @return array
     */
    public function formatConditions(Request $request, $fillableKeys): array
    {
        $formattedLeads = [];

        foreach($request->conditions as $condition)
        {
            $childConditions = [];
            foreach($condition as $key => $value)
            {
                if(in_array($key, $fillableKeys)){
                    $childConditions[] = [
                        'key' => $key,
                        'value' => $value,
                        'isJson' => false
                    ];

                    continue;
                }

                $childConditions[] = [
                    'key' => preg_replace('/[^a-zA-Z0-9_.]/', '', $key),
                    'value' => strtolower($value),
                    'isJson' => true
                ];
            }

            $formattedLeads[] = $childConditions;
        }

        return $formattedLeads;
    }

    /**
     * Returns the condition method based on the given index.
     *
     * @param int $index
     * @return string
     */
    public function getConditionMethod(int $index): string
    {
       return $index == 0 ? 'where' : 'orWhere';
    }

    /**
     * Formats the given leads and updates the given fields in the platform data table.
     *
     * The request should contain the following parameters:
     *
     * - `leads`: An array of objects where each object contains the following keys:
     *   - `id`: The ID of the lead to be updated.
     *   - `$fillable`: The fields in the platform data table that can be updated.
     *
     * @param Request $request
     * @return void
     */
    public function formatAndUpdateLeads(Request $request): void
    {
        $filledData = collect($request->leads);
        if(empty($filledData)) {
            throw new \Exception('No data provided');
        }

        $fillable = (new PlatformData())->getFillable();
        $fields = collect($filledData[0])->keys();

        $selectableFields = $fields->filter(fn($field) => in_array($field, $fillable))->toArray();
        $isAmountField = in_array('revenue', $fields->toArray()) || in_array('affiliate_payout', $fields->toArray());

        $leads = PlatformData::query()
                    ->select('id', 'datas', ... $selectableFields)
                    ->whereIn('id', $filledData->pluck('id'))
                    ->when(! empty($isAmountField), function($query) {
                        return $query->addSelect([
                            'affiliate_id',
                            'list_id',
                            'buyer_id',
                            'affid',
                            'buyer_integration_id',
                            'affiliate_specs_id',
                            'affm_source_id'
                        ]);
                    })
                    ->get();

        if(empty($leads)) {
            throw new \Exception('No leads found');
        }

        $leadGenerator = $this->generateLeadData($filledData, $leads->toArray(), $fillable);
        $allLeadData = iterator_to_array($leadGenerator, false);

        if(empty($allLeadData)) {
            throw new \Exception('No leads found');
        }

        $platformUpdatableField = collect($selectableFields)
                                    ->filter(fn($field) => ! in_array($field, ['revenue', 'affiliate_payout']))
                                    ->push('datas')
                                    ->toArray();

        PlatformData::upsert(
            $allLeadData,
            ['id'],
            $platformUpdatableField
        );

        $leadData = collect($allLeadData);
        $leadGroup = $leadData->groupBy('id')->all();
        $leadIds = $leadData->pluck('id')->all();

        $this->updateRelevantLeadReports($fields, $leadGroup, $leadIds);
        $this->updateRelevantLeadLog($fields, $leadGroup, $leadIds, $fillable);
    }

    /**
     * Updates the lead reports in the database based on the provided updated leads data.
     *
     * @param Collection $fields
     * @param array $leadGroup
     * @param array $leadIds
     *
     * @return void
     */
    public function updateRelevantLeadReports(Collection $fields, array $leadGroup, array $leadIds): void
    {
        $updatableReportFields = $fields
                                    ->filter(fn($item) => in_array($item, ['affid', 'revenue', 'affiliate_payout']))
                                    ->map(fn($item) => $item === 'revenue' ? 'lead_revenue' : $item)
                                    ->all();

        if(empty($updatableReportFields)) return;

        $leadReports = LeadReport::whereIn('lead_id', $leadIds)
                            ->select(
                                'id',
                                'lead_id',
                                'affiliate_id',
                                'list_id',
                                'buyer_id',
                                'buyer_integration_id',
                                'affiliate_specs_id',
                                'affm_source_id',
                                'is_retainer',
                                'affid',
                                'lead_revenue',
                                'affiliate_payout',
                                'lead_profit',
                                'affiliate_margin',
                                'profit_margin'
                            )
                            ->get();

        if(empty($leadReports)) return;

        $leadRevenuePayouts = [];

        $leadReportDataGenerator = $this->generateLeadReportData($leadReports->toArray(), $leadGroup, $updatableReportFields, $leadRevenuePayouts);
        $leadReportData = iterator_to_array($leadReportDataGenerator, false);

        if(count($leadReportData) > 0) {
            LeadReport::upsert(
                $leadReportData,
                ['id'],
                $updatableReportFields
            );

            GlobalPostBackTriggerJob::dispatch([
                'type' => 'bulk_retainer',
                'lead_reports' => $leadReportData
            ]);
        }

        if(count($leadRevenuePayouts) > 0) {
            PlatformData::upsert(
                $leadRevenuePayouts,
                ['id'],
                ['payout', 'revenue']
            );
        }
    }

    /**
     * Updates the lead logs for a collection of leads.
     *
     * @param array $updatableFields
     * @param array $leadGroup
     * @param array $leadIds
     * @param array $fillable
     *
     * @return void
     */
    public function updateRelevantLeadLog(Collection $fields, array $leadGroup, array $leadIds, array $fillable): void
    {
        $logs = LeadLog::whereIn('lead_id', $leadIds)
                    ->select('id', 'lead_id', 'log_data')
                    ->get();

        $logDataGenerator = $this->generatorLeadLogs($logs, $leadGroup, $fields, $fillable);
        $logData = iterator_to_array($logDataGenerator, false);

        if(empty($logData)) return;

        LeadLog::upsert(
            $logData,
            ['id'],
            ['log_data']
        );
    }

    /**
     * Generator to generate lead log data from the given $logs and $leadGroup data.
     * This will iterate over the lead logs and update the original payload of the log data
     * with the updatable fields from the lead group data.
     *
     * @param \Illuminate\Database\Eloquent\Collection $logs
     * @param array $leads
     * @param array $updatableFields
     * @param array $fillable
     *
     * @yield array
     */
    private function generatorLeadLogs($logs, array $leads, Collection $fields, array $fillable): Generator
    {
        $fields = $fields->reject(fn($item) => in_array($item, ['id']))->all();

        foreach($logs as $log) {
            $lead = $leads[$log->lead_id][0] ?? null;
            if(empty($lead)) continue;

            $logData = $log->log_data;
            $originalPayload = $logData && $logData['original_payload'] ? $logData['original_payload'] : null;
            if(empty($originalPayload)) continue;

            $data = is_string($lead['datas']) ? json_decode($lead['datas'], true) : $lead['datas'];

            foreach($fields as $key){
                if(! array_key_exists($key, $originalPayload)) continue;

                if(in_array($key, $fillable)) {
                    $originalPayload[$key] = $lead[$key];
                }

                if(array_key_exists($key, $data)) {
                    $originalPayload[$key] = $data[$key];
                }
            }

            $logData['original_payload'] = $originalPayload;

            yield [
                'id' => $log->id,
                'log_data' => json_encode($logData)
            ];
        }
    }

    /**
     * Generator to generate lead report data from the given $leadReports and $leadGroup data.
     * This will also update the $leadRevenuePayouts array with the revenue and payout data.
     *
     * @param array $leadReports
     * @param array $leadGroup
     * @param array $updatableReportFields
     * @param array $leadRevenuePayouts
     * @return Generator
     */
    private function generateLeadReportData(array $leadReports, array $leadGroup, array $updatableReportFields, &$leadRevenuePayouts): Generator
    {
        $lead = null;
        $isRetainer = false;

        $revenue = 0;
        $payout = 0;
        $totalReports = count($leadReports) - 1;

        foreach ($leadReports as $key => $report) {

            if(empty($lead) || ($lead && $lead['id'] != $report['lead_id'])) {

                if(! empty($lead) && ! $isRetainer) {
                    $newReport = $this->getFormData($lead);
                    $this->sumRevenuePayout($revenue, $payout, $newReport);
                    yield $newReport;
                }

                if(! empty($lead)) {
                    $leadRevenuePayouts[] = $this->getRevenuePayout($lead['id'], $revenue, $payout);;
                }

                $lead = $leadGroup[$report['lead_id']][0] ?? null;
                $this->resetInitialKeys($isRetainer, $revenue, $payout);
            }

            if (empty($lead)) continue;

            foreach ($updatableReportFields as $field) {
                if(in_array($field, ['lead_revenue', 'affiliate_payout'])) continue;
                $report[$field] = $lead[$field];
            }

            if(($lead['id'] === $report['lead_id']) && !empty($report['is_retainer'])) {

                if(array_key_exists('revenue', $lead)) {
                    $report['lead_revenue'] = $lead['revenue'];
                }

                if(array_key_exists('affiliate_payout', $lead)) {
                    $report['affiliate_payout'] = $lead['affiliate_payout'];
                }

                $amountFields = $this->calculateRevenuePayout($report['lead_revenue'], $report['affiliate_payout']);
                $report = array_merge($report, $amountFields);

                $isRetainer = true;
            }

            $this->sumRevenuePayout($revenue, $payout, $report);

            yield $report;

            if($key == $totalReports) {
                if(! empty($lead) && ! $isRetainer) {
                    $newReport = $this->getFormData($lead);
                    $this->sumRevenuePayout($revenue, $payout, $newReport);
                    yield $newReport;
                }

                if(! empty($lead)) {
                    $leadRevenuePayouts[] = $this->getRevenuePayout($lead['id'], $revenue, $payout);
                }

                $this->resetInitialKeys($isRetainer, $revenue, $payout);
            }
        }
    }

    /**
     * Resets the initial keys (isRetainer, revenue, payout) to their default values.
     *
     * @param bool $isRetainer
     * @param float $revenue
     * @param float $payout
     */
    private function resetInitialKeys(&$isRetainer, &$revenue, &$payout)
    {
        $isRetainer = false;
        $revenue = 0;
        $payout = 0;
    }

    /**
     * Sums the revenue and payout from the given report to the given variables.
     *
     * @param float $revenue
     * @param float $payout
     * @param array $report
     */
    private function sumRevenuePayout(&$revenue, &$payout, $report): void
    {
        $revenue += (float) $report['lead_revenue'];
        $payout += (float) $report['affiliate_payout'];
    }

    /**
     * Returns an array containing the lead ID, revenue and payout.
     * The payout is calculated by subtracting the affiliate payout from the revenue.
     *
     * @param int $leadId
     * @param float $revenue
     * @param float $affiliatePayout
     * @return array
     */
    private function getRevenuePayout($leadId, $revenue, $payout) {
        return [
            'id' => $leadId,
            'revenue' => $revenue,
            'payout' => $revenue - $payout
        ];
    }

    /**
    * Retrieves the form data for creating a new lead report from the given lead data and any revenue/payout data.
    *
    * @param PlatformData $lead
    * @param array $leadReportData
    * @return array
    */
    public function getFormData(array $lead): array
    {
        $formData = [
            'id' => null,
            'affiliate_id' => $lead['affiliate_id'] ?? null,
            'lead_id' => $lead['id'] ?? null,
            'list_id' => $lead['list_id'] ?? null,
            'buyer_id' => $lead['buyer_id'] ?? null,
            'affid' => $lead['affid'] ?? null,
            'buyer_integration_id' => $lead['buyer_integration_id'] ?? null,
            'affiliate_specs_id' => $lead['affiliate_specs_id'] ?? null,
            'affm_source_id' => $lead['affm_source_id'] ?? null,
            'is_retainer' => true,
            'lead_revenue' => $lead['revenue'] ?? 0,
            'affiliate_payout' => $lead['affiliate_payout'] ?? 0,
            'lead_profit' => 0,
            'affiliate_margin' => 0,
            'profit_margin' => 0
        ];

        $reportData = $this->calculateRevenuePayout((float) $formData['lead_revenue'], (float) $formData['affiliate_payout']);

        return array_merge($formData, $reportData);
    }

    /**
     * Calculates the revenue, profit, affiliate margin and profit margin given the revenue and affiliate payout.
     *
     * @param float|int $revenue
     * @param float|int $affiliatePayout
     * @return array
     */
    public function calculateRevenuePayout( float | int $revenue = 0, float | int $affiliatePayout): array
    {
        $profit = $revenue - $affiliatePayout;
        $affiliateMargin = $revenue ? (($affiliatePayout / $revenue) * 100) : 0;
        $profitMargin = $revenue ? (($profit / $revenue) * 100) : 0;

        return [
            'lead_profit' => $profit,
            'affiliate_margin' => $affiliateMargin,
            'profit_margin' => $profitMargin
        ];
    }

    /**
     * Generate a generator of lead data with updated fields
     *
     * @param Collection $filledData
     * @param array $leads
     * @param array $fillable
     *
     * @return Generator
     */
    private function generateLeadData(Collection $filledData, array $leads, array $fillable): Generator
    {
        $groupFilledData = $filledData->groupBy('id')->all();

        foreach ($leads as &$lead) {

            $newLead = $groupFilledData[$lead['id']][0] ?? null;
            if(empty($newLead)) continue;

            unset($newLead['id']);
            $data = $lead['datas'];

            foreach($newLead as $key => $value) {

                if(in_array($key, $fillable)) {
                    $lead[$key] = $value;
                    continue;
                }

                if(array_key_exists($key, $data)) {
                    $data[$key] = $value;
                }
            }

            $lead['datas'] = json_encode($data);

            yield $lead;
        }
    }
}
