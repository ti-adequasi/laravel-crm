<?php

namespace Webkul\LeadPeering\DataGrids;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class LeadPeeringDataGrid extends DataGrid
{
    /**
     * Human-readable labels/colors for the peeringdb_type column.
     */
    protected const TYPE_BADGES = [
        'org' => ['Organização', 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400'],
        'net' => ['Rede', 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400'],
        'fac' => ['Data Center', 'bg-teal-100 text-teal-800 dark:bg-teal-900/20 dark:text-teal-400'],
    ];

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
        $queryBuilder = DB::table('lead_peering_prospects')
            ->leftJoin('users', 'lead_peering_prospects.used_by', '=', 'users.id')
            ->select(
                'lead_peering_prospects.id',
                'lead_peering_prospects.peeringdb_id',
                'lead_peering_prospects.peeringdb_type',
                'lead_peering_prospects.name',
                'lead_peering_prospects.country',
                'lead_peering_prospects.city',
                'lead_peering_prospects.state',
                'lead_peering_prospects.asn',
                'lead_peering_prospects.info_type',
                'lead_peering_prospects.net_count',
                'lead_peering_prospects.fac_count',
                'lead_peering_prospects.ix_count',
                'lead_peering_prospects.website',
                'lead_peering_prospects.lead_status',
                'lead_peering_prospects.enrichment_status',
                'lead_peering_prospects.enrichment_score',
                'lead_peering_prospects.email',
                'lead_peering_prospects.whatsapp',
                'lead_peering_prospects.used_at',
                'lead_peering_prospects.used_by',
                'lead_peering_prospects.opportunity_id',
                'users.name as used_by_name'
            );

        $this->addFilter('name', 'lead_peering_prospects.name');
        $this->addFilter('peeringdb_type', 'lead_peering_prospects.peeringdb_type');
        $this->addFilter('country', 'lead_peering_prospects.country');
        $this->addFilter('city', 'lead_peering_prospects.city');
        $this->addFilter('asn', 'lead_peering_prospects.asn');
        $this->addFilter('info_type', 'lead_peering_prospects.info_type');
        $this->addFilter('website', 'lead_peering_prospects.website');
        $this->addFilter('lead_status', 'lead_peering_prospects.lead_status');
        $this->addFilter('enrichment_status', 'lead_peering_prospects.enrichment_status');
        $this->addFilter('created_at', 'lead_peering_prospects.created_at');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'name',
            'label' => trans('leadpeering::app.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            'visibility' => true,
        ]);

        $this->addColumn([
            'index' => 'peeringdb_type',
            'label' => trans('leadpeering::app.datagrid.type'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('leadpeering::app.search.type-org'), 'value' => 'org'],
                ['label' => trans('leadpeering::app.search.type-net'), 'value' => 'net'],
                ['label' => trans('leadpeering::app.search.type-fac'), 'value' => 'fac'],
            ],
            'sortable' => true,
            'visibility' => true,
            'closure' => function ($row) {
                [$label, $color] = self::TYPE_BADGES[$row->peeringdb_type] ?? [$row->peeringdb_type, 'bg-gray-100 text-gray-800'];

                return '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.$color.'">'.$label.'</span>';
            },
        ]);

        $this->addColumn([
            'index' => 'country',
            'label' => trans('leadpeering::app.datagrid.country'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            'visibility' => true,
            'closure' => function ($row) {
                if (! $row->country) {
                    return '<span class="text-xs text-gray-400">—</span>';
                }

                $location = $row->city ? $row->city.' / '.$row->country : $row->country;

                return '<span class="text-sm text-gray-700 dark:text-gray-300">'.htmlspecialchars($location).'</span>';
            },
        ]);

        $this->addColumn([
            'index' => 'asn',
            'label' => trans('leadpeering::app.datagrid.asn'),
            'type' => 'integer',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            // Hidden by default — '-' for every org/fac row (only 'net'
            // prospects have one), and with it visible this grid's ten
            // columns collide at ~1024px (confirmed live). Still filterable/
            // sortable, and already shown in the detail modal.
            'visibility' => false,
            'closure' => fn ($row) => $row->asn ?: '-',
        ]);

        $this->addColumn([
            'index' => 'info_type',
            'label' => trans('leadpeering::app.datagrid.network-type'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable' => true,
            // Hidden by default — only meaningful for 'net' prospects, and
            // this grid already mixes org/net/fac rows in one table.
            'visibility' => false,
            'closure' => fn ($row) => $row->info_type
                ? '<span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10 dark:bg-indigo-900/20 dark:text-indigo-400">'.htmlspecialchars($row->info_type).'</span>'
                : '-',
        ]);

        $this->addColumn([
            'index' => 'presence',
            'label' => trans('leadpeering::app.datagrid.presence'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable' => false,
            // Hidden by default — a nice-to-know scale signal, not a
            // primary column; still available via the column picker.
            'visibility' => false,
            'closure' => function ($row) {
                $parts = array_filter([
                    $row->fac_count ? $row->fac_count.' DCs' : null,
                    $row->net_count ? $row->net_count.' redes' : null,
                    $row->ix_count ? $row->ix_count.' IXs' : null,
                ]);

                return $parts ? '<span class="text-xs text-gray-500 dark:text-gray-400">'.implode(' · ', $parts).'</span>' : '-';
            },
        ]);

        $this->addColumn([
            'index' => 'website',
            'label' => trans('leadpeering::app.datagrid.website'),
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

                return '<div class="max-w-[200px] overflow-hidden"><a href="'.htmlspecialchars($row->website).'" target="_blank" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 hover:underline truncate" title="'.htmlspecialchars($row->website).'"><span class="icon-organization text-lg shrink-0"></span><span class="text-xs truncate">'.htmlspecialchars($display).'</span></a></div>';
            },
        ]);

        $this->addColumn([
            'index' => 'lead_status',
            'label' => trans('leadpeering::app.datagrid.status'),
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
                    return '<a href="'.route('admin.leads.view', $row->opportunity_id).'" class="underline decoration-dotted underline-offset-2 hover:opacity-75" title="'.trans('leadpeering::app.datagrid.view-opportunity').'">'.$badge.'</a>';
                }

                return $badge;
            },
        ]);

        $this->addColumn([
            'index' => 'enrichment_status',
            'label' => trans('leadpeering::app.datagrid.enrichment'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => true,
            'filterable_type' => 'dropdown',
            'filterable_options' => [
                ['label' => trans('leadpeering::app.enrichment.status-enriched'), 'value' => 'enriched'],
                ['label' => trans('leadpeering::app.enrichment.status-pending'), 'value' => 'pending'],
                ['label' => trans('leadpeering::app.enrichment.status-empty'), 'value' => 'empty'],
                ['label' => trans('leadpeering::app.enrichment.status-no-website'), 'value' => 'no_website'],
                ['label' => trans('leadpeering::app.enrichment.status-failed'), 'value' => 'failed'],
            ],
            'sortable' => true,
            'visibility' => true,
            'closure' => function ($row) {
                $map = [
                    'enriched' => ['bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400', trans('leadpeering::app.enrichment.status-enriched')],
                    'pending' => ['bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400', trans('leadpeering::app.enrichment.status-pending')],
                    'empty' => ['bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300', trans('leadpeering::app.enrichment.status-empty')],
                    'no_website' => ['bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300', trans('leadpeering::app.enrichment.status-no-website')],
                    'failed' => ['bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400', trans('leadpeering::app.enrichment.status-failed')],
                ];

                [$color, $label] = $map[$row->enrichment_status] ?? ['bg-amber-100 text-amber-800', trans('leadpeering::app.enrichment.status-pending')];

                $badge = '<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.$color.'">'.$label.'</span>';

                if ($row->enrichment_status === 'enriched' && $row->enrichment_score) {
                    $badge .= '<span class="ml-1 text-xs text-gray-500 dark:text-gray-400">'.(int) $row->enrichment_score.'/100</span>';
                }

                $tags = '';

                if (! empty($row->email)) {
                    $tags .= '<span class="inline-flex items-center gap-1 rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-400" title="'.htmlspecialchars($row->email).'"><span class="icon-mail dark:!text-blue-400"></span>E-mail</span>';
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
            'index' => 'lead_actions',
            'label' => trans('leadpeering::app.datagrid.actions'),
            'type' => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable' => false,
            'visibility' => true,
            'closure' => function ($row) {
                $actions = '<button onclick="openLeadPeeringModal('.$row->id.')" class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800" title="'.trans('leadpeering::app.datagrid.view').'"><span class="icon-eye"></span></button>';

                $status = $row->raw_lead_status ?? $row->lead_status;

                if (in_array($status, ['novo', 'reaproveitavel'])) {
                    $actions .= '<button onclick="window.convertLeadPeering('.$row->id.')" class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800" title="'.trans('leadpeering::app.datagrid.convert').'"><span class="icon-forward"></span></button>';
                    $actions .= '<button onclick="window.discardLeadPeering('.$row->id.')" class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800" title="'.trans('leadpeering::app.datagrid.discard').'"><span class="icon-error"></span></button>';
                } elseif ($status === 'convertido' && $row->opportunity_id) {
                    $actions .= '<a href="'.route('admin.leads.view', $row->opportunity_id).'" class="cursor-pointer rounded-md p-1.5 text-2xl text-gray-600 transition-all hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-800 inline-block" title="'.trans('leadpeering::app.datagrid.view-opportunity').'"><span class="icon-forward"></span></a>';
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
