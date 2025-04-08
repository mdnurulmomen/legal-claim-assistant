<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class ClearListCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected ?string $tag = null)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $url = "https://monetize.affimedia.nl/api/v1/clear-list-cache/" . $this->tag;

        try{
            $response = Http::get($url);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            info($e->getMessage());
        }
    }
}
