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
        Schema::table('lead_green_prospects', function (Blueprint $table) {
            // Nullable, not a plain boolean default: null means "Disify was
            // never reached," distinct from a real fail (false) — see
            // LeadEnrichmentService::verifyEmail().
            $table->boolean('email_verified')->nullable()->after('email_quality');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lead_green_prospects', function (Blueprint $table) {
            $table->dropColumn('email_verified');
        });
    }
};
