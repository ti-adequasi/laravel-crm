<?php

namespace Webkul\LeadGreen\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Webkul\LeadGreen\DataGrids\LeadGreenDataGrid;
use Webkul\LeadGreen\Repositories\LeadGreenRepository;
use Webkul\LeadGreen\Services\GoogleMapsService;

class LeadGreenController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected LeadGreenRepository $leadGreenRepository) {}

    /**
     * Display a listing of the prospects.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(LeadGreenDataGrid::class)->process();
        }

        return view('leadgreen::index');
    }

    /**
     * Show the Google Maps search page.
     */
    public function searchForm()
    {
        return view('leadgreen::search');
    }

    /**
     * Run a Google Maps search and return a preview (no insert yet).
     *
     * Only businesses with a website are kept. Each result is flagged as a
     * duplicate when its business_id already exists. The full filtered set is
     * cached under a token so the import step can reuse it without re-calling
     * the external API.
     */
    public function search(Request $request, GoogleMapsService $service)
    {
        $request->validate([
            'query' => 'required|string|max:255',
            'limit' => 'nullable|integer|min:1|max:300',
        ]);

        try {
            $results = $service->search($request->input('query'), (int) $request->input('limit', 100));

            $total = count($results);

            $withWebsite = array_values(array_filter(
                $results,
                fn ($r) => ! empty($r['website']) && empty($r['is_permanently_closed'])
            ));

            $existing = $this->leadGreenRepository->findExistingBusinessIds(
                array_filter(array_column($withWebsite, 'business_id'))
            );

            $leads = array_map(function ($r) use ($existing) {
                return [
                    'business_id'           => $r['business_id'] ?? null,
                    'name'                  => $r['name'] ?? null,
                    'phone_number'          => $r['phone_number'] ?? null,
                    'website'               => $r['website'] ?? null,
                    'full_address'          => $r['full_address'] ?? null,
                    'city'                  => $r['city'] ?? null,
                    'state'                 => $r['state'] ?? null,
                    'rating'                => $r['rating'] ?? null,
                    'review_count'          => $r['review_count'] ?? null,
                    'types'                 => $r['types'] ?? [],
                    'working_hours'         => $r['working_hours'] ?? null,
                    'price_level'           => $r['price_level'] ?? null,
                    'is_claimed'            => ! empty($r['is_claimed']),
                    'verified'              => ! empty($r['verified']),
                    'is_permanently_closed' => ! empty($r['is_permanently_closed']),
                    'is_temporarily_closed' => ! empty($r['is_temporarily_closed']),
                    'latitude'              => $r['latitude'] ?? null,
                    'longitude'             => $r['longitude'] ?? null,
                    'place_link'            => $r['place_link'] ?? null,
                    'photos'                => $r['photos'] ?? [],
                    'is_duplicate'          => in_array($r['business_id'] ?? null, $existing, true),
                ];
            }, $withWebsite);

            $duplicates = count(array_filter($leads, fn ($l) => $l['is_duplicate']));

            $token = (string) Str::uuid();

            Cache::put('leadgreen_search_'.$token, $withWebsite, now()->addMinutes(30));

            return response()->json([
                'token'  => $token,
                'counts' => [
                    'total'        => $total,
                    'with_website' => count($withWebsite),
                    'duplicates'   => $duplicates,
                    'new'          => count($withWebsite) - $duplicates,
                ],
                'leads'  => $leads,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirm the import of a previously previewed search.
     */
    public function import(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        $results = Cache::get('leadgreen_search_'.$request->input('token'));

        if ($results === null) {
            return response()->json(['message' => trans('leadgreen::app.search.error.expired')], 422);
        }

        try {
            $stats = $this->leadGreenRepository->importResults($results);

            Cache::forget('leadgreen_search_'.$request->input('token'));

            return response()->json([
                'message' => trans('leadgreen::app.search.success', $stats),
                'stats'   => $stats,
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
        $lead = $this->leadGreenRepository->find($id);

        if (! $lead) {
            return response()->json(['message' => trans('leadgreen::app.error.not-found')], 404);
        }

        return response()->json([
            'lead' => $lead,
        ]);
    }

    /**
     * Convert a prospect into a real CRM lead.
     */
    public function convert(int $id)
    {
        $lead = $this->leadGreenRepository->find($id);

        if (! $lead) {
            return response()->json(['message' => trans('leadgreen::app.error.not-found')], 404);
        }

        if ($lead->isConverted()) {
            return response()->json(['message' => trans('leadgreen::app.error.already-converted')], 400);
        }

        try {
            $opportunity = $this->leadGreenRepository->convertToLead($id);

            return response()->json([
                'message'  => trans('leadgreen::app.success.converted'),
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

        $lead = $this->leadGreenRepository->find($id);

        if (! $lead) {
            return response()->json(['message' => trans('leadgreen::app.error.not-found')], 404);
        }

        try {
            $this->leadGreenRepository->markAsUsed($id, 'descartado', $request->reason);

            return response()->json(['message' => trans('leadgreen::app.success.discarded')]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Enrich a single prospect from its website (synchronous, on demand).
     */
    public function enrich(int $id)
    {
        $lead = $this->leadGreenRepository->find($id);

        if (! $lead) {
            return response()->json(['message' => trans('leadgreen::app.error.not-found')], 404);
        }

        try {
            $lead = $this->leadGreenRepository->enrich($id);

            return response()->json([
                'message' => trans('leadgreen::app.enrichment.success'),
                'lead'    => $lead,
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
        $byStatus = $this->leadGreenRepository->getModel()
            ->selectRaw('enrichment_status, count(*) as total')
            ->groupBy('enrichment_status')
            ->pluck('total', 'enrichment_status');

        $total = (int) $byStatus->sum();
        $pending = (int) ($byStatus['pending'] ?? 0);
        $processed = $total - $pending;

        return response()->json([
            'total'      => $total,
            'pending'    => $pending,
            'enriched'   => (int) ($byStatus['enriched'] ?? 0),
            'empty'      => (int) ($byStatus['empty'] ?? 0),
            'no_website' => (int) ($byStatus['no_website'] ?? 0),
            'failed'     => (int) ($byStatus['failed'] ?? 0),
            'processed'  => $processed,
            'percent'    => $total > 0 ? (int) round($processed / $total * 100) : 100,
        ]);
    }

    /**
     * Export prospects as CSV or a standalone HTML report.
     */
    public function export(Request $request)
    {
        try {
            return $this->leadGreenRepository->export($request->all(), $request->get('format', 'csv'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
