<?php

return [
    'menu' => [
        'title' => 'Tenants',
    ],

    'acl' => [
        'title' => 'Tenants',
        'create' => 'Criar',
        'edit' => 'Editar',
        'delete' => 'Excluir',
    ],

    'index' => [
        'title' => 'Tenants',
        'create-btn' => 'Adicionar Tenant',
        'create-success' => 'Tenant criado com sucesso.',
        'update-success' => 'Tenant atualizado com sucesso.',
        'delete-success' => 'Tenant excluído com sucesso.',
        'delete-failed' => 'Falha ao excluir o tenant.',
        'datagrid' => [
            'id' => 'ID',
            'name' => 'Nome',
            'code' => 'Código',
            'status' => 'Status',
            'active' => 'Ativo',
            'inactive' => 'Inativo',
            'created-at' => 'Criado em',
            'edit' => 'Editar',
            'delete' => 'Excluir',
        ],
    ],

    'create' => [
        'title' => 'Adicionar Tenant',
        'name' => 'Nome',
        'code' => 'Código',
        'code-placeholder' => 'ex.: acme',
        'code-info' => 'Um identificador curto, único e seguro para URL deste tenant. Não deve ser trocado sem cuidado depois que integrações passarem a depender dele.',
        'active' => 'Ativo',
        'save-btn' => 'Salvar Tenant',

        'admin-account-title' => 'Primeira Conta de Administrador',
        'admin-account-info' => 'Cria o pipeline padrão do tenant, um papel de administrador e este primeiro usuário em uma única etapa — um tenant novo não consegue usar as telas de Negócios sem os três.',
        'admin-name' => 'Nome',
        'admin-email' => 'E-mail',
        'admin-password' => 'Senha',
        'admin-password-confirmation' => 'Confirmar Senha',

        'default-pipeline-name' => 'Pipeline Padrão — :tenant',
        'default-stage-new' => 'Novo',
        'default-stage-negotiation' => 'Negociação',
        'default-stage-won' => 'Ganho',
        'default-role-name' => 'Administrador :tenant',
    ],

    'edit' => [
        'title' => 'Editar Tenant',
        'name' => 'Nome',
        'code' => 'Código',
        'active' => 'Ativo',
        'save-btn' => 'Salvar Tenant',

        'users-title' => 'Usuários do Tenant',
        'users-empty' => 'Este tenant ainda não tem usuários.',
        'users-name' => 'Nome',
        'users-email' => 'E-mail',
        'users-status' => 'Status',
        'users-active' => 'Ativo',
        'users-inactive' => 'Inativo',

        'add-user-title' => 'Adicionar Usuário',
        'add-user-name' => 'Nome',
        'add-user-email' => 'E-mail',
        'add-user-password' => 'Senha',
        'add-user-password-confirmation' => 'Confirmar Senha',
        'add-user-btn' => 'Adicionar Usuário',
        'user-create-success' => 'Usuário adicionado com sucesso.',
        'no-role-error' => 'Este tenant não tem um papel de permissão para associar o novo usuário. Corrija no banco de dados ou contate o suporte.',
    ],
];
