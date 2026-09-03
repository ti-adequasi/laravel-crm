<?php

namespace Webkul\LeadGreen\Console\Commands;

use Illuminate\Console\Command;
use Webkul\LeadGreen\Repositories\LeadGreenRepository;

class EnrichPendingLeadGreenProspects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leadgreen:enrich-pending
                            {--limit=20 : How many prospects to enrich per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich pending LeadGreen prospects (email, socials, WhatsApp, CNPJ) from their website';

    /**
     * Execute the console command.
     */
    public function handle(LeadGreenRepository $repository): int
    {
        $model = $repository->getModel();

        // Pending prospects with no website can never be enriched — mark them once.
        $model->where('enrichment_status', 'pending')
            ->where(function ($query) {
                $query->whereNull('website')->orWhere('website', '');
            })
            ->update([
                'enrichment_status' => 'no_website',
                'enriched_at'       => now(),
            ]);

        $limit = (int) $this->option('limit');

        $pending = $model->where('enrichment_status', 'pending')
            ->whereNotNull('website')
            ->where('website', '!=', '')
            ->limit($limit)
            ->pluck('id');

        if ($pending->isEmpty()) {
            $this->info('No prospects pending enrichment.');

            return self::SUCCESS;
        }

        $this->info("Enriching {$pending->count()} prospect(s)...");

        foreach ($pending as $id) {
            try {
                $repository->enrich($id);
            } catch (\Throwable $e) {
                $model->where('id', $id)->update([
                    'enrichment_status' => 'failed',
                    'enriched_at'       => now(),
                ]);

                logger()->warning("LeadGreen enrichment failed for prospect {$id}: ".$e->getMessage());
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
