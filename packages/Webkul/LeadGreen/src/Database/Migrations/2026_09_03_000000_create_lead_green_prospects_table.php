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
        Schema::create('lead_green_prospects', function (Blueprint $table) {
            $table->id();

            // Business identity, from the Google Maps search result.
            $table->string('business_id')->nullable()->index();
            $table->string('name');
            $table->string('phone_number')->nullable();
            $table->text('website')->nullable();
            $table->text('full_address')->nullable();
            $table->json('full_address_array')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('state', 2)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->float('rating')->nullable();
            $table->string('timezone')->nullable();
            $table->string('place_id')->nullable();
            $table->text('place_link')->nullable();
            $table->json('types')->nullable();
            $table->string('price_level')->nullable();
            $table->json('working_hours')->nullable();
            $table->boolean('is_claimed')->default(false);
            $table->boolean('verified')->default(false);
            $table->boolean('is_permanently_closed')->default(false);
            $table->boolean('is_temporarily_closed')->default(false);
            $table->json('photos')->nullable();
            $table->json('description')->nullable();

            // Prospecting funnel: novo, em_prospeccao, convertido, descartado, reaproveitavel.
            $table->string('lead_status')->default('novo')->index();
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by')->nullable();
            $table->string('used_reason')->nullable();
            $table->unsignedBigInteger('opportunity_id')->nullable();

            // Website enrichment.
            $table->string('email')->nullable();
            $table->string('email_source')->nullable();
            $table->unsignedTinyInteger('email_quality')->nullable();
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

            // CNPJ / Brazilian company-registry enrichment.
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

            $table->timestamps();
        });

        // convertToLead() tags converted leads with a "Google" source — make sure
        // it exists, since Krayin's own seeder doesn't ship one.
        if (! DB::table('lead_sources')->where('name', 'Google')->exists()) {
            DB::table('lead_sources')->insert([
                'name'       => 'Google',
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
        Schema::dropIfExists('lead_green_prospects');
    }
};
