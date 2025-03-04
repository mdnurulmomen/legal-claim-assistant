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
        if( !Schema::hasTable('conference_events') ) {
            Schema::create('conference_events', function (Blueprint $table) {
                $table->id();
                $table->string('tag', 100)->unique();
                $table->string('title');
                $table->string('thumb')->nullable();
                $table->longText('description')->nullable();
                $table->date('event_start_date')->nullable();
                $table->date('event_end_date')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('conference_events');
    }
};
