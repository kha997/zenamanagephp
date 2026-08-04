<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

class CodeownersTest extends TestCase
{
    public function test_codeowners_file_exists_and_covers_governance_paths(): void
    {
        $path = dirname(__DIR__, 3) . '/.github/CODEOWNERS';
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('/docs/owner-decisions/', $content);
        $this->assertStringContainsString('/docs/owner-governance/', $content);
        $this->assertStringContainsString('@kha997', $content);
    }

    public function test_codeowners_states_it_is_not_yet_an_active_merge_gate(): void
    {
        $content = file_get_contents(dirname(__DIR__, 3) . '/.github/CODEOWNERS');
        $this->assertStringContainsString('NOT active yet', $content);
    }

    public function test_operating_model_states_activation_is_deferred_and_documents_deadlock_risk(): void
    {
        $content = file_get_contents(dirname(__DIR__, 3) . '/docs/owner-governance/OWNER_OPERATING_MODEL.md');
        $this->assertStringContainsString('separately authorized, future operation', $content);
        $this->assertStringContainsString('sole repository collaborator', $content);
    }

    public function test_activation_runbook_exists_with_all_six_preconditions(): void
    {
        $content = file_get_contents(dirname(__DIR__, 3) . '/docs/owner-governance/BRANCH_PROTECTION_ACTIVATION_RUNBOOK.md');
        $this->assertStringContainsString('NOT executed by this plan', $content);
        foreach ([
            'distinct trusted GitHub user',
            'repository review access',
            'covered by `.github/CODEOWNERS`',
            'test Draft PR proves the author cannot satisfy',
            'independent reviewer can approve and unblock',
            'Rollback instructions are documented',
        ] as $precondition) {
            $this->assertStringContainsString($precondition, $content, "Missing precondition: {$precondition}");
        }
    }

    public function test_verify_distinct_reviewer_script_exists_and_is_executable_shape(): void
    {
        $path = dirname(__DIR__, 3) . '/scripts/ci/verify-distinct-reviewer-identity.sh';
        $this->assertFileExists($path);
        $content = file_get_contents($path);
        $this->assertStringContainsString('collaborators', $content);
        $this->assertStringContainsString('CODEOWNERS', $content);
    }

    public function test_no_live_branch_protection_mutation_command_runs_unconditionally_in_this_task(): void
    {
        // Structural guard against regression: Task 8's own committed artifacts
        // must never themselves invoke the activation PUT — it must only ever
        // appear inside the runbook document, as instructional text for a human,
        // never as an executable script this plan's CI would run automatically.
        $scriptsDir = dirname(__DIR__, 3) . '/scripts';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($scriptsDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->getExtension() !== 'sh' && $fileInfo->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($fileInfo->getPathname());
            $this->assertStringNotContainsString(
                'require_code_owner_reviews]=true',
                $content,
                "{$fileInfo->getPathname()} must not itself execute the branch-protection activation mutation."
            );
        }
    }
}
