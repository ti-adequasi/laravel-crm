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
    ],

    'edit' => [
        'title' => 'Editar Tenant',
        'name' => 'Nome',
        'code' => 'Código',
        'active' => 'Ativo',
        'save-btn' => 'Salvar Tenant',
    ],
];
