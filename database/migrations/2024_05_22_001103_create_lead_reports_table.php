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
        Schema::create('lead_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliate_id')->nullable()->index();
            $table->unsignedBigInteger('lead_id')->index();
            $table->unsignedBigInteger('list_id')->index();
            $table->unsignedBigInteger('buyer_id')->index();
            $table->unsignedBigInteger('buyer_integration_id');
            $table->unsignedBigInteger('affiliate_specs_id')->nullable();
            $table->string('sold_type')->nullable();
            $table->boolean('is_retainer')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_internal')->default(false);
            $table->decimal('lead_revenue')->default(0);
            $table->decimal('affiliate_payout')->default(0);
            $table->decimal('lead_profit')->default(0);
            $table->decimal('affiliate_margin')->default(0);
            $table->decimal('profit_margin')->default(0);
            $table->string('page_source')->nullable();
            $table->string('affm_source_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_reports');
    }
};
