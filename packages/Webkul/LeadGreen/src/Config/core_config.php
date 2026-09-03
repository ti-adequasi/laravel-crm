<?php

return [
    [
        'key'  => 'lead_green',
        'name' => 'leadgreen::app.settings.tab',
        'info' => 'leadgreen::app.settings.tab-info',
        'sort' => 9,
    ], [
        'key'  => 'lead_green.settings',
        'name' => 'leadgreen::app.settings.section',
        'info' => 'leadgreen::app.settings.section-info',
        'icon' => 'icon-settings',
        'sort' => 1,
    ], [
        'key'  => 'lead_green.settings.api_keys',
        'name' => 'leadgreen::app.settings.api-keys.title',
        'info' => 'leadgreen::app.settings.api-keys.info',
        'sort' => 1,
        'fields' => [
            [
                'name'  => 'rapidapi_maps_key',
                'title' => 'leadgreen::app.settings.api-keys.rapidapi-maps-key',
                'type'  => 'password',
            ], [
                'name'    => 'rapidapi_maps_host',
                'title'   => 'leadgreen::app.settings.api-keys.rapidapi-maps-host',
                'type'    => 'text',
                'default' => 'maps-data.p.rapidapi.com',
            ], [
                'name'  => 'cnpja_api_key',
                'title' => 'leadgreen::app.settings.api-keys.cnpja-api-key',
                'type'  => 'password',
            ], [
                'name'       => 'cnpja_daily_limit',
                'title'      => 'leadgreen::app.settings.api-keys.cnpja-daily-limit',
                'type'       => 'number',
                'default'    => 45,
                'validation' => 'min:1',
            ],
        ],
    ],
];
