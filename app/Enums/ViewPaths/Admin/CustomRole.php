<?php

namespace App\Enums\ViewPaths\Admin;

enum CustomRole
{
    const LIST = [
        URI => '/',
        VIEW => 'admin-views.custom-role.index'
    ];

    const ADD = [
        URI => 'create',
        VIEW => 'admin-views.custom-role.create'
    ];

    const EDIT = [
        URI => 'edit',
        VIEW => 'admin-views.custom-role.edit'
    ];

    const UPDATE = [
        URI => 'update',
        VIEW => 'admin-views.custom-role.edit'
    ];

    const DELETE = [
        URI => 'delete',
        VIEW => ''
    ];
    const SEARCH = [
        URI => 'search',
        VIEW => 'admin-views.custom-role.partials._table'
    ];
}
