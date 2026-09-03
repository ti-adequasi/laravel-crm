<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Organizations have no "site" attribute out of the box — LeadGreen's
     * findOrCreateOrganization() (and LeadEnrichment, which reads it back)
     * both depend on one existing. Created here, guarded, since either
     * package could be installed first.
     */
    public function up(): void
    {
        $exists = DB::table('attributes')
            ->where('code', 'site')
            ->where('entity_type', 'organizations')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('attributes')->insert([
            'code'            => 'site',
            'name'            => 'Site',
            'type'            => 'text',
            'entity_type'     => 'organizations',
            'lookup_type'     => null,
            'validation'      => 'url',
            'sort_order'      => 10,
            'is_required'     => 0,
            'is_unique'       => 0,
            'quick_add'       => 0,
            'is_user_defined' => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * Left as a no-op: dropping the attribute would silently discard any
     * "site" value already saved on real organizations.
     */
    public function down(): void {}
};
