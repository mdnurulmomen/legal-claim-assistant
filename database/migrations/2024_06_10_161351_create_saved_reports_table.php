<?php

use App\Models\PageSetting;
use App\Models\User;
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
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignIdFor(User::class)->nullable()->constrained();
            $table->string('title');
            $table->json('filters')->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->index(['uid', 'title', 'visited_at'], 'saved_reports_uid_title_visited_at_index');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
