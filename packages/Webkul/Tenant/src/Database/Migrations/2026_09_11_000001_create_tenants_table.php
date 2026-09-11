<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This must run before every `add_tenant_id_column_in_*_table` migration
     * across every other package (Laravel runs all discovered migrations in
     * filename order regardless of which package they live in) — the
     * `2026_09_11_000001` prefix is deliberately earlier than the
     * `2026_09_11_000002`+ prefixes used for those.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
