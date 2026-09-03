<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * LeadEnrichment adds a manual "Enrich" action to any CRM lead, reusing
 * LeadGreen's scraping/CNPJ engine — see packages/Webkul/LeadEnrichment/README.md.
 * It has no entity of its own: everything here exercises the Controller,
 * the Route, and the view_render_event button injection.
 */
uses(DatabaseTransactions::class);

function makeLeadWithOrganization(?string $site = 'https://example.com'): Lead
{
    $organization = Organization::create(['name' => 'Empresa Pest', 'entity_type' => 'organizations']);

    if ($site) {
        app(AttributeValueRepository::class)->save([
            'entity_type' => 'organizations',
            'entity_id'   => $organization->id,
            'site'        => $site,
        ]);
    }

    $person = Person::create([
        'name'            => 'Contato Pest',
        'organization_id' => $organization->id,
        'entity_type'     => 'persons',
        'emails'          => [['value' => 'contato@empresapest.com.br', 'label' => 'work']],
        'contact_numbers' => [['value' => '11999998888', 'label' => 'work']],
    ]);

    return Lead::create([
        'title'                  => 'Lead Pest',
        'lead_value'             => 0,
        'status'                 => 'open',
        'person_id'              => $person->id,
        'user_id'                => 1,
        'lead_pipeline_id'       => 1,
        'lead_pipeline_stage_id' => 1,
    ]);
}

it('injects the enrich button into the lead detail page without editing it', function () {
    $lead = makeLeadWithOrganization();

    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertSee('v-lead-enrichment-button', false);
});

it('refuses to enrich a lead that has no website or registered CNPJ', function () {
    $organization = Organization::create(['name' => 'Sem Site LTDA', 'entity_type' => 'organizations']);

    $person = Person::create([
        'name'            => 'Sem Contato',
        'organization_id' => $organization->id,
        'entity_type'     => 'persons',
        'emails'          => [['value' => 'quem@gmail.com', 'label' => 'work']],
        'contact_numbers' => [['value' => '11999998888', 'label' => 'work']],
    ]);

    $lead = Lead::create([
        'title' => 'Lead Sem Site', 'lead_value' => 0, 'status' => 'open',
        'person_id' => $person->id, 'user_id' => 1,
        'lead_pipeline_id' => 1, 'lead_pipeline_stage_id' => 1,
    ]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leads.enrich', $lead->id))
        ->assertStatus(422);
});

it('enriches a lead from its organization site and posts a note to the timeline', function () {
    Http::fake([
        'example.com' => Http::response('<html><body>Contact: contato@example.com</body></html>', 200),
    ]);

    $lead = makeLeadWithOrganization('https://example.com');

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leads.enrich', $lead->id))
        ->assertOk()
        ->assertJson(fn ($json) => $json->has('message')->etc());

    $note = $lead->activities()->latest('id')->first();

    expect($note)->not->toBeNull();
    expect($note->comment)->toContain('example.com');
});

it('falls back to the contact email domain when the organization has no site', function () {
    Http::fake(['minhaempresa.com.br' => Http::response('<html></html>', 200)]);

    $organization = Organization::create(['name' => 'Sem Site Mas Com Email', 'entity_type' => 'organizations']);

    $person = Person::create([
        'name'            => 'Contato Pest 2',
        'organization_id' => $organization->id,
        'entity_type'     => 'persons',
        'emails'          => [['value' => 'contato@minhaempresa.com.br', 'label' => 'work']],
        'contact_numbers' => [['value' => '11999998888', 'label' => 'work']],
    ]);

    $lead = Lead::create([
        'title' => 'Lead Fallback Email', 'lead_value' => 0, 'status' => 'open',
        'person_id' => $person->id, 'user_id' => 1,
        'lead_pipeline_id' => 1, 'lead_pipeline_stage_id' => 1,
    ]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leads.enrich', $lead->id))
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'minhaempresa.com.br'));
});

it('redirects guests away from the enrich route', function () {
    $lead = makeLeadWithOrganization();

    test()->post(route('admin.leads.enrich', $lead->id))
        ->assertRedirect(route('admin.session.create'));
});
