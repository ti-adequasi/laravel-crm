<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Webkul\Contact\Models\Person;
use Webkul\LeadGreen\Models\LeadGreen;
use Webkul\LeadGreen\Services\LeadEnrichmentService;
use Webkul\Marketing\Mail\CampaignMail;
use Webkul\Marketing\Models\Campaign;
use Webkul\Tenant\Repositories\TenantRepository;

uses(DatabaseTransactions::class);

it('gives every active tenant its own --limit budget instead of one tenant consuming it all', function () {
    $tenantA = app(TenantRepository::class)->create(['name' => 'LeadGreen Tenant A', 'code' => 'leadgreen-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'LeadGreen Tenant B', 'code' => 'leadgreen-b-'.uniqid(), 'is_active' => true]);

    // Tenant A has a bigger backlog than the per-run limit below — on an
    // unscoped run this alone would consume the entire budget and Tenant
    // B's one prospect would never be touched.
    foreach (range(1, 3) as $i) {
        LeadGreen::create(['tenant_id' => $tenantA->id, 'name' => "A Prospect {$i}", 'website' => 'https://a-prospect-'.$i.'.example']);
    }

    $prospectB = LeadGreen::create(['tenant_id' => $tenantB->id, 'name' => 'B Prospect', 'website' => 'https://b-prospect.example']);

    // Fakes the real website-scraping call this command would otherwise
    // make — the command's own tenant-iteration is what's under test.
    $this->mock(LeadEnrichmentService::class, function ($mock) {
        $mock->shouldReceive('enrichFromWebsite')->andReturn([]);
    });

    $this->artisan('leadgreen:enrich-pending', ['--limit' => 1])->assertSuccessful();

    expect(LeadGreen::where('tenant_id', $tenantA->id)->whereNotNull('enriched_at')->count())->toBe(1)
        // Tenant B's own single prospect got its own turn in the same run.
        ->and($prospectB->fresh()->enriched_at)->not->toBeNull()
        // The run's final, null-tenant pass (super-admin's own bucket)
        // must stay confined to genuinely tenant-less rows — none exist
        // here, so it must NOT reach back into Tenant A's own remaining
        // backlog and process more than its fair one-per-run share.
        ->and(LeadGreen::where('tenant_id', $tenantA->id)->whereNull('enriched_at')->count())->toBe(2);
});

it('never lets one tenant\'s campaign reach another tenant\'s contacts', function () {
    Mail::fake();

    $tenantA = app(TenantRepository::class)->create(['name' => 'Campaign Tenant A', 'code' => 'campaign-a-'.uniqid(), 'is_active' => true]);
    $tenantB = app(TenantRepository::class)->create(['name' => 'Campaign Tenant B', 'code' => 'campaign-b-'.uniqid(), 'is_active' => true]);

    Person::create(['tenant_id' => $tenantA->id, 'name' => 'Person A', 'emails' => [['value' => 'person-a@example.com', 'label' => 'work']]]);
    Person::create(['tenant_id' => $tenantB->id, 'name' => 'Person B', 'emails' => [['value' => 'person-b@example.com', 'label' => 'work']]]);

    // status=1, no event/template — Campaign::process()'s own query treats
    // a campaign with no linked event as always due (left join + orWhereNull).
    Campaign::create(['tenant_id' => $tenantA->id, 'name' => 'Tenant A Campaign', 'subject' => 'Hello A', 'status' => 1]);

    $this->artisan('campaign:process')->assertSuccessful();

    Mail::assertQueued(CampaignMail::class, fn ($mail) => $mail->email === 'person-a@example.com');
    Mail::assertNotQueued(CampaignMail::class, fn ($mail) => $mail->email === 'person-b@example.com');
});
