<?php declare(strict_types=1);

return [
    'permission_aliases' => [
        'project.read' => ['project.view'],
        'project.create' => ['project.write'],
        'project.update' => ['project.write', 'project.edit'],
        'project.delete' => ['project.write'],
        'project.write' => ['project.create', 'project.update'],
        'project.view' => ['project.read'],
        'project.edit' => ['project.update'],
        'task.write' => ['task.create', 'task.update'],
        'task.view' => ['task.read'],
        'task.edit' => ['task.update'],
    ],

    'legacy_permission_aliases' => [
        'project.write' => 'project.write',
        'project.view' => 'project.view',
        'project.edit' => 'project.edit',
        'task.edit' => 'task.edit',
        'task.view' => 'task.view',
        'task.write' => 'task.write',
    ],

    'action_alias_map' => [
        'view' => ['read'],
        'edit' => ['update'],
        'write' => ['create', 'update'],
    ],

    'project_scoped_modules' => [
        'project',
        'task',
        'component',
        'baseline',
        'template',
        'work_template',
        'task_assignment',
        'project_template',
        'project_task',
        'document',
    ],

    'bypass_testing' => env('RBAC_BYPASS_TESTING', true),
];
