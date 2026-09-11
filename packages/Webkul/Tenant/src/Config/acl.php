<?php

return [
    [
        'key' => 'tenant',
        'name' => 'tenant::app.acl.title',
        'route' => 'admin.tenant.index',
        'sort' => 10,
    ], [
        'key' => 'tenant.create',
        'name' => 'tenant::app.acl.create',
        'route' => ['admin.tenant.create', 'admin.tenant.store'],
        'sort' => 1,
    ], [
        'key' => 'tenant.edit',
        'name' => 'tenant::app.acl.edit',
        'route' => ['admin.tenant.edit', 'admin.tenant.update'],
        'sort' => 2,
    ], [
        'key' => 'tenant.delete',
        'name' => 'tenant::app.acl.delete',
        'route' => 'admin.tenant.destroy',
        'sort' => 3,
    ],
];
