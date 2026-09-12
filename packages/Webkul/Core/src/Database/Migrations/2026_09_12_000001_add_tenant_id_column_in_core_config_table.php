<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable, not the BelongsToTenant trait — core_config deliberately
     * needs 2-level fallback (a tenant's own row first, else the global
     * row) rather than a global scope that would simply hide the global
     * row from every tenant-scoped request. See CoreConfigRepository and
     * SystemConfig::getConfigData() for where that fallback lives.
     *
     * core_config.code has no unique constraint today, so a tenant row
     * and a global row sharing the same code is already exactly the
     * shape this table tolerates — no other schema change needed.
     */
    public function up(): void
    {
        Schema::table('core_config', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('core_config', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
