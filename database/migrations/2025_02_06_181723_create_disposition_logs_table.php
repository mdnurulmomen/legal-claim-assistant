<?php

use App\Models\DispositionConfig;
use App\Models\PlatformData;
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
        Schema::create('disposition_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(DispositionConfig::class)->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->foreignIdFor(PlatformData::class);
            $table->string('lead_status')->index()->nullable();
            $table->boolean('is_duplicate')->index()->default(0);
            $table->json('data')->nullable();
            $table->json('updatable_data')->nullable();
            $table->index(['platform_data_id', 'lead_status', 'is_duplicate'], 'data_id_lead_status_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disposition_logs');
    }
};
