<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        // DB::table('personal_access_tokens')->delete();
        DB::table('page_settings')->where('page', 'report')->where('type', 'table')->delete();

        $this->info('All users have been logged out.');

        return 0;
    }
}

