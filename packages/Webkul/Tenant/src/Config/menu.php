<?php

return [
    [
        'key' => 'tenant',
        'name' => 'tenant::app.menu.title',
        'route' => 'admin.tenant.index',
        'sort' => 10,
        'icon-class' => 'icon-setting',
        // Webkul\Core\Menu hides any item carrying this flag from every
        // user except a super-admin (tenant_id NULL) — this route is
        // already gated the same way by EnsureSuperAdmin, so without this
        // flag a tenant-scoped admin with the 'all' permission role would
        // see the link in their sidebar and get a 403 clicking it.
        'super_admin_only' => true,
    ],
];
