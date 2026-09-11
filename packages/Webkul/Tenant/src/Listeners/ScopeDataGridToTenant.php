<?php

namespace Webkul\Tenant\Listeners;

use Illuminate\Support\Facades\Schema;
use Webkul\DataGrid\DataGrid;
use Webkul\Tenant\Support\CurrentTenant;

/**
 * Every DataGrid builds its listing with a raw DB::table() query builder,
 * not Eloquent — confirmed across every DataGrid in this codebase (Lead,
 * Pipeline, Tenant's own, etc.). BelongsToTenant's global scope is an
 * Eloquent mechanism, so it has zero effect on these listings; without
 * this listener, every DataGrid page would show every tenant's rows
 * regardless of the scope applied everywhere else. Hooked onto the base
 * DataGrid class's own 'query_builder.set.after' event (dispatched,
 * per-subclass-name, as "datagrid.<snake_case_class>.query_builder.set.after")
 * via Laravel's wildcard listener support — no edit to
 * packages/Webkul/DataGrid needed.
 */
class ScopeDataGridToTenant
{
    /**
     * Tables confirmed to have a tenant_id column, cached for the life of
     * the request — Schema::hasColumn() is a real query, and a DataGrid
     * page can re-set its query builder more than once per request.
     */
    protected static array $tenantOwnedTables = [];

    public function handle(string $eventName, array $payload): void
    {
        $dataGrid = $payload[0] ?? null;

        if (! $dataGrid instanceof DataGrid) {
            return;
        }

        $tenantId = CurrentTenant::id();

        if ($tenantId === null) {
            return;
        }

        $queryBuilder = $dataGrid->getQueryBuilder();

        if (! is_object($queryBuilder) || ! property_exists($queryBuilder, 'from') || ! is_string($queryBuilder->from)) {
            return;
        }

        $table = $queryBuilder->from;

        if (! $this->tableHasTenantColumn($table)) {
            return;
        }

        $queryBuilder->where("{$table}.tenant_id", $tenantId);
    }

    protected function tableHasTenantColumn(string $table): bool
    {
        if (! array_key_exists($table, static::$tenantOwnedTables)) {
            static::$tenantOwnedTables[$table] = Schema::hasColumn($table, 'tenant_id');
        }

        return static::$tenantOwnedTables[$table];
    }
}
