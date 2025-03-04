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
        Schema::table('global_postbacks', function (Blueprint $table) {
            $table->string('postback_event')->nullable()->after('conditions');
            $table->text('url')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_postbacks', function (Blueprint $table) {
            $table->dropColumn(['postback_event']);
            $table->string('url', 255)->change();
        });
    }
};
