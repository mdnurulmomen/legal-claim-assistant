<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mongodb')->create('disposition_log_mongos', function (Blueprint $collection) {
            $collection->index('disposition_config_mongo_id');
            $collection->index('platform_data_id');
            $collection->index('lead_status');
            $collection->index('is_duplicate');
            $collection->index('platform_data_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mongodb')->dropIfExists('disposition_log_mongos');
    }
};
