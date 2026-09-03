<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DeploymentGuardTest extends TestCase
{
    public function test_deploy_yml_workflow_no_longer_exists(): void
    {
        $this->assertFileDoesNotExist(
            base_path('.github/workflows/deploy.yml'),
            'deploy.yml must be retired as a production entry point (GAP-049).'
        );
    }

    public function test_legacy_deploy_sh_no_longer_exists(): void
    {
        $this->assertFileDoesNotExist(
            base_path('deploy.sh'),
            'Legacy deploy.sh must be retired, not patched (GAP-049).'
        );
    }

    public function test_ci_cd_workflow_has_no_placeholder_deploy_job(): void
    {
        $yaml = Yaml::parseFile(base_path('.github/workflows/ci-cd.yml'));
        $this->assertArrayNotHasKey(
            'deploy',
            $yaml['jobs'] ?? [],
            'ci-cd.yml must not contain a placeholder deploy job that fakes a production-deployment signal (GAP-049).'
        );
    }

    public function test_automated_deployment_workflow_cannot_reach_production(): void
    {
        $yaml = Yaml::parseFile(base_path('.github/workflows/automated-deployment.yml'));
        $jobs = $yaml['jobs'] ?? [];

        foreach (['deploy-production', 'rollback', 'blue-green-deployment', 'canary-deployment'] as $jobName) {
            $this->assertArrayHasKey($jobName, $jobs, "Expected job {$jobName} to still be defined (disabled, not deleted).");
            $this->assertSame(
                false,
                $jobs[$jobName]['if'] ?? null,
                "Job {$jobName} in automated-deployment.yml must be statically disabled (if: false) so Candidate B cannot independently deploy to production per GAP-049."
            );
        }

        $dispatchInputs = $yaml['on']['workflow_dispatch']['inputs']['environment']['options'] ?? [];
        $this->assertNotContains(
            'production',
            $dispatchInputs,
            'automated-deployment.yml workflow_dispatch must not offer production as a selectable environment (GAP-049 Candidate B deferred).'
        );
    }

    public function test_production_yml_is_the_only_workflow_with_a_live_ssh_deploy_step(): void
    {
        $workflowsDir = base_path('.github/workflows');
        $offenders = [];

        foreach (glob($workflowsDir . '/*.yml') as $file) {
            if (basename($file) === 'production.yml') {
                continue;
            }

            $yaml = Yaml::parseFile($file);
            foreach (($yaml['jobs'] ?? []) as $jobName => $job) {
                // Staging-only jobs are exempt — this guard is about the production entry
                // point being singular, not about disabling staging deploys entirely.
                if (($job['environment'] ?? null) === 'staging') {
                    continue;
                }

                $usesSsh = false;
                foreach (($job['steps'] ?? []) as $step) {
                    if (str_starts_with($step['uses'] ?? '', 'appleboy/ssh-action')) {
                        $usesSsh = true;
                    }
                }
                if ($usesSsh && ($job['if'] ?? null) !== false) {
                    $offenders[] = basename($file) . ':' . $jobName;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Only production.yml may contain a live (non-disabled) SSH deploy step: ' . implode(', ', $offenders)
        );
    }
}
