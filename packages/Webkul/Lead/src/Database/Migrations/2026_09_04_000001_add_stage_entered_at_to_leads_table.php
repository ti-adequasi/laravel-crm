<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('stage_entered_at')->nullable()->after('lead_pipeline_stage_id');
        });

        // Existing leads have no recorded stage history, so the best
        // available estimate of when they entered their current stage is
        // when they were created.
        DB::table('leads')->whereNull('stage_entered_at')->update([
            'stage_entered_at' => DB::raw('created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('stage_entered_at');
        });
    }
};
