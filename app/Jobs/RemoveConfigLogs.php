<?php

namespace App\Jobs;

use App\Models\DispositionConfig;
use App\Models\DispositionLog;
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
    public function __construct(protected int $configId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        do {
            $deletedRows = DispositionLog::where('disposition_config_id', $this->configId)->limit(1000)->delete();
        } while ($deletedRows > 0);

        DispositionConfig::where('id', $this->configId)->delete();
    }
}
