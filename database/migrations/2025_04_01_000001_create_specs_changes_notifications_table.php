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
        Schema::create('specs_changes_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('email_template_id')->nullable()->constrained('email_templates');
            $table->string('status');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specs_changes_notifications');
    }
};