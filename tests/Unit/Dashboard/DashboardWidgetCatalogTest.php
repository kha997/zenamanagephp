<?php

namespace Tests\Unit\Dashboard;

use App\Exceptions\Dashboard\UnsupportedDashboardRole;
use App\Services\Dashboard\DashboardRoleValidator;
use App\Services\Dashboard\DashboardWidgetCatalog;
use Tests\TestCase;

class DashboardWidgetCatalogTest extends TestCase
{
    private DashboardWidgetCatalog $catalog;

    private DashboardRoleValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new DashboardWidgetCatalog();
        $this->validator = new DashboardRoleValidator();
    }

    public function test_catalog_has_exactly_the_seven_approved_roles(): void
    {
        self::assertSame([
            'system_admin',
            'project_manager',
            'design_lead',
            'site_engineer',
            'qc_inspector',
            'client_rep',
            'subcontractor_lead',
        ], $this->catalog->roles());
    }

    public function test_unknown_role_fails_closed_instead_of_inheriting_client_rep_catalog(): void
    {
        $this->expectException(UnsupportedDashboardRole::class);

        $this->validator->assertSupported('unknown_role');
    }

    public function test_capability_matrix_contains_exactly_the_twelve_existing_provider_codes(): void
    {
        $expected = [
            'project_overview',
            'task_progress',
            'rfi_status',
            'budget_tracking',
            'schedule_timeline',
            'team_performance',
            'quality_metrics',
            'safety_summary',
            'inspection_schedule',
            'ncr_tracking',
            'system_health',
            'user_management',
        ];

        $supported = [];
        foreach ($this->catalog->roles() as $role) {
            foreach ($this->catalog->capabilitiesForRole($role) as $code => $capability) {
                if ($capability === 'supported') {
                    $supported[] = $code;
                }
            }
        }

        sort($expected);
        $supported = array_values(array_unique($supported));
        sort($supported);

        self::assertSame($expected, $supported);
    }

    public function test_four_roles_with_no_local_provider_remain_explicitly_unsupported(): void
    {
        foreach (['design_lead', 'site_engineer', 'client_rep', 'subcontractor_lead'] as $role) {
            self::assertNotContains('supported', $this->catalog->capabilitiesForRole($role));
            self::assertNotEmpty($this->catalog->forRole($role));
        }
    }
}
