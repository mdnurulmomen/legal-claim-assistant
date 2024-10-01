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
        Schema::table('admin_roles', function (Blueprint $table) {
            $table->string('admin_role')->after('name')->default('admin')->index();
            $table->boolean('is_show_affiliate')->after('admin_role')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_roles', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->dropColumn('is_show_affiliate');
        });
    }
};
