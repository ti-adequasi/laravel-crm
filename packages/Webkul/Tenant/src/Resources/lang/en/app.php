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

        'admin-account-title' => 'First Administrator Account',
        'admin-account-info' => 'Creates the tenant\'s own default pipeline, an administrator role, and this first user in one step — a new tenant cannot use the Leads screens at all until it has all three.',
        'admin-name' => 'Name',
        'admin-email' => 'Email',
        'admin-password' => 'Password',
        'admin-password-confirmation' => 'Confirm Password',

        'default-pipeline-name' => ':tenant Default Pipeline',
        'default-stage-new' => 'New',
        'default-stage-negotiation' => 'Negotiation',
        'default-stage-won' => 'Won',
        'default-role-name' => ':tenant Administrator',
    ],

    'edit' => [
        'title' => 'Edit Tenant',
        'name' => 'Name',
        'code' => 'Code',
        'active' => 'Active',
        'save-btn' => 'Save Tenant',

        'users-title' => 'Tenant Users',
        'users-empty' => 'This tenant has no users yet.',
        'users-name' => 'Name',
        'users-email' => 'Email',
        'users-status' => 'Status',
        'users-active' => 'Active',
        'users-inactive' => 'Inactive',

        'add-user-title' => 'Add User',
        'add-user-name' => 'Name',
        'add-user-email' => 'Email',
        'add-user-password' => 'Password',
        'add-user-password-confirmation' => 'Confirm Password',
        'add-user-btn' => 'Add User',
        'user-create-success' => 'User added successfully.',
        'no-role-error' => 'This tenant has no role to assign the new user to. Fix it in the database or contact support.',
    ],
];
