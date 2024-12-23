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
        Schema::create('creative_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('tag', 100)->unique();
            $table->foreignId('user_id');
            $table->string('name');
            $table->longText('description')->nullable();
            $table->mediumText('attachments')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_uploads');
    }
};
