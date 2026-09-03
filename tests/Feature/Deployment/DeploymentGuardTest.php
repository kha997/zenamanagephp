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

    public function test_production_workflow_triggers_only_on_manual_dispatch_with_required_sha_input(): void
    {
        $yaml = Yaml::parseFile(base_path('.github/workflows/production.yml'));

        $this->assertArrayHasKey('workflow_dispatch', $yaml['on']);
        $this->assertArrayNotHasKey('push', $yaml['on'], 'production.yml must not auto-deploy on push to main during the pilot phase (GAP-049 A-1).');

        $shaInput = $yaml['on']['workflow_dispatch']['inputs']['sha'] ?? null;
        $this->assertNotNull($shaInput, 'production.yml workflow_dispatch must accept an exact sha input.');
        $this->assertTrue($shaInput['required'] ?? false, 'The sha input must be required, not optional.');
    }

    public function test_production_workflow_never_uses_git_pull_origin_main(): void
    {
        $content = file_get_contents(base_path('.github/workflows/production.yml'));
        $this->assertStringNotContainsString('git pull origin main', $content);
        $this->assertStringNotContainsString('migrate:rollback', $content);
    }

    public function test_production_workflow_has_serialization_concurrency_guard(): void
    {
        $yaml = Yaml::parseFile(base_path('.github/workflows/production.yml'));
        $this->assertSame('production-deploy', $yaml['concurrency']['group'] ?? null);
        $this->assertFalse($yaml['concurrency']['cancel-in-progress'] ?? true);
    }
}
