<?php

return [
    'menu' => [
        'title' => 'PBX',
        'info' => 'Connect this tenant to its own PBX domain for click-to-call.',
    ],

    'acl' => [
        'title' => 'PBX',
        'calls-title' => 'Place Calls',
    ],

    'settings' => [
        'title' => 'PBX Settings',
        'info' => 'Connect this tenant to its own PBX Inovalen domain — the API key alone identifies which domain a request belongs to, so every tenant on this install uses the same PBX server with its own key.',
        'enabled' => 'Enabled',
        'auto-log-activity' => 'Log every call automatically',
        'auto-log-activity-hint' => 'When on, every call placed through the CRM is added to the Lead/Person timeline as soon as it ends — even if the browser tab was closed before then. Turn off to place calls without adding timeline entries; the full call history stays available either way.',
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

    'calls' => [
        'call-button' => 'Call',
        'hangup-button' => 'Hang up',
        'calling' => 'Calling...',
        'in-progress' => 'Call in progress',
        'ended' => 'Call ended',
        'not-configured' => 'This tenant has no PBX connection configured yet.',
        'no-extension' => 'You have no extension (Ramal) configured yet — set one under your account settings first.',
        'invalid-phone' => 'This doesn\'t look like a valid phone number.',
        'originate-failed' => 'The PBX refused the call: :error',
        'originate-failed-generic' => 'Could not place the call. Please try again.',
        'not-found' => 'This call could not be found.',
        'not-yours' => 'This call belongs to another user.',
        'hangup-failed-generic' => 'Could not hang up. It may have already ended.',
        'activity-title' => 'Call to :phone',
        'activity-comment' => 'Outbound call to :phone from extension :ramal.',
    ],
];
