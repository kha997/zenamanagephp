<?php

namespace App\Services\Dashboard;

use App\Exceptions\Dashboard\UnsupportedDashboardRole;

class DashboardRoleValidator
{
    public const ROLES = [
        'system_admin',
        'project_manager',
        'design_lead',
        'site_engineer',
        'qc_inspector',
        'client_rep',
        'subcontractor_lead',
    ];

    public function assertSupported(string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new UnsupportedDashboardRole($role);
        }
    }
}
