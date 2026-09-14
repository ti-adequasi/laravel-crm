<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * LGPD privacy-policy/DPO detection removed by direct request — not a
     * meaningful prospecting signal for this data source (same call made
     * for the sibling LeadPeering module, which never shipped this at all).
     * The shared LeadEnrichmentService no longer returns these keys at all,
     * so the columns would only ever hold stale data from before this change.
     */
    public function up(): void
    {
        Schema::table('lead_green_prospects', function (Blueprint $table) {
            $table->dropColumn(['has_privacy_policy', 'privacy_policy_url', 'has_dpo', 'dpo_name', 'dpo_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_green_prospects', function (Blueprint $table) {
            $table->boolean('has_privacy_policy')->default(false);
            $table->text('privacy_policy_url')->nullable();
            $table->boolean('has_dpo')->default(false);
            $table->string('dpo_name')->nullable();
            $table->string('dpo_email')->nullable();
        });
    }
};
