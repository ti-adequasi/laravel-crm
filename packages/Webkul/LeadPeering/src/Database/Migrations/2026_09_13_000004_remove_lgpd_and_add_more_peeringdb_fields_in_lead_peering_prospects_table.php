<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Two changes requested directly after the module's first ship:
     * - LGPD privacy-policy/DPO detection dropped entirely — not a
     *   meaningful prospecting signal for this data source (same call
     *   made for LeadGreen in a companion migration there).
     * - More of PeeringDB's own real fields captured, so a prospect
     *   carries genuine PeeringDB detail beyond just identity/location:
     *   `info_scope`/`policy_general` (net), `region_continent` and
     *   direct sales/tech contacts (fac) — the latter is real, structured
     *   contact data PeeringDB provides itself, not something enrichment
     *   has to go scrape a website for.
     */
    public function up(): void
    {
        Schema::table('lead_peering_prospects', function (Blueprint $table) {
            $table->dropColumn(['has_privacy_policy', 'privacy_policy_url', 'has_dpo', 'dpo_name', 'dpo_email']);

            $table->string('info_scope')->nullable()->after('info_traffic');
            $table->string('policy_general')->nullable()->after('info_scope');
            $table->string('region_continent')->nullable()->after('ix_count');
            $table->string('sales_email')->nullable()->after('region_continent');
            $table->string('sales_phone')->nullable()->after('sales_email');
            $table->string('tech_email')->nullable()->after('sales_phone');
            $table->string('tech_phone')->nullable()->after('tech_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_peering_prospects', function (Blueprint $table) {
            $table->dropColumn(['info_scope', 'policy_general', 'region_continent', 'sales_email', 'sales_phone', 'tech_email', 'tech_phone']);

            $table->boolean('has_privacy_policy')->default(false);
            $table->text('privacy_policy_url')->nullable();
            $table->boolean('has_dpo')->default(false);
            $table->string('dpo_name')->nullable();
            $table->string('dpo_email')->nullable();
        });
    }
};
