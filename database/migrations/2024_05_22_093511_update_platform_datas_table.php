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
            $table->unsignedBigInteger('buyer_id')->nullable()->after('is_retainer')->index();
            $table->unsignedBigInteger('buyer_integration_id')->nullable()->after('is_retainer');
            $table->boolean('is_sold')->after('is_retainer')->default(0);
            $table->unsignedBigInteger('affiliate_specs_id')->nullable()->after('is_retainer');
            $table->string('sold_type')->nullable()->after('retained_date');
            $table->string('page_source')->nullable()->after('retained_date');
            $table->string('affm_source_id')->nullable()->after('retained_date');
            $table->string('affid')->nullable()->index()->after('list_id');
            $table->unsignedBigInteger('affiliate_id')->nullable()->after('list_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
