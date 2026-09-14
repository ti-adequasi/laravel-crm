<?php

return [
    [
        'key' => 'lead_peering',
        'name' => 'leadpeering::app.settings.tab',
        'info' => 'leadpeering::app.settings.tab-info',
        'sort' => 10,
    ], [
        'key' => 'lead_peering.settings',
        'name' => 'leadpeering::app.settings.section',
        'info' => 'leadpeering::app.settings.section-info',
        'icon' => 'icon-settings',
        'sort' => 1,
    ], [
        'key' => 'lead_peering.settings.api_keys',
        'name' => 'leadpeering::app.settings.api-keys.title',
        'info' => 'leadpeering::app.settings.api-keys.info',
        'sort' => 1,
        'fields' => [
            [
                'name' => 'peeringdb_api_key',
                'title' => 'leadpeering::app.settings.api-keys.peeringdb-api-key',
                'info' => 'leadpeering::app.settings.api-keys.peeringdb-api-key-info',
                'type' => 'password',
            ], [
                'name' => 'cnpja_api_key',
                'title' => 'leadpeering::app.settings.api-keys.cnpja-api-key',
                'type' => 'password',
            ], [
                'name' => 'cnpja_daily_limit',
                'title' => 'leadpeering::app.settings.api-keys.cnpja-daily-limit',
                'type' => 'number',
                'default' => 45,
                'validation' => 'min:1',
            ],
        ],
    ], [
        'key' => 'lead_peering.settings.enrichment',
        'name' => 'leadpeering::app.settings.enrichment.title',
        'info' => 'leadpeering::app.settings.enrichment.info',
        'sort' => 2,
        'fields' => [
            [
                'name' => 'detect_lgpd_signals',
                'title' => 'leadpeering::app.settings.enrichment.detect-lgpd-signals',
                'info' => 'leadpeering::app.settings.enrichment.detect-lgpd-signals-info',
                'type' => 'boolean',
                'default' => 1,
            ],
        ],
    ],
];
