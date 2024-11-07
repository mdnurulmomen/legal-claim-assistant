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
        if( !Schema::hasTable('account_managers') ) {
            Schema::create('account_managers', function (Blueprint $table) {
                // $table->id();
                $table->unsignedBigInteger('affiliate_id');
                $table->unsignedBigInteger('user_id');
                $table->unique(['affiliate_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('account_managers');
    }
};
