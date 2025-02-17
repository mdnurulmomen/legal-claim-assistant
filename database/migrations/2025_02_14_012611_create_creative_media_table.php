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
        Schema::create('creative_media', function (Blueprint $table) {
            $table->id();
            $table->string('tag', 100)->unique();
            $table->foreignId('creative_upload_id');
            $table->string('file_type')->nullable();
            $table->mediumText('attachment');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_media');
    }
};
