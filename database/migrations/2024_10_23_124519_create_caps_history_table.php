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
        Schema::create('caps_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('list_id')->index();
            $table->unsignedBigInteger(column: 'integration_id');
            $table->unsignedBigInteger('buyer_id');
            $table->json('caps');
            $table->index(['list_id', 'integration_id', 'buyer_id', 'column_scope'], 'caps_history_list_integration_buyer_index');
            $table->string('status')->default('Active');
            $table->string('column_scope')->default('None');
            $table->bigInteger('cap_amount')->default(0);
            $table->string('duration')->default('daily');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caps_history');
    }
};
