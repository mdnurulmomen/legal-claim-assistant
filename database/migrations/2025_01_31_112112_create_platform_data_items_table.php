<?php

use App\Models\PlatformData;
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
        Schema::create('platform_data_items', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PlatformData::class)->constrained('platform_datas', 'id');
            $table->string('field')->nullable();
            $table->string('value')->nullable();
            $table->string('key_type')->nullable();
            $table->text('additional_info')->nullable();
            $table->index(['field', 'value', 'key_type'], 'platform_data_items_field_value_type_index');
            $table->timestamps();
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->string('lead_id_key')->index()->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_data_items');

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('lead_id_key');
        });
    }
};
