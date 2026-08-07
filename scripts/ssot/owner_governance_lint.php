<?php declare(strict_types=1);

/**
 * owner-governance-lint — structural validator for docs/owner-decisions/**
 * packet files against docs/owner-governance/packet-schema.yml.
 *
 * Usage: php scripts/ssot/owner_governance_lint.php [path-or-directory ...]
 * With no arguments, scans docs/owner-decisions/ recursively.
 * Exit 0 = no violations. Exit 1 = at least one violation (message printed,
 * plus a ::error GitHub Actions annotation per violation when GITHUB_ACTIONS
 * is set — mirrors scripts/ci/docs-lint.sh's existing annotation contract).
 *
 * This script validates STRUCTURE and ENUM MEMBERSHIP only. It never
 * evaluates whether a decision is authentic — see decision_provenance.trust_level,
 * which this script treats as a required, schema-valid field, not a fact it verifies.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

final class OwnerGovernanceLintViolation
{
    public function __construct(
        public readonly string $file,
        public readonly string $rule,
        public readonly string $message,
    ) {
    }
}

/**
 * @return OwnerGovernanceLintViolation[]
 */
function owner_governance_validate_packet(string $filePath, string $content, array $schema): array
{
    $violations = [];

    if (!preg_match('/^---\n(.*?)\n---\n(.*)$/s', $content, $matches)) {
        return [new OwnerGovernanceLintViolation($filePath, 'frontmatter', 'Missing or malformed --- frontmatter block.')];
    }

    try {
        $fm = Yaml::parse($matches[1]);
    } catch (\Throwable $e) {
        return [new OwnerGovernanceLintViolation($filePath, 'frontmatter', 'Frontmatter is not valid YAML: ' . $e->getMessage())];
    }

    if (!is_array($fm)) {
        return [new OwnerGovernanceLintViolation($filePath, 'frontmatter', 'Frontmatter did not parse to a mapping.')];
    }

    $body = $matches[2];

    // Required top-level fields present.
    foreach ($schema['required_top_level_fields'] as $field) {
        if (!array_key_exists($field, $fm)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'required-field', "Missing required field '{$field}'.");
        }
    }
    if ($violations !== []) {
        return $violations; // Can't check anything downstream reliably without the basics.
    }

    // work_id pattern.
    if (!preg_match('/' . $schema['work_id_pattern'] . '/', (string) $fm['work_id'])) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'work-id-pattern', "work_id '{$fm['work_id']}' does not match the canonical pattern.");
    }

    // gate must be 1, 2, or 3 and must have a schema entry.
    $gate = $fm['gate'] ?? null;
    if (!in_array($gate, [1, 2, 3], true)) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'gate-value', "gate must be 1, 2, or 3, got '" . var_export($gate, true) . "'.");

        return $violations;
    }
    $gateSchema = $schema['gates'][$gate];

    // gate_status enum, per-gate.
    $gateStatus = $fm['gate_status'] ?? null;
    if (!in_array($gateStatus, $gateSchema['gate_status_values'], true)) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'gate-status-enum', "gate_status '{$gateStatus}' is not valid for gate {$gate}.");
    }

    // owner_decision.value enum, per-gate.
    $ownerDecisionValue = $fm['owner_decision']['value'] ?? null;
    if (!in_array($ownerDecisionValue, $gateSchema['owner_decision_values'], true)) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'owner-decision-enum', "owner_decision.value '{$ownerDecisionValue}' is not valid for gate {$gate}.");
    }

    // gate_status <-> owner_decision compatibility (the contradiction check).
    if ($gateStatus !== null && array_key_exists($gateStatus, $schema['gate_status_requires_owner_decision'])) {
        $allowed = $schema['gate_status_requires_owner_decision'][$gateStatus];
        if ($allowed !== [] && !in_array($ownerDecisionValue, $allowed, true)) {
            $violations[] = new OwnerGovernanceLintViolation(
                $filePath,
                'status-decision-contradiction',
                "gate_status '{$gateStatus}' requires owner_decision.value to be one of [" . implode(', ', $allowed) . "], got '{$ownerDecisionValue}'."
            );
        }
    }

    // decision_requested rule: non-null iff gate_status is awaiting_owner.
    $decisionRequested = $fm['decision_requested'] ?? null;
    if ($gateStatus === 'awaiting_owner' && $decisionRequested === null) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'decision-requested-missing', "gate_status is 'awaiting_owner' but decision_requested is null.");
    }
    if ($gateStatus !== 'awaiting_owner' && $decisionRequested !== null) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'decision-requested-leaked', "Only 'awaiting_owner' packets may set decision_requested; gate_status is '{$gateStatus}'.");
    }
    if ($decisionRequested !== null && !in_array($decisionRequested, $schema['decision_requested_values'], true)) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'decision-requested-enum', "decision_requested '{$decisionRequested}' is not a member of decision_requested_values in packet-schema.yml.");
    }

    // Honest provenance: any non-'none' owner_decision must carry real attribution.
    if ($ownerDecisionValue !== 'none') {
        $provenance = $fm['decision_provenance'] ?? [];
        foreach (['trust_level', 'recorded_by', 'recorded_at'] as $requiredProvenanceField) {
            if (empty($provenance[$requiredProvenanceField])) {
                $violations[] = new OwnerGovernanceLintViolation(
                    $filePath,
                    'dishonest-provenance',
                    "owner_decision.value is '{$ownerDecisionValue}' but decision_provenance.{$requiredProvenanceField} is empty — a recorded decision must carry attribution."
                );
            }
        }
        $trustLevel = $provenance['trust_level'] ?? null;
        if ($trustLevel !== null && !in_array($trustLevel, $schema['decision_provenance_trust_level_values'], true)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'trust-level-enum', "decision_provenance.trust_level '{$trustLevel}' is not a recognized value.");
        }
    }

    // Gate-3-only fields: required on gate 3, forbidden on gate 1/2.
    foreach ($schema['gate_3_only_fields'] as $gate3Field) {
        $present = array_key_exists($gate3Field, $fm);
        if ($gateSchema['requires_technical_readiness'] && !$present) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'gate3-field-missing', "Gate 3 packet is missing required field '{$gate3Field}'.");
        }
        if (!$gateSchema['requires_technical_readiness'] && $present) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'gate3-field-leaked', "Field '{$gate3Field}' is Gate-3-only but present on a gate {$gate} packet.");
        }
    }
    if ($gateSchema['requires_technical_readiness']) {
        $trValue = $fm['technical_readiness']['value'] ?? null;
        if (!in_array($trValue, $schema['technical_readiness_values'], true)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'technical-readiness-enum', "technical_readiness.value '{$trValue}' is not valid.");
        }
        // technical_readiness must never be owner-authored: this script does not
        // (and cannot, from a single file) prove *who* wrote a value — it only
        // enforces the generated_by tag is present and correct as a documented
        // convention, matching design §3.7's "generated_by: engineering_evidence".
        if (($fm['technical_readiness']['generated_by'] ?? null) !== 'engineering_evidence') {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'technical-readiness-provenance', "technical_readiness.generated_by must be 'engineering_evidence'.");
        }

        // --- Evidence-binding contract (Correction 2, revised) ---
        // awaiting_owner / approved must never coexist with a non-ready readiness.
        if (in_array($gateStatus, ['awaiting_owner', 'approved'], true) && $trValue !== 'ready') {
            $violations[] = new OwnerGovernanceLintViolation(
                $filePath,
                'not-ready-but-decision-eligible',
                "gate_status is '{$gateStatus}' but technical_readiness.value is '{$trValue}', not 'ready'."
            );
        }

        // technical_evidence sub-fields must be present whenever this gate
        // requires technical_readiness at all (i.e. on every Gate 3 packet,
        // not only decided ones) — the field names themselves are the
        // canonical contract Correction 2 requires consistently everywhere.
        $evidence = $fm['technical_evidence'] ?? [];
        foreach ($schema['required_technical_evidence_fields'] as $teField) {
            if (!array_key_exists($teField, $evidence)) {
                $violations[] = new OwnerGovernanceLintViolation($filePath, 'technical-evidence-field-missing', "technical_evidence is missing required field '{$teField}'.");
            }
        }
        $bindingFieldsPresent = $fm['owner_decision_binding'] ?? [];
        foreach ($schema['required_owner_decision_binding_fields'] as $bField) {
            if (!array_key_exists($bField, $bindingFieldsPresent)) {
                $violations[] = new OwnerGovernanceLintViolation($filePath, 'owner-decision-binding-field-missing', "owner_decision_binding is missing required field '{$bField}'.");
            }
        }

        // Once a decision APPROVES release, the binding must be populated and
        // must match the packet's own technical_evidence.implementation_tree_digest
        // exactly — this is the structural half of staleness detection. Because
        // the digest is a git-tree hash that structurally excludes only this
        // exact Gate 3 record file (never the whole work-ID directory), a
        // commit that touches ONLY this file leaves the digest unchanged, while
        // a commit touching anything else (Gate 1/Gate 2 records, schemas,
        // scripts, CI config, ...) changes it and correctly invalidates
        // readiness. The live half — recomputing this digest against the
        // ACTUAL current git tree rather than just internal self-consistency —
        // is scripts/ci/check-evidence-freshness.sh, since that requires `git`,
        // not just this file.
        //
        // Scoped to 'approved' specifically, NOT "!== 'none'": a binding
        // exists to detect staleness of a decision that GRANTS release
        // against an exact tree state. 'deferred' and 'correction_requested'
        // grant nothing -- there is no approval to go stale, so requiring a
        // binding for them forces fabricating one, which is worse than
        // requiring none. Caught recording OWN-2026-001's own real 'deferred'
        // decision: the original `!== 'none'` condition demanded a binding
        // digest for a decision that explicitly does not approve anything.
        if ($ownerDecisionValue === 'approved') {
            $binding = $fm['owner_decision_binding'] ?? [];

            if (empty($binding['implementation_tree_digest']) || empty($binding['decision_recorded_at'])) {
                $violations[] = new OwnerGovernanceLintViolation(
                    $filePath,
                    'evidence-binding-required-once-decided',
                    "owner_decision.value is '{$ownerDecisionValue}' but owner_decision_binding is not populated — a recorded decision must bind to the implementation tree it was made against."
                );
            } elseif (($binding['implementation_tree_digest'] ?? null) !== ($evidence['implementation_tree_digest'] ?? null)) {
                $violations[] = new OwnerGovernanceLintViolation(
                    $filePath,
                    'stale-decision-tree-digest-mismatch',
                    "owner_decision_binding.implementation_tree_digest does not match technical_evidence.implementation_tree_digest — the implementation has changed since this decision was recorded. Per design: this packet's gate_status must return to 'preparing' or 'blocked_technical', not stay 'approved'/'awaiting_owner'. The lint reports this; it does not rewrite the file itself."
                );
            }
        }
    }

    // Supersession links must resolve to files that actually exist in the repo.
    $repoRoot = dirname(__DIR__, 2);
    foreach (['supersedes', 'superseded_by'] as $supersessionField) {
        $target = $fm[$supersessionField] ?? null;
        if ($target !== null && !is_file($repoRoot . '/' . $target)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'dangling-supersession-link', "{$supersessionField} points to '{$target}', which does not exist.");
        }
    }

    // references.spec / references.plan, if non-null, must resolve.
    foreach (['spec', 'plan'] as $refField) {
        $target = $fm['references'][$refField] ?? null;
        if ($target !== null && !is_file($repoRoot . '/' . $target)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'dangling-eel-link', "references.{$refField} points to '{$target}', which does not exist.");
        }
    }

    // No placeholder tokens anywhere in the body.
    foreach ($schema['placeholder_tokens'] as $token) {
        if (str_contains($body, $token)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'placeholder-token', "Body contains forbidden placeholder token '{$token}'.");
        }
    }

    return $violations;
}

/**
 * Given basenames of Gate 3 release packets found for one work_id (e.g.
 * ["03-release.md", "03-release-v2.md"]), returns the basename of the
 * ACTIVE (highest-versioned) one. "03-release.md" is version 1;
 * "03-release-vN.md" is version N. A plain lexicographic sort is wrong
 * here: "-" (0x2D) sorts before "." (0x2E) in ASCII, so the string
 * "03-release-v2.md" sorts BEFORE "03-release.md" even though v2 is the
 * newer packet — caught by the final whole-branch review on PR #239,
 * which found `ls ... | sort | tail -n1`-style selection already
 * mis-picking GAP-031's superseded base packet over its active
 * 03-release-v2.md. Version compare (not string compare) is required.
 *
 * @param array<int, string> $basenames
 */
function owner_governance_pick_active_gate3_basename(array $basenames): string
{
    $best = null;
    $bestVersion = -1;
    foreach ($basenames as $basename) {
        if ($basename === '03-release.md') {
            $version = 1;
        } elseif (preg_match('/^03-release-v(\d+)\.md$/', $basename, $m) === 1) {
            $version = (int) $m[1];
        } else {
            continue;
        }
        if ($version > $bestVersion) {
            $bestVersion = $version;
            $best = $basename;
        }
    }
    if ($best === null) {
        throw new \InvalidArgumentException('owner_governance_pick_active_gate3_basename: no recognized 03-release*.md basename found among: ' . implode(', ', $basenames));
    }
    return $best;
}

/**
 * Computes the implementation-tree digest per packet-schema.yml's
 * implementation_tree_digest_algorithm: sha256 of the sorted list of
 * "<blob_sha> <path>" lines for the complete git tree at $sha, excluding
 * ONLY the ACTIVE docs/owner-decisions/<workId>/03-release*.md packet
 * (resolved via owner_governance_pick_active_gate3_basename against
 * whatever release packet basenames exist in the tree at $sha) — never the
 * whole work-ID directory, and never a superseded/older packet, which
 * would otherwise leave the currently-active packet counted in the digest
 * and reintroduce the original self-reference bug for exactly the
 * supersession pattern GAP-031 demonstrates. This is what makes the
 * digest immune to the self-reference bug (a Gate-3-packet-only commit
 * does not change it) while still changing on any real implementation or
 * governance edit, including Gate 1/Gate 2 records, schemas, scripts, and
 * CI config.
 *
 * Requires a real git checkout at $repoRoot containing $sha (works for any
 * commit reachable in that repo's history, not just the current HEAD).
 */
function owner_governance_compute_implementation_tree_digest(string $sha, string $workId, string $repoRoot): string
{
    // Confirm the commit object actually exists in this checkout BEFORE
    // trusting `git ls-tree`'s output. Caught on this function's own real
    // CI run on PR #239: on a `pull_request` trigger, actions/checkout
    // defaults to the synthetic refs/pull/<n>/merge commit, not the real
    // PR head SHA passed here — that commit object was never fetched, so
    // `git ls-tree -r <sha> 2>/dev/null` silently returned empty output
    // (stderr suppressed), which hashed to sha256("") and was reported as
    // a plausible-looking but completely wrong digest, misdiagnosed as a
    // genuine staleness mismatch. An empty/missing tree must be a hard
    // error, never a silently-computed (and silently wrong) digest.
    $existsCommand = sprintf('git -C %s cat-file -e %s 2>&1', escapeshellarg($repoRoot), escapeshellarg($sha . '^{commit}'));
    exec($existsCommand, $existsOutput, $existsExitCode);
    if ($existsExitCode !== 0) {
        throw new \RuntimeException("owner_governance_compute_implementation_tree_digest: commit '{$sha}' does not exist in the checkout at '{$repoRoot}' — cannot compute a trustworthy digest. This usually means the checkout fetched a different ref (e.g. a merge commit) than the SHA being verified.");
    }

    $workDir = "docs/owner-decisions/{$workId}/";

    $command = sprintf('git -C %s ls-tree -r %s 2>/dev/null', escapeshellarg($repoRoot), escapeshellarg($sha));
    $output = shell_exec($command) ?? '';

    $entries = [];
    foreach (explode("\n", rtrim($output, "\n")) as $rawLine) {
        if ($rawLine === '') {
            continue;
        }
        [$meta, $path] = explode("\t", $rawLine, 2);
        $metaParts = preg_split('/\s+/', $meta);
        $type = $metaParts[1] ?? null;
        $blobSha = $metaParts[2] ?? null;
        if ($type !== 'blob') {
            continue;
        }
        $entries[] = ['path' => $path, 'blobSha' => $blobSha];
    }

    // Resolve the ACTIVE Gate 3 packet path from what actually exists in
    // this tree (not the working directory, so historical shas resolve
    // correctly too) so the exclusion always targets whichever packet is
    // currently claiming readiness, not a superseded one.
    $excludePath = null;
    $candidateBasenames = [];
    foreach ($entries as $entry) {
        if (str_starts_with($entry['path'], $workDir) && preg_match('#^03-release(-v\d+)?\.md$#', basename($entry['path'])) === 1) {
            $candidateBasenames[] = basename($entry['path']);
        }
    }
    if ($candidateBasenames !== []) {
        $excludePath = $workDir . owner_governance_pick_active_gate3_basename($candidateBasenames);
    }

    $lines = [];
    foreach ($entries as $entry) {
        if ($entry['path'] === $excludePath) {
            continue;
        }
        $lines[] = "{$entry['blobSha']} {$entry['path']}";
    }

    if ($lines === []) {
        throw new \RuntimeException("owner_governance_compute_implementation_tree_digest: commit '{$sha}' exists but its tree contains zero non-excluded blobs — this is almost certainly a bug (a real repository tree is never empty), not a legitimately empty implementation.");
    }

    sort($lines);

    return hash('sha256', implode("\n", $lines));
}

/**
 * Governance-doc path prefixes eligible for the design-only gate-ordering
 * exemption (OWN-2026-005): a submission whose changed files are ALL under
 * one of these prefixes touches only governance/design documentation, never
 * application code, tooling/schema, CI, or tests. Deliberately narrow and
 * NOT `docs/owner-governance/**` — schema/workflow/script changes are a
 * different risk class (they change enforcement itself) and must keep going
 * through their own governed work item (e.g. this very correction,
 * OWN-2026-005), never through this exemption.
 */
const OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES = [
    'docs/owner-decisions/',
    'docs/superpowers/specs/',
    'docs/superpowers/plans/',
];

/**
 * True iff $changedFiles is non-empty and every entry falls under one of
 * OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES. An empty list is deliberately
 * NOT design-only (no evidence of what changed is not evidence of safety).
 *
 * @param array<int, string> $changedFiles
 */
function owner_governance_changed_files_are_design_only(array $changedFiles): bool
{
    if ($changedFiles === []) {
        return false;
    }
    foreach ($changedFiles as $file) {
        $matched = false;
        foreach (OWNER_GOVERNANCE_DESIGN_ONLY_PATH_PREFIXES as $prefix) {
            if (str_starts_with($file, $prefix)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return false;
        }
    }

    return true;
}

/**
 * Gate-ordering enforcement (Task 9, revised by OWN-2026-005).
 *
 * Scans $governedDocFiles (docs/superpowers/plans/*.md + .../specs/*.md by
 * default, or explicit CLI targets) and enforces that any file declaring
 * governance frontmatter (work_id/owner_governance_version/owner_gate_2_record)
 * resolves to a real, correctly-referenced Gate 2 packet for that work_id.
 *
 * OWN-2026-005 principle: `owner_gate_2_record` is a REFERENCE to the Gate 2
 * packet, not proof by itself that Gate 2 is approved — a design spec must be
 * allowed to reference its OWN work_id's Gate 2 packet while that packet is
 * still `awaiting_owner`, because the spec and the packet are exactly the
 * pair of documents being presented to the owner FOR that decision. This is
 * gated strictly: the exemption applies ONLY when (a) the referenced Gate 2
 * packet's gate_status is exactly 'awaiting_owner' (never 'preparing',
 * 'changes_requested', 'deferred', 'superseded', or any other non-approved
 * status — those still fail unconditionally, matching a rejected/declined
 * decision), AND (b) $changedFiles is provided and EVERY changed file in the
 * current submission is a governance/design document (see
 * owner_governance_changed_files_are_design_only()) — any application code,
 * tooling, CI, or test file present in the diff forfeits the exemption and
 * the packet must still be approved. When $changedFiles is null (no
 * diff-scope information available — e.g. a local run, or a `push` event),
 * behavior is IDENTICAL to before this correction: gate-2-not-approved still
 * fires unconditionally. Every other rule (missing packet, wrong path,
 * cross-work-id mismatch, missing frontmatter) is completely unaffected by
 * $changedFiles and continues to fail exactly as before in every case.
 *
 * @param array<int, string> $governedDocFiles
 * @param array<int, string> $legacyIds
 * @param array<int, string>|null $changedFiles
 * @return OwnerGovernanceLintViolation[]
 */
function owner_governance_enforce_gate_ordering(
    array $governedDocFiles,
    bool $scanningExplicitFiles,
    string $repoRoot,
    array $legacyIds,
    ?array $changedFiles = null
): array {
    $orderingViolations = [];

    foreach ($governedDocFiles as $docFile) {
        $basename = basename($docFile);
        $content = file_get_contents($docFile);

        // Correction 3: frontmatter is the PRIMARY, authoritative source.
        // Filename regex is FALLBACK-ONLY, and only ever used to identify
        // a document as legacy — never to identify a NEW work_id.
        $workId = null;
        $hasGovernanceFrontmatter = false;
        $docFm = null;
        if (preg_match('/^---\n(.*?)\n---\n/s', $content, $fmMatch)) {
            $docFm = Yaml::parse($fmMatch[1]);
            if (is_array($docFm) && isset($docFm['work_id'])) {
                $workId = $docFm['work_id'];
                $hasGovernanceFrontmatter = isset($docFm['owner_governance_version'], $docFm['owner_gate_2_record']);
            }
        }

        if ($workId === null) {
            // No frontmatter work_id — fall back to filename regex, but
            // ONLY to check whether this is a pre-existing legacy file.
            if (preg_match('/\b(GAP-\d{3}|OWN-\d{4}-\d{3})\b/', $basename, $idMatch)) {
                $filenameWorkId = $idMatch[1];
                if (in_array($filenameWorkId, $legacyIds, true)) {
                    continue; // Genuinely legacy — filename-only reference is acceptable for pre-existing documents.
                }
                // Not on the legacy list and has no frontmatter: this is either a
                // new document that should have declared frontmatter and didn't,
                // or a filename coincidentally containing an ID pattern. Either
                // way, per Correction 3, filename text alone cannot establish a
                // NEW work_id's governance status — this is exactly the case
                // Correction 3 exists to close.
                $orderingViolations[] = new OwnerGovernanceLintViolation(
                    $basename,
                    'missing-governance-frontmatter',
                    "File references '{$filenameWorkId}' in its filename but is not on the legacy exemption list and has no governance frontmatter (work_id/owner_governance_version/owner_gate_2_record). New governed work must declare frontmatter explicitly — see docs/owner-governance/GOVERNED_DOCUMENT_FRONTMATTER.md."
                );
                continue;
            }

            if (!$scanningExplicitFiles) {
                // Bulk directory scan: a document with neither frontmatter nor a
                // filename-recognizable work-ID token is unidentifiable by this
                // lint — not this check's concern (matches original behavior for
                // truly unrelated docs, and for the pre-existing corpus whose
                // filenames don't carry a recognizable token, e.g. GAP-031's
                // real plan/spec files which use lowercase "gap031").
                continue;
            }

            // Explicitly targeted file, with neither frontmatter nor a
            // filename-recognizable work-ID token: still fails. An explicit
            // target is a deliberate request to check this document's
            // governance — filename-regex fallback exists only to recognize
            // LEGACY documents, never to excuse an explicitly-checked document
            // from needing frontmatter.
            $orderingViolations[] = new OwnerGovernanceLintViolation(
                $basename,
                'missing-governance-frontmatter',
                'File has no governance frontmatter (work_id/owner_governance_version/owner_gate_2_record) and no recognizable work-ID token in its filename either. New governed work must declare frontmatter explicitly — see docs/owner-governance/GOVERNED_DOCUMENT_FRONTMATTER.md.'
            );
            continue;
        }

        if (in_array($workId, $legacyIds, true)) {
            continue; // Explicitly exempt, even if it also has frontmatter.
        }

        if (!$hasGovernanceFrontmatter) {
            $orderingViolations[] = new OwnerGovernanceLintViolation(
                $basename,
                'incomplete-governance-frontmatter',
                "work_id '{$workId}' is declared but owner_governance_version and/or owner_gate_2_record is missing."
            );
            continue;
        }

        $gate2Path = $repoRoot . "/docs/owner-decisions/{$workId}/02-design.md";
        if (!is_file($gate2Path)) {
            $orderingViolations[] = new OwnerGovernanceLintViolation($basename, 'gate-2-before-plan', "Document declares work_id '{$workId}', which has no approved Gate 2 packet ({$gate2Path} missing) and is not in the legacy exemption list.");
            continue;
        }

        $declaredGate2Path = $repoRoot . '/' . $docFm['owner_gate_2_record'];
        if (realpath($declaredGate2Path) !== realpath($gate2Path)) {
            $orderingViolations[] = new OwnerGovernanceLintViolation($basename, 'gate2-record-mismatch', "owner_gate_2_record ('{$docFm['owner_gate_2_record']}') does not match the derived path for work_id '{$workId}' ('docs/owner-decisions/{$workId}/02-design.md').");
        }

        $gate2 = Yaml::parse(preg_replace('/^---\n(.*?)\n---\n.*$/s', '$1', file_get_contents($gate2Path)));
        $gate2Status = $gate2['gate_status'] ?? null;
        $gate2Decision = $gate2['owner_decision']['value'] ?? null;
        if ($gate2Decision !== 'approved') {
            // OWN-2026-005 design-only exemption: see this function's docblock.
            $isDesignOnlySubmission = $gate2Status === 'awaiting_owner'
                && $changedFiles !== null
                && owner_governance_changed_files_are_design_only($changedFiles);

            if (!$isDesignOnlySubmission) {
                $orderingViolations[] = new OwnerGovernanceLintViolation($basename, 'gate-2-not-approved', "Document declares work_id '{$workId}', whose Gate 2 packet exists but owner_decision.value is not 'approved'.");
            }
        }
    }

    return $orderingViolations;
}

/**
 * Counts checks that are neither 'pass' nor 'skipping' (GitHub CLI's `bucket`
 * field from `gh pr checks --json name,state,bucket`) — i.e. pending or
 * failed. Extracted as a pure, unit-testable function so
 * scripts/ci/check-evidence-freshness.sh's "is current-head CI green" logic
 * isn't duplicated inline and untestable inside a bash `php -r` snippet.
 *
 * @param array<int, array{name: string, state?: string, bucket?: string}> $checks
 */
function owner_governance_count_blocking_checks(array $checks): int
{
    return count(array_filter(
        $checks,
        fn ($c) => ($c['bucket'] ?? '') !== 'pass' && ($c['bucket'] ?? '') !== 'skipping'
    ));
}

// --- CLI entrypoint ---
// Guards against running when this file is require/require_once'd as a library
// (OwnerGovernanceLintFixtureTest.php, and check-evidence-freshness.sh's `php -r`
// snippets, need owner_governance_compute_implementation_tree_digest() and
// owner_governance_count_blocking_checks() without triggering a directory
// scan + exit()). `PHP_SAPI === 'cli'` is true for every CLI process
// including `php artisan test`, so it must NOT be used here — only an exact match
// against the actually-invoked script path is safe.
if (realpath($argv[0] ?? '') === __FILE__) {
    $repoRoot = dirname(__DIR__, 2);
    $schema = Yaml::parseFile($repoRoot . '/docs/owner-governance/packet-schema.yml');

    // Flags (arguments starting with "--") are never scan targets — filter them
    // out before deciding whether $targets is empty, so `--enforce-gate-ordering`
    // passed alone still falls through to the default docs/owner-decisions scan
    // instead of silently scanning zero files.
    $targets = array_values(array_filter(array_slice($argv, 1), fn ($arg) => !str_starts_with($arg, '--')));
    $targetsExplicit = $targets !== [];
    if ($targets === []) {
        $targets = [$repoRoot . '/docs/owner-decisions'];
    }

    $files = [];
    foreach ($targets as $target) {
        if (is_dir($target)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->getExtension() === 'md') {
                    $files[] = $fileInfo->getPathname();
                }
            }
        } elseif (is_file($target)) {
            $files[] = $target;
        }
    }

    $allViolations = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $relative = str_starts_with($file, $repoRoot) ? ltrim(substr($file, strlen($repoRoot)), '/') : $file;
        foreach (owner_governance_validate_packet($relative, $content, $schema) as $violation) {
            $allViolations[] = $violation;
        }
    }

    $isGithubActions = (getenv('GITHUB_ACTIONS') === 'true');
    foreach ($allViolations as $violation) {
        if ($isGithubActions) {
            echo "::error file={$violation->file}::owner-governance-lint [{$violation->rule}]: {$violation->message}\n";
        } else {
            echo "{$violation->file} [{$violation->rule}]: {$violation->message}\n";
        }
    }

    // Structural failure no longer exits immediately: --enforce-gate-ordering
    // (Task 9) may target a governed-document fixture that is deliberately
    // NOT a Gate packet (e.g. a Correction-3 spec/plan frontmatter fixture),
    // which structural validation correctly rejects with its own 'frontmatter'
    // rule. Both checks run and report independently; the final exit code
    // reflects either one failing, so no diagnostic is ever silently skipped.
    $structuralFailed = $allViolations !== [];
    if ($structuralFailed) {
        printf("\n❌ owner-governance-lint FAIL (%d violation(s) across %d file(s) scanned)\n", count($allViolations), count($files));
    } else {
        printf("✅ owner-governance-lint PASS (%d file(s) scanned, 0 violations)\n", count($files));
    }

    $orderingFailed = false;

    // --- Gate-ordering enforcement (Task 9), only runs with --enforce-gate-ordering ---
    if (in_array('--enforce-gate-ordering', $argv, true)) {
        $boundary = Yaml::parseFile($repoRoot . '/docs/owner-governance/enforcement-boundary.yml');
        $legacyIds = array_filter(
            array_map('trim', file($repoRoot . '/' . $boundary['legacy_exemption_file'])),
            fn ($line) => $line !== '' && !str_starts_with($line, '#')
        );

        // With no explicit file/dir targets on the CLI, scan the standard
        // governed-document directories (docs/superpowers/plans, .../specs).
        // With explicit targets (e.g. running this directly against a
        // Correction-3 fixture, as Task 9's verification step does), check
        // exactly those files instead — an explicit target is a deliberate
        // request to evaluate that document's governance, so unlike a bulk
        // directory scan it is never silently skipped for lacking a
        // filename-recognizable work-ID token (see below).
        $explicitFileTargets = $targetsExplicit ? array_values(array_filter($targets, 'is_file')) : [];
        $governedDocFiles = $explicitFileTargets !== []
            ? $explicitFileTargets
            : array_merge(
                glob($repoRoot . '/docs/superpowers/plans/*.md'),
                glob($repoRoot . '/docs/superpowers/specs/*.md')
            );
        $scanningExplicitFiles = $explicitFileTargets !== [];

        // OWN-2026-005: --changed-files=<comma-separated relative paths>
        // carries this submission's diff scope (e.g. computed by CI from
        // `gh pr view --json files` on a pull_request event). Absent this
        // flag, $changedFiles stays null and owner_governance_enforce_gate_ordering()
        // behaves exactly as before this correction — no exemption without
        // explicit, positive evidence of what changed.
        $changedFiles = null;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--changed-files=')) {
                $changedFiles = array_values(array_filter(
                    array_map('trim', explode(',', substr($arg, strlen('--changed-files=')))),
                    fn ($f) => $f !== ''
                ));
                break;
            }
        }

        $orderingViolations = owner_governance_enforce_gate_ordering(
            $governedDocFiles,
            $scanningExplicitFiles,
            $repoRoot,
            $legacyIds,
            $changedFiles
        );

        foreach ($orderingViolations as $violation) {
            echo ($isGithubActions ? "::error file={$violation->file}::" : '') . "owner-governance-lint [{$violation->rule}]: {$violation->message}\n";
        }
        if ($orderingViolations !== []) {
            printf("\n❌ owner-governance-lint --enforce-gate-ordering FAIL (%d violation(s))\n", count($orderingViolations));
            $orderingFailed = true;
        } else {
            echo "✅ owner-governance-lint --enforce-gate-ordering PASS\n";
        }
    }

    exit(($structuralFailed || $orderingFailed) ? 1 : 0);
}
