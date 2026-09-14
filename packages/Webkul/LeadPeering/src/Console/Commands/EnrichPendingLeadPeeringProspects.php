<?php

namespace Webkul\LeadPeering\Console\Commands;

use Illuminate\Console\Command;
use Webkul\LeadPeering\Repositories\LeadPeeringRepository;
use Webkul\Tenant\Support\CurrentTenant;

class EnrichPendingLeadPeeringProspects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leadpeering:enrich-pending
                            {--limit=20 : How many prospects to enrich per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich pending LeadPeering prospects (email, socials, WhatsApp, CNPJ) from their website';

    /**
     * Execute the console command.
     *
     * Runs once per active tenant via CurrentTenant::eachActiveTenant() —
     * so --limit is a per-tenant budget, not a global one a single large
     * tenant's backlog could consume entirely before a smaller tenant's
     * prospects ever get a turn.
     */
    public function handle(LeadPeeringRepository $repository): int
    {
        $limit = (int) $this->option('limit');

        $totalEnriched = 0;

        CurrentTenant::eachActiveTenant(function (?int $tenantId) use ($repository, $limit, &$totalEnriched) {
            $totalEnriched += $this->enrichPendingFor($repository, $limit, $tenantId);
        });

        $this->info($totalEnriched > 0
            ? "Done — enriched {$totalEnriched} prospect(s)."
            : 'No prospects pending enrichment.');

        return self::SUCCESS;
    }

    /**
     * Enrich up to $limit pending prospects visible under whichever tenant
     * CurrentTenant::eachActiveTenant() currently has bound — LeadPeering's
     * own BelongsToTenant scope does the actual filtering. Returns how many
     * were attempted, for the caller's running total.
     */
    protected function enrichPendingFor(LeadPeeringRepository $repository, int $limit, ?int $tenantId): int
    {
        $model = $repository->getModel();

        // Pending prospects with no website can never be enriched — mark them once.
        $model->where('enrichment_status', 'pending')
            ->where(function ($query) {
                $query->whereNull('website')->orWhere('website', '');
            })
            ->update([
                'enrichment_status' => 'no_website',
                'enriched_at' => now(),
            ]);

        $pending = $model->where('enrichment_status', 'pending')
            ->whereNotNull('website')
            ->where('website', '!=', '')
            ->limit($limit)
            ->pluck('id');

        if ($pending->isEmpty()) {
            return 0;
        }

        $tenantLabel = $tenantId === null ? 'none (legacy/global)' : (string) $tenantId;

        $this->info("Tenant {$tenantLabel}: enriching {$pending->count()} prospect(s)...");

        foreach ($pending as $id) {
            try {
                $repository->enrich($id);
            } catch (\Throwable $e) {
                $model->where('id', $id)->update([
                    'enrichment_status' => 'failed',
                    'enriched_at' => now(),
                ]);

                logger()->warning("LeadPeering enrichment failed for prospect {$id}: ".$e->getMessage());
            }
        }

        return $pending->count();
    }
}
