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
        Schema::create('creative_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tag', 100)->unique();
            $table->foreignId('template_offer_id');
            $table->string('name');
            $table->mediumText('attachments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_templates');
    }
};
