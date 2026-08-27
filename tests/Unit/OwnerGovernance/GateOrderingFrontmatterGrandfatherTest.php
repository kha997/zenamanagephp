<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php';

/**
 * Covers GAP-047 Defect B: the bulk-scan silent-skip of new governed
 * spec/plan documents lacking governance frontmatter, and its B3 fix — an
 * explicit, exact-path grandfather list for the historical corpus, with
 * unconditional fail-closed enforcement for everything else regardless of
 * filename casing, shape, or Work-ID-token presence.
 *
 * Fixtures are plain temp directories (no git needed), built fresh per test
 * and torn down after, matching GateOrderingDesignOnlyExemptionTest.php's
 * pattern.
 */
class GateOrderingFrontmatterGrandfatherTest extends TestCase
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
        $dir = sys_get_temp_dir() . '/gap-047-frontmatter-grandfather-test-' . bin2hex(random_bytes(6));
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

    // --- GAP-047 case 6: new lowercase filename, no frontmatter, bulk scan: FAIL ---

    public function test_gap047_case6_new_lowercase_filename_no_frontmatter_bulk_scan_fails(): void
    {
        $root = $this->makeTempRoot();
        $specRel = 'docs/superpowers/specs/2026-08-27-gap047-fixture-lowercase.md';
        $this->writeFile($root, $specRel, "# A brand-new spec with no frontmatter, lowercase gap047 token.\n");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            false, // BULK SCAN — this is exactly the branch Defect B silently skips
            $root,
            [],
            null,
            [] // empty grandfather list — nothing grandfathered
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains(
            'missing-governance-frontmatter',
            $rules,
            'GAP-047 case 6 (pre-B3 RED): a new lowercase-named spec with no frontmatter, reached via a BULK scan, must FAIL missing-governance-frontmatter. Before the B3 fix, this silently passes (Defect B).'
        );
    }

    // --- GAP-047 case 7: new uppercase filename, no frontmatter, bulk scan: FAIL ---

    public function test_gap047_case7_new_uppercase_filename_no_frontmatter_bulk_scan_fails(): void
    {
        $root = $this->makeTempRoot();
        $specRel = 'docs/superpowers/specs/2026-08-27-GAP-947-fixture-uppercase.md';
        $this->writeFile($root, $specRel, "# A brand-new spec with no frontmatter, uppercase GAP-947 token.\n");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            false,
            $root,
            [],
            null,
            []
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('missing-governance-frontmatter', $rules, 'GAP-047 case 7: an uppercase-hyphenated token must not change the fail result — casing is irrelevant under B3.');
    }

    // --- GAP-047 case 8: new tokenless filename, no frontmatter, bulk scan: FAIL ---

    public function test_gap047_case8_new_tokenless_filename_no_frontmatter_bulk_scan_fails(): void
    {
        $root = $this->makeTempRoot();
        $specRel = 'docs/superpowers/plans/2026-08-27-some-new-feature-slug.md';
        $this->writeFile($root, $specRel, "# A brand-new plan, no Work-ID token in the filename at all, no frontmatter.\n");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            false,
            $root,
            [],
            null,
            []
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('missing-governance-frontmatter', $rules, 'GAP-047 case 8: a brand-new tokenless filename must still FAIL under B3 — new documents get no free pass, only the frozen historical snapshot does.');
    }

    // --- GAP-047 case 9: exact grandfathered historical path: PASS ---

    public function test_gap047_case9_exact_grandfathered_path_passes(): void
    {
        $root = $this->makeTempRoot();
        $specRel = 'docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design.md';
        $this->writeFile($root, $specRel, "# Historical GAP-032 spec, no frontmatter (real historical shape).\n");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            false,
            $root,
            [],
            null,
            [$specRel]
        );

        $this->assertSame([], $violations, 'GAP-047 case 9: an exact grandfathered path must PASS.');
    }

    // --- GAP-047 case 10: non-grandfathered file that merely looks old: FAIL ---

    public function test_gap047_case10_non_grandfathered_legacy_looking_path_fails(): void
    {
        $root = $this->makeTempRoot();
        $specRel = 'docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design-v2.md';
        $this->writeFile($root, $specRel, "# Looks like the grandfathered GAP-032 file but is NOT the exact same path.\n");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            false,
            $root,
            [],
            null,
            // Grandfather list contains the ORIGINAL path, not this v2 variant.
            ['docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design.md']
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('missing-governance-frontmatter', $rules, 'GAP-047 case 10: a path that merely resembles a grandfathered path, but is not an exact match, must FAIL.');
    }

    // --- GAP-047 case 11: complete correct frontmatter: existing behavior preserved (PASS) ---

    public function test_gap047_case11_complete_frontmatter_preserved(): void
    {
        $root = $this->makeTempRoot();
        $workId = 'GAP-948';
        $gate2Rel = "docs/owner-decisions/{$workId}/02-design.md";
        $specRel = 'docs/superpowers/specs/2026-08-27-fixture-gap047-case11.md';

        $this->writeFile($root, $gate2Rel, "---\nwork_id: {$workId}\ngate: 2\ngate_status: approved\nowner_decision:\n  value: approved\n  authority: human_owner\ndecision_requested: null\nreferences:\n  spec: null\n  plan: null\n  branch: null\n  pr: null\n  release: null\ndecision_provenance:\n  trust_level: claimed_repo_record\n  recorded_by: agent\n  recorded_at: \"2026-08-27T08:00:00+07:00\"\n  owner_response_reference: null\n  reconciliation_required: false\nsupersedes: null\nsuperseded_by: null\ntimestamps:\n  created_at: \"2026-08-27T08:00:00+07:00\"\n  updated_at: \"2026-08-27T08:00:00+07:00\"\ngenerated_by: agent\n---\n\nFixture.\n");
        $this->writeFile($root, $specRel, "---\nwork_id: {$workId}\nowner_governance_version: 1\nowner_gate_2_record: {$gate2Rel}\n---\n\n# Fixture spec, complete frontmatter.\n");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            false,
            $root,
            [],
            null,
            []
        );

        $this->assertSame([], $violations, 'GAP-047 case 11: complete frontmatter + approved Gate 2 must still PASS exactly as before.');
    }

    // --- GAP-047 case 12: incomplete frontmatter: FAIL (unchanged rule) ---

    public function test_gap047_case12_incomplete_frontmatter_fails(): void
    {
        $root = $this->makeTempRoot();
        $specRel = 'docs/superpowers/specs/2026-08-27-fixture-gap047-case12.md';
        $this->writeFile($root, $specRel, "---\nwork_id: GAP-949\n---\n\n# Missing owner_governance_version and owner_gate_2_record.\n");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            false,
            $root,
            [],
            null,
            []
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('incomplete-governance-frontmatter', $rules, 'GAP-047 case 12: incomplete frontmatter must FAIL exactly as before, never consulted against the grandfather list.');
    }

    // --- GAP-047 case 15: historical exact GAP-032 path passes via path grandfather ONLY (no legacy-list involvement) ---

    public function test_gap047_case15_gap032_path_passes_via_grandfather_only_not_legacy_list(): void
    {
        $root = $this->makeTempRoot();
        $specRel = 'docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design.md';
        $this->writeFile($root, $specRel, "# Real historical GAP-032 spec shape, no frontmatter.\n");

        // Deliberately pass an EMPTY legacy-ids list — proves the pass comes
        // from the grandfather file, never from legacy-work-ids.txt.
        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            false,
            $root,
            [], // empty legacy ids
            null,
            [$specRel]
        );

        $this->assertSame([], $violations, 'GAP-047 case 15: GAP-032 must pass via the path grandfather list alone, with an empty legacy-work-ids list.');
    }

    // --- GAP-047 case 16: new non-grandfathered GAP032-shaped filename: FAIL ---

    public function test_gap047_case16_new_gap032_looking_path_fails(): void
    {
        $root = $this->makeTempRoot();
        $specRel = 'docs/superpowers/specs/2026-09-01-gap032-followup-design.md'; // NEW file, different date, GAP032-shaped
        $this->writeFile($root, $specRel, "# A NEW file that merely LOOKS like a GAP-032 doc, no frontmatter.\n");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $specRel],
            false,
            $root,
            [],
            null,
            ['docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design.md']
        );

        $rules = array_map(fn ($v) => $v->rule, $violations);
        $this->assertContains('missing-governance-frontmatter', $rules, 'GAP-047 case 16: a NEW GAP032-looking filename not on the exact grandfather list must FAIL — legacy-looking filename text cannot create exemption.');
    }

    // --- GAP-047 case 17: recognizable-token vs tokenless, both non-grandfathered, no frontmatter: SAME FAIL result ---

    public function test_gap047_case17_token_vs_tokenless_same_fail_result(): void
    {
        $root = $this->makeTempRoot();
        $withToken = 'docs/superpowers/specs/2026-08-27-GAP-950-fixture-with-token.md';
        $tokenless = 'docs/superpowers/specs/2026-08-27-fixture-without-token.md';
        $this->writeFile($root, $withToken, "# Has a recognizable token, no frontmatter.\n");
        $this->writeFile($root, $tokenless, "# No recognizable token, no frontmatter.\n");

        $violationsWithToken = \owner_governance_enforce_gate_ordering([$root . '/' . $withToken], false, $root, [], null, []);
        $violationsTokenless = \owner_governance_enforce_gate_ordering([$root . '/' . $tokenless], false, $root, [], null, []);

        $rulesWithToken = array_map(fn ($v) => $v->rule, $violationsWithToken);
        $rulesTokenless = array_map(fn ($v) => $v->rule, $violationsTokenless);

        $this->assertContains('missing-governance-frontmatter', $rulesWithToken);
        $this->assertContains('missing-governance-frontmatter', $rulesTokenless);
        $this->assertSame($rulesWithToken, $rulesTokenless, 'GAP-047 case 17: filename-token presence must never change the pass/fail rule set — only diagnostic wording may differ.');
    }

    // --- GAP-047 case 19: grandfather immutability — a NEW no-frontmatter file after the snapshot, without an explicit grandfather-config edit, FAILS ---

    public function test_gap047_case19_new_omission_not_silently_absorbed(): void
    {
        $root = $this->makeTempRoot();
        $existingGrandfathered = 'docs/superpowers/specs/2026-08-10-gap032-document-status-semantics-design.md';
        $newOmission = 'docs/superpowers/specs/2026-08-27-brand-new-omission.md';
        $this->writeFile($root, $existingGrandfathered, "# Historical.\n");
        $this->writeFile($root, $newOmission, "# A new document introduced after the snapshot, no frontmatter, no grandfather entry.\n");

        $violations = \owner_governance_enforce_gate_ordering(
            [$root . '/' . $existingGrandfathered, $root . '/' . $newOmission],
            false,
            $root,
            [],
            null,
            [$existingGrandfathered] // does NOT include $newOmission
        );

        $byFile = [];
        foreach ($violations as $v) {
            $byFile[$v->file][] = $v->rule;
        }
        $this->assertArrayNotHasKey(basename($existingGrandfathered), $byFile, 'The existing grandfathered file must pass.');
        $this->assertContains('missing-governance-frontmatter', $byFile[basename($newOmission)] ?? [], 'GAP-047 case 19: a NEW omission introduced after the snapshot must FAIL — the grandfather list is frozen, not a rolling allowlist.');
    }

    /**
     * GAP-047 case 18: snapshot completeness at the implementation head —
     * every REAL file in docs/superpowers/specs/*.md and
     * docs/superpowers/plans/*.md in this actual repository either has
     * complete governance frontmatter, or its exact path is on the real
     * grandfather file. This is what makes the design's "does not
     * retroactively break main" claim a TESTED FACT, not an assertion —
     * it runs against the real repository tree, not a fixture.
     */
    public function test_gap047_case18_real_repo_snapshot_completeness(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $legacyIdsPath = $repoRoot . '/docs/owner-governance/legacy-work-ids.txt';
        $legacyIds = array_filter(array_map('trim', file($legacyIdsPath)), fn ($l) => $l !== '' && !str_starts_with($l, '#'));

        $governedDocFiles = array_merge(
            glob($repoRoot . '/docs/superpowers/plans/*.md'),
            glob($repoRoot . '/docs/superpowers/specs/*.md')
        );

        $grandfatheredPaths = \owner_governance_load_grandfathered_paths(
            $repoRoot . '/docs/owner-governance/grandfathered-nonfrontmatter-documents.txt'
        );

        $violations = \owner_governance_enforce_gate_ordering(
            $governedDocFiles,
            false, // bulk scan — the real CI mode
            $repoRoot,
            $legacyIds,
            null,
            $grandfatheredPaths
        );

        $missingFrontmatterViolations = array_filter($violations, fn ($v) => $v->rule === 'missing-governance-frontmatter');

        $this->assertSame(
            [],
            array_values($missingFrontmatterViolations),
            'GAP-047 case 18: a fresh bulk scan against the REAL repository tree must produce ZERO missing-governance-frontmatter violations — every no-frontmatter file must be on the grandfather list. Failures: ' . json_encode(array_map(fn ($v) => $v->file, $missingFrontmatterViolations))
        );
    }
}
