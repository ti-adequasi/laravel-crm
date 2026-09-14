<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lead_peering_prospects', function (Blueprint $table) {
            $table->id();

            // PeeringDB identity — which object type/id this came from. Ids
            // are only unique *within* a type (org #2 and net #2 are
            // unrelated records), so the pair is what dedup/import key on
            // (see LeadPeeringRepository::findExistingPeeringDbKeys()).
            $table->unsignedBigInteger('peeringdb_id')->index();
            $table->string('peeringdb_type', 10)->index();
            $table->string('name');
            $table->string('aka')->nullable();
            $table->text('website')->nullable();
            $table->unsignedBigInteger('asn')->nullable()->index();
            $table->string('info_type')->nullable();
            $table->string('info_traffic')->nullable();
            $table->text('notes')->nullable();
            $table->json('social_media')->nullable();

            // Location — populated for 'org'/'fac'. PeeringDB's 'net' object
            // carries no geography of its own (confirmed live against the
            // real API: net?country=... is silently ignored, unlike org/fac),
            // so these stay null for a 'net' prospect.
            $table->string('country', 2)->nullable()->index();
            $table->string('state')->nullable();
            $table->string('city')->nullable()->index();
            $table->text('address1')->nullable();
            $table->text('address2')->nullable();
            $table->string('zipcode')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // PeeringDB scale signals — real qualifying data for a
            // data-center-focused pitch (e.g. "224 facilities, 251
            // exchanges" for a Tier 1 network).
            $table->unsignedInteger('net_count')->nullable();
            $table->unsignedInteger('fac_count')->nullable();
            $table->unsignedInteger('ix_count')->nullable();

            // Prospecting funnel: novo, em_prospeccao, convertido, descartado, reaproveitavel.
            $table->string('lead_status')->default('novo')->index();
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by')->nullable();
            $table->string('used_reason')->nullable();
            $table->unsignedBigInteger('opportunity_id')->nullable();

            // Website enrichment (identical shape to lead_green_prospects —
            // LeadEnrichmentService/CnpjService are ported near-verbatim).
            $table->string('email')->nullable();
            $table->string('email_source')->nullable();
            $table->unsignedTinyInteger('email_quality')->nullable();
            $table->boolean('email_verified')->nullable();
            $table->json('emails_found')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('enrichment_status')->default('pending')->index();
            $table->unsignedTinyInteger('enrichment_score')->nullable();
            $table->timestamp('enriched_at')->nullable();
            $table->boolean('has_privacy_policy')->default(false);
            $table->text('privacy_policy_url')->nullable();
            $table->boolean('has_dpo')->default(false);
            $table->string('dpo_name')->nullable();
            $table->string('dpo_email')->nullable();

            // CNPJ / Brazilian company-registry enrichment. Only ever
            // populated for a 'BR' prospect in practice — extractCnpj()'s
            // regex simply won't match a non-Brazilian site's HTML.
            $table->string('cnpj')->nullable()->index();
            $table->string('cnpj_source')->nullable();
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('situacao_cadastral')->nullable();
            $table->date('data_abertura')->nullable();
            $table->string('cnae_code')->nullable();
            $table->string('cnae_description')->nullable();
            $table->string('inscricao_estadual')->nullable();
            $table->string('porte')->nullable();
            $table->string('natureza_juridica')->nullable();
            $table->decimal('capital_social', 15, 2)->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_email')->nullable();
            $table->boolean('opcao_simples')->default(false);
            $table->boolean('opcao_mei')->default(false);
            $table->json('socios')->nullable();
            $table->timestamp('company_data_at')->nullable();

            // Multi-tenancy, from the start (unlike lead_green_prospects,
            // which needed a later retrofit migration) — unsignedBigInteger
            // to match tenants.id's real type ($table->id() there too).
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();

            $table->timestamps();
        });

        // convertToLead() tags converted leads with a "PeeringDB" source —
        // make sure it exists, since Krayin's own seeder doesn't ship one.
        if (! DB::table('lead_sources')->where('name', 'PeeringDB')->exists()) {
            DB::table('lead_sources')->insert([
                'name' => 'PeeringDB',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_peering_prospects');
    }
};
