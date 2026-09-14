<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Organizations have no "site" attribute out of the box —
     * findOrCreateOrganization() (and the enrichment it reads back) depends
     * on one existing. Webkul\LeadGreen ships an identical, identically-
     * guarded migration for the same attribute — whichever package installs
     * first creates it, this one is a no-op on an instance that already has
     * LeadGreen. Same code/id ('site', entity_type=organizations), so both
     * modules read and write the very same field.
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
            'code' => 'site',
            'name' => 'Site',
            'type' => 'text',
            'entity_type' => 'organizations',
            'lookup_type' => null,
            'validation' => 'url',
            'sort_order' => 10,
            'is_required' => 0,
            'is_unique' => 0,
            'quick_add' => 0,
            'is_user_defined' => 1,
            'created_at' => now(),
            'updated_at' => now(),
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
