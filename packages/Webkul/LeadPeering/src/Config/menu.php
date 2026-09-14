<?php

return [
    [
        'key' => 'lead_peering',
        'name' => 'leadpeering::app.menu.title',
        'route' => 'admin.leadpeering.index',
        'sort' => 7,
        // icon-globe is not a confirmed class in Admin's compiled icon font
        // (grepped: the only other reference in this codebase is inside a
        // DataGrid closure's <span>, where a missing glyph is harmless —
        // a top-level menu entry is more visible, so this reuses the same
        // confirmed-real class LeadGreen's own menu.php uses rather than
        // gambling on an unconfirmed one here.
        'icon-class' => 'icon-leads',
    ],
];
