<?php

return [
    'button-title' => 'Enrich from website / CNPJ',
    'note-title'   => 'Automatic enrichment',
    'note-header'  => 'Automatic enrichment — :date',
    'success'      => 'Enriched — a note was added to the timeline.',

    'error' => [
        'not-found'        => 'Lead not found.',
        'nothing-to-enrich' => 'No website or CNPJ to enrich from (checked the organization\'s site and the contact\'s email domain).',
    ],

    'note' => [
        'website'            => 'Website: :website',
        'contacts-title'     => '--- Contacts ---',
        'email'              => '- Email: :email',
        'other-emails'       => '- Other emails: :emails',
        'company-title'      => '--- Company (CNPJ) ---',
        'cnpj'               => '- CNPJ: :cnpj',
        'company-name'       => '- Legal name: :value',
        'trade-name'         => '- Trade name: :value',
        'status'             => '- Status: :value',
        'size'               => '- Size: :value',
        'activity'           => '- Activity (CNAE): :value',
        'state-registration' => '- State registration: :value',
        'partners-title'     => 'Partners / administrators:',
        'lgpd-title'         => '--- LGPD ---',
        'privacy-policy'     => '- Privacy policy: yes',
        'dpo'                => '- DPO: :contact',
        'nothing-found'      => 'No additional data found.',
    ],
];
