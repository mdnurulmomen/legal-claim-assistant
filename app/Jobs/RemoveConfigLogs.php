<?php

namespace App\Jobs;

use App\Models\DispositionConfigMongo;
use App\Models\DispositionLogMongo;
use App\Models\DispositionMissingRecordMongo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RemoveConfigLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected string $configId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        do {
            $deletedRows = DispositionLogMongo::where('disposition_config_id', $this->configId)->limit(5000)->delete();
        } while ($deletedRows > 0);

        do {
            $missingRows = DispositionMissingRecordMongo::where('disposition_config_id', $this->configId)->limit(5000)->delete();
        } while ($missingRows > 0);

        DispositionConfigMongo::where('id', $this->configId)->delete();
    }
}
