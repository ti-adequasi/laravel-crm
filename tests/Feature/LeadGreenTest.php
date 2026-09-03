<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\LeadGreen\Models\LeadGreen;
use Webkul\LeadGreen\Models\LeadGreenProxy;
use Webkul\LeadGreen\Services\CnpjService;
use Webkul\LeadGreen\Services\LeadEnrichmentService;

/**
 * LeadGreen ports Google Maps prospecting + website/CNPJ enrichment from
 * ti-adequasi/adequa.crm into the self-contained module shape documented in
 * the crm-package-development skill — see packages/Webkul/LeadGreen/README.md.
 */
uses(DatabaseTransactions::class);

function makeProspect(array $overrides = []): LeadGreen
{
    return LeadGreen::create(array_merge([
        'business_id' => 'test-'.uniqid(),
        'name' => 'Padaria Pest LTDA',
        'phone_number' => '+5511999998888',
        'website' => 'https://example.com',
        'full_address' => 'Rua Teste, 123 - Osasco - SP, 06010-000',
        'city' => 'Osasco',
        'state' => 'SP',
        'rating' => 4.5,
        'review_count' => 42,
        'lead_status' => 'novo',
    ], $overrides));
}

it('resolves the LeadGreen contract through Concord', function () {
    expect(LeadGreenProxy::modelClass())->toBe(LeadGreen::class);
});

it('registers acl, menu, and settings entries', function () {
    expect(collect(config('acl'))->pluck('key'))->toContain('lead_green');
    expect(collect(config('menu.admin'))->pluck('key'))->toContain('lead_green');
    expect(collect(config('core_config'))->pluck('key'))->toContain('lead_green.settings.api_keys');
    expect(collect(config('core_config'))->pluck('key'))->toContain('lead_green.settings.enrichment');
});

it('redirects guests away from the leadgreen pages', function () {
    test()->get(route('admin.leadgreen.index'))
        ->assertRedirect(route('admin.session.create'));
});

it('shows the leadgreen index and search pages to an authenticated admin', function () {
    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.leadgreen.index'))
        ->assertOk();

    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.leadgreen.search.form'))
        ->assertOk();
});

it('fails the search with a clear message when no API key is configured', function () {
    // This environment may already have a real key saved through the settings
    // screen (outside any test transaction) — clear it for this test only;
    // DatabaseTransactions rolls the deletion back afterward.
    DB::table('core_config')->where('code', 'lead_green.settings.api_keys.rapidapi_maps_key')->delete();
    config(['services.rapidapi_maps.key' => null]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.search'), ['query' => 'Padarias em Osasco - SP'])
        ->assertStatus(500)
        ->assertJson(fn ($json) => $json->has('message'));
});

it('previews businesses without a website too, flagged so they cannot be selected for import', function () {
    config(['services.rapidapi_maps.key' => 'test-key']);

    $existing = makeProspect(['business_id' => 'dup-1']);

    Http::fake([
        'maps-data.p.rapidapi.com/*' => Http::response([
            'data' => [
                ['business_id' => 'dup-1', 'name' => 'Already imported', 'website' => 'https://dup.example.com'],
                ['business_id' => 'new-1', 'name' => 'Brand new business', 'website' => 'https://new.example.com'],
                ['business_id' => 'no-site-1', 'name' => 'No website business'],
                ['business_id' => 'closed-1', 'name' => 'Shut down', 'website' => 'https://closed.example.com', 'is_permanently_closed' => true],
            ],
        ], 200),
    ]);

    $search = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.search'), ['query' => 'Padarias em Osasco - SP'])
        ->assertOk()
        ->json();

    // Permanently-closed is dropped outright; the no-website one is kept
    // (visible, filterable) but flagged as not having a site.
    expect($search['leads'])->toHaveCount(3);
    expect(collect($search['leads'])->pluck('business_id'))->not->toContain('closed-1');

    $noSite = collect($search['leads'])->firstWhere('business_id', 'no-site-1');
    expect($noSite['has_website'])->toBeFalse();

    expect($search['counts']['with_website'])->toBe(2);
    expect($search['counts']['duplicates'])->toBe(1);
    expect($search['counts']['new'])->toBe(1);
});

it('imports only the selected business_ids, leaving the rest of the batch untouched', function () {
    config(['services.rapidapi_maps.key' => 'test-key']);

    Http::fake([
        'maps-data.p.rapidapi.com/*' => Http::response([
            'data' => [
                ['business_id' => 'pick-me', 'name' => 'Wanted', 'website' => 'https://wanted.example.com'],
                ['business_id' => 'skip-me', 'name' => 'Not wanted', 'website' => 'https://skip.example.com'],
                ['business_id' => 'no-site-1', 'name' => 'No website business'],
            ],
        ], 200),
    ]);

    $search = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.search'), ['query' => 'Padarias em Osasco - SP'])
        ->assertOk()
        ->json();

    // Selecting the no-website row is harmless — the backend still refuses
    // to import it (no page for the CRM to reach), it just counts as skipped.
    $import = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.import'), [
            'token' => $search['token'],
            'business_ids' => ['pick-me', 'no-site-1'],
        ])
        ->assertOk()
        ->json();

    expect($import['stats'])->toBe(['found' => 2, 'inserted' => 1, 'skipped' => 1, 'converted' => 0]);
    expect(LeadGreen::where('business_id', 'pick-me')->exists())->toBeTrue();
    expect(LeadGreen::where('business_id', 'skip-me')->exists())->toBeFalse();

    // No pipeline_id was sent — matches the prospect-only behaviour from
    // before this feature existed, nothing gets auto-converted.
    expect(LeadGreen::where('business_id', 'pick-me')->first()->lead_status)->toBe('novo');
});

it('imports and immediately converts into a real opportunity when a pipeline is chosen', function () {
    config(['services.rapidapi_maps.key' => 'test-key']);

    $pipeline = Pipeline::first();

    Http::fake([
        'maps-data.p.rapidapi.com/*' => Http::response([
            'data' => [
                ['business_id' => 'go-straight-1', 'name' => 'Prospected And Ready', 'website' => 'https://ready.example.com'],
            ],
        ], 200),
    ]);

    $search = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.search'), ['query' => 'Padarias em Osasco - SP'])
        ->json();

    $import = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.import'), [
            'token' => $search['token'],
            'business_ids' => ['go-straight-1'],
            'pipeline_id' => $pipeline->id,
        ])
        ->assertOk()
        ->json();

    expect($import['stats'])->toBe(['found' => 1, 'inserted' => 1, 'skipped' => 0, 'converted' => 1]);

    $prospect = LeadGreen::where('business_id', 'go-straight-1')->first();
    expect($prospect->lead_status)->toBe('convertido');
    expect($prospect->opportunity_id)->not->toBeNull();

    $lead = Lead::find($prospect->opportunity_id);
    expect($lead->lead_pipeline_id)->toBe($pipeline->id);
    expect($lead->lead_pipeline_stage_id)->toBe($pipeline->stages()->orderBy('sort_order')->first()->id);
});

it('requires at least one business_id to import', function () {
    config(['services.rapidapi_maps.key' => 'test-key']);

    Http::fake(['maps-data.p.rapidapi.com/*' => Http::response(['data' => [
        ['business_id' => 'a', 'name' => 'A', 'website' => 'https://a.example.com'],
    ]], 200)]);

    $search = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.search'), ['query' => 'Padarias em Osasco - SP'])
        ->json();

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.import'), ['token' => $search['token']])
        ->assertSessionHasErrors('business_ids');
});

it('converts a prospect into a CRM lead, linked through a Person to an Organization', function () {
    $prospect = makeProspect();

    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.leadgreen.convert', $prospect->id))
        ->assertOk()
        ->assertJson(fn ($json) => $json->has('redirect')->etc());

    $prospect->refresh();

    expect($prospect->lead_status)->toBe('convertido');
    expect($prospect->opportunity_id)->not->toBeNull();

    $lead = Lead::find($prospect->opportunity_id);
    expect($lead)->not->toBeNull();
    expect($lead->title)->toBe('Padaria Pest LTDA');

    $person = Person::find($lead->person_id);
    expect($person)->not->toBeNull();

    $organization = Organization::find($person->organization_id);
    expect($organization)->not->toBeNull();
    expect($organization->name)->toBe('Padaria Pest LTDA');
});

it('converts into a specific pipeline and stage when one is chosen, not just the default', function () {
    $otherPipeline = Pipeline::create(['name' => 'Prospecção Fria', 'is_default' => false, 'rotten_days' => 30]);
    $otherStage = $otherPipeline->stages()->create(['name' => 'Novo Contato', 'code' => 'novo-contato', 'sort_order' => 1]);

    $prospect = makeProspect();

    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.leadgreen.convert', $prospect->id).'?pipeline_id='.$otherPipeline->id)
        ->assertOk();

    $lead = Lead::find($prospect->refresh()->opportunity_id);

    expect($lead->lead_pipeline_id)->toBe($otherPipeline->id);
    expect($lead->lead_pipeline_stage_id)->toBe($otherStage->id);
});

it('refuses to convert an already-converted prospect', function () {
    $prospect = makeProspect(['lead_status' => 'convertido', 'opportunity_id' => 999]);

    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.leadgreen.convert', $prospect->id))
        ->assertStatus(400);
});

it('discards a prospect with a reason', function () {
    $prospect = makeProspect();

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.discard', $prospect->id), ['reason' => 'Fora do perfil'])
        ->assertOk();

    $prospect->refresh();

    expect($prospect->lead_status)->toBe('descartado');
    expect($prospect->used_reason)->toBe('Fora do perfil');
});

it('validates a CNPJ by its check digits', function () {
    $service = app(CnpjService::class);

    // A real, valid, well-known CNPJ format (check digits verified).
    expect($service->isValidCnpj('11222333000181'))->toBeTrue();
    expect($service->isValidCnpj('11111111111111'))->toBeFalse();
    expect($service->isValidCnpj('123'))->toBeFalse();
});

it('detects privacy policy / DPO signals by default, and skips the extra fetch entirely once disabled', function () {
    Http::fake([
        'empresa-teste.com.br/privacidade' => Http::response('<html>Encarregado de Dados: joao@empresa-teste.com.br</html>', 200),
        'empresa-teste.com.br/*' => Http::response('<html><a href="/privacidade">Política de Privacidade</a></html>', 200),
    ]);

    $service = app(LeadEnrichmentService::class);

    $enabled = $service->enrichFromWebsite('https://empresa-teste.com.br');

    expect($enabled['has_privacy_policy'])->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/privacidade'));

    DB::table('core_config')->updateOrInsert(
        ['code' => 'lead_green.settings.enrichment.detect_lgpd_signals'],
        ['value' => 0]
    );

    Http::fake([
        'empresa-teste.com.br/privacidade' => Http::response('<html>Encarregado de Dados: joao@empresa-teste.com.br</html>', 200),
        'empresa-teste.com.br/*' => Http::response('<html><a href="/privacidade">Política de Privacidade</a></html>', 200),
    ]);

    $disabled = $service->enrichFromWebsite('https://empresa-teste.com.br');

    expect($disabled['has_privacy_policy'])->toBeFalse();
    expect($disabled['has_dpo'])->toBeFalse();
    // Not just "ignored" — the privacy page is never even requested.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/privacidade'));
});

it('hides the privacy policy / DPO grid columns once LGPD detection is disabled', function () {
    $columns = fn () => test()->actingAs(getDefaultAdmin())
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson(route('admin.leadgreen.index'))
        ->json('columns');

    $visible = fn ($columns, $index) => collect($columns)->firstWhere('index', $index)['visibility'];

    expect($visible($columns(), 'has_privacy_policy'))->toBeTrue();
    expect($visible($columns(), 'has_dpo'))->toBeTrue();

    DB::table('core_config')->updateOrInsert(
        ['code' => 'lead_green.settings.enrichment.detect_lgpd_signals'],
        ['value' => 0]
    );

    expect($visible($columns(), 'has_privacy_policy'))->toBeFalse();
    expect($visible($columns(), 'has_dpo'))->toBeFalse();
});
