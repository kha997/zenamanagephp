<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php';

/**
 * Covers OWN-2026-005: `owner_governance_enforce_gate_ordering()`'s
 * design-only exemption. Before this correction, ANY governed spec/plan
 * file referencing a work_id whose Gate 2 packet was not yet `approved`
 * failed `--enforce-gate-ordering` unconditionally — including the exact
 * spec document that IS the Gate 2 design being presented to the owner FOR
 * that decision, which made it structurally impossible to ever commit a
 * Gate 2 design packet while it was still `awaiting_owner` (discovered
 * while preparing GAP-010b's Gate 2 packet, 2026-08-06/07).
 *
 * `owner_gate_2_record` is a REFERENCE to the Gate 2 packet, never proof by
 * itself that Gate 2 is approved. The fix: when the caller supplies
 * $changedFiles (the current submission's diff scope) and gate_status is
 * exactly 'awaiting_owner', a submission whose changed files are ALL
 * governance/design documents is exempt from 'gate-2-not-approved'. Every
 * other rule, and every other gate_status, is completely unaffected — see
 * the function's own docblock in scripts/ssot/owner_governance_lint.php.
 *
 * Fixtures are plain temp directories (no git needed — the function under
 * test never shells out to git), built fresh per test and torn down after,
 * so no test here can touch the real repository's docs/ tree.
 */
class GateOrderingDesignOnlyExemptionTest extends TestCase
{
    /** @var string[] */
    private array $tempRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->tempRoots as $root) {
            exec('rm -rf ' . escapeshellarg($root));
        }
        $this->tempRoots = [];
        parent::tearDown();
    }

    private function makeTempRoot(): string
    {
        $dir = sys_get_temp_dir() . '/own-2026-005-gate-ordering-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        $this->tempRoots[] = $dir;

        return $dir;
    }

    private function writeFile(string $repoRoot, string $relPath, string $content): string
    {
        $full = $repoRoot . '/' . $relPath;
        @mkdir(dirname($full), 0777, true);
        file_put_contents($full, $content);

        return $relPath;
    }

    private function gate2Frontmatter(string $workId, string $gateStatus, string $ownerDecisionValue): string
    {
        $decisionRequested = $gateStatus === 'awaiting_owner' ? '"approve_or_changes_or_decline"' : 'null';
        $recordedAt = $ownerDecisionValue === 'none' ? 'null' : '"2026-08-07T08:00:00+07:00"';
        $recordedBy = $ownerDecisionValue === 'none' ? 'null' : 'agent';
        $trustLevel = $ownerDecisionValue === 'none' ? 'claimed_repo_record' : 'claimed_repo_record';

        return <<<YAML
---
work_id: {$workId}
gate: 2
gate_status: {$gateStatus}
owner_decision:
  value: {$ownerDecisionValue}
  authority: human_owner
decision_requested: {$decisionRequested}
references:
  spec: null
  plan: null
  branch: null
  pr: null
  release: null
decision_provenance:
  trust_level: {$trustLevel}
  recorded_by: {$recordedBy}
  recorded_at: {$recordedAt}
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-07T08:00:00+07:00"
  updated_at: "2026-08-07T08:00:00+07:00"
generated_by: agent
---

## Fixture Gate 2 packet for OWN-2026-005 regression tests.
YAML;
    }

    private function specFrontmatter(string $workId, string $gate2RecordPath): string
    {
        return <<<MD
---
work_id: {$workId}
owner_governance_version: 1
owner_gate_2_record: {$gate2RecordPath}
---

# Fixture design spec for OWN-2026-005 regression tests.
MD;
    }

    // --- Scenario 1: Packet awaiting_owner + spec same work_id, only design docs changed: PASS ---

    public function test_awaiting_owner_gate2_with_design_only_diff_passes(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-901';
        $gate2Rel = "docs/owner-decisions/{$workId}/02-design.md";
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-design.md';

        $this->writeFile($root, $gate2Rel, $this->gate2Frontmatter($workId, 'awaiting_owner', 'none'));
        $this->writeFile($root, $specRel, $this->specFrontmatter($workId, $gate2Rel));

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            [$gate2Rel, $specRel]
        );

        $this->assertSame([], $violations, 'Design-only diff against an awaiting_owner Gate 2 packet must PASS: ' . json_encode(array_map(fn ($v) => $v->rule, $violations)));
    }

    // --- Scenario 2: Packet awaiting_owner but implementation code changed: FAIL ---

    public function test_awaiting_owner_gate2_with_implementation_file_in_diff_fails(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-901';
        $gate2Rel = "docs/owner-decisions/{$workId}/02-design.md";
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-design.md';

        $this->writeFile($root, $gate2Rel, $this->gate2Frontmatter($workId, 'awaiting_owner', 'none'));
        $this->writeFile($root, $specRel, $this->specFrontmatter($workId, $gate2Rel));

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            [$gate2Rel, $specRel, 'app/Http/Controllers/Api/ExportController.php']
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('gate-2-not-approved', $rules, 'A diff that includes an application file must still fail, even though Gate 2 is awaiting_owner.');
    }

    // --- Scenario 3: Packet approved + valid reference: PASS ---

    public function test_approved_gate2_passes_regardless_of_changed_files(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-902';
        $gate2Rel = "docs/owner-decisions/{$workId}/02-design.md";
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-design-approved.md';

        $this->writeFile($root, $gate2Rel, $this->gate2Frontmatter($workId, 'approved', 'approved'));
        $this->writeFile($root, $specRel, $this->specFrontmatter($workId, $gate2Rel));

        foreach ([null, [], [$gate2Rel, $specRel], ['app/SomeFile.php']] as $changedFiles) {
            $violations = \owner_governance_enforce_gate_ordering(
                [$root . '/' . $specRel],
                true,
                $root,
                [],
                $changedFiles
            );
            $this->assertSame([], $violations, 'An approved Gate 2 packet must always pass, regardless of $changedFiles (' . json_encode($changedFiles) . ').');
        }
    }

    // --- Scenario 4: Packet rejected (declined/deferred): FAIL, even with a design-only diff ---

    public function test_declined_gate2_fails_even_with_design_only_diff(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-903';
        $gate2Rel = "docs/owner-decisions/{$workId}/02-design.md";
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-design-declined.md';

        // Per packet-schema.yml: 'declined' owner_decision pairs with gate_status 'deferred'.
        $this->writeFile($root, $gate2Rel, $this->gate2Frontmatter($workId, 'deferred', 'declined'));
        $this->writeFile($root, $specRel, $this->specFrontmatter($workId, $gate2Rel));

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            [$gate2Rel, $specRel] // design-only diff — must NOT rescue a declined decision.
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('gate-2-not-approved', $rules, 'A declined/deferred Gate 2 packet must still fail — the design-only exemption applies ONLY to gate_status===awaiting_owner.');
    }

    // --- Scenario 5: Packet does not exist: FAIL, regardless of changed files ---

    public function test_missing_gate2_packet_fails_regardless_of_changed_files(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-904';
        $gate2Rel = "docs/owner-decisions/{$workId}/02-design.md"; // deliberately never written
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-design-missing-gate2.md';

        $this->writeFile($root, $specRel, $this->specFrontmatter($workId, $gate2Rel));

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            [$specRel]
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('gate-2-before-plan', $rules, 'A spec referencing a Gate 2 packet that does not exist must always fail.');
    }

    // --- Scenario 6: Spec and packet reference a DIFFERENT work_id: FAIL ---

    public function test_spec_referencing_a_different_work_ids_gate2_directory_fails(): void
    {
        $root = $this->makeTempRoot();
        $specWorkId = 'GAP-905';
        $otherWorkId = 'GAP-906';
        $otherGate2Rel = "docs/owner-decisions/{$otherWorkId}/02-design.md";
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-design-cross-workid.md';

        // The OTHER work item's Gate 2 exists and is even approved — must not matter,
        // because this spec declares a DIFFERENT work_id than the one whose Gate 2
        // directory its owner_gate_2_record points at.
        $this->writeFile($root, $otherGate2Rel, $this->gate2Frontmatter($otherWorkId, 'approved', 'approved'));
        $this->writeFile($root, $specRel, $this->specFrontmatter($specWorkId, $otherGate2Rel));

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            [$specRel]
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        // work_id is GAP-905, so the derived Gate 2 path is docs/owner-decisions/GAP-905/02-design.md,
        // which does not exist (only GAP-906's does) -> gate-2-before-plan.
        $this->assertContains('gate-2-before-plan', $rules, "A spec's own work_id must resolve to its OWN Gate 2 directory, never a different work item's.");
    }

    // --- Scenario 7: owner_gate_2_record path does not match the derived path: FAIL ---

    public function test_invalid_owner_gate2_record_path_fails(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-907';
        $realGate2Rel = "docs/owner-decisions/{$workId}/02-design.md";
        $wrongGate2Rel = "docs/owner-decisions/{$workId}/02-design-WRONG.md";
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-design-wrong-path.md';

        $this->writeFile($root, $realGate2Rel, $this->gate2Frontmatter($workId, 'awaiting_owner', 'none'));
        // Frontmatter deliberately points at a path that does not match the derived one.
        $this->writeFile($root, $specRel, $this->specFrontmatter($workId, $wrongGate2Rel));

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            [$realGate2Rel, $specRel] // design-only diff — must NOT rescue a wrong path.
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('gate2-record-mismatch', $rules, 'An owner_gate_2_record that does not match the derived path must always fail, even with a design-only diff.');
    }

    // --- Scenario 8: unrelated, pre-existing Gate 1/Gate 2 rules are unaffected ---

    public function test_missing_governance_frontmatter_rule_is_unaffected_by_changed_files(): void
    {
        $root = $this->makeTempRoot();
        $specRel = 'docs/superpowers/plans/some-plan-with-no-frontmatter.md';
        $this->writeFile($root, $specRel, "# A plan with no governance frontmatter at all.\n\nNothing here.");

        foreach ([null, [], [$specRel]] as $changedFiles) {
            $violations = \owner_governance_enforce_gate_ordering(
                [$root . '/' . $specRel],
                true,
                $root,
                [],
                $changedFiles
            );
            $rules = array_map(fn ($v) => $v->rule, $violations);
            $this->assertContains('missing-governance-frontmatter', $rules, 'missing-governance-frontmatter must fire regardless of $changedFiles (' . json_encode($changedFiles) . ').');
        }
    }

    public function test_incomplete_governance_frontmatter_rule_is_unaffected_by_changed_files(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-908';
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-incomplete-frontmatter.md';
        // Declares work_id but omits owner_governance_version/owner_gate_2_record.
        $this->writeFile($root, $specRel, "---\nwork_id: {$workId}\n---\n\n# Incomplete frontmatter.");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            [$specRel]
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('incomplete-governance-frontmatter', $rules);
    }

    public function test_legacy_exempt_work_id_always_passes(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-031';
        $specRel = 'docs/superpowers/specs/legacy-fixture.md';
        // No Gate 2 packet exists at all for this work_id in this fixture tree —
        // would fail gate-2-before-plan if not legacy-exempt.
        $this->writeFile($root, $specRel, $this->specFrontmatter($workId, "docs/owner-decisions/{$workId}/02-design.md"));

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            ['GAP-031'], // legacy-exempt list
            ['app/SomeUnrelatedFile.php'] // even a non-design-only diff must not matter — legacy exemption short-circuits first.
        );

        $this->assertSame([], $violations, 'A legacy-exempt work_id must always pass, independent of $changedFiles.');
    }

    // --- Helper function itself: owner_governance_changed_files_are_design_only() ---

    public function test_design_only_helper_requires_non_empty_and_all_matching_prefixes(): void
    {
        $this->assertFalse(\owner_governance_changed_files_are_design_only([]), 'An empty changed-files list must NOT be treated as design-only.');
        $this->assertTrue(\owner_governance_changed_files_are_design_only([
            'docs/owner-decisions/GAP-901/02-design.md',
            'docs/superpowers/specs/x.md',
            'docs/superpowers/plans/y.md',
        ]));
        $this->assertFalse(\owner_governance_changed_files_are_design_only([
            'docs/owner-decisions/GAP-901/02-design.md',
            'app/Http/Controllers/Api/ExportController.php',
        ]));
        $this->assertFalse(\owner_governance_changed_files_are_design_only([
            'docs/owner-decisions/GAP-901/02-design.md',
            'docs/owner-governance/packet-schema.yml',
        ]), 'docs/owner-governance/** (schema/tooling) must NOT count as design-only — it is a different risk class with its own governed work item.');
    }

    /**
     * Owner review round 2: proves the exemption logic itself has no
     * hidden ~100-item limitation (the original bug this correction fixes
     * was specifically about a 100-item CAP on the changed-files SOURCE —
     * scripts/ci/fetch-pr-changed-files.sh's own multi-page/101-file
     * coverage lives in FetchPrChangedFilesTest.php; this proves the
     * downstream enforcement decision is correct once handed such a list).
     */
    public function test_101st_file_being_an_implementation_file_still_fails_end_to_end(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-909';
        $gate2Rel = "docs/owner-decisions/{$workId}/02-design.md";
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-101-files.md';

        $this->writeFile($root, $gate2Rel, $this->gate2Frontmatter($workId, 'awaiting_owner', 'none'));
        $this->writeFile($root, $specRel, $this->specFrontmatter($workId, $gate2Rel));

        $changedFiles = array_map(fn ($i) => "docs/superpowers/specs/fixture-{$i}.md", range(1, 100));
        $changedFiles[] = 'app/Http/Controllers/Api/ExportController.php'; // file #101

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            $changedFiles
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('gate-2-not-approved', $rules, 'A 101-file diff where only file #101 is an implementation file must still fail — the exemption logic must not have any 100-item blind spot.');
    }

    /**
     * A changed-file path containing a comma must be classified correctly
     * (never comma-split into two entries, one of which could spuriously
     * "look like" an allowed governance-doc path).
     */
    public function test_filename_containing_a_comma_is_classified_correctly_end_to_end(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-910';
        $gate2Rel = "docs/owner-decisions/{$workId}/02-design.md";
        $specRel = 'docs/superpowers/specs/2026-08-07-fixture-comma.md';

        $this->writeFile($root, $gate2Rel, $this->gate2Frontmatter($workId, 'awaiting_owner', 'none'));
        $this->writeFile($root, $specRel, $this->specFrontmatter($workId, $gate2Rel));

        // A single design-only file whose name itself contains a comma.
        $commaFile = 'docs/superpowers/specs/weird, filename, with commas.md';
        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            [$gate2Rel, $specRel, $commaFile]
        );
        $this->assertSame([], $violations, 'A design-only diff must still PASS when one of its filenames contains a comma — proves the comma is never mis-split.');

        // Same comma-containing name, but now treated as if it were an
        // implementation file (i.e. proving the helper does path-prefix
        // matching, not naive comma-tokenizing that could accidentally
        // "match" an allowed prefix from a fragment).
        $trickyFile = 'app/weird, docs/owner-decisions, filename.php';
        $violationsWithTrickyFile = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            true,
            $root,
            [],
            [$gate2Rel, $specRel, $trickyFile]
        );
        $rules = array_map(fn ($v) => $v->rule, $violationsWithTrickyFile);
        $this->assertContains('gate-2-not-approved', $rules, 'A filename starting with app/ must still be classified as non-design-only, even if a substring after a comma looks like an allowed path.');
    }
}
