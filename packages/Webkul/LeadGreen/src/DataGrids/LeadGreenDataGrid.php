<?php

namespace Webkul\LeadGreen\DataGrids;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\DataGrid\DataGrid;

class LeadGreenDataGrid extends DataGrid
{
    /**
     * Default sort order.
     *
     * @var string
     */
    protected $sortOrder = 'desc';

    /**
     * Default index column.
     *
     * @var string
     */
    protected $index = 'id';

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('lead_green_prospects')
            ->leftJoin('users', 'lead_green_prospects.used_by', '=', 'users.id')
            ->select(
                'lead_green_prospects.id',
                'lead_green_prospects.business_id',
                'lead_green_prospects.name',
                'lead_green_prospects.city',
                'lead_green_prospects.state',
                'lead_green_prospects.types',
                'lead_green_prospects.rating',
                'lead_green_prospects.review_count',
                'lead_green_prospects.website',
                'lead_green_prospects.phone_number',
                'lead_green_prospects.lead_status',
                'lead_green_prospects.enrichment_status',
                'lead_green_prospects.enrichment_score',
                'lead_green_prospects.email',
                'lead_green_prospects.whatsapp',
                'lead_green_prospects.has_privacy_policy',
                'lead_green_prospects.has_dpo',
                'lead_green_prospects.dpo_name',
                'lead_green_prospects.dpo_email',
                'lead_green_prospects.used_at',
                'lead_green_prospects.used_by',
                'lead_green_prospects.full_address',
                'lead_green_prospects.opportunity_id',
                'users.name as used_by_name'
            );

        $this->addFilter('name', 'lead_green_prospects.name');
        $this->addFilter('city', 'lead_green_prospects.city');
        $this->addFilter('state', 'lead_green_prospects.state');
        $this->addFilter('types', 'lead_green_prospects.types');
        $this->addFilter('rating', 'lead_green_prospects.rating');
        $this->addFilter('review_count', 'lead_green_prospects.review_count');
        $this->addFilter('website', 'lead_green_prospects.website');
        $this->addFilter('lead_status', 'lead_green_prospects.lead_status');
        $this->addFilter('enrichment_status', 'lead_green_prospects.enrichment_status');
        $this->addFilter('has_privacy_policy', 'lead_green_prospects.has_privacy_policy');
        $this->addFilter('has_dpo', 'lead_green_prospects.has_dpo');
        $this->addFilter('created_at', 'lead_green_prospects.created_at');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns()
    {
        // Configuration > Lead Green > Enrichment — off for segments where a
        // privacy policy / DPO isn't a meaningful prospecting signal. Columns
        // stay hidden rather than removed so a re-enable doesn't lose data
        // already gathered while it was on.
        $detectLgpd = (bool) core()->getConfigData('lead_green.settings.enrichment.detect_lgpd_signals');

        $this->addColumn([
            'index' => 'name',
            'label' => trans('leadgreen::app.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            'visibility' => true,
        ]);

        $this->addColumn([
            'index' => 'city',
            'label' => trans('leadgreen::app.datagrid.city'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            'visibility' => true,
        ]);

        $this->addColumn([
            'index' => 'state',
            'label' => trans('leadgreen::app.datagrid.state'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            // Hidden by default — redundant with Cidade for the common
            // single-region prospecting session, and this grid already has
            // enough equal-width columns to collide at ordinary laptop
            // widths (~1024-1280px). Still filterable/sortable; a user can
            // re-enable it from the grid's own column-visibility picker.
            'visibility' => false,
        ]);

        $this->addColumn([
            'index' => 'types',
            'label' => trans('leadgreen::app.datagrid.types'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable' => false,
            // Hidden by default — rich category badges take real width on a
            // grid that's already tight on room (see the note on 'state'
            // above); still filterable, and the category chips already
            // shown on the search page cover the "what kind of business is
            // this" question before anything gets imported.
            'visibility' => false,
            'closure' => function ($row) {
                $types = json_decode($row->types, true);

                if (! is_array($types) || empty($types)) {
                    return '-';
                }

                $html = '';

                foreach (array_slice($types, 0, 2) as $type) {
                    $html .= '<span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 mr-1 dark:bg-blue-900/20 dark:text-blue-400">'.htmlspecialchars($type).'</span>';
                }

                if (count($types) > 2) {
                    $html .= '<span class="text-xs text-gray-500 dark:text-gray-400">+'.(count($types) - 2).'</span>';
                }

                return $html;
            },
        ]);

        $this->addColumn([
            'index' => 'rating',
            'label' => trans('leadgreen::app.datagrid.rating'),
            'type' => 'float',
            'searchable' => false,
            'filterable' => true,
            'sortable' => true,
            'visibility' => true,
            'closure' => function ($row) {
                if (! $row->rating) {
                    return '-';
                }

                $reviews = $row->review_count
                    ? '<span class="text-xs text-gray-500 dark:text-gray-400">('.$row->review_count.')</span>'
                    : '';

                return '<div class="flex items-center gap-1"><span class="text-yellow-500">★</span><span class="text-sm font-semibold text-gray-900 dark:text-white">'.number_format($row->rating, 1).'</span>'.$reviews.'</div>';
            },
        ]);

        $this->addColumn([
            'index' => 'review_count',
            'label' => trans('leadgreen::app.datagrid.reviews'),
            'type' => 'integer',
            'searchable' => false,
            'filterable' => true,
            'sortable' => true,
            // Hidden by default — shown inline in parentheses on the rating
            // column instead ("★ 4.4 (3683)"), which is how this pairing
            // reads everywhere else in the product (the search-results
            // preview, the prospect detail modal). Still its own filterable
            // column underneath, so "minimum reviews" filtering is unaffected.
            'visibility' => false,
            'closure' => fn ($row) => $row->review_count ?? '-',
        ]);

        $this->addColumn([
            'index' => 'website',
            'label' => trans('leadgreen::app.datagrid.website'),
            'type' => 'boolean',
            'searchable' => false,
            'filterable' => true,
            'sortable' => false,
            'visibility' => true,
            'closure' => function ($row) {
                if (! $row->website) {
                    return '-';
                }

                $domain = parse_url($row->website, PHP_URL_HOST);
                $display = $domain ?: (strlen($row->website) > 25 ? substr($row->website, 0, 25).'...' : $row->website);

                if (strlen($display) > 25) {
                    $display = substr($display, 0, 22).'...';
                }

                return '<div class="max-w-[200px] overflow-hidden"><a href="'.htmlspecialchars($row->website).'" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 hover:underline truncate" title="'.htmlspecialchars($row->website).'"><span class="icon-globe text-lg shrink-0"></span><span class="text-xs truncate">'.htmlspecialchars($display).'</span></a></div>';
            },
        ]);

        $this->addColumn([
            'index' => 'lead_status',
            'label' => trans('leadgreen::app.datagrid.status'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable' => true,
            'visibility' => true,
            'closure' => function ($row) {
                $colors = [
                    'novo' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400',
                    'em_prospeccao' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
                    'convertido' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                    'descartado' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                    'reaproveitavel' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                ];

                $labels = [
                    'novo' => 'Novo',
                    'em_prospeccao' => 'Em prospecção',
                    'convertido' => 'Convertido',
                    'descartado' => 'Descartado',
                    'reaproveitavel' => 'Reaproveitável',
                ];

                $color = $colors[$row->lead_status] ?? 'bg-gray-100 text-gray-800';
                $label = $labels[$row->lead_status] ?? $row->lead_status;

                // The datagrid engine overwrites $row->lead_status in place with
                // whatever this closure returns, so the raw value is gone by the
                // time a later column's closure (lead_actions, below) runs on the
                // same row — stash it under another key so that check still works.
                $row->raw_lead_status = $row->lead_status;

                $badge = '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.$color.'">'.$label.'</span>';

                // A converted prospect's whole reason for being visited again is
                // to jump to what it became — make the badge itself the link,
                // right where the eye is already looking.
                if ($row->raw_lead_status === 'convertido' && $row->opportunity_id) {
                    return '<a href="'.route('admin.leads.view', $row->opportunity_id).'" class="underline decoration-dotted underline-offset-2 hover:opacity-75" title="'.trans('leadgreen::app.datagrid.view-opportunity').'">'.$badge.'</a>';
                }

                return $badge;
            },
        ]);

        $this->addColumn([
            'index' => 'enrichment_status',
            'label' => trans('leadgreen::app.datagrid.enrichment'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('leadgreen::app.enrichment.status-enriched'), 'value' => 'enriched'],
                ['label' => trans('leadgreen::app.enrichment.status-pending'), 'value' => 'pending'],
                ['label' => trans('leadgreen::app.enrichment.status-empty'), 'value' => 'empty'],
                ['label' => trans('leadgreen::app.enrichment.status-no-website'), 'value' => 'no_website'],
                ['label' => trans('leadgreen::app.enrichment.status-failed'), 'value' => 'failed'],
            ],
            'sortable' => true,
            'visibility' => true,
            'closure' => function ($row) {
                $map = [
                    'enriched' => ['bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400', trans('leadgreen::app.enrichment.status-enriched')],
                    'pending' => ['bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400', trans('leadgreen::app.enrichment.status-pending')],
                    'empty' => ['bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300', trans('leadgreen::app.enrichment.status-empty')],
                    'no_website' => ['bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300', trans('leadgreen::app.enrichment.status-no-website')],
                    'failed' => ['bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400', trans('leadgreen::app.enrichment.status-failed')],
                ];

                [$color, $label] = $map[$row->enrichment_status] ?? ['bg-amber-100 text-amber-800', trans('leadgreen::app.enrichment.status-pending')];

                $badge = '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.$color.'">'.$label.'</span>';

                if ($row->enrichment_status === 'enriched' && $row->enrichment_score) {
                    $badge .= '<span class="ml-1 text-xs text-gray-500 dark:text-gray-400">'.(int) $row->enrichment_score.'/100</span>';
                }

                $tags = '';

                if (! empty($row->email)) {
                    $tags .= '<span class="inline-flex items-center gap-1 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-400" title="'.htmlspecialchars($row->email).'"><span class="icon-mail"></span>E-mail</span>';
                }

                if (! empty($row->whatsapp)) {
                    $tags .= '<span class="inline-flex items-center rounded bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400" title="WhatsApp: '.htmlspecialchars($row->whatsapp).'">WhatsApp</span>';
                }

                if ($tags) {
                    $badge .= '<div class="mt-1 flex flex-wrap items-center gap-1">'.$tags.'</div>';
                }

                return $badge;
            },
        ]);

        $this->addColumn([
            'index' => 'has_privacy_policy',
            'label' => trans('leadgreen::app.datagrid.privacy'),
            'type' => 'boolean',
            'searchable' => false,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('leadgreen::app.enrichment.yes'), 'value' => 1],
                ['label' => trans('leadgreen::app.enrichment.no'), 'value' => 0],
            ],
            'sortable' => true,
            'visibility' => $detectLgpd,
            'closure' => function ($row) {
                if ($row->has_privacy_policy) {
                    return '<span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/20 dark:text-green-400">✓ '.trans('leadgreen::app.enrichment.yes').'</span>';
                }

                return '<span class="text-xs text-gray-400">—</span>';
            },
        ]);

        $this->addColumn([
            'index' => 'has_dpo',
            'label' => trans('leadgreen::app.datagrid.dpo'),
            'type' => 'boolean',
            'searchable' => false,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('leadgreen::app.enrichment.yes'), 'value' => 1],
                ['label' => trans('leadgreen::app.enrichment.no'), 'value' => 0],
            ],
            'sortable' => true,
            'visibility' => $detectLgpd,
            'closure' => function ($row) {
                if (! $row->has_dpo) {
                    return '<span class="text-xs text-gray-400">—</span>';
                }

                $html = '<span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800 dark:bg-purple-900/20 dark:text-purple-400">✓ '.trans('leadgreen::app.enrichment.yes').'</span>';

                if (! empty($row->dpo_name)) {
                    $html .= '<div class="mt-1 text-xs text-gray-600 dark:text-gray-300" title="'.htmlspecialchars($row->dpo_name).'">'.htmlspecialchars(Str::limit($row->dpo_name, 22)).'</div>';
                }

                if (! empty($row->dpo_email)) {
                    $html .= '<a href="mailto:'.htmlspecialchars($row->dpo_email).'" class="text-xs text-blue-600 hover:underline dark:text-blue-400" title="'.htmlspecialchars($row->dpo_email).'">'.htmlspecialchars(Str::limit($row->dpo_email, 22)).'</a>';
                }

                return $html;
            },
        ]);

        $this->addColumn([
            'index' => 'lead_actions',
            'label' => trans('leadgreen::app.datagrid.actions'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable' => false,
            'visibility' => true,
            'closure' => function ($row) {
                $actions = '<button onclick="openLeadGreenModal('.$row->id.')" class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800" title="'.trans('leadgreen::app.datagrid.view').'"><span class="icon-eye"></span></button>';

                $status = $row->raw_lead_status ?? $row->lead_status;

                if (in_array($status, ['novo', 'reaproveitavel'])) {
                    // icon-forward, not icon-add — this transforms an existing
                    // prospect, it doesn't create a new one (icon-add means
                    // "add new X" everywhere else in Admin). Also ties
                    // visually to the "view opportunity" action above, which
                    // uses the same icon once a prospect is actually converted.
                    $actions .= '<button onclick="window.convertLead('.$row->id.')" class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800" title="'.trans('leadgreen::app.datagrid.convert').'"><span class="icon-forward"></span></button>';
                    $actions .= '<button onclick="window.discardLead('.$row->id.')" class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800" title="'.trans('leadgreen::app.datagrid.discard').'"><span class="icon-error"></span></button>';
                } elseif ($status === 'convertido' && $row->opportunity_id) {
                    // Already converted — the only thing left to do from here
                    // is go see what it became.
                    $actions .= '<a href="'.route('admin.leads.view', $row->opportunity_id).'" class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800 inline-block" title="'.trans('leadgreen::app.datagrid.view-opportunity').'"><span class="icon-forward"></span></a>';
                }

                return $actions;
            },
        ]);
    }

    /**
     * Prepare actions. Rendered as a custom column instead (`lead_actions`).
     */
    public function prepareActions() {}
}
