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

    /**
     * Owner Gate-3 Round-1 correction item 6 (Gate-2 A-2/A-5 post-cutover
     * recovery contract). These tests assert on the workflow's structural
     * content (step ids, conditions, script text) rather than executing
     * it — the workflow can only be genuinely exercised against a real
     * host, which this repo does not provision in automated tests.
     */
    private function deployJobSteps(): array
    {
        $yaml = Yaml::parseFile(base_path('.github/workflows/production.yml'));
        return $yaml['jobs']['deploy']['steps'] ?? [];
    }

    private function stepById(array $steps, string $id): ?array
    {
        foreach ($steps as $step) {
            if (($step['id'] ?? null) === $id) {
                return $step;
            }
        }
        return null;
    }

    public function test_recovery_step_exists_and_is_gated_on_post_cutover_verification_failure(): void
    {
        $recovery = $this->stepById($this->deployJobSteps(), 'recovery');
        $this->assertNotNull($recovery, 'production.yml must have a step with id: recovery implementing the Gate-2 A-2/A-5 recovery contract.');

        $condition = (string) ($recovery['if'] ?? '');
        $this->assertStringContainsString("steps.activate.outcome == 'success'", $condition);
        $this->assertStringContainsString("steps.readiness.outcome == 'failure'", $condition);
        $this->assertStringContainsString("steps.queue-canary.outcome == 'failure'", $condition);
    }

    /**
     * Regression guard for a real Critical bug caught by the second final
     * whole-branch review: `if:` conditions on `recovery` and
     * `post-recovery-readiness` originally had NO explicit status-check
     * function (always()/failure()/etc). GitHub Actions implicitly ANDs an
     * unqualified `if:` with success() — which is false whenever an
     * earlier step in the job failed, i.e. false in EVERY scenario these
     * two steps exist to handle. The recovery contract was therefore
     * unreachable dead code despite 100% of its content-level tests
     * passing (they only ever parsed the committed YAML, never executed
     * it). This test would have caught that.
     */
    public function test_recovery_and_post_recovery_readiness_steps_use_an_explicit_status_check_function(): void
    {
        $steps = $this->deployJobSteps();

        $recovery = $this->stepById($steps, 'recovery');
        $this->assertNotNull($recovery);
        $recoveryCondition = (string) ($recovery['if'] ?? '');
        $this->assertMatchesRegularExpression(
            '/\b(always|failure|cancelled)\s*\(\s*\)/',
            $recoveryCondition,
            'The recovery step\'s `if:` must contain an explicit status-check function (always()/failure()/cancelled()) — otherwise GitHub Actions\' implicit success() makes it unreachable in every failure scenario it exists to handle.'
        );

        $postRecoveryReadiness = $this->stepById($steps, 'post-recovery-readiness');
        $this->assertNotNull($postRecoveryReadiness);
        $postRecoveryCondition = (string) ($postRecoveryReadiness['if'] ?? '');
        $this->assertMatchesRegularExpression(
            '/\b(always|failure|cancelled)\s*\(\s*\)/',
            $postRecoveryCondition,
            'The post-recovery-readiness step\'s `if:` must contain an explicit status-check function for the same reason.'
        );
    }

    public function test_recovery_also_triggers_when_activate_itself_fails_after_a_successful_cutover(): void
    {
        // e.g. a post-switch service-restart failure inside the activate
        // step — the atomic switch already happened, so this must still
        // be recoverable, not silently excluded because the step that
        // performed the switch reported an overall failure outcome.
        $recovery = $this->stepById($this->deployJobSteps(), 'recovery');
        $this->assertNotNull($recovery);
        $condition = (string) ($recovery['if'] ?? '');
        $this->assertStringContainsString("steps.activate.outcome == 'failure'", $condition);
    }

    public function test_recovery_captures_previous_release_before_cutover_and_never_infers_head_tilde(): void
    {
        $content = file_get_contents(base_path('.github/workflows/production.yml'));

        // Previous-release capture happens in the activate step, before
        // activate-release.sh (the atomic switch) ever runs.
        $activatePos = strpos($content, '.previous-release');
        $switchPos = strpos($content, 'activate-release.sh" "$ROOT" "$SHA"');
        $this->assertNotFalse($activatePos, 'production.yml must capture the previous release to a .previous-release marker before switching.');
        $this->assertNotFalse($switchPos);
        $this->assertLessThan($switchPos, $activatePos, 'Previous-release capture must happen before the atomic current-symlink switch, not after.');

        $this->assertStringNotContainsString('HEAD~1', $content);
        $this->assertStringNotContainsString('HEAD^', $content);
    }

    public function test_recovery_rollback_target_is_read_from_explicit_pre_cutover_capture(): void
    {
        $content = file_get_contents(base_path('.github/workflows/production.yml'));
        $this->assertStringContainsString('PREV_SHA="$(cat "${ROOT}/.previous-release")"', $content);
        $this->assertStringContainsString('rollback.sh" "$ROOT" "$PREV_SHA"', $content);
    }

    public function test_breaking_migration_failure_never_triggers_automatic_rollback(): void
    {
        $content = file_get_contents(base_path('.github/workflows/production.yml'));

        // Isolate the breaking-migration branch of the recovery step's
        // script: from its ALLOW_BREAKING_MIGRATIONS check to its `exit 0`.
        $branchStart = strpos($content, 'if [ "${ALLOW_BREAKING_MIGRATIONS:-false}" = "true" ]; then');
        $this->assertNotFalse($branchStart, 'Recovery step must branch on the breaking-migration flag.');
        $branchEnd = strpos($content, 'exit 0', $branchStart);
        $this->assertNotFalse($branchEnd);
        $breakingBranch = substr($content, $branchStart, $branchEnd - $branchStart);

        $this->assertStringNotContainsString('rollback.sh', $breakingBranch, 'The breaking-migration recovery branch must never invoke rollback.sh — Gate-2 A-5 requires maintenance mode + operator action, never an automatic code/schema rollback.');
        $this->assertStringContainsString('php artisan down', $breakingBranch, 'The breaking-migration recovery branch must ensure/retain maintenance mode.');
    }

    public function test_first_deploy_with_no_previous_release_never_invents_a_rollback_target(): void
    {
        $content = file_get_contents(base_path('.github/workflows/production.yml'));

        $branchStart = strpos($content, 'if [ ! -s "${ROOT}/.previous-release" ]; then');
        $this->assertNotFalse($branchStart, 'Recovery step must handle the no-previous-release (first deploy) case explicitly.');
        $branchEnd = strpos($content, 'exit 0', $branchStart);
        $this->assertNotFalse($branchEnd);
        $firstDeployBranch = substr($content, $branchStart, $branchEnd - $branchStart);

        $this->assertStringNotContainsString('rollback.sh', $firstDeployBranch, 'The first-deploy-with-no-prior-release case must never invent a rollback target.');
        $this->assertStringContainsString('php artisan down', $firstDeployBranch);
    }

    public function test_final_state_never_reduces_an_explicit_readiness_failure_to_deployed_unverified(): void
    {
        $content = file_get_contents(base_path('.github/workflows/production.yml'));

        $setStatePos = strpos($content, 'echo "Production deployment state: attempted');
        $this->assertNotFalse($setStatePos);
        $finalBlockStart = strpos($content, 'name: Set final deployment state output');
        $this->assertNotFalse($finalBlockStart);
        $finalBlock = substr($content, $finalBlockStart);

        $deployedUnverifiedLinePos = strpos($finalBlock, 'state=deployed_unverified');
        $this->assertNotFalse($deployedUnverifiedLinePos, 'production.yml must still have a deployed_unverified branch for genuine edge cases (e.g. readiness step never ran).');

        // The condition guarding that branch (the "elif [...]; then" line
        // immediately preceding it) must explicitly exclude an outcome of
        // 'failure' for the readiness step — i.e. a readiness step that
        // actually ran and failed must never fall into this branch.
        $elifStart = strrpos(substr($finalBlock, 0, $deployedUnverifiedLinePos), 'elif [');
        $this->assertNotFalse($elifStart);
        $elifLine = substr($finalBlock, $elifStart, $deployedUnverifiedLinePos - $elifStart);
        $this->assertStringContainsString("steps.readiness.outcome", $elifLine);
        $this->assertStringContainsString('!= "failure"', $elifLine);
    }

    public function test_final_state_rolled_back_requires_non_breaking_and_successful_post_recovery_readiness(): void
    {
        $content = file_get_contents(base_path('.github/workflows/production.yml'));

        $rolledBackLinePos = strpos($content, 'state=rolled_back');
        $this->assertNotFalse($rolledBackLinePos, 'production.yml must be able to report the rolled_back state.');

        $elifStart = strrpos(substr($content, 0, $rolledBackLinePos), 'elif [');
        $this->assertNotFalse($elifStart);
        $elifLine = substr($content, $elifStart, $rolledBackLinePos - $elifStart);

        $this->assertStringContainsString('steps.recovery.outcome', $elifLine);
        $this->assertStringContainsString("allow_breaking_migrations", $elifLine);
        $this->assertStringContainsString('!= "true"', $elifLine, 'rolled_back must never be reported for a breaking-migration deployment (Gate-2 A-5: breaking failures are maintenance/recovery-required, never a claimed rollback).');
        $this->assertStringContainsString('steps.post-recovery-readiness.outcome', $elifLine);
    }
}
