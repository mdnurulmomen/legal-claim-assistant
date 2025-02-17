<?php

namespace App\Jobs;

use App\Models\DispositionConfig;
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
        info("User id {$this->configId}");
        DispositionConfig::where('id', $this->configId)->delete();
    }
}
