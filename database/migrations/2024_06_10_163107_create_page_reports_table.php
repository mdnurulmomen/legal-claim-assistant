<?php

use App\Models\PageSetting;
use App\Models\SavedReport;
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
        Schema::create('page_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PageSetting::class);
            $table->foreignIdFor(SavedReport::class);
            $table->index(['page_setting_id', 'saved_report_id'], 'page_reports_page_setting_id_saved_report_id_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_reports');
    }
};
