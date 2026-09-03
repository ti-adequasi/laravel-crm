<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\LeadGreen\Models\LeadGreen;
use Webkul\LeadGreen\Models\LeadGreenProxy;
use Webkul\LeadGreen\Services\CnpjService;

/**
 * LeadGreen ports Google Maps prospecting + website/CNPJ enrichment from
 * ti-adequasi/adequa.crm into the self-contained module shape documented in
 * the crm-package-development skill — see packages/Webkul/LeadGreen/README.md.
 */
uses(DatabaseTransactions::class);

function makeProspect(array $overrides = []): LeadGreen
{
    return LeadGreen::create(array_merge([
        'business_id'  => 'test-'.uniqid(),
        'name'         => 'Padaria Pest LTDA',
        'phone_number' => '+5511999998888',
        'website'      => 'https://example.com',
        'full_address' => 'Rua Teste, 123 - Osasco - SP, 06010-000',
        'city'         => 'Osasco',
        'state'        => 'SP',
        'rating'       => 4.5,
        'review_count' => 42,
        'lead_status'  => 'novo',
    ], $overrides));
}

it('resolves the LeadGreen contract through Concord', function () {
    expect(LeadGreenProxy::modelClass())->toBe(LeadGreen::class);
});

it('registers acl, menu, and settings entries', function () {
    expect(collect(config('acl'))->pluck('key'))->toContain('lead_green');
    expect(collect(config('menu.admin'))->pluck('key'))->toContain('lead_green');
    expect(collect(config('core_config'))->pluck('key'))->toContain('lead_green.settings.api_keys');
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

it('previews and imports search results, deduping by business_id and dropping businesses without a website', function () {
    config(['services.rapidapi_maps.key' => 'test-key']);

    $existing = makeProspect(['business_id' => 'dup-1']);

    Http::fake([
        'maps-data.p.rapidapi.com/*' => Http::response([
            'data' => [
                ['business_id' => 'dup-1', 'name' => 'Already imported', 'website' => 'https://dup.example.com'],
                ['business_id' => 'new-1', 'name' => 'Brand new business', 'website' => 'https://new.example.com'],
                ['business_id' => 'no-site-1', 'name' => 'No website business'],
            ],
        ], 200),
    ]);

    $search = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.search'), ['query' => 'Padarias em Osasco - SP'])
        ->assertOk()
        ->json();

    expect($search['counts']['with_website'])->toBe(2);
    expect($search['counts']['duplicates'])->toBe(1);
    expect($search['counts']['new'])->toBe(1);

    $import = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadgreen.import'), ['token' => $search['token']])
        ->assertOk()
        ->json();

    expect($import['stats'])->toBe(['found' => 2, 'inserted' => 1, 'skipped' => 1]);
    expect(LeadGreen::where('business_id', 'new-1')->exists())->toBeTrue();
    expect(LeadGreen::where('business_id', 'dup-1')->count())->toBe(1);
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
