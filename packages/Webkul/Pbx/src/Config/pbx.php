<?php

return [
    /*
     * Every tenant on this install shares one physical PBX server — a
     * tenant's own identity comes entirely from its api_key (see
     * PbxSetting), not from a per-tenant hostname, confirmed against the
     * live API: GET /v1/me returns domain_name purely as information
     * about which key was sent, it's never something the caller supplies
     * to select a domain. The docs' own "https://SEU-DOMINIO.pbx.inovalen.com.br"
     * example describes Inovalen's general SaaS product, not necessarily
     * this specific self-hosted instance — set PBX_BASE_URL if that ever
     * changes.
     */
    'base_url' => env('PBX_BASE_URL', 'https://192.168.20.187/pbxapi'),

    /*
     * Self-signed/internal-CA certificate on this local network, same as
     * every other internal host this install already talks to — matches
     * how the CRM's own server and every browser-testing script in this
     * project already handle it. Set to true if the PBX is ever put
     * behind a publicly-trusted certificate.
     */
    'verify_ssl' => env('PBX_VERIFY_SSL', false),

    /*
     * Seconds to wait for the PBX before giving up — origination/status
     * calls are interactive (an admin is waiting on a click), so this
     * stays short rather than the Laravel HTTP client's own long default.
     */
    'timeout' => env('PBX_TIMEOUT', 10),
];
