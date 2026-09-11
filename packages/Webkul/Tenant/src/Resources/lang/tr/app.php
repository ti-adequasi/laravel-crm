<?php

return [
    'menu' => [
        'title' => 'Tenants',
    ],

    'acl' => [
        'title' => 'Tenants',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ],

    'index' => [
        'title' => 'Tenants',
        'create-btn' => 'Add Tenant',
        'create-success' => 'Tenant created successfully.',
        'update-success' => 'Tenant updated successfully.',
        'delete-success' => 'Tenant deleted successfully.',
        'delete-failed' => 'Failed to delete tenant.',
        'datagrid' => [
            'id' => 'ID',
            'name' => 'Name',
            'code' => 'Code',
            'status' => 'Status',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'created-at' => 'Created At',
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],
    ],

    'create' => [
        'title' => 'Add Tenant',
        'name' => 'Name',
        'code' => 'Code',
        'code-placeholder' => 'e.g. acme',
        'code-info' => 'A short, unique, URL-safe identifier for this tenant. Cannot be changed carelessly once integrations depend on it.',
        'active' => 'Active',
        'save-btn' => 'Save Tenant',
    ],

    'edit' => [
        'title' => 'Edit Tenant',
        'name' => 'Name',
        'code' => 'Code',
        'active' => 'Active',
        'save-btn' => 'Save Tenant',
    ],
];
