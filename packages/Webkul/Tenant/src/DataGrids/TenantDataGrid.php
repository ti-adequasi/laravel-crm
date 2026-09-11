<?php

namespace Webkul\Tenant\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class TenantDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('tenants')
            ->addSelect(
                'tenants.id',
                'tenants.name',
                'tenants.code',
                'tenants.is_active',
                'tenants.created_at',
            );

        $this->addFilter('id', 'tenants.id');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('tenant::app.index.datagrid.id'),
            'type' => 'string',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'name',
            'label' => trans('tenant::app.index.datagrid.name'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'code',
            'label' => trans('tenant::app.index.datagrid.code'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'is_active',
            'label' => trans('tenant::app.index.datagrid.status'),
            'type' => 'boolean',
            'searchable' => false,
            'filterable' => true,
            'sortable' => true,
            'closure' => fn ($row) => trans('tenant::app.index.datagrid.'.($row->is_active ? 'active' : 'inactive')),
        ]);

        $this->addColumn([
            'index' => 'created_at',
            'label' => trans('tenant::app.index.datagrid.created-at'),
            'type' => 'datetime',
            'sortable' => true,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        $this->addAction([
            'index' => 'edit',
            'icon' => 'icon-edit',
            'title' => trans('tenant::app.index.datagrid.edit'),
            'method' => 'GET',
            'url' => fn ($row) => route('admin.tenant.edit', $row->id),
        ]);

        $this->addAction([
            'index' => 'delete',
            'icon' => 'icon-delete',
            'title' => trans('tenant::app.index.datagrid.delete'),
            'method' => 'DELETE',
            'url' => fn ($row) => route('admin.tenant.destroy', $row->id),
        ]);
    }
}
