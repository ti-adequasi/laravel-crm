<?php

namespace Webkul\LeadPeering\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\LeadPeering\Contracts\LeadPeering;
use Webkul\LeadPeering\Services\CnpjService;
use Webkul\LeadPeering\Services\LeadEnrichmentService;

class LeadPeeringRepository extends Repository
{
    /**
     * Human-readable labels for PeeringDB's own info_type vocabulary — used
     * to translate a raw prospect's technical fields into a readable
     * Portuguese sales-context blurb (buildEnrichmentDescription()).
     * PeeringDB itself does not translate these; the values are its real,
     * fixed enum (confirmed against the live API's own returned values).
     */
    protected const NETWORK_TYPE_LABELS = [
        'NSP' => 'Provedor de rede (NSP)',
        'Content' => 'Provedor de conteúdo',
        'Cable/DSL/ISP' => 'Provedor de acesso (Cable/DSL/ISP)',
        'Enterprise' => 'Rede corporativa',
        'Educational/Research' => 'Rede educacional/pesquisa',
        'Non-Profit' => 'Rede sem fins lucrativos',
        'Route Server' => 'Route server',
        'Network Services' => 'Serviços de rede',
        'Route Collector' => 'Route collector',
        'Government' => 'Rede governamental',
        'Not Disclosed' => 'Tipo não divulgado',
    ];

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
        return LeadPeering::class;
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
     * Convert a PeeringDB prospect into a real CRM lead: find-or-create the
     * Organization, create a Person from whatever enrichment data is
     * available (a partner/administrator or the DPO), then create the Lead
     * against both.
     *
     * @param  int|null  $pipelineId  target pipeline; falls back to whichever
     *                                pipeline is flagged default when omitted
     * @param  int|null  $stageId  target stage within that pipeline; falls
     *                             back to the pipeline's first stage (sort
     *                             order) when omitted — a freshly-found,
     *                             not-yet-contacted prospect belongs at the
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
                'lead_source_id' => $this->getLeadSourceId('peeringdb'),
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
     * prospect's own PeeringDB address (and its enriched "site" attribute,
     * when the Organization entity has one).
     *
     * Unlike LeadGreen's equivalent (which hardcodes country => 'BR', since
     * its Maps search is Brazil-only), this uses the prospect's real
     * `country` — PeeringDB is a global directory, and a 'net' prospect may
     * legitimately have no country at all (see the class docblock on
     * LeadPeering — networks carry no geography of their own).
     */
    protected function findOrCreateOrganization(LeadPeering $prospect)
    {
        $existing = $this->organizationRepository
            ->getModel()
            ->where('name', 'like', '%'.$prospect->name.'%')
            ->first();

        if ($existing) {
            return $existing;
        }

        $address = trim(implode(', ', array_filter([$prospect->address1, $prospect->address2])));

        $organization = $this->organizationRepository->create([
            'name' => $prospect->name,
            'address' => [
                'address' => $address,
                'country' => $prospect->country ?? '',
                'state' => $prospect->state ?? '',
                'city' => $prospect->city ?? '',
                'postcode' => $prospect->zipcode ?? '',
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
     * sócios", falls back to the DPO, then to the prospect's own name — a
     * Lead reaches its organization through a person, so one is always
     * created even without real enrichment data.
     */
    protected function createPersonFromLead(LeadPeering $prospect, $organization)
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

        $phones = collect([$prospect->whatsapp, $prospect->company_phone])
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
     * Build the lead description from everything PeeringDB and enrichment
     * found — translating PeeringDB's own technical fields (info_type,
     * info_traffic, *_count) into a readable, sales-context summary rather
     * than dumping the raw values.
     */
    protected function buildEnrichmentDescription(LeadPeering $prospect): string
    {
        $lines = [$prospect->name];

        $location = trim(implode(', ', array_filter([$prospect->address1, $prospect->city, $prospect->state, $prospect->country])));

        if ($location) {
            $lines[] = $location;
        }

        $lines[] = '';
        $lines[] = '--- PeeringDB ---';
        $lines[] = 'Fonte: https://www.peeringdb.com/'.$prospect->peeringdb_type.'/'.$prospect->peeringdb_id;

        if ($prospect->peeringdb_type === 'net') {
            if ($prospect->asn) {
                $lines[] = "ASN: {$prospect->asn}";
            }

            if ($prospect->info_type) {
                $lines[] = 'Tipo de rede: '.(self::NETWORK_TYPE_LABELS[$prospect->info_type] ?? $prospect->info_type);
            }

            if ($prospect->info_traffic) {
                $lines[] = "Tráfego estimado: {$prospect->info_traffic}";
            }
        }

        $presence = array_filter([
            $prospect->fac_count ? "{$prospect->fac_count} instalações/data centers" : null,
            $prospect->net_count ? "{$prospect->net_count} redes presentes" : null,
            $prospect->ix_count ? "{$prospect->ix_count} pontos de troca de tráfego (IX)" : null,
        ]);

        if ($presence) {
            $lines[] = 'Presença: '.implode(', ', $presence);
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
     * Return which of the given "type:id" keys already exist in the table
     * (see LeadPeering::getPeeringdbKeyAttribute()) — the composite-key
     * analog of LeadGreen's findExistingBusinessIds(), needed because a
     * bare id is only unique *within* a PeeringDB object type.
     */
    public function findExistingPeeringDbKeys(array $keys): array
    {
        if (! $keys) {
            return [];
        }

        $idsByType = [];

        foreach ($keys as $key) {
            [$type, $id] = array_pad(explode(':', (string) $key, 2), 2, null);

            if ($type !== null && $id !== null && ctype_digit($id)) {
                $idsByType[$type][] = (int) $id;
            }
        }

        $existing = [];

        foreach ($idsByType as $type => $ids) {
            $found = $this->getModel()
                ->where('peeringdb_type', $type)
                ->whereIn('peeringdb_id', $ids)
                ->pluck('peeringdb_id');

            foreach ($found as $id) {
                $existing[] = "{$type}:{$id}";
            }
        }

        return $existing;
    }

    /**
     * Import a list of raw, already-normalized PeeringDB search results
     * (see LeadPeeringController::search()'s "leads" shape), deduped by
     * their "type:id" key. Only prospects with a website that are still
     * 'ok' at the source are kept.
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

        $existing = $this->findExistingPeeringDbKeys(array_filter(array_column($results, 'key')));

        foreach ($results as $result) {
            $key = $result['key'] ?? null;

            if (empty($result['website']) || ($result['status'] ?? 'ok') !== 'ok') {
                $skipped++;

                continue;
            }

            if (! $key || in_array($key, $existing, true)) {
                $skipped++;

                continue;
            }

            try {
                $prospect = $this->create($this->normalizeResult($result));

                $existing[] = $key;
                $inserted++;
            } catch (\Exception $e) {
                logger()->error('LeadPeering import failed for '.$key, ['message' => $e->getMessage()]);

                $skipped++;

                continue;
            }

            if ($pipelineId) {
                try {
                    $this->convertToLead($prospect->id, $pipelineId);

                    $converted++;
                } catch (\Exception $e) {
                    // The prospect itself was created fine — it just stays
                    // unconverted, so it's still visible and convertible by
                    // hand later.
                    logger()->error('LeadPeering auto-convert failed for prospect '.$prospect->id, ['message' => $e->getMessage()]);
                }
            }
        }

        return compact('found', 'inserted', 'skipped', 'converted');
    }

    /**
     * Normalize a single (already controller-shaped) PeeringDB search
     * result into a row for the prospects table.
     */
    protected function normalizeResult(array $r): array
    {
        return [
            'peeringdb_id' => $r['peeringdb_id'],
            'peeringdb_type' => $r['peeringdb_type'],
            'name' => $r['name'] ?? '',
            'aka' => $r['aka'] ?? null,
            'website' => $r['website'] ?? null,
            'asn' => $r['asn'] ?? null,
            'info_type' => $r['info_type'] ?? null,
            'info_traffic' => $r['info_traffic'] ?? null,
            'notes' => $r['notes'] ?? null,
            'social_media' => $r['social_media'] ?? [],
            'country' => $r['country'] ?? null,
            'state' => $r['state'] ?? null,
            'city' => $r['city'] ?? null,
            'address1' => $r['address1'] ?? null,
            'address2' => $r['address2'] ?? null,
            'zipcode' => $r['zipcode'] ?? null,
            'latitude' => $r['latitude'] ?? null,
            'longitude' => $r['longitude'] ?? null,
            'net_count' => $r['net_count'] ?? null,
            'fac_count' => $r['fac_count'] ?? null,
            'ix_count' => $r['ix_count'] ?? null,
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
            logger()->info('LeadPeering export performed by user '.auth()->id(), ['filters' => $filters]);
        }

        $query = $this->getModel()->newQuery();
        $filterValues = $filters['filters'] ?? [];

        if (! empty($filterValues['peeringdb_type'])) {
            $query->filterByPeeringdbType($filterValues['peeringdb_type']);
        }

        if (! empty($filterValues['country'])) {
            $query->filterByCountry($filterValues['country']);
        }

        if (! empty($filterValues['city'])) {
            $query->filterByCity($filterValues['city']);
        }

        if (! empty($filterValues['lead_status'])) {
            $query->filterByStatus($filterValues['lead_status']);
        } else {
            $query->available();
        }

        $exportData = $query->get()->map(function ($prospect) {
            return [
                'ID' => $prospect->id,
                'Nome' => $prospect->name,
                'Tipo' => $this->getTypeLabel($prospect->peeringdb_type),
                'ASN' => $prospect->asn ?? '',
                'Website' => $prospect->website ?? '',
                'País' => $prospect->country ?? '',
                'Cidade' => $prospect->city ?? '',
                'Estado' => $prospect->state ?? '',
                'Status' => $this->getStatusLabel($prospect->lead_status),
                'Link PeeringDB' => 'https://www.peeringdb.com/'.$prospect->peeringdb_type.'/'.$prospect->peeringdb_id,
            ];
        });

        $filename = 'leadpeering-'.date('Y-m-d-His');

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
                .'<td>'.e($row['Tipo']).'</td>'
                .'<td>'.e($row['ASN']).'</td>'
                .'<td>'.e($row['País']).'</td>'
                .'<td>'.e($row['Cidade']).'</td>'
                .'<td>'.e($row['Estado']).'</td>'
                .'<td>'.($row['Website'] ? '<a href="'.e($row['Website']).'">'.e($row['Website']).'</a>' : '-').'</td>'
                .'<td>'.e($row['Status']).'</td>'
                .'</tr>';
        })->implode('');

        $html = view('leadpeering::export.html', [
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

    protected function getTypeLabel(string $type): string
    {
        return [
            'org' => 'Organização',
            'net' => 'Rede',
            'fac' => 'Data Center',
        ][$type] ?? $type;
    }
}
