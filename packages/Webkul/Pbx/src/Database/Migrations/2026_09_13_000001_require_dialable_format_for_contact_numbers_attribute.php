<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enforces the exact number shape the PBX's own outbound dial-plan
     * requires (confirmed directly with a real test call — see the PBX
     * integration plan's own notes): a literal leading "0" trunk-prefix
     * digit, then a 2-digit DDD, then either an 8-digit landline number
     * (starting 2-5) or a 9-digit mobile number (starting with the
     * mandatory post-2016 "9"). Requested explicitly, after that same
     * real test call revealed a Person could otherwise hold a number the
     * PBX can never actually dial.
     *
     * `attributes.validation` already existed and was already seeded
     * `'numeric'` for this exact attribute — silently ignored until now,
     * since AttributeForm/LeadForm's own `phone`-type branch never read
     * it at all (fixed alongside this migration). A plain data update,
     * not a schema change, which is why it lives here (Pbx's own
     * migrations) rather than in Attribute's — this specific format is a
     * PBX/Brazil-specific business rule, not a general Krayin default.
     *
     * Scoped to `entity_type = 'persons'` only — Organizations don't have
     * a `contact_numbers` attribute at all today (confirmed directly, not
     * assumed), so there's nothing else to update.
     */
    public function up(): void
    {
        DB::table('attributes')
            ->where('code', 'contact_numbers')
            ->where('entity_type', 'persons')
            ->update(['validation' => 'regex:/^0[1-9][1-9](?:[2-5]\d{7}|9\d{8})$/']);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('code', 'contact_numbers')
            ->where('entity_type', 'persons')
            ->update(['validation' => 'numeric']);
    }
};
