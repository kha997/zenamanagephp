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

        // --- Evidence-binding contract (Correction 2) ---
        // awaiting_owner / approved must never coexist with a non-ready readiness.
        if (in_array($gateStatus, ['awaiting_owner', 'approved'], true) && $trValue !== 'ready') {
            $violations[] = new OwnerGovernanceLintViolation(
                $filePath,
                'not-ready-but-decision-eligible',
                "gate_status is '{$gateStatus}' but technical_readiness.value is '{$trValue}', not 'ready'."
            );
        }

        // Once a real decision is recorded, the binding must be populated and must
        // match the packet's own technical_evidence exactly — this is the
        // structural half of staleness detection (the live half — comparing
        // against the ACTUAL current branch HEAD/CI state rather than just
        // internal self-consistency — is scripts/ci/check-evidence-freshness.sh,
        // Task 9 Step 3b, since that requires `git`/`gh`, not just this file).
        if ($ownerDecisionValue !== 'none') {
            $binding = $fm['owner_decision_binding'] ?? [];
            $evidence = $fm['technical_evidence'] ?? [];

            if (empty($binding['evidence_head_sha']) || empty($binding['evidence_digest'])) {
                $violations[] = new OwnerGovernanceLintViolation(
                    $filePath,
                    'evidence-binding-required-once-decided',
                    "owner_decision.value is '{$ownerDecisionValue}' but owner_decision_binding is not populated — a recorded decision must bind to the evidence it was made against."
                );
            } else {
                if (($binding['evidence_head_sha'] ?? null) !== ($evidence['head_sha'] ?? null)) {
                    $violations[] = new OwnerGovernanceLintViolation(
                        $filePath,
                        'stale-decision-different-head-sha',
                        "owner_decision_binding.evidence_head_sha ('{$binding['evidence_head_sha']}') does not match technical_evidence.head_sha ('{$evidence['head_sha']}') — the decision was recorded against a different commit than the packet now describes."
                    );
                }
                if (($binding['evidence_digest'] ?? null) !== ($evidence['evidence_digest'] ?? null)) {
                    $violations[] = new OwnerGovernanceLintViolation(
                        $filePath,
                        'stale-decision-digest-mismatch',
                        "owner_decision_binding.evidence_digest does not match the current technical_evidence.evidence_digest — the technical evidence has changed since this decision was recorded. Per design: this packet's gate_status must return to 'preparing' or 'blocked_technical', not stay 'approved'/'awaiting_owner'. The lint reports this; it does not rewrite the file itself."
                    );
                }
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
 * Computes the evidence digest per packet-schema.yml's evidence_digest_algorithm:
 * sha256( head_sha + "\n" + sorted-joined check names + "\n" + sorted-joined conclusions ).
 * $checks is an array of ['name' => string, 'conclusion' => string] pairs, e.g. from
 * `gh pr checks <PR> --json name,state` — sorted by name here so the digest is
 * deterministic regardless of the order the caller supplies them in.
 */
function owner_governance_compute_evidence_digest(string $headSha, array $checks): string
{
    usort($checks, fn ($a, $b) => $a['name'] <=> $b['name']);
    $names = implode(',', array_column($checks, 'name'));
    $conclusions = implode(',', array_column($checks, 'conclusion'));

    return hash('sha256', $headSha . "\n" . $names . "\n" . $conclusions);
}

// --- CLI entrypoint ---
// Guards against running when this file is require/require_once'd as a library
// (OwnerGovernanceLintFixtureTest.php, and check-evidence-freshness.sh's `php -r`
// snippet both need owner_governance_compute_evidence_digest() without triggering
// a directory scan + exit()). `PHP_SAPI === 'cli'` is true for every CLI process
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

    if ($allViolations !== []) {
        printf("\n❌ owner-governance-lint FAIL (%d violation(s) across %d file(s) scanned)\n", count($allViolations), count($files));
        exit(1);
    }

    printf("✅ owner-governance-lint PASS (%d file(s) scanned, 0 violations)\n", count($files));
    exit(0);
}
