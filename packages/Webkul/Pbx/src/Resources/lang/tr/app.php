<?php

return [
    'menu' => [
        'title' => 'PBX',
        'info' => 'Connect this tenant to its own PBX domain for click-to-call.',
    ],

    'acl' => [
        'title' => 'PBX',
    ],

    'settings' => [
        'title' => 'PBX Settings',
        'info' => 'Connect this tenant to its own PBX Inovalen domain — the API key alone identifies which domain a request belongs to, so every tenant on this install uses the same PBX server with its own key.',
        'enabled' => 'Enabled',
        'api-key' => 'API Key',
        'api-key-hint' => 'Leave blank to keep the currently saved key.',
        'test-connection' => 'Test Connection',
        'testing' => 'Testing...',
        'test-success' => 'Connected — this key belongs to domain ":domain".',
        'test-failed' => 'Connection failed: :error',
        'test-failed-generic' => 'Could not test the connection. Please try again.',
        'test-missing-key' => 'Enter an API key first.',
        'save-btn' => 'Save',
        'saving' => 'Saving...',
        'save-success' => 'PBX settings saved successfully.',
        'save-failed-generic' => 'Could not save the PBX settings. Please try again.',
    ],
];
