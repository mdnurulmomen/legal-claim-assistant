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
    public function __construct(protected array $data, protected string $postBackType = 'on_retainer_added')
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        if($this->data['type'] === 'bulk_retainer') {
            $this->triggerBulkRetainer($this->data['lead_ids']);
            return;
        }

        $this->triggerSingleRetainer($this->data['lead_id']);
    }

    public function triggerBulkRetainer(array $leadIds) {
        foreach($leadIds as $leadId) {
            $this->triggerSingleRetainer($leadId);
        }
    }

    /**
     * Triggers a single post back for the given lead id.
     *
     * @param int $leadId
     */
    public function triggerSingleRetainer(int $leadId)
    {
        $lead = PlatformData::query()
                    ->leftJoin('buyers', 'buyers.id', 'platform_datas.buyer_id')
                    ->leftJoin('platform_lists as pl', 'pl.id', 'platform_datas.list_id')
                    ->select('platform_datas.*', 'buyers.name as buyer_name', 'pl.name as list_name')
                    ->find($leadId);

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

        (new PostBackTriggerService())->trigger($triggerData, $this->postBackType);
    }

}
