<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * GAP-115 W-1: production is deployed only by production.yml (manual, exact SHA, migration safety). The tag-based
 * release workflow must not reach the production host.
 */
class ReleaseWorkflowNoProductionDeployTest extends TestCase
{
    public function test_release_workflow_does_not_deploy_to_production(): void
    {
        $release = (string) file_get_contents(base_path('.github/workflows/release-management.yml'));
        $this->assertStringNotContainsString('PRODUCTION_SSH_KEY', $release);
        $this->assertStringNotContainsString('PRODUCTION_HOST', $release);
        $this->assertStringNotContainsString('git pull origin main', $release);
        $this->assertDoesNotMatchRegularExpression('/environment:\s*production/', $release);

        $production = (string) file_get_contents(base_path('.github/workflows/production.yml'));
        $this->assertStringContainsString('workflow_dispatch', $production);
        $this->assertStringContainsString('deploy:migrate', $production . (string) @file_get_contents(base_path('scripts/deploy/activate-release.sh')));
    }
}
