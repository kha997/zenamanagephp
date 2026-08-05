<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

require_once dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php';

/**
 * Correction 2 (revised 2026-08-05): the evidence-binding contract was
 * replaced from a self-referential CI-check digest (hash of head_sha + live
 * check state — which changed the moment the Gate 3 packet itself was
 * committed, since that commit changes head_sha) to a git-tree digest that
 * structurally excludes only the exact Gate 3 record file. These tests
 * prove the replacement actually has the required properties, using
 * disposable throwaway git repos in the system temp directory — never the
 * real project repository — so no test here can lose real work.
 */
class EvidenceBindingTest extends TestCase
{
    /** @var string[] */
    private array $tempRepos = [];

    protected function tearDown(): void
    {
        foreach ($this->tempRepos as $repo) {
            exec('rm -rf ' . escapeshellarg($repo));
        }
        $this->tempRepos = [];
        parent::tearDown();
    }

    private function makeTempRepo(): string
    {
        $dir = sys_get_temp_dir() . '/owner-gov-evidence-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        exec('git -C ' . escapeshellarg($dir) . ' init -q');
        exec('git -C ' . escapeshellarg($dir) . ' config user.email test@example.com');
        exec('git -C ' . escapeshellarg($dir) . ' config user.name "Test"');
        $this->tempRepos[] = $dir;

        return $dir;
    }

    private function commitFile(string $repo, string $relPath, string $content, string $message): string
    {
        $full = $repo . '/' . $relPath;
        @mkdir(dirname($full), 0777, true);
        file_put_contents($full, $content);
        exec('git -C ' . escapeshellarg($repo) . ' add ' . escapeshellarg($relPath));
        exec('git -C ' . escapeshellarg($repo) . ' commit -q -m ' . escapeshellarg($message));

        return trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD'));
    }

    // --- Required test 1 & 5: a Gate 3 packet-only commit does not invalidate the digest ---

    public function test_gate_3_packet_only_commit_does_not_change_implementation_digest(): void
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed implementation');
        $shaAfterFirstPacket = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/03-release.md', "packet v1\n", 'first packet commit');
        $digestAfterFirst = \owner_governance_compute_implementation_tree_digest($shaAfterFirstPacket, 'OWN-2026-001', $repo);

        // A second, later, packet-only commit — the exact scenario that broke
        // the old CI-check-based model (presenting the packet is itself a commit).
        $shaAfterSecondPacket = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/03-release.md', "packet v2 — refreshed narrative only\n", 'second packet-only commit');
        $digestAfterSecond = \owner_governance_compute_implementation_tree_digest($shaAfterSecondPacket, 'OWN-2026-001', $repo);

        $this->assertSame($digestAfterFirst, $digestAfterSecond, 'A commit touching only the Gate 3 record file must not change the implementation-tree digest.');
    }

    public function test_multiple_sequential_gate_3_only_edits_leave_digest_stable(): void
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed implementation');
        $shaFirst = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/03-release.md', "v1\n", 'packet 1');
        $digestFirst = \owner_governance_compute_implementation_tree_digest($shaFirst, 'OWN-2026-001', $repo);

        for ($i = 2; $i <= 5; $i++) {
            $sha = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/03-release.md', "v{$i}\n", "packet {$i}");
            $digest = \owner_governance_compute_implementation_tree_digest($sha, 'OWN-2026-001', $repo);
            $this->assertSame($digestFirst, $digest, "Gate-3-only edit #{$i} must not change the digest.");
        }
    }

    // --- Required test 2: changing a governance PHP script invalidates it ---

    public function test_changing_a_governance_php_script_invalidates_digest(): void
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed implementation');
        $shaBefore = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/03-release.md', "packet\n", 'packet commit');
        $digestBefore = \owner_governance_compute_implementation_tree_digest($shaBefore, 'OWN-2026-001', $repo);

        $shaAfter = $this->commitFile($repo, 'scripts/ssot/owner_governance_lint.php', "<?php // changed lint logic\n", 'change governance script');
        $digestAfter = \owner_governance_compute_implementation_tree_digest($shaAfter, 'OWN-2026-001', $repo);

        $this->assertNotSame($digestBefore, $digestAfter, 'Changing a governance script must invalidate the digest.');
    }

    // --- Required test 3: changing a CI workflow invalidates it ---

    public function test_changing_a_ci_workflow_invalidates_digest(): void
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed implementation');
        $shaBefore = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/03-release.md', "packet\n", 'packet commit');
        $digestBefore = \owner_governance_compute_implementation_tree_digest($shaBefore, 'OWN-2026-001', $repo);

        $shaAfter = $this->commitFile($repo, '.github/workflows/owner-governance-lint.yml', "name: changed\n", 'change CI workflow');
        $digestAfter = \owner_governance_compute_implementation_tree_digest($shaAfter, 'OWN-2026-001', $repo);

        $this->assertNotSame($digestBefore, $digestAfter, 'Changing a CI workflow must invalidate the digest.');
    }

    // --- Required test 4: changing Gate 1 or Gate 2 invalidates it ---

    public function test_changing_gate_1_record_invalidates_digest(): void
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed implementation');
        $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/01-request.md', "gate 1 v1\n", 'gate 1 commit');
        $shaBefore = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/03-release.md', "packet\n", 'packet commit');
        $digestBefore = \owner_governance_compute_implementation_tree_digest($shaBefore, 'OWN-2026-001', $repo);

        $shaAfter = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/01-request.md', "gate 1 v2 — changed\n", 'change gate 1 record');
        $digestAfter = \owner_governance_compute_implementation_tree_digest($shaAfter, 'OWN-2026-001', $repo);

        $this->assertNotSame($digestBefore, $digestAfter, 'Changing the Gate 1 record must invalidate the digest — only the exact Gate 3 file (03-release.md) is excluded.');
    }

    public function test_changing_gate_2_record_invalidates_digest(): void
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed implementation');
        $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/02-design.md', "gate 2 v1\n", 'gate 2 commit');
        $shaBefore = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/03-release.md', "packet\n", 'packet commit');
        $digestBefore = \owner_governance_compute_implementation_tree_digest($shaBefore, 'OWN-2026-001', $repo);

        $shaAfter = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/02-design.md', "gate 2 v2 — changed\n", 'change gate 2 record');
        $digestAfter = \owner_governance_compute_implementation_tree_digest($shaAfter, 'OWN-2026-001', $repo);

        $this->assertNotSame($digestBefore, $digestAfter, 'Changing the Gate 2 record must invalidate the digest — only the exact Gate 3 file (03-release.md) is excluded.');
    }

    // --- Required test 6: a stale subject_sha with a non-packet intervening commit fails ---

    public function test_stale_subject_sha_with_non_packet_intervening_commit_produces_different_digest(): void
    {
        $repo = $this->makeTempRepo();
        $this->commitFile($repo, 'app/Foo.php', "<?php // v1\n", 'seed implementation');
        $subjectSha = $this->commitFile($repo, 'docs/owner-decisions/OWN-2026-001/03-release.md', "packet at subject_sha\n", 'packet commit at subject_sha');
        $digestAtSubject = \owner_governance_compute_implementation_tree_digest($subjectSha, 'OWN-2026-001', $repo);

        // A real, non-packet intervening commit — e.g. a follow-up implementation fix.
        $currentHead = $this->commitFile($repo, 'app/Foo.php', "<?php // v2 — real change after the decision was recorded\n", 'real implementation change after subject_sha');
        $digestAtCurrentHead = \owner_governance_compute_implementation_tree_digest($currentHead, 'OWN-2026-001', $repo);

        $this->assertNotSame(
            $digestAtSubject,
            $digestAtCurrentHead,
            'A non-packet-only intervening commit must make the current-head digest differ from the digest recorded at subject_sha — this is what makes the recorded evidence stale.'
        );
    }

    // --- Required test 7: awaiting_owner fails when current-head CI is incomplete or red ---

    public function test_count_blocking_checks_flags_pending_and_failed(): void
    {
        $checks = [
            ['name' => 'a', 'bucket' => 'pass'],
            ['name' => 'b', 'bucket' => 'pending'],
            ['name' => 'c', 'bucket' => 'fail'],
            ['name' => 'd', 'bucket' => 'skipping'],
        ];

        $this->assertSame(2, \owner_governance_count_blocking_checks($checks), 'Only pending and fail buckets should count as blocking; pass and skipping must not.');
    }

    public function test_count_blocking_checks_is_zero_when_all_pass_or_skip(): void
    {
        $checks = [
            ['name' => 'a', 'bucket' => 'pass'],
            ['name' => 'b', 'bucket' => 'skipping'],
        ];

        $this->assertSame(0, \owner_governance_count_blocking_checks($checks));
    }

    // --- Required test 8: `approved` fails when the binding digest differs ---

    public function test_approved_packet_with_mismatched_binding_digest_fails_validation(): void
    {
        $schema = Yaml::parseFile(dirname(__DIR__, 3) . '/docs/owner-governance/packet-schema.yml');
        $content = $this->buildGate3Fixture(
            gateStatus: 'approved',
            ownerDecisionValue: 'approved',
            evidenceDigest: 'aaaa000000000000000000000000000000000000000000000000000000000000',
            bindingDigest: 'bbbb000000000000000000000000000000000000000000000000000000000000',
        );

        $violations = \owner_governance_validate_packet('test.md', $content, $schema);
        $rules = array_map(fn ($v) => $v->rule, $violations);

        $this->assertContains('stale-decision-tree-digest-mismatch', $rules);
    }

    public function test_approved_packet_with_matching_binding_digest_passes_the_staleness_check(): void
    {
        $schema = Yaml::parseFile(dirname(__DIR__, 3) . '/docs/owner-governance/packet-schema.yml');
        $sameDigest = str_repeat('c', 64);
        $content = $this->buildGate3Fixture(
            gateStatus: 'approved',
            ownerDecisionValue: 'approved',
            evidenceDigest: $sameDigest,
            bindingDigest: $sameDigest,
        );

        $violations = \owner_governance_validate_packet('test.md', $content, $schema);
        $rules = array_map(fn ($v) => $v->rule, $violations);

        $this->assertNotContains('stale-decision-tree-digest-mismatch', $rules);
    }

    // --- Required test 9: the linter cannot pass the real c4250146 vs f775d286 mismatch under the OLD contract ---
    // Regression proof, using this repository's own real history: the ONLY
    // difference between commit f775d286 (before the OWN-2026-001 packet
    // existed) and c4250146 (which added it) is the packet file itself. The
    // OLD, CI-check-based model hashed head_sha directly, so recording
    // evidence at f775d286 inside a packet committed AT c4250146 was
    // self-invalidating by construction — the packet's own commit changed
    // head_sha out from under it before it could ever be valid. The NEW
    // model's exclusion makes the two commits produce an IDENTICAL digest,
    // because the only change between them is the excluded file.

    public function test_real_repo_history_c4250146_and_f775d286_produce_identical_digest(): void
    {
        $repoRoot = dirname(__DIR__, 3);

        $hasF775 = trim((string) shell_exec('git -C ' . escapeshellarg($repoRoot) . ' cat-file -e f775d286637a02d8e25f164a78bad8a70281a201 2>&1; echo $?'));
        $hasC425 = trim((string) shell_exec('git -C ' . escapeshellarg($repoRoot) . ' cat-file -e c4250146a3f7da97e93ff25b8db13430cdb7aab4 2>&1; echo $?'));
        if ($hasF775 !== '0' || $hasC425 !== '0') {
            $this->markTestSkipped('This regression test requires commits f775d286 and c4250146 to be reachable in the real repository history (they should be, on this branch).');
        }

        $digestAtF775 = \owner_governance_compute_implementation_tree_digest('f775d286637a02d8e25f164a78bad8a70281a201', 'OWN-2026-001', $repoRoot);
        $digestAtC425 = \owner_governance_compute_implementation_tree_digest('c4250146a3f7da97e93ff25b8db13430cdb7aab4', 'OWN-2026-001', $repoRoot);

        $this->assertSame(
            $digestAtF775,
            $digestAtC425,
            'The real Blocker 2 bug: under the corrected model, the commit that ONLY added the Gate 3 packet (c4250146) must produce the same digest as the commit right before it (f775d286) — proving the new model would never have flagged this exact scenario as stale.'
        );
    }

    // --- Required test 10: the corrected contract passes after fresh evidence is regenerated ---

    public function test_fresh_evidence_regenerated_from_real_current_head_passes_validation(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $schema = Yaml::parseFile($repoRoot . '/docs/owner-governance/packet-schema.yml');

        $currentHead = trim((string) shell_exec('git -C ' . escapeshellarg($repoRoot) . ' rev-parse HEAD'));
        $freshDigest = \owner_governance_compute_implementation_tree_digest($currentHead, 'OWN-2026-001', $repoRoot);

        $this->assertSame(64, strlen($freshDigest), 'Expected a real sha256 hex digest.');

        $content = $this->buildGate3Fixture(
            gateStatus: 'blocked_technical',
            ownerDecisionValue: 'none',
            evidenceDigest: $freshDigest,
            bindingDigest: null,
            subjectSha: $currentHead,
        );

        $violations = \owner_governance_validate_packet('test.md', $content, $schema);

        $this->assertCount(0, $violations, 'A packet whose technical_evidence.implementation_tree_digest was freshly recomputed from the real current HEAD must pass validation with zero violations: ' . json_encode(array_map(fn ($v) => $v->message, $violations)));
    }

    /**
     * Builds a minimal, schema-valid Gate 3 packet fixture string for direct
     * use with owner_governance_validate_packet(), varying only the fields
     * each test needs to control.
     */
    private function buildGate3Fixture(
        string $gateStatus,
        string $ownerDecisionValue,
        string $evidenceDigest,
        ?string $bindingDigest,
        string $subjectSha = 'aaaa000000000000000000000000000000000000',
    ): string {
        $decisionProvenance = $ownerDecisionValue !== 'none'
            ? "decision_provenance: {trust_level: claimed_repo_record, recorded_by: \"test\", recorded_at: \"2026-08-05T00:00:00+07:00\", owner_response_reference: \"test\", reconciliation_required: false}"
            : "decision_provenance: {trust_level: claimed_repo_record, recorded_by: null, recorded_at: null, owner_response_reference: null, reconciliation_required: false}";

        $bindingYaml = $bindingDigest !== null
            ? "owner_decision_binding: {implementation_tree_digest: \"{$bindingDigest}\", decision_recorded_at: \"2026-08-05T00:00:00+07:00\"}"
            : "owner_decision_binding: {implementation_tree_digest: null, decision_recorded_at: null}";

        $decisionRequested = $gateStatus === 'awaiting_owner' ? '"approve_or_correction_or_defer"' : 'null';

        return <<<YAML
---
work_id: OWN-2026-001
gate: 3
gate_status: {$gateStatus}
technical_readiness: {value: ready, generated_by: engineering_evidence}
owner_decision: {value: {$ownerDecisionValue}, authority: human_owner}
decision_requested: {$decisionRequested}
references: {spec: null, plan: null, branch: null, pr: null, release: null}
{$decisionProvenance}
supersedes: null
superseded_by: null
timestamps: {created_at: "2026-08-05T00:00:00+07:00", updated_at: "2026-08-05T00:00:00+07:00"}
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "test fixture"
technical_evidence:
  subject_sha: "{$subjectSha}"
  implementation_tree_digest: "{$evidenceDigest}"
  verified_pr_head_sha: "{$subjectSha}"
  verified_at: "2026-08-05T00:00:00+07:00"
{$bindingYaml}
---

## Test fixture body — no forbidden placeholder tokens here.
YAML;
    }
}
