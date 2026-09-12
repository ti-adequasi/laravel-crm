<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PBX extension ("ramal") an agent answers on — nullable, since not
     * every user is a phone agent. Lives here (not in Webkul\Pbx) because
     * it's a column on a table Webkul\User already owns, matching how the
     * tenant_id columns were placed in each table's own owning package
     * rather than all bundled in Webkul\Tenant.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('extension')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('extension');
        });
    }
};
