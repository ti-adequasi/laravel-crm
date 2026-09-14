<?php

namespace Webkul\LeadPeering\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Webkul\LeadPeering\DataGrids\LeadPeeringDataGrid;
use Webkul\LeadPeering\Repositories\LeadPeeringRepository;
use Webkul\LeadPeering\Services\PeeringDbService;

class LeadPeeringController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected LeadPeeringRepository $leadPeeringRepository) {}

    /**
     * Display a listing of the prospects.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(LeadPeeringDataGrid::class)->process();
        }

        return view('leadpeering::index');
    }

    /**
     * Show the PeeringDB search page.
     */
    public function searchForm()
    {
        return view('leadpeering::search');
    }

    /**
     * Run a PeeringDB search and return a preview (no insert yet).
     *
     * Only prospects with a website are kept. Each result is flagged as a
     * duplicate when its "type:id" key already exists. The full filtered
     * set is cached under a token so the import step can reuse it without
     * re-calling the external API.
     */
    public function search(Request $request, PeeringDbService $service)
    {
        $request->validate([
            'type' => 'required|string|in:'.implode(',', PeeringDbService::TYPES),
            'query' => 'nullable|string|max:255',
            'country' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'asn' => 'nullable|integer|min:0',
            'info_type' => 'nullable|string|max:255',
            'info_scope' => 'nullable|string|max:255',
            'policy_general' => 'nullable|string|max:255',
            'region_continent' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:300',
        ]);

        $type = $request->input('type');

        try {
            $results = $service->search(
                $type,
                $this->buildFilters($type, $request),
                (int) $request->input('limit', 50)
            );

            $total = count($results);

            // A soft-deleted/inactive PeeringDB record can never be imported
            // meaningfully — dropped outright, same spirit as LeadGreen
            // dropping permanently-closed businesses.
            $candidates = array_values(array_filter(
                $results,
                fn ($r) => ($r['status'] ?? 'ok') === 'ok'
            ));

            $normalized = array_map(fn ($r) => $this->normalizePreview($type, $r), $candidates);

            $existing = $this->leadPeeringRepository->findExistingPeeringDbKeys(
                array_filter(array_column($normalized, 'key'))
            );

            foreach ($normalized as &$lead) {
                $lead['is_duplicate'] = in_array($lead['key'], $existing, true);
            }

            $withWebsite = array_filter($normalized, fn ($l) => $l['has_website']);
            $duplicates = count(array_filter($withWebsite, fn ($l) => $l['is_duplicate']));

            $token = (string) Str::uuid();

            // Cache the full normalized candidate set so a later, more
            // permissive import selection can still draw from it.
            Cache::put('leadpeering_search_'.$token, $normalized, now()->addMinutes(30));

            return response()->json([
                'token' => $token,
                'type' => $type,
                'counts' => [
                    'total' => $total,
                    'with_website' => count($withWebsite),
                    'duplicates' => $duplicates,
                    'new' => count($withWebsite) - $duplicates,
                ],
                'leads' => $normalized,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Translate the search form's user-facing fields into PeeringDB's own
     * filter syntax, per object type.
     *
     * A 'net' object carries no country/city/state of its own — passing one
     * to GET /api/net is not an error, PeeringDB just silently ignores it
     * (confirmed live), which would otherwise look like "the filter did
     * nothing" with no explanation. Rather than send a doomed filter, those
     * fields are simply never built for 'net' — the search form hides them
     * for that type too (see search.blade.php), so this mirrors what the
     * user was actually shown, not a silent extra restriction.
     */
    protected function buildFilters(string $type, Request $request): array
    {
        $filters = [];

        if ($request->filled('query')) {
            $filters['name__contains'] = $request->input('query');
        }

        if ($type !== 'net') {
            if ($request->filled('country')) {
                $filters['country'] = strtoupper($request->input('country'));
            }

            if ($request->filled('city')) {
                $filters['city__contains'] = $request->input('city');
            }

            if ($request->filled('state')) {
                $filters['state'] = $request->input('state');
            }
        }

        if ($type === 'net') {
            if ($request->filled('asn')) {
                $filters['asn'] = (int) $request->input('asn');
            }

            if ($request->filled('info_type')) {
                $filters['info_type'] = $request->input('info_type');
            }

            if ($request->filled('info_scope')) {
                $filters['info_scope'] = $request->input('info_scope');
            }

            if ($request->filled('policy_general')) {
                $filters['policy_general'] = $request->input('policy_general');
            }
        }

        if ($type === 'fac' && $request->filled('region_continent')) {
            $filters['region_continent'] = $request->input('region_continent');
        }

        return $filters;
    }

    /**
     * Normalize one raw PeeringDB object (org/net/fac — each with a
     * different field set) into the flat preview shape the search UI and
     * importResults() both consume.
     */
    protected function normalizePreview(string $type, array $r): array
    {
        return [
            'key' => "{$type}:{$r['id']}",
            'peeringdb_id' => $r['id'],
            'peeringdb_type' => $type,
            'name' => $r['name'] ?? null,
            'aka' => $r['aka'] ?? null,
            'website' => $r['website'] ?? null,
            'has_website' => ! empty($r['website']),
            'asn' => $r['asn'] ?? null,
            'info_type' => $r['info_type'] ?? null,
            'info_traffic' => $r['info_traffic'] ?? null,
            'info_scope' => $r['info_scope'] ?? null,
            'policy_general' => $r['policy_general'] ?? null,
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
            'region_continent' => $r['region_continent'] ?? null,
            'sales_email' => $r['sales_email'] ?? null,
            'sales_phone' => $r['sales_phone'] ?? null,
            'tech_email' => $r['tech_email'] ?? null,
            'tech_phone' => $r['tech_phone'] ?? null,
            'status' => $r['status'] ?? 'ok',
        ];
    }

    /**
     * Confirm the import of a previously previewed search — restricted to
     * the caller's selected prospect keys, since not every previewed
     * result is necessarily wanted.
     */
    public function import(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'prospect_keys' => 'required|array|min:1',
            'prospect_keys.*' => 'string',
            'pipeline_id' => 'nullable|integer|exists:lead_pipelines,id',
        ]);

        $results = Cache::get('leadpeering_search_'.$request->input('token'));

        if ($results === null) {
            return response()->json(['message' => trans('leadpeering::app.search.error.expired')], 422);
        }

        $selected = array_values(array_filter(
            $results,
            fn ($r) => in_array($r['key'] ?? null, $request->input('prospect_keys'), true)
        ));

        try {
            $stats = $this->leadPeeringRepository->importResults($selected, $request->input('pipeline_id'));

            return response()->json([
                'message' => trans('leadpeering::app.search.success', $stats),
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display details of a prospect.
     */
    public function view(int $id)
    {
        $lead = $this->leadPeeringRepository->find($id);

        if (! $lead) {
            return response()->json(['message' => trans('leadpeering::app.error.not-found')], 404);
        }

        return response()->json([
            'lead' => $lead,
        ]);
    }

    /**
     * Convert a prospect into a real CRM lead.
     *
     * POST, unlike LeadGreen's equivalent (a GET that mutates state) — a
     * deliberate correction for this new module, not a mirrored convention;
     * see the crm-package-development skill.
     */
    public function convert(Request $request, int $id)
    {
        $request->validate([
            'pipeline_id' => 'nullable|integer|exists:lead_pipelines,id',
        ]);

        $lead = $this->leadPeeringRepository->find($id);

        if (! $lead) {
            return response()->json(['message' => trans('leadpeering::app.error.not-found')], 404);
        }

        if ($lead->isConverted()) {
            return response()->json(['message' => trans('leadpeering::app.error.already-converted')], 400);
        }

        try {
            $opportunity = $this->leadPeeringRepository->convertToLead($id, $request->input('pipeline_id'));

            return response()->json([
                'message' => trans('leadpeering::app.success.converted'),
                'redirect' => route('admin.leads.view', $opportunity->id),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Discard a prospect.
     */
    public function discard(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:255']);

        $lead = $this->leadPeeringRepository->find($id);

        if (! $lead) {
            return response()->json(['message' => trans('leadpeering::app.error.not-found')], 404);
        }

        try {
            $this->leadPeeringRepository->markAsUsed($id, 'descartado', $request->reason);

            return response()->json(['message' => trans('leadpeering::app.success.discarded')]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Enrich a single prospect from its website (synchronous, on demand).
     */
    public function enrich(int $id)
    {
        $lead = $this->leadPeeringRepository->find($id);

        if (! $lead) {
            return response()->json(['message' => trans('leadpeering::app.error.not-found')], 404);
        }

        try {
            $lead = $this->leadPeeringRepository->enrich($id);

            return response()->json([
                'message' => trans('leadpeering::app.enrichment.success'),
                'lead' => $lead,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return enrichment progress counts for the status banner.
     */
    public function enrichmentStatus()
    {
        $byStatus = $this->leadPeeringRepository->getModel()
            ->selectRaw('enrichment_status, count(*) as total')
            ->groupBy('enrichment_status')
            ->pluck('total', 'enrichment_status');

        $total = (int) $byStatus->sum();
        $pending = (int) ($byStatus['pending'] ?? 0);
        $processed = $total - $pending;

        return response()->json([
            'total' => $total,
            'pending' => $pending,
            'enriched' => (int) ($byStatus['enriched'] ?? 0),
            'empty' => (int) ($byStatus['empty'] ?? 0),
            'no_website' => (int) ($byStatus['no_website'] ?? 0),
            'failed' => (int) ($byStatus['failed'] ?? 0),
            'processed' => $processed,
            'percent' => $total > 0 ? (int) round($processed / $total * 100) : 100,
        ]);
    }

    /**
     * Export prospects as CSV or a standalone HTML report.
     */
    public function export(Request $request)
    {
        try {
            return $this->leadPeeringRepository->export($request->all(), $request->get('format', 'csv'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
