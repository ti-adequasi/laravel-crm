<?php

return [
    'acl' => [
        'title' => 'Lead Green',
    ],

    'menu' => [
        'title' => 'Lead Green',
    ],

    'title'         => 'Lead Green',
    'search-button' => 'Search Google Maps',

    'search' => [
        'title'             => 'Search Google Maps',
        'back-to-list'      => 'Back to list',
        'description'       => 'Search for businesses on Google Maps and import the ones worth prospecting.',
        'website-only-note' => 'Only businesses with a website are kept — there is no way to reach the rest through the CRM.',
        'query-label'       => 'What are you looking for?',
        'query-placeholder' => 'e.g. Schools in Mogi das Cruzes - SP',
        'query-hint'        => 'Be specific: a business type plus a city works best.',
        'limit-label'       => 'Result limit',
        'submit'            => 'Search',
        'searching'         => 'Searching…',
        'new-search'        => 'New search',
        'importing'         => 'Importing…',
        'success'           => ':inserted imported, :skipped skipped (of :found found).',

        'error' => [
            'no-api-key'      => 'No RapidAPI key configured — add one under Configuration > Lead Green.',
            'request-failed'  => 'The Google Maps search failed (HTTP :status).',
            'expired'         => 'This search preview has expired — search again.',
        ],

        'preview' => [
            'title'          => 'Results',
            'empty'          => 'No businesses matched.',
            'total'          => 'Found',
            'with-website'   => 'With website',
            'duplicates'     => 'Already imported',
            'new'            => 'New',
            'col-name'       => 'Name',
            'col-location'   => 'Location',
            'col-phone'      => 'Phone',
            'col-website'    => 'Website',
            'col-rating'     => 'Rating',
            'col-status'     => 'Status',
            'badge-duplicate' => 'Already imported',
            'badge-new'      => 'New',
            'nothing-new'    => 'Nothing new to import.',
            'import-btn'     => 'Import :count new prospect(s)',
        ],
    ],

    'datagrid' => [
        'name'       => 'Name',
        'city'       => 'City',
        'state'      => 'State',
        'types'      => 'Type',
        'rating'     => 'Rating',
        'reviews'    => 'Reviews',
        'website'    => 'Website',
        'status'     => 'Status',
        'enrichment' => 'Enrichment',
        'privacy'    => 'Privacy policy',
        'dpo'        => 'DPO',
        'actions'    => 'Actions',
        'view'       => 'View',
        'convert'    => 'Convert to lead',
        'discard'    => 'Discard',
    ],

    'enrichment' => [
        'title'          => 'Enrichment',
        'not-enriched'   => 'Not enriched yet.',
        'success'        => 'Prospect enriched successfully.',
        'email'          => 'Email',
        'whatsapp'       => 'WhatsApp',
        'instagram'      => 'Instagram',
        'facebook'       => 'Facebook',
        'company-title'  => 'Company (CNPJ)',
        'cnpj'           => 'CNPJ',
        'razao-social'   => 'Legal name',
        'situacao'       => 'Status',
        'porte'          => 'Size',
        'privacy-title'  => 'LGPD',
        'yes'            => 'Yes',
        'no'             => 'No',
        'status-enriched'   => 'Enriched',
        'status-pending'    => 'Pending',
        'status-empty'      => 'No data found',
        'status-no-website' => 'No website',
        'status-failed'     => 'Failed',
    ],

    'modal' => [
        'phone'                  => 'Phone',
        'website'                => 'Website',
        'address'                => 'Address',
        'close'                  => 'Close',
        'confirm-convert'        => 'Convert this prospect into a CRM lead?',
        'discard-reason-prompt'  => 'Why are you discarding this prospect?',
    ],

    'error' => [
        'not-found'          => 'Prospect not found.',
        'already-converted'  => 'This prospect has already been converted.',
    ],

    'success' => [
        'converted' => 'Converted into a lead successfully.',
        'discarded' => 'Prospect discarded.',
    ],

    'settings' => [
        'tab'      => 'Lead Green',
        'tab-info' => 'Google Maps prospecting and enrichment',
        'section'      => 'Settings',
        'section-info' => 'API keys used for prospecting and enrichment',

        'api-keys' => [
            'title'               => 'API keys',
            'info'                => 'Both integrations are optional — leave a key blank to skip that source.',
            'rapidapi-maps-key'   => 'RapidAPI key (maps-data)',
            'rapidapi-maps-host'  => 'RapidAPI host',
            'cnpja-api-key'       => 'CNPJá commercial API key',
            'cnpja-daily-limit'   => 'CNPJá daily credit limit',
        ],
    ],
];
