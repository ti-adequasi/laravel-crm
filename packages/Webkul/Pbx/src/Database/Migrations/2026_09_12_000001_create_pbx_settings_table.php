<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per tenant (nullable tenant_id = the super-admin's own row,
     * same convention as every other tenant-owned table) — enforced by a
     * unique index, not by the trait, which only filters reads/stamps
     * creates. api_key is stored as text (ciphertext from the model's
     * `encrypted` cast is long, same reasoning as GoogleContactAccount's
     * access/refresh tokens).
     */
    public function up(): void
    {
        Schema::create('pbx_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->boolean('enabled')->default(false);
            $table->text('api_key')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbx_settings');
    }
};
