<?php

return [
    'acl' => [
        'title' => 'Lead Green',
    ],

    'menu' => [
        'title' => 'Lead Green',
    ],

    'title'         => 'Lead Green',
    'search-button' => 'Buscar no Google Maps',

    'search' => [
        'title'             => 'Buscar no Google Maps',
        'back-to-list'      => 'Voltar para a lista',
        'description'       => 'Busque empresas no Google Maps e importe as que valem a pena prospectar.',
        'website-only-note' => 'Só são mantidas empresas com site — o CRM não tem como alcançar as demais.',
        'query-label'       => 'O que você está procurando?',
        'query-placeholder' => 'ex.: Escolas em Mogi das Cruzes - SP',
        'query-hint'        => 'Seja específico: um tipo de negócio mais uma cidade funciona melhor.',
        'limit-label'       => 'Limite de resultados',
        'submit'            => 'Buscar',
        'searching'         => 'Buscando…',
        'new-search'        => 'Nova busca',
        'importing'         => 'Importando…',
        'success'           => ':inserted importados, :skipped ignorados (de :found encontrados).',

        'error' => [
            'no-api-key'     => 'Nenhuma chave RapidAPI configurada — adicione uma em Configuração > Lead Green.',
            'request-failed' => 'A busca no Google Maps falhou (HTTP :status).',
            'expired'        => 'Essa prévia de busca expirou — busque novamente.',
        ],

        'preview' => [
            'title'           => 'Resultados',
            'empty'           => 'Nenhuma empresa encontrada.',
            'total'           => 'Encontrados',
            'with-website'    => 'Com site',
            'duplicates'      => 'Já importados',
            'new'             => 'Novos',
            'col-name'        => 'Nome',
            'col-location'    => 'Localização',
            'col-phone'       => 'Telefone',
            'col-website'     => 'Site',
            'col-rating'      => 'Avaliação',
            'col-status'      => 'Status',
            'badge-duplicate' => 'Já importado',
            'badge-new'       => 'Novo',
            'nothing-new'     => 'Nada novo para importar.',
            'import-btn'      => 'Importar :count prospect(s) novo(s)',
        ],
    ],

    'datagrid' => [
        'name'       => 'Nome',
        'city'       => 'Cidade',
        'state'      => 'Estado',
        'types'      => 'Tipo',
        'rating'     => 'Avaliação',
        'reviews'    => 'Reviews',
        'website'    => 'Site',
        'status'     => 'Status',
        'enrichment' => 'Enriquecimento',
        'privacy'    => 'Política de privacidade',
        'dpo'        => 'DPO',
        'actions'    => 'Ações',
        'view'       => 'Visualizar',
        'convert'    => 'Converter em lead',
        'discard'    => 'Descartar',
    ],

    'enrichment' => [
        'title'         => 'Enriquecimento',
        'not-enriched'  => 'Ainda não enriquecido.',
        'success'       => 'Prospect enriquecido com sucesso.',
        'email'         => 'E-mail',
        'whatsapp'      => 'WhatsApp',
        'instagram'     => 'Instagram',
        'facebook'      => 'Facebook',
        'company-title' => 'Empresa (CNPJ)',
        'cnpj'          => 'CNPJ',
        'razao-social'  => 'Razão social',
        'situacao'      => 'Situação',
        'porte'         => 'Porte',
        'privacy-title' => 'LGPD',
        'yes'           => 'Sim',
        'no'            => 'Não',
        'status-enriched'   => 'Enriquecido',
        'status-pending'    => 'Pendente',
        'status-empty'      => 'Nada encontrado',
        'status-no-website' => 'Sem site',
        'status-failed'     => 'Falhou',
    ],

    'modal' => [
        'phone'                 => 'Telefone',
        'website'               => 'Site',
        'address'               => 'Endereço',
        'close'                 => 'Fechar',
        'confirm-convert'       => 'Converter este prospect em lead do CRM?',
        'discard-reason-prompt' => 'Por que você está descartando este prospect?',
    ],

    'error' => [
        'not-found'         => 'Prospect não encontrado.',
        'already-converted' => 'Este prospect já foi convertido.',
    ],

    'success' => [
        'converted' => 'Convertido em lead com sucesso.',
        'discarded' => 'Prospect descartado.',
    ],

    'settings' => [
        'tab'          => 'Lead Green',
        'tab-info'     => 'Prospecção e enriquecimento via Google Maps',
        'section'      => 'Configurações',
        'section-info' => 'Chaves de API usadas na prospecção e enriquecimento',

        'api-keys' => [
            'title'              => 'Chaves de API',
            'info'               => 'As duas integrações são opcionais — deixe uma chave em branco para pular essa fonte.',
            'rapidapi-maps-key'  => 'Chave RapidAPI (maps-data)',
            'rapidapi-maps-host' => 'Host RapidAPI',
            'cnpja-api-key'      => 'Chave da API comercial CNPJá',
            'cnpja-daily-limit'  => 'Limite diário de créditos CNPJá',
        ],
    ],
];
