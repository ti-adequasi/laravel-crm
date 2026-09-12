<?php

namespace Webkul\Marketing\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Marketing\Helpers\Campaign;
use Webkul\Tenant\Support\CurrentTenant;

class CampaignCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process campaigns and send emails to the contact persons.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(protected Campaign $campaignHelper)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * Campaign::process() reads both the campaign and its recipients
     * through Eloquent (a plain model instance, unlike a DataGrid's raw
     * query builder), so both queries are already tenant-aware — but only
     * once a tenant is actually bound. Without this loop, a scheduled run
     * has none bound at all, and a campaign would reach every tenant's
     * contacts at once, not just its owner's — a real cross-tenant leak,
     * not just an unfair processing order like leadgreen:enrich-pending's.
     * Each tenant gets its own try/catch so one tenant's bad campaign
     * can't block every other tenant's from sending that day.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting campaign processing...');

        CurrentTenant::eachActiveTenant(function (?int $tenantId) {
            try {
                $this->campaignHelper->process();
            } catch (\Exception $e) {
                $tenantLabel = $tenantId === null ? 'none (legacy/global)' : (string) $tenantId;

                $this->error("❌ An error occurred during campaign processing for tenant {$tenantLabel}: ".$e->getMessage());

                logger()->warning("Campaign processing failed for tenant {$tenantLabel}: ".$e->getMessage());
            }
        });

        $this->info('✅ Campaign processing completed successfully!');

        return self::SUCCESS;
    }
}
