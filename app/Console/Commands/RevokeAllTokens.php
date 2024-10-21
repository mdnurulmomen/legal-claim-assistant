<?php

namespace App\Console\Commands;

use App\Models\PageSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Concurrency;
class RevokeAllTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sanctum:revoke-all-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force logout all users by revoking all personal access tokens';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::table('personal_access_tokens')->delete();
        // $isDeleted = PageSetting::where('page', 'report')->where('type', 'table')->delete();
        // $this->info($isDeleted ? 'Deleted' : 'Not deleted');

        $this->info('All users have been logged out');

        return 0;
    }
}
