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
        Schema::create('case_study_tag', function (Blueprint $table) {
            $table->bigInteger('case_study_id')->unsigned();
            $table->bigInteger('case_study_tag_id')->unsigned();
            $table->unique(['case_study_id', 'case_study_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_study_tag');
    }
};
