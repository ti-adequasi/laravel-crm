<?php

return [
    'menu' => [
        'title' => 'PBX',
        'info' => 'Conecte este tenant ao seu próprio domínio no PBX para discagem direta.',
    ],

    'acl' => [
        'title' => 'PBX',
    ],

    'settings' => [
        'title' => 'Configurações do PBX',
        'info' => 'Conecte este tenant ao seu próprio domínio no PBX Inovalen — a chave de API sozinha identifica a qual domínio uma requisição pertence, então todo tenant nesta instalação usa o mesmo servidor PBX, cada um com sua própria chave.',
        'enabled' => 'Habilitado',
        'api-key' => 'Chave de API',
        'api-key-hint' => 'Deixe em branco para manter a chave já salva.',
        'test-connection' => 'Testar Conexão',
        'testing' => 'Testando...',
        'test-success' => 'Conectado — esta chave pertence ao domínio ":domain".',
        'test-failed' => 'Falha na conexão: :error',
        'test-failed-generic' => 'Não foi possível testar a conexão. Tente novamente.',
        'test-missing-key' => 'Informe uma chave de API primeiro.',
        'save-btn' => 'Salvar',
        'saving' => 'Salvando...',
        'save-success' => 'Configurações do PBX salvas com sucesso.',
        'save-failed-generic' => 'Não foi possível salvar as configurações do PBX. Tente novamente.',
    ],
];
