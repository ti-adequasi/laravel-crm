<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant toggle for auto-logging every click-to-call as a Lead/Person
     * Activity (Phase 3) — defaults on, since that's the more useful default
     * for a team that doesn't place enough calls for it to be noisy. A team
     * that finds it noisy can turn it off without losing any history: the
     * live call-history panel (Phase 4) queries the PBX directly regardless
     * of this setting.
     */
    public function up(): void
    {
        Schema::table('pbx_settings', function (Blueprint $table) {
            $table->boolean('auto_log_activity')->default(true)->after('enabled');
        });
    }

    public function down(): void
    {
        Schema::table('pbx_settings', function (Blueprint $table) {
            $table->dropColumn('auto_log_activity');
        });
    }
};
