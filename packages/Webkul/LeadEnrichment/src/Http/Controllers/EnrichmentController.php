<?php

namespace Webkul\LeadEnrichment\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\LeadGreen\Services\CnpjService;
use Webkul\LeadGreen\Services\LeadEnrichmentService;

class EnrichmentController extends Controller
{
    /**
     * E-mail domains that don't identify a company.
     *
     * @var array
     */
    protected array $genericMailDomains = [
        'gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com', 'yahoo.com.br',
        'bol.com.br', 'uol.com.br',
    ];

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected ActivityRepository $activityRepository,
    ) {}

    /**
     * Enrich a lead from its organization's website and/or CNPJ, and post
     * the result as a note on the lead's timeline.
     */
    public function enrich(int $id): JsonResponse
    {
        $lead = $this->leadRepository->find($id);

        if (! $lead) {
            return response()->json(['message' => trans('lead_enrichment::app.error.not-found')], 404);
        }

        $website = $this->resolveWebsite($lead);
        $registeredCnpj = $this->resolveCnpj($lead);

        if (! $website && ! $registeredCnpj) {
            return response()->json(['message' => trans('lead_enrichment::app.error.nothing-to-enrich')], 422);
        }

        try {
            $data = $website
                ? app(LeadEnrichmentService::class)->enrichFromWebsite($website)
                : [];

            $cnpj = $registeredCnpj ?: ($data['cnpj'] ?? null);

            $company = $cnpj ? app(CnpjService::class)->lookup($cnpj) : null;

            $activity = $this->activityRepository->create([
                'type'    => 'note',
                'comment' => $this->buildNote($website, $data, $company),
                'title'   => trans('lead_enrichment::app.note-title'),
                'user_id' => auth()->id(),
            ]);

            $activity->leads()->attach($lead->id);

            return response()->json(['message' => trans('lead_enrichment::app.success')]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolve the company website: the linked Organization's site, falling
     * back to the domain of the contact's first non-generic e-mail.
     */
    protected function resolveWebsite($lead): ?string
    {
        $person = $lead->person;

        if (! $person) {
            return null;
        }

        $organization = $person->organization;

        if ($organization && ! empty($organization->site)) {
            return $organization->site;
        }

        $email = $person->emails[0]['value'] ?? null;

        if ($email && str_contains($email, '@')) {
            $domain = Str::after($email, '@');

            if (! in_array($domain, $this->genericMailDomains, true)) {
                return 'http://'.$domain;
            }
        }

        return null;
    }

    /**
     * Resolve a CNPJ already registered on the linked Organization, if any.
     */
    protected function resolveCnpj($lead): ?string
    {
        $organization = $lead->person?->organization;

        return $organization && ! empty($organization->cnpj) ? $organization->cnpj : null;
    }

    /**
     * Build the note comment from whatever enrichment data was found.
     */
    protected function buildNote(?string $website, array $data, ?array $company): string
    {
        $lines = [trans('lead_enrichment::app.note-header', ['date' => now()->format('d/m/Y H:i')])];

        if ($website) {
            $lines[] = trans('lead_enrichment::app.note.website', ['website' => $website]);
        }

        $contacts = [];

        if (! empty($data['email'])) {
            $contacts[] = trans('lead_enrichment::app.note.email', ['email' => $data['email']]);
        }

        $others = ! empty($data['emails_found']) && is_array($data['emails_found'])
            ? array_values(array_filter($data['emails_found'], fn ($e) => $e !== ($data['email'] ?? null)))
            : [];

        if ($others) {
            $contacts[] = trans('lead_enrichment::app.note.other-emails', ['emails' => implode(', ', $others)]);
        }

        foreach (['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn'] as $key => $label) {
            if (! empty($data[$key])) {
                $contacts[] = "- {$label}: ".$data[$key];
            }
        }

        if ($contacts) {
            $lines[] = '';
            $lines[] = trans('lead_enrichment::app.note.contacts-title');
            $lines = array_merge($lines, $contacts);
        }

        if ($company) {
            $lines[] = '';
            $lines[] = trans('lead_enrichment::app.note.company-title');
            $lines[] = trans('lead_enrichment::app.note.cnpj', ['cnpj' => $company['cnpj'] ?? '-']);

            foreach ([
                'razao_social'       => 'lead_enrichment::app.note.company-name',
                'nome_fantasia'      => 'lead_enrichment::app.note.trade-name',
                'situacao_cadastral' => 'lead_enrichment::app.note.status',
                'porte'              => 'lead_enrichment::app.note.size',
                'cnae_description'   => 'lead_enrichment::app.note.activity',
                'inscricao_estadual' => 'lead_enrichment::app.note.state-registration',
            ] as $field => $key) {
                if (! empty($company[$field])) {
                    $lines[] = trans($key, ['value' => $company[$field]]);
                }
            }

            if (! empty($company['socios']) && is_array($company['socios'])) {
                $lines[] = '';
                $lines[] = trans('lead_enrichment::app.note.partners-title');

                foreach ($company['socios'] as $socio) {
                    $q = ! empty($socio['qualificacao']) ? ' — '.$socio['qualificacao'] : '';
                    $lines[] = '  - '.($socio['nome'] ?? '-').$q;
                }
            }
        }

        if (! empty($data['has_privacy_policy']) || ! empty($data['has_dpo'])) {
            $lines[] = '';
            $lines[] = trans('lead_enrichment::app.note.lgpd-title');

            if (! empty($data['has_privacy_policy'])) {
                $lines[] = trans('lead_enrichment::app.note.privacy-policy');
            }

            if (! empty($data['dpo_name']) || ! empty($data['dpo_email'])) {
                $lines[] = trans('lead_enrichment::app.note.dpo', [
                    'contact' => trim(($data['dpo_name'] ?? '').' '.(! empty($data['dpo_email']) ? "<{$data['dpo_email']}>" : '')),
                ]);
            }
        }

        if (count($lines) <= 1) {
            $lines[] = '';
            $lines[] = trans('lead_enrichment::app.note.nothing-found');
        }

        return implode("\n", $lines);
    }
}
