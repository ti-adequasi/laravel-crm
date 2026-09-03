<?php

namespace Webkul\LeadGreen\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\LeadGreen\Contracts\LeadGreen;
use Webkul\LeadGreen\Services\CnpjService;
use Webkul\LeadGreen\Services\LeadEnrichmentService;

class LeadGreenRepository extends Repository
{
    /**
     * Create a new repository instance.
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected PersonRepository $personRepository,
        protected OrganizationRepository $organizationRepository,
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     */
    public function model()
    {
        return LeadGreen::class;
    }

    /**
     * Get prospects with filters and pagination.
     */
    public function getLeads(array $filters = [], int $perPage = 10)
    {
        $query = $this->getModel()->newQuery();

        if (! empty($filters['city'])) {
            $query->filterByCity($filters['city']);
        }

        if (! empty($filters['state'])) {
            $query->filterByState($filters['state']);
        }

        if (! empty($filters['type'])) {
            $query->filterByType($filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->filterByStatus($filters['status']);
        } else {
            $query->available();
        }

        if (! empty($filters['rating_min'])) {
            $query->filterByMinRating($filters['rating_min']);
        }

        if (! empty($filters['reviews_min'])) {
            $query->filterByMinReviews($filters['reviews_min']);
        }

        if (isset($filters['has_phone'])) {
            $query->hasPhone($filters['has_phone']);
        }

        if (isset($filters['has_website'])) {
            $query->hasWebsite($filters['has_website']);
        }

        return $query
            ->orderBy('rating', 'desc')
            ->orderBy('review_count', 'desc')
            ->paginate($perPage);
    }

    /**
     * Mark a prospect used with the given status.
     */
    public function markAsUsed(int $id, string $status, ?string $reason = null): bool
    {
        $lead = $this->findOrFail($id);

        return $lead->markAsUsed($status, $reason);
    }

    /**
     * Convert a Google prospect into a real CRM lead: find-or-create the
     * Organization, create a Person from whatever enrichment data is
     * available (a partner/administrator or the DPO), then create the Lead
     * against both.
     *
     * @param  int|null  $pipelineId  target pipeline; falls back to whichever
     *                                pipeline is flagged default when omitted
     * @param  int|null  $stageId  target stage within that pipeline; falls
     *                             back to the pipeline's first stage (sort
     *                             order) when omitted — a freshly-scraped,
     *                             not-yet-contacted business belongs at the
     *                             start of the funnel, never mid-pipeline
     */
    public function convertToLead(int $id, ?int $pipelineId = null, ?int $stageId = null)
    {
        $prospect = $this->findOrFail($id);

        if ($prospect->isConverted()) {
            throw new \Exception('This prospect has already been converted.');
        }

        $pipelineId = $pipelineId ?: $this->getDefaultPipelineId();
        $stageId = $stageId ?: $this->getFirstStageId($pipelineId);

        DB::beginTransaction();

        try {
            $organization = $this->findOrCreateOrganization($prospect);

            $person = $this->createPersonFromLead($prospect, $organization);

            // Leads have no organization_id of their own — the Organization is
            // reached through the Person created above (Person::organization_id).
            $lead = $this->leadRepository->create([
                'title' => $prospect->name,
                'description' => $this->buildEnrichmentDescription($prospect),
                'lead_value' => 0,
                'status' => 'open',
                'user_id' => auth()->id(),
                'person_id' => $person?->id,
                'lead_source_id' => $this->getLeadSourceId('google'),
                'lead_pipeline_id' => $pipelineId,
                'lead_pipeline_stage_id' => $stageId,
                'entity_type' => 'leads',
            ]);

            $prospect->lead_status = 'convertido';
            $prospect->used_at = now();
            $prospect->used_by = auth()->id();
            $prospect->used_reason = 'Converted into a CRM lead';
            $prospect->opportunity_id = $lead->id;
            $prospect->save();

            DB::commit();

            return $lead;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Find an existing Organization by name, or create one from the
     * prospect's address (and its enriched "site" attribute, when the
     * Organization entity has one).
     */
    protected function findOrCreateOrganization(LeadGreen $prospect)
    {
        $existing = $this->organizationRepository
            ->getModel()
            ->where('name', 'like', '%'.$prospect->name.'%')
            ->first();

        if ($existing) {
            return $existing;
        }

        $zipCode = $this->extractCEP($prospect->full_address);

        $organization = $this->organizationRepository->create([
            'name' => $prospect->name,
            'address' => [
                'address' => $prospect->full_address ?? '',
                'country' => 'BR',
                'state' => $prospect->state ?? '',
                'city' => $prospect->city ?? '',
                'postcode' => $zipCode,
                'website' => $prospect->website ?? '',
            ],
            'entity_type' => 'organizations',
        ]);

        if ($prospect->website) {
            $siteAttribute = $this->attributeRepository->findOneWhere([
                'entity_type' => 'organizations',
                'code' => 'site',
            ]);

            if ($siteAttribute) {
                $this->attributeValueRepository->save([
                    'entity_type' => 'organizations',
                    'entity_id' => $organization->id,
                    'site' => $prospect->website,
                ]);
            }
        }

        return $organization;
    }

    /**
     * Create a Person from the prospect's enriched data and link it to the
     * organization. Prefers a partner/administrator from the CNPJ "quadro de
     * sócios", falls back to the DPO, then to the company's own name — a
     * Lead reaches its organization through a person, so one is always
     * created even without real enrichment data.
     */
    protected function createPersonFromLead(LeadGreen $prospect, $organization)
    {
        $name = null;
        $jobTitle = null;

        $socios = is_array($prospect->socios) ? $prospect->socios : [];

        foreach ($socios as $socio) {
            if (! empty($socio['nome']) && stripos($socio['qualificacao'] ?? '', 'administrador') !== false) {
                $name = $socio['nome'];
                $jobTitle = $socio['qualificacao'] ?? null;

                break;
            }
        }

        if (! $name && ! empty($socios[0]['nome'])) {
            $name = $socios[0]['nome'];
            $jobTitle = $socios[0]['qualificacao'] ?? null;
        }

        if (! $name && ! empty($prospect->dpo_name)) {
            $name = $prospect->dpo_name;
            $jobTitle = 'Encarregado de Dados (DPO)';
        }

        if (! $name) {
            $name = $prospect->name;
        }

        $emails = collect([$prospect->email, $prospect->company_email, $prospect->dpo_email])
            ->merge(is_array($prospect->emails_found) ? $prospect->emails_found : [])
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($e) => ['value' => $e, 'label' => 'work'])
            ->all();

        $phones = collect([$prospect->phone_number, $prospect->whatsapp, $prospect->company_phone])
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($p) => ['value' => $p, 'label' => 'work'])
            ->all();

        return $this->personRepository->create([
            'name' => $name,
            'job_title' => $jobTitle,
            'emails' => $emails ?: [['value' => null, 'label' => 'work']],
            'contact_numbers' => $phones ?: [['value' => null, 'label' => 'work']],
            'organization_id' => $organization->id,
            'user_id' => auth()->id(),
            'entity_type' => 'persons',
        ]);
    }

    /**
     * Build the lead description from everything enrichment found.
     */
    protected function buildEnrichmentDescription(LeadGreen $prospect): string
    {
        $lines = [$prospect->name];

        if ($prospect->full_address) {
            $lines[] = $prospect->full_address;
        }

        if ($prospect->rating) {
            $lines[] = "Rating: {$prospect->rating} ({$prospect->review_count} reviews)";
        }

        $contacts = [];

        if ($prospect->email) {
            $contacts[] = "Email: {$prospect->email}";
        }

        $others = is_array($prospect->emails_found)
            ? array_values(array_filter($prospect->emails_found, fn ($e) => $e !== $prospect->email))
            : [];

        if ($others) {
            $contacts[] = 'Other emails: '.implode(', ', $others);
        }

        if ($prospect->whatsapp) {
            $contacts[] = "WhatsApp: {$prospect->whatsapp}";
        }

        if ($prospect->instagram) {
            $contacts[] = "Instagram: {$prospect->instagram}";
        }

        if ($prospect->facebook) {
            $contacts[] = "Facebook: {$prospect->facebook}";
        }

        if ($prospect->linkedin) {
            $contacts[] = "LinkedIn: {$prospect->linkedin}";
        }

        if ($contacts) {
            $lines[] = '';
            $lines[] = '--- Contacts ---';
            $lines = array_merge($lines, $contacts);
        }

        if ($prospect->cnpj) {
            $lines[] = '';
            $lines[] = '--- Company (CNPJ) ---';
            $lines[] = "CNPJ: {$prospect->cnpj}";

            if ($prospect->razao_social) {
                $lines[] = "Legal name: {$prospect->razao_social}";
            }

            if ($prospect->nome_fantasia) {
                $lines[] = "Trade name: {$prospect->nome_fantasia}";
            }

            if ($prospect->situacao_cadastral) {
                $lines[] = "Status: {$prospect->situacao_cadastral}";
            }

            if ($prospect->porte) {
                $lines[] = "Size: {$prospect->porte}";
            }

            if ($prospect->cnae_description) {
                $lines[] = "Activity (CNAE): {$prospect->cnae_description}";
            }

            if ($prospect->data_abertura) {
                $lines[] = 'Founded: '.Carbon::parse($prospect->data_abertura)->format('d/m/Y');
            }

            if ($prospect->capital_social) {
                $lines[] = 'Share capital: R$ '.number_format((float) $prospect->capital_social, 2, ',', '.');
            }

            $regime = array_filter([
                $prospect->opcao_simples ? 'Simples Nacional' : null,
                $prospect->opcao_mei ? 'MEI' : null,
            ]);

            if ($regime) {
                $lines[] = 'Tax regime: '.implode(', ', $regime);
            }

            $socios = is_array($prospect->socios) ? $prospect->socios : [];

            if ($socios) {
                $lines[] = '';
                $lines[] = 'Partners / administrators:';

                foreach ($socios as $socio) {
                    $q = ! empty($socio['qualificacao']) ? ' — '.$socio['qualificacao'] : '';
                    $lines[] = '  - '.($socio['nome'] ?? '-').$q;
                }
            }
        }

        if ($prospect->has_privacy_policy || $prospect->has_dpo) {
            $lines[] = '';
            $lines[] = '--- LGPD ---';

            if ($prospect->has_privacy_policy) {
                $lines[] = 'Privacy policy: yes'.($prospect->privacy_policy_url ? " ({$prospect->privacy_policy_url})" : '');
            }

            if ($prospect->dpo_name || $prospect->dpo_email) {
                $lines[] = 'DPO: '.trim(($prospect->dpo_name ?? '').' '.($prospect->dpo_email ? "<{$prospect->dpo_email}>" : ''));
            }
        }

        return implode("\n", $lines);
    }

    protected function getLeadSourceId(string $name): ?int
    {
        $source = DB::table('lead_sources')->where('name', 'like', '%'.ucfirst($name).'%')->first();

        return $source->id ?? null;
    }

    protected function getDefaultPipelineId(): int
    {
        $pipeline = DB::table('lead_pipelines')->where('is_default', 1)->first()
            ?? DB::table('lead_pipelines')->first();

        return $pipeline->id ?? 1;
    }

    protected function getFirstStageId(int $pipelineId): int
    {
        $stage = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->orderBy('sort_order', 'asc')
            ->first();

        return $stage->id ?? 1;
    }

    /**
     * Return which of the given business_ids already exist in the table.
     */
    public function findExistingBusinessIds(array $businessIds): array
    {
        if (! $businessIds) {
            return [];
        }

        return $this->getModel()->whereIn('business_id', $businessIds)->pluck('business_id')->all();
    }

    /**
     * Import a list of raw Google Maps results, deduped by business_id.
     * Only businesses with a website that aren't permanently closed are kept.
     *
     * @param  int|null  $pipelineId  when given, each imported prospect is
     *                                immediately converted into a real CRM
     *                                lead in this pipeline — importing is no
     *                                longer a dead-end staging step. Omit to
     *                                keep the old behaviour (import only,
     *                                convert later from the prospect list).
     * @return array{found: int, inserted: int, skipped: int, converted: int}
     */
    public function importResults(array $results, ?int $pipelineId = null): array
    {
        $found = count($results);
        $inserted = 0;
        $skipped = 0;
        $converted = 0;

        $existing = $this->findExistingBusinessIds(array_filter(array_column($results, 'business_id')));

        foreach ($results as $result) {
            $businessId = $result['business_id'] ?? null;

            if (empty($result['website']) || ! empty($result['is_permanently_closed'])) {
                $skipped++;

                continue;
            }

            if (! $businessId || in_array($businessId, $existing, true)) {
                $skipped++;

                continue;
            }

            try {
                $prospect = $this->create($this->normalizeResult($result));

                $existing[] = $businessId;
                $inserted++;
            } catch (\Exception $e) {
                logger()->error('LeadGreen import failed for business_id '.$businessId, ['message' => $e->getMessage()]);

                $skipped++;

                continue;
            }

            if ($pipelineId) {
                try {
                    $this->convertToLead($prospect->id, $pipelineId);

                    $converted++;
                } catch (\Exception $e) {
                    // The prospect itself was created fine — it just stays
                    // unconverted, same as before this feature existed, so
                    // it's still visible and convertible by hand later.
                    logger()->error('LeadGreen auto-convert failed for prospect '.$prospect->id, ['message' => $e->getMessage()]);
                }
            }
        }

        return compact('found', 'inserted', 'skipped', 'converted');
    }

    /**
     * Normalize a single Google Maps result into a row for the prospects table.
     */
    protected function normalizeResult(array $r): array
    {
        $originalCity = is_string($r['city'] ?? null) ? $r['city'] : '';
        $originalAddress = is_string($r['full_address'] ?? null) ? $r['full_address'] : '';

        $state = $r['state'] ?? null;

        if (! $state && $originalCity && str_contains($originalCity, '-')) {
            $parts = array_map('trim', explode('-', $originalCity));

            if (! empty($parts[1])) {
                $state = strtoupper(substr($parts[1], 0, 2));
            }
        }

        if (! $state && $originalAddress && preg_match('/\s-\s([A-Z]{2})\b/', $originalAddress, $m)) {
            $state = strtoupper($m[1]);
        }

        $city = $originalCity ? trim(explode('-', $originalCity)[0]) : null;

        return [
            'business_id' => $r['business_id'],
            'phone_number' => $r['phone_number'] ?? null,
            'name' => $r['name'] ?? '',
            'full_address' => $r['full_address'] ?? null,
            'full_address_array' => $r['full_address_array'] ?? [],
            'latitude' => $r['latitude'] ?? null,
            'longitude' => $r['longitude'] ?? null,
            'review_count' => $r['review_count'] ?? null,
            'rating' => $r['rating'] ?? null,
            'timezone' => $r['timezone'] ?? null,
            'website' => $r['website'] ?? null,
            'place_id' => $r['place_id'] ?? null,
            'place_link' => $r['place_link'] ?? null,
            'types' => $r['types'] ?? [],
            'price_level' => $r['price_level'] ?? null,
            'working_hours' => $r['working_hours'] ?? [],
            'city' => $city,
            'state' => $state,
            'is_claimed' => ! empty($r['is_claimed']),
            'verified' => ! empty($r['verified']),
            'is_permanently_closed' => ! empty($r['is_permanently_closed']),
            'is_temporarily_closed' => ! empty($r['is_temporarily_closed']),
            'photos' => $r['photos'] ?? [],
            'description' => $r['description'] ?? [],
            'lead_status' => 'novo',
        ];
    }

    /**
     * Enrich a single prospect from its website (synchronous, on demand).
     */
    public function enrich(int $id)
    {
        $prospect = $this->find($id);

        if (! $prospect) {
            return null;
        }

        if (empty($prospect->website)) {
            $prospect->update(['enrichment_status' => 'no_website', 'enriched_at' => now()]);

            return $prospect;
        }

        $data = app(LeadEnrichmentService::class)
            ->enrichFromWebsite($prospect->website);

        $cnpj = $data['cnpj'] ?? null;
        unset($data['cnpj']);

        if ($cnpj) {
            $cnpjService = app(CnpjService::class);
            $company = $cnpjService->lookup($cnpj);

            if ($company) {
                $company['cnpj_source'] = 'site';
                $data = array_merge($data, $company);
            } else {
                $data['cnpj'] = $cnpjService->isValidCnpj($cnpj)
                    ? preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpj)
                    : null;
                $data['cnpj_source'] = $cnpj ? 'site' : null;
            }
        }

        $data['enriched_at'] = now();

        $prospect->update($data);

        return $prospect->fresh();
    }

    /**
     * Export prospects to CSV or a styled, standalone HTML report.
     */
    public function export(array $filters, string $format)
    {
        if (auth()->check()) {
            logger()->info('LeadGreen export performed by user '.auth()->id(), ['filters' => $filters]);
        }

        $query = $this->getModel()->newQuery();
        $filterValues = $filters['filters'] ?? [];

        if (! empty($filterValues['city'])) {
            $query->filterByCity($filterValues['city']);
        }

        if (! empty($filterValues['state'])) {
            $query->filterByState($filterValues['state']);
        }

        if (! empty($filterValues['types'])) {
            $query->filterByType($filterValues['types']);
        }

        if (! empty($filterValues['lead_status'])) {
            $query->filterByStatus($filterValues['lead_status']);
        } else {
            $query->available();
        }

        if (! empty($filterValues['rating'])) {
            $query->filterByMinRating($filterValues['rating']);
        }

        if (! empty($filterValues['review_count'])) {
            $query->filterByMinReviews($filterValues['review_count']);
        }

        $exportData = $query->get()->map(function ($prospect) {
            $types = is_array($prospect->types) ? implode(', ', $prospect->types) : '';

            return [
                'ID' => $prospect->id,
                'Nome' => $prospect->name,
                'Telefone' => $prospect->phone_number ?? '',
                'Website' => $prospect->website ?? '',
                'Cidade' => $prospect->city ?? '',
                'Estado' => $prospect->state ?? '',
                'CEP' => $this->extractCEP($prospect->full_address),
                'Endereço' => $prospect->full_address ?? '',
                'Tipos' => $types,
                'Avaliação' => $prospect->rating ?? '',
                'Reviews' => $prospect->review_count ?? 0,
                'Status' => $this->getStatusLabel($prospect->lead_status),
                'Link Google' => $prospect->place_link ?? '',
            ];
        });

        $filename = 'leadgreen-'.date('Y-m-d-His');

        return $format === 'html'
            ? $this->exportToHtml($exportData, $filename)
            : $this->exportToCsv($exportData, $filename);
    }

    protected function exportToCsv($data, string $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        return response()->stream(function () use ($data) {
            $file = fopen('php://output', 'w');

            fprintf($file, "\xEF\xBB\xBF");

            if ($data->count() > 0) {
                fputcsv($file, array_keys($data->first()));
            }

            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        }, 200, $headers);
    }

    protected function exportToHtml($data, string $filename)
    {
        $rows = $data->map(function ($row) {
            return '<tr>'
                .'<td>'.e($row['ID']).'</td>'
                .'<td>'.e($row['Nome']).'</td>'
                .'<td>'.e($row['Telefone']).'</td>'
                .'<td>'.e($row['Cidade']).'</td>'
                .'<td>'.e($row['Estado']).'</td>'
                .'<td>'.e($row['Tipos']).'</td>'
                .'<td>'.e($row['Avaliação']).'</td>'
                .'<td>'.e($row['Reviews']).'</td>'
                .'<td>'.($row['Website'] ? '<a href="'.e($row['Website']).'">'.e($row['Website']).'</a>' : '-').'</td>'
                .'<td>'.e($row['Status']).'</td>'
                .'</tr>';
        })->implode('');

        $html = view('leadgreen::export.html', [
            'rows' => $rows,
            'total' => $data->count(),
            'exportedAt' => now()->format('d/m/Y H:i'),
        ])->render();

        return response()->stream(function () use ($html) {
            echo $html;
        }, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.html\"",
        ]);
    }

    protected function getStatusLabel(string $status): string
    {
        return [
            'novo' => 'Novo',
            'em_prospeccao' => 'Em prospecção',
            'convertido' => 'Convertido',
            'descartado' => 'Descartado',
            'reaproveitavel' => 'Reaproveitável',
        ][$status] ?? $status;
    }

    protected function extractCEP(?string $address): string
    {
        if (! $address) {
            return '';
        }

        if (preg_match('/\b(\d{5})-(\d{3})\b/', $address, $m)) {
            return $m[1].'-'.$m[2];
        }

        if (preg_match('/\b(\d{8})\b/', $address, $m)) {
            return substr($m[1], 0, 5).'-'.substr($m[1], 5, 3);
        }

        return '';
    }
}
