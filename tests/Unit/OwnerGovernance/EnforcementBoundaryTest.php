<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class EnforcementBoundaryTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_enforcement_boundary_file_exists_with_effective_date(): void
    {
        $boundary = Yaml::parseFile($this->repoRoot() . '/docs/owner-governance/enforcement-boundary.yml');
        $this->assertSame('2026-08-04', $boundary['effective_date']);
    }

    public function test_legacy_work_ids_file_contains_gap_031(): void
    {
        $path = $this->repoRoot() . '/docs/owner-governance/legacy-work-ids.txt';
        $this->assertFileExists($path);
        $ids = array_filter(array_map('trim', file($path)), fn ($l) => $l !== '' && !str_starts_with($l, '#'));

        $this->assertContains('GAP-031', $ids, 'GAP-031 pre-dates the owner-governance system and must be legacy-exempt.');
    }

    public function test_owner_governance_workflow_triggers_on_docs_paths(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/workflows/owner-governance-lint.yml');
        $this->assertStringContainsString('docs/owner-decisions/**', $content);
        $this->assertStringContainsString('docs/owner-governance/**', $content);
        $this->assertStringContainsString('docs/superpowers/plans/**', $content);
    }

    public function test_lint_script_supports_enforce_gate_ordering_flag(): void
    {
        $content = file_get_contents($this->repoRoot() . '/scripts/ssot/owner_governance_lint.php');
        $this->assertStringContainsString('--enforce-gate-ordering', $content);
    }

    public function test_gate3_before_ready_script_checks_legacy_exemption(): void
    {
        $content = file_get_contents($this->repoRoot() . '/scripts/ci/check-gate3-before-ready.sh');
        $this->assertStringContainsString('legacy-work-ids.txt', $content);
    }

    public function test_evidence_freshness_script_exists_and_uses_the_shared_digest_function(): void
    {
        $content = file_get_contents($this->repoRoot() . '/scripts/ci/check-evidence-freshness.sh');
        $this->assertStringContainsString('owner_governance_compute_implementation_tree_digest', $content);
        $this->assertStringContainsString('owner_governance_count_blocking_checks', $content);
        $this->assertStringContainsString('owner_decision_binding', $content);
        $this->assertStringNotContainsString('owner_governance_compute_evidence_digest', $content, 'The old, self-referential CI-check digest function must not be referenced anymore.');
    }

    public function test_governed_document_frontmatter_contract_documents_all_three_required_fields(): void
    {
        $content = file_get_contents($this->repoRoot() . '/docs/owner-governance/GOVERNED_DOCUMENT_FRONTMATTER.md');
        foreach (['work_id', 'owner_governance_version', 'owner_gate_2_record'] as $field) {
            $this->assertStringContainsString($field, $content);
        }
        $this->assertStringContainsString('fallback-only', $content);
    }

    public function test_lint_defines_the_missing_governance_frontmatter_rule(): void
    {
        // --enforce-gate-ordering is a directory-scan CLI mode, not a single-file
        // library call like validate_packet(), so its live behavior against the
        // governed-plan-missing-frontmatter.md fixture is exercised via the CLI
        // directly in Step 12 below, not re-simulated here. This test only
        // confirms the rule name this plan committed to exists in the script.
        $content = file_get_contents($this->repoRoot() . '/scripts/ssot/owner_governance_lint.php');
        $this->assertStringContainsString('missing-governance-frontmatter', $content);
    }
}
