<?php

return [
    [
        'key' => 'settings.pbx',
        'name' => 'pbx::app.acl.title',
        'route' => ['admin.pbx.edit', 'admin.pbx.update', 'admin.pbx.test'],
        'sort' => 10,
    ], [
        // Nests under the pre-existing top-level 'leads' node (defined in
        // Admin's own acl.php) purely through the shared dotted-key
        // convention — Acl::prepareAclItems() groups every package's
        // contributed keys together via Arr::dot()/Arr::undot() on the
        // whole merged config, regardless of which file each key came
        // from, so this needs no edit to that core file. A role must be
        // granted this separately from 'leads.view' — a user who can see
        // a Lead doesn't necessarily place calls on the tenant's PBX.
        'key' => 'leads.calls',
        'name' => 'pbx::app.acl.calls-title',
        'route' => ['admin.pbx.calls.originate', 'admin.pbx.calls.status', 'admin.pbx.calls.hangup'],
        'sort' => 5,
    ],
];
