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
/*         Schema::create('platform_lists', function (Blueprint $table) {
            $table->id();
            $table->string('tag')->index();
            $table->string('name');
            $table->string('campaign_name')->nullable();
            $table->string('source')->index();
            $table->unsignedBigInteger('total')->default(1);
            $table->json('headers')->nullable();
            $table->boolean('is_test')->default(0);
            $table->json('cv_trigger')->nullable();
            $table->json('integrations')->nullable();
            $table->json('options')->nullable();
            $table->json('insights')->nullable();
            $table->string('status')->default('Active')->index()->nullable();
            $table->timestamps();
        }); */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('platform_lists');
    }
};
