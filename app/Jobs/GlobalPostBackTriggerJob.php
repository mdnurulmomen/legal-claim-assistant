<?php

namespace App\Jobs;

use App\Models\GlobalPostback;
use App\Models\LeadReport;
use App\Models\PlatformData;
use App\Models\PlatformList;
use App\Services\PostBackTriggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GlobalPostBackTriggerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected array $data)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if($this->data['type'] === 'bulk_retainer') {
            $this->triggerBulkRetainer($this->data['lead_reports']);
            return;
        }

        if($this->data['type'] === 'single_retainer') {
            $this->triggerSingleRetainer($this->data['lead_id']);
            return;
        }
    }

    public function triggerBulkRetainer(array $leadReports) {

        $reportGroups = collect($leadReports)->groupBy('lead_id');
        $newReportConditions = [];

        foreach($reportGroups as $leadId => $leadReport) {
            $newReportConditions[] = [
                'lead_id' => $leadId,
                'report_ids' => collect($leadReport)->pluck('id')->filter()->values()
            ];
        }

        LeadReport::query()
            ->where(function($query) use ($newReportConditions) {
                foreach($newReportConditions as $key => $newReportCondition) {
                    $method = $this->getConditionMethod($key);

                    $query->$method(function($query) use ($newReportCondition) {
                        $query->where('lead_id', $newReportCondition['lead_id'])
                        ->whereNotIn('id', $newReportCondition['report_ids']);
                    });
                }
            })
            ->select('id', 'lead_id')
            ->lazy(1000)
            ->each(function($leadReport) {
                $this->triggerSingleRetainer($leadReport->lead_id);
            });
    }

    /**
     * Triggers a single post back for the given lead id.
     *
     * @param int $leadId
     */
    public function triggerSingleRetainer(int $leadId)
    {
        $lead = PlatformData::find($leadId);
        if(empty($lead)) {
            \Sentry\captureMessage('Lead not found: ' . $leadId);
            return;
        }

        $list = PlatformList::find($lead->list_id);
        if(empty($list)) {
            \Sentry\captureMessage('List not found: ' . $lead->list_id);
            return;
        }

        $triggerData = [
            "payload" => $lead->toArray(),
            "platform_list" => $list->toArray()
        ];

        (new PostBackTriggerService())->trigger($triggerData);
    }

    /**
     * Returns the condition method based on the given index.
     *
     * @param int $index
     * @return string
     */
    public function getConditionMethod(int $index): string
    {
        return ($index == 0) ? 'where' : 'orWhere';
    }

}
