<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per click-to-call attempt originated through the CRM
     * (Phase 3). `call_uuid` is whatever the PBX's own originate response
     * identifies the call by — the OpenAPI spec leaves that response
     * genuinely untyped, so `PbxCallService` extracts it defensively and
     * this table just stores the result. `raw_originate_response` and
     * `raw_last_status` keep the full, unparsed PBX payloads alongside the
     * few fields this app actually acts on, so a wrong guess about the
     * shape is recoverable from data already on hand instead of only from
     * the next live call.
     *
     * `initiated_by_user_id` is who may control this call (see status/hangup
     * authorization in PbxCallController) — nullable + set-null-on-delete so
     * a departed user's call history survives them, just un-attributed.
     * `lead_id`/`person_id` record which Lead/Person the call was placed
     * from, for attaching the auto-logged Activity (Phase 3) to both.
     *
     * `initiated_by_user_id`/`lead_id`/`person_id` are plain `unsignedInteger`
     * — checked directly against information_schema, `users`/`leads`/
     * `persons`.`id` are all `int unsigned`, not `bigint unsigned` like
     * `tenants`.`id`; a foreign key requires an exact column-type match
     * (MySQL error 1005 otherwise), so `tenant_id` alone stays
     * `unsignedBigInteger`.
     */
    public function up(): void
    {
        Schema::create('pbx_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('call_uuid')->unique();
            $table->unsignedInteger('initiated_by_user_id')->nullable();
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('person_id')->nullable();
            $table->string('ramal');
            $table->string('telefone_raw');
            $table->string('telefone');
            $table->string('direction')->default('outbound');
            $table->string('status')->default('originating');
            $table->json('raw_originate_response')->nullable();
            $table->json('raw_last_status')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('activity_logged_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('initiated_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->foreign('person_id')->references('id')->on('persons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pbx_calls');
    }
};
