<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\LeadPeering\Models\LeadPeering;
use Webkul\LeadPeering\Models\LeadPeeringProxy;
use Webkul\LeadPeering\Repositories\LeadPeeringRepository;
use Webkul\LeadPeering\Services\CnpjService;
use Webkul\LeadPeering\Services\LeadEnrichmentService;
use Webkul\Tenant\Repositories\TenantRepository;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

/**
 * LeadPeering mirrors LeadGreen's self-contained module shape for a
 * different data source — PeeringDB's public directory of network
 * operators, data centers, and organizations — instead of Google Maps.
 * See packages/Webkul/LeadPeering and the crm-package-development skill.
 */
uses(DatabaseTransactions::class);

function makePeeringProspect(array $overrides = []): LeadPeering
{
    return LeadPeering::create(array_merge([
        'peeringdb_id' => random_int(100000, 999999),
        'peeringdb_type' => 'net',
        'name' => 'Rede Pest LTDA',
        'website' => 'https://example.com',
        'asn' => random_int(60000, 69999),
        'lead_status' => 'novo',
    ], $overrides));
}

function makePeeringTenantUser(int $tenantId): User
{
    $admin = getDefaultAdmin();

    $role = Role::create([
        'tenant_id' => $tenantId,
        'name' => 'LeadPeering Test Role '.uniqid(),
        'permission_type' => 'all',
        'permissions' => [],
    ]);

    $user = User::create([
        'name' => 'LeadPeering Test User',
        'email' => uniqid().'@example.com',
        'password' => $admin->password,
        'status' => 1,
        'role_id' => $role->id,
    ]);

    $user->forceFill(['tenant_id' => $tenantId])->save();

    return $user->fresh();
}

it('resolves the LeadPeering contract through Concord', function () {
    expect(LeadPeeringProxy::modelClass())->toBe(LeadPeering::class);
});

it('registers acl, menu, and settings entries', function () {
    expect(collect(config('acl'))->pluck('key'))->toContain('lead_peering');
    expect(collect(config('menu.admin'))->pluck('key'))->toContain('lead_peering');
    expect(collect(config('core_config'))->pluck('key'))->toContain('lead_peering.settings.api_keys');
});

it('redirects guests away from the leadpeering pages', function () {
    test()->get(route('admin.leadpeering.index'))
        ->assertRedirect(route('admin.session.create'));
});

it('shows the leadpeering index and search pages to an authenticated admin', function () {
    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.leadpeering.index'))
        ->assertOk();

    test()->actingAs(getDefaultAdmin())
        ->get(route('admin.leadpeering.search.form'))
        ->assertOk();
});

it('searches successfully even with no PeeringDB API key configured', function () {
    // Unlike LeadGreen's RapidAPI key, PeeringDB allows anonymous read
    // access — there must be no equivalent "no API key" failure mode here.
    DB::table('core_config')->where('code', 'lead_peering.settings.api_keys.peeringdb_api_key')->delete();
    config(['services.peeringdb.key' => null]);

    Http::fake([
        'www.peeringdb.com/api/net*' => Http::response(['data' => [], 'meta' => []], 200),
    ]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'net', 'query' => 'Equinix'])
        ->assertOk()
        ->assertJson(fn ($json) => $json->has('leads')->etc());

    Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
});

it('previews prospects without a website too, flagged so they cannot be selected for import', function () {
    $existing = makePeeringProspect(['peeringdb_id' => 111, 'peeringdb_type' => 'net']);

    Http::fake([
        'www.peeringdb.com/api/net*' => Http::response(['data' => [
            ['id' => 111, 'name' => 'Already Imported Net', 'website' => 'https://dup.example.com', 'asn' => 111],
            ['id' => 222, 'name' => 'Brand New Net', 'website' => 'https://new.example.com', 'asn' => 222],
            ['id' => 333, 'name' => 'No Website Net', 'asn' => 333],
            ['id' => 444, 'name' => 'Deleted Net', 'website' => 'https://gone.example.com', 'asn' => 444, 'status' => 'deleted'],
        ], 'meta' => []], 200),
    ]);

    $search = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'net', 'query' => 'Net'])
        ->assertOk()
        ->json();

    // A non-'ok' status record is dropped outright; the no-website one is
    // kept (visible, filterable) but flagged as not having a site.
    expect($search['leads'])->toHaveCount(3);
    expect(collect($search['leads'])->pluck('key'))->not->toContain('net:444');

    $noSite = collect($search['leads'])->firstWhere('key', 'net:333');
    expect($noSite['has_website'])->toBeFalse();

    $duplicate = collect($search['leads'])->firstWhere('key', 'net:111');
    expect($duplicate['is_duplicate'])->toBeTrue();

    expect($search['counts']['with_website'])->toBe(2);
    expect($search['counts']['duplicates'])->toBe(1);
    expect($search['counts']['new'])->toBe(1);
});

it('never sends a country filter when searching networks, since PeeringDB silently ignores it', function () {
    Http::fake([
        'www.peeringdb.com/api/net*' => Http::response(['data' => [], 'meta' => []], 200),
    ]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'net', 'country' => 'BR', 'city' => 'Sao Paulo'])
        ->assertOk();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/net')
            && ! str_contains($request->url(), 'country=')
            && ! str_contains($request->url(), 'city__contains=');
    });
});

it('does send a country filter when searching organizations', function () {
    Http::fake([
        'www.peeringdb.com/api/org*' => Http::response(['data' => [], 'meta' => []], 200),
    ]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'org', 'country' => 'br'])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'country=BR'));
});

it('sends info_scope and policy_general filters only when searching networks', function () {
    Http::fake([
        'www.peeringdb.com/api/net*' => Http::response(['data' => [], 'meta' => []], 200),
        'www.peeringdb.com/api/org*' => Http::response(['data' => [], 'meta' => []], 200),
    ]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'net', 'info_scope' => 'Global', 'policy_general' => 'Open'])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/net')
        && str_contains($request->url(), 'info_scope=Global')
        && str_contains($request->url(), 'policy_general=Open'));

    // Org has no info_scope/policy_general of its own — the filter must
    // never be built for it, same reasoning as the country/net case above.
    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'org', 'info_scope' => 'Global', 'policy_general' => 'Open'])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/org')
        && ! str_contains($request->url(), 'info_scope=')
        && ! str_contains($request->url(), 'policy_general='));
});

it('sends a region_continent filter only when searching facilities', function () {
    Http::fake([
        'www.peeringdb.com/api/fac*' => Http::response(['data' => [], 'meta' => []], 200),
        'www.peeringdb.com/api/net*' => Http::response(['data' => [], 'meta' => []], 200),
    ]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'fac', 'region_continent' => 'North America'])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/fac')
        && str_contains(urldecode($request->url()), 'region_continent=North America'));

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'net', 'region_continent' => 'North America'])
        ->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/net')
        && ! str_contains($request->url(), 'region_continent'));
});

it('imports only the selected prospect_keys, leaving the rest of the batch untouched', function () {
    Http::fake([
        'www.peeringdb.com/api/net*' => Http::response(['data' => [
            ['id' => 501, 'name' => 'Wanted Net', 'website' => 'https://wanted.example.com', 'asn' => 501],
            ['id' => 502, 'name' => 'Not Wanted Net', 'website' => 'https://skip.example.com', 'asn' => 502],
            ['id' => 503, 'name' => 'No Website Net', 'asn' => 503],
        ], 'meta' => []], 200),
    ]);

    $search = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'net', 'query' => 'Net'])
        ->assertOk()
        ->json();

    // Selecting the no-website row is harmless — the backend still refuses
    // to import it (no page for the CRM to reach), it just counts as skipped.
    $import = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.import'), [
            'token' => $search['token'],
            'prospect_keys' => ['net:501', 'net:503'],
        ])
        ->assertOk()
        ->json();

    expect($import['stats'])->toBe(['found' => 2, 'inserted' => 1, 'skipped' => 1, 'converted' => 0]);
    expect(LeadPeering::where('peeringdb_type', 'net')->where('peeringdb_id', 501)->exists())->toBeTrue();
    expect(LeadPeering::where('peeringdb_type', 'net')->where('peeringdb_id', 502)->exists())->toBeFalse();

    // No pipeline_id was sent — nothing gets auto-converted.
    expect(LeadPeering::where('peeringdb_type', 'net')->where('peeringdb_id', 501)->first()->lead_status)->toBe('novo');
});

it('imports and immediately converts into a real opportunity when a pipeline is chosen', function () {
    $pipeline = Pipeline::first();

    Http::fake([
        'www.peeringdb.com/api/net*' => Http::response(['data' => [
            ['id' => 601, 'name' => 'Prospected And Ready Net', 'website' => 'https://ready.example.com', 'asn' => 601],
        ], 'meta' => []], 200),
    ]);

    $search = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'net', 'query' => 'Ready'])
        ->json();

    $import = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.import'), [
            'token' => $search['token'],
            'prospect_keys' => ['net:601'],
            'pipeline_id' => $pipeline->id,
        ])
        ->assertOk()
        ->json();

    expect($import['stats'])->toBe(['found' => 1, 'inserted' => 1, 'skipped' => 0, 'converted' => 1]);

    $prospect = LeadPeering::where('peeringdb_type', 'net')->where('peeringdb_id', 601)->first();
    expect($prospect->lead_status)->toBe('convertido');
    expect($prospect->opportunity_id)->not->toBeNull();

    $lead = Lead::find($prospect->opportunity_id);
    expect($lead->lead_pipeline_id)->toBe($pipeline->id);
    expect($lead->lead_pipeline_stage_id)->toBe($pipeline->stages()->orderBy('sort_order')->first()->id);
});

it('requires at least one prospect_key to import', function () {
    Http::fake(['www.peeringdb.com/api/net*' => Http::response(['data' => [
        ['id' => 701, 'name' => 'A Net', 'website' => 'https://a.example.com', 'asn' => 701],
    ], 'meta' => []], 200)]);

    $search = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'net', 'query' => 'A'])
        ->json();

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.import'), ['token' => $search['token']])
        ->assertSessionHasErrors('prospect_keys');
});

it('carries new PeeringDB fields (scope, policy, region, direct facility contacts) through search and import', function () {
    Http::fake([
        'www.peeringdb.com/api/net*' => Http::response(['data' => [
            ['id' => 901, 'name' => 'Scoped Net', 'website' => 'https://scoped.example.com', 'asn' => 901, 'info_scope' => 'Global', 'policy_general' => 'Open'],
        ], 'meta' => []], 200),
        'www.peeringdb.com/api/fac*' => Http::response(['data' => [
            ['id' => 902, 'name' => 'Contactable DC', 'website' => 'https://dc.example.com', 'region_continent' => 'North America', 'sales_email' => 'sales@dc.example.com', 'sales_phone' => '555-0100', 'tech_email' => 'noc@dc.example.com', 'tech_phone' => '555-0101'],
        ], 'meta' => []], 200),
    ]);

    $netSearch = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'net', 'query' => 'Scoped'])
        ->json();

    expect($netSearch['leads'][0]['info_scope'])->toBe('Global');
    expect($netSearch['leads'][0]['policy_general'])->toBe('Open');

    test()->actingAs(getDefaultAdmin())->post(route('admin.leadpeering.import'), [
        'token' => $netSearch['token'],
        'prospect_keys' => ['net:901'],
    ]);

    $netProspect = LeadPeering::where('peeringdb_type', 'net')->where('peeringdb_id', 901)->first();
    expect($netProspect->info_scope)->toBe('Global');
    expect($netProspect->policy_general)->toBe('Open');

    $facSearch = test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.search'), ['type' => 'fac', 'query' => 'Contactable'])
        ->json();

    test()->actingAs(getDefaultAdmin())->post(route('admin.leadpeering.import'), [
        'token' => $facSearch['token'],
        'prospect_keys' => ['fac:902'],
    ]);

    $facProspect = LeadPeering::where('peeringdb_type', 'fac')->where('peeringdb_id', 902)->first();
    expect($facProspect->region_continent)->toBe('North America');
    expect($facProspect->sales_email)->toBe('sales@dc.example.com');
    expect($facProspect->tech_email)->toBe('noc@dc.example.com');

    // These real, PeeringDB-provided contacts feed straight into the
    // created Person's own emails/phones on conversion — no website
    // scraping needed for a facility that lists its own contacts.
    $lead = app(LeadPeeringRepository::class)->convertToLead($facProspect->id);
    $person = Person::find($lead->person_id);

    expect(collect($person->emails)->pluck('value'))->toContain('sales@dc.example.com');
    expect(collect($person->contact_numbers)->pluck('value'))->toContain('555-0100');
});

it('converts a prospect into a CRM lead, linked through a Person to an Organization, using its real PeeringDB country', function () {
    $prospect = makePeeringProspect([
        'peeringdb_type' => 'org',
        'peeringdb_id' => 801,
        'name' => 'Organizacao Pest LTDA',
        'country' => 'US',
        'city' => 'Redwood City',
        'state' => 'CA',
    ]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.convert', $prospect->id))
        ->assertOk()
        ->assertJson(fn ($json) => $json->has('redirect')->etc());

    $prospect->refresh();

    expect($prospect->lead_status)->toBe('convertido');
    expect($prospect->opportunity_id)->not->toBeNull();

    $lead = Lead::find($prospect->opportunity_id);
    expect($lead)->not->toBeNull();
    expect($lead->title)->toBe('Organizacao Pest LTDA');

    $person = Person::find($lead->person_id);
    expect($person)->not->toBeNull();

    $organization = Organization::find($person->organization_id);
    expect($organization)->not->toBeNull();
    expect($organization->name)->toBe('Organizacao Pest LTDA');
    // Unlike LeadGreen (which hardcodes 'BR'), this must carry the
    // prospect's own real PeeringDB country through, unchanged.
    expect($organization->address['country'] ?? null)->toBe('US');
});

it('converts into a specific pipeline and stage when one is chosen, not just the default', function () {
    $otherPipeline = Pipeline::create(['name' => 'Prospecção PeeringDB Fria', 'is_default' => false, 'rotten_days' => 30]);
    $otherStage = $otherPipeline->stages()->create(['name' => 'Novo Contato', 'code' => 'novo-contato', 'sort_order' => 1]);

    $prospect = makePeeringProspect();

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.convert', $prospect->id), ['pipeline_id' => $otherPipeline->id])
        ->assertOk();

    $lead = Lead::find($prospect->refresh()->opportunity_id);

    expect($lead->lead_pipeline_id)->toBe($otherPipeline->id);
    expect($lead->lead_pipeline_stage_id)->toBe($otherStage->id);
});

it('refuses to convert an already-converted prospect', function () {
    $prospect = makePeeringProspect(['lead_status' => 'convertido', 'opportunity_id' => 999]);

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.convert', $prospect->id))
        ->assertStatus(400);
});

it('discards a prospect with a reason', function () {
    $prospect = makePeeringProspect();

    test()->actingAs(getDefaultAdmin())
        ->post(route('admin.leadpeering.discard', $prospect->id), ['reason' => 'Fora do perfil'])
        ->assertOk();

    $prospect->refresh();

    expect($prospect->lead_status)->toBe('descartado');
    expect($prospect->used_reason)->toBe('Fora do perfil');
});

it('never lets one tenant view, convert, or discard another tenant\'s PeeringDB prospect by id', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'LeadPeering Tenant A', 'code' => 'leadpeering-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'LeadPeering Tenant B', 'code' => 'leadpeering-b-'.uniqid(), 'is_active' => true]);

    $userA = makePeeringTenantUser($tenantA->id);
    $prospectB = makePeeringProspect(['tenant_id' => $tenantB->id, 'name' => 'Tenant B Net']);

    test()->actingAs($userA)->getJson(route('admin.leadpeering.view', $prospectB->id))->assertStatus(404);
    test()->actingAs($userA)->post(route('admin.leadpeering.convert', $prospectB->id))->assertStatus(404);
    test()->actingAs($userA)->post(route('admin.leadpeering.discard', $prospectB->id), ['reason' => 'x'])->assertStatus(404);

    // Confirm the cross-tenant attempts were genuinely refused, not merely
    // reporting 404 while quietly mutating the row anyway.
    expect($prospectB->fresh()->lead_status)->toBe('novo');
});

it('validates a CNPJ by its check digits', function () {
    $service = app(CnpjService::class);

    // A real, valid, well-known CNPJ format (check digits verified).
    expect($service->isValidCnpj('11222333000181'))->toBeTrue();
    expect($service->isValidCnpj('11111111111111'))->toBeFalse();
    expect($service->isValidCnpj('123'))->toBeFalse();
});

it('falls back to ReceitaWS when BrasilAPI and CNPJá Open both fail, converting its DD/MM/YYYY date to ISO', function () {
    Http::fake([
        'brasilapi.com.br/*' => Http::response(null, 404),
        'open.cnpja.com/*' => Http::response(null, 404),
        'receitaws.com.br/*' => Http::response([
            'status' => 'OK',
            'nome' => 'Empresa Peering Teste LTDA',
            'fantasia' => 'Teste',
            'situacao' => 'ATIVA',
            'abertura' => '02/09/2009',
            'atividade_principal' => [['code' => '61.10-8-01', 'text' => 'Telecomunicações']],
            'porte' => 'DEMAIS',
            'natureza_juridica' => '206-2 - Sociedade Empresária Limitada',
            'capital_social' => '1000.00',
            'telefone' => '(11) 4000-0000',
            'email' => 'contato@empresapeeringteste.com.br',
            'simples' => ['optante' => false],
            'simei' => ['optante' => false],
            'qsa' => [['nome' => 'Fulano de Tal', 'qual' => 'Sócio-Administrador']],
        ], 200),
    ]);

    $service = app(CnpjService::class);
    $data = $service->lookup('11222333000181');

    expect($data)->not->toBeNull();
    expect($data['razao_social'])->toBe('Empresa Peering Teste LTDA');
    // 02/09/2009 is September 2nd in Brazilian order — proves it wasn't
    // misread as US-style February 9th by a bare Carbon::parse().
    expect($data['data_abertura'])->toBe('2009-09-02');
    expect($data['socios'][0]['nome'])->toBe('Fulano de Tal');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'receitaws.com.br'));
});

it('verifies the picked e-mail via Disify, and stores email_quality as its numeric rank weight', function () {
    Http::fake([
        'disify.com/*' => Http::response([
            'format' => true,
            'domain' => 'empresa-teste-peering.com.br',
            'disposable' => false,
            'dns' => true,
        ], 200),
        'empresa-teste-peering.com.br/*' => Http::response('<html><a href="mailto:contato@empresa-teste-peering.com.br">Contato</a></html>', 200),
    ]);

    $result = app(LeadEnrichmentService::class)->enrichFromWebsite('https://empresa-teste-peering.com.br');

    expect($result['email'])->toBe('contato@empresa-teste-peering.com.br');
    expect($result['email_verified'])->toBeTrue();
    // "contato@" is the 'role' category — weight 0, not the string 'role'
    // (the column is a tinyint; storing the category string would truncate).
    expect($result['email_quality'])->toBe(0);
});

it('marks an e-mail unverified when its domain has no mail server', function () {
    Http::fake([
        'disify.com/*' => Http::response(['format' => true, 'domain' => 'empresa-teste-peering.com.br', 'disposable' => false, 'dns' => false], 200),
        'empresa-teste-peering.com.br/*' => Http::response('<html><a href="mailto:contato@empresa-teste-peering.com.br">Contato</a></html>', 200),
    ]);

    $result = app(LeadEnrichmentService::class)->enrichFromWebsite('https://empresa-teste-peering.com.br');

    expect($result['email_verified'])->toBeFalse();
});

it('leaves email_verified null (not false) when Disify itself is unreachable', function () {
    Http::fake([
        'disify.com/*' => Http::response(null, 500),
        'empresa-teste-peering.com.br/*' => Http::response('<html><a href="mailto:contato@empresa-teste-peering.com.br">Contato</a></html>', 200),
    ]);

    $result = app(LeadEnrichmentService::class)->enrichFromWebsite('https://empresa-teste-peering.com.br');

    expect($result['email_verified'])->toBeNull();
});
