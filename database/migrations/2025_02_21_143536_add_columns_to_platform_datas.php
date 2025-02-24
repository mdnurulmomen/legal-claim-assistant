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
        Schema::table('platform_datas', function (Blueprint $table) {
            $table->datetime('returned_date')->nullable()->after('retained_date');
            $table->boolean('is_returned')->default(0)->after('is_retainer');
        });

        Schema::table('lead_reports', function (Blueprint $table) {
            $table->boolean('is_returned')->default(0)->after('is_retainer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_datas', function (Blueprint $table) {
            $table->dropColumn(['returned_date', 'is_returned']);
        });

        Schema::table('lead_reports', function (Blueprint $table) {
            $table->dropColumn(['is_returned']);
        });
    }
};
