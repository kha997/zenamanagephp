#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p storage/logs

ORPHAN_TMP="$(mktemp)"
DENY_TMP="$(mktemp)"
LEGACY_TMP="$(mktemp)"
EXPERIMENTAL_TMP="$(mktemp)"
SLOW_TMP="$(mktemp)"
FILES_TMP="$(mktemp)"
RAW_USER_TMP="$(mktemp)"
RAW_MODEL_FEATURE_TMP="$(mktemp)"
RAW_MODEL_INTEGRATION_TMP="$(mktemp)"
HARD_TMP="$(mktemp)"
SKIP_INVENTORY_TMP="$(mktemp)"
SKIP_VIOLATIONS_TMP="$(mktemp)"
SKIP_SOURCES_TMP="$(mktemp)"
RAW_USER_TOP_TMP="$(mktemp)"
RAW_MODEL_FEATURE_TOP_TMP="$(mktemp)"
NEW_VS_BASE_TMP="$(mktemp)"
REPORT="storage/logs/ssot_obsolete_tests.md"
CANONICAL_REPORT="scripts/ssot/ssot_obsolete_tests.md"
LEGACY_ENRICHED_TMP="$(mktemp)"
trap 'rm -f "$ORPHAN_TMP" "$DENY_TMP" "$LEGACY_TMP" "$EXPERIMENTAL_TMP" "$SLOW_TMP" "$FILES_TMP" "$LEGACY_ENRICHED_TMP" "$RAW_USER_TMP" "$RAW_MODEL_FEATURE_TMP" "$RAW_MODEL_INTEGRATION_TMP" "$HARD_TMP" "$SKIP_INVENTORY_TMP" "$SKIP_VIOLATIONS_TMP" "$SKIP_SOURCES_TMP" "$RAW_USER_TOP_TMP" "$RAW_MODEL_FEATURE_TOP_TMP" "$NEW_VS_BASE_TMP"' EXIT

HARD_BASE="scripts/ssot/baselines/hardcoded_api_paths.txt"
DENY_BASE="scripts/ssot/baselines/denylist_hits.txt"
RAW_USER_BASE="scripts/ssot/baselines/raw_user_create.txt"
RAW_MODEL_FEATURE_BASE="scripts/ssot/baselines/raw_model_create_feature.txt"
RAW_MODEL_INTEGRATION_BASE="scripts/ssot/baselines/raw_model_create_integration.txt"
SKIP_BASE="scripts/ssot/baselines/skipped_tests_baseline.txt"
ALLOW_FILE="scripts/ssot/allowlist_endpoints.txt"

is_allowlisted_path() {
  local path="$1"

  [[ -f "$ALLOW_FILE" ]] || return 1

  while IFS= read -r pattern; do
    pattern="${pattern%%#*}"
    pattern="$(echo "$pattern" | xargs)"
    [[ -z "$pattern" ]] && continue

    if [[ "$path" == $pattern ]]; then
      return 0
    fi
  done < "$ALLOW_FILE"

  return 1
}

collect_hardcoded() {
  local out_file="$1"
  : > "$out_file"

  local raw_file
  raw_file="$(mktemp)"

  rg -n -S "['\"]/api/(v1|zena)/" \
    tests/Feature/Api tests/Feature/Zena 2>/dev/null \
    | rg -v "ssot-allow-hardcode" \
    | sort -u > "$raw_file" || true

  while IFS= read -r line; do
    [[ -z "$line" ]] && continue
    if ! [[ "$line" =~ (getJson|postJson|putJson|deleteJson|patchJson|get\(|post\(|put\(|delete\(|patch\(|apiGet|apiPost|apiPut|apiDelete|apiPatch|apiPostMultipart|zenaGet|zenaPost|zenaPut|zenaDelete|zenaPatch)\( ]]; then
      continue
    fi

    local path
    path="$(echo "$line" | rg -o -m1 "/api/(v1|zena)/[^'\" )]*" || true)"
    [[ -z "$path" ]] && continue

    if is_allowlisted_path "$path"; then
      continue
    fi

    echo "$line" >> "$out_file"
  done < "$raw_file"

  rm -f "$raw_file"
}

collect_raw_user() {
  local out_file="$1"
  : > "$out_file"

  rg -n -S "User::create\(" tests/Feature tests/Integration 2>/dev/null \
    | rg -v "ssot-allow-raw-user" \
    | sort -u > "$out_file" || true
}

collect_raw_model_feature() {
  local out_file="$1"
  : > "$out_file"

  rg -n -S "(User|Tenant|Role|Permission|Project)::create\(" tests/Feature 2>/dev/null \
    | rg -v "^tests/Feature/Zena/" \
    | rg -v "ssot-allow-raw-model" \
    | sort -u > "$out_file" || true
}

collect_raw_model_integration() {
  local out_file="$1"
  : > "$out_file"

  rg -n -S "(User|Tenant|Role|Permission|Project)::create\(" tests/Integration 2>/dev/null \
    | rg -v "ssot-allow-raw-model" \
    | sort -u > "$out_file" || true
}

top_offenders_by_file() {
  local in_file="$1"
  local out_file="$2"
  local limit="${3:-15}"

  : > "$out_file"
  [[ -s "$in_file" ]] || return 0

  awk -F: '{print $1}' "$in_file" \
    | sort \
    | uniq -c \
    | sort -k1,1nr -k2,2 \
    | head -n "$limit" \
    | awk '{count=$1; $1=""; sub(/^ +/,""); print $0 "|" count}' > "$out_file"
}

emit_new_vs_baseline() {
  local label="$1"
  local current_file="$2"
  local baseline_file="$3"

  [[ -f "$baseline_file" ]] || : > "$baseline_file"
  local new_file
  new_file="$(mktemp)"
  comm -13 "$baseline_file" "$current_file" > "$new_file" || true
  local total
  total="$(wc -l < "$current_file" | tr -d ' ')"
  local added
  added="$(wc -l < "$new_file" | tr -d ' ')"
  echo "$label|$total|$added"
  rm -f "$new_file"
}

php scripts/ssot/find_orphan_test_routes.php --report-only > "$ORPHAN_TMP"

while IFS= read -r pattern; do
  [[ -z "$pattern" || "$pattern" =~ ^# ]] && continue
  rg -n -F "$pattern" tests || true
done < scripts/ssot/denylist_endpoints.txt | sort -u > "$DENY_TMP"

rg --files tests/Legacy tests/Feature/Legacy 2>/dev/null | sort -u > "$LEGACY_TMP" || true
rg -n -S "debug_api\.php|/api/debug|EXPERIMENTAL|experimental" tests 2>/dev/null | sort -u > "$EXPERIMENTAL_TMP" || true
rg -n -S "@group (slow|stress|load)|RUN_SLOW_TESTS|RUN_STRESS_TESTS|RUN_LOAD_TESTS" tests 2>/dev/null | sort -u > "$SLOW_TMP" || true

collect_hardcoded "$HARD_TMP"
collect_raw_user "$RAW_USER_TMP"
collect_raw_model_feature "$RAW_MODEL_FEATURE_TMP"
collect_raw_model_integration "$RAW_MODEL_INTEGRATION_TMP"
top_offenders_by_file "$RAW_USER_TMP" "$RAW_USER_TOP_TMP" 15
top_offenders_by_file "$RAW_MODEL_FEATURE_TMP" "$RAW_MODEL_FEATURE_TOP_TMP" 15

php scripts/ssot/collect_skip_inventory.php \
  --tests-dir tests \
  --sources-out "$SKIP_SOURCES_TMP" \
  --inventory-out "$SKIP_INVENTORY_TMP" \
  --violations-out "$SKIP_VIOLATIONS_TMP"

{
  emit_new_vs_baseline "hardcoded_api_paths" "$HARD_TMP" "$HARD_BASE"
  emit_new_vs_baseline "denylist_hits" "$DENY_TMP" "$DENY_BASE"
  emit_new_vs_baseline "raw_user_create" "$RAW_USER_TMP" "$RAW_USER_BASE"
  emit_new_vs_baseline "raw_model_create_feature" "$RAW_MODEL_FEATURE_TMP" "$RAW_MODEL_FEATURE_BASE"
  emit_new_vs_baseline "raw_model_create_integration" "$RAW_MODEL_INTEGRATION_TMP" "$RAW_MODEL_INTEGRATION_BASE"
  emit_new_vs_baseline "skipped_tests_inventory" "$SKIP_INVENTORY_TMP" "$SKIP_BASE"
} | sort -u > "$NEW_VS_BASE_TMP"

php -r '
$root = getcwd();
$orphanFile = $argv[1];
$routesFile = $argv[2];
$outFile = $argv[3];
$rows = @json_decode((string) @file_get_contents($routesFile), true);
if (!is_array($rows)) {
    $rows = [];
}

$routes = [];
foreach ($rows as $row) {
    $uri = trim((string) ($row["uri"] ?? ""));
    if ($uri === "") {
        continue;
    }
    $routes[] = [
        "uri" => trim($uri, "/"),
        "regex" => "#^/" . preg_replace("/\\\{[^}]+\\\}/", "[^/]+", preg_quote(trim($uri, "/"), "#")) . "$#",
        "method" => strtoupper((string) ($row["method"] ?? "")),
        "name" => (string) ($row["name"] ?? ""),
    ];
}

$lines = @file($orphanFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$out = [];
foreach ($lines as $line) {
    if (!preg_match("/^(tests\\/[^:]+:\\d+) -> \\/([^ ]+) => (ORPHAN|LEGACY)$/", $line, $m)) {
        continue;
    }

    $fileLine = $m[1];
    $path = $m[2];
    $class = $m[3];
    [$file, $lineNum] = explode(":", $fileLine, 2);
    $lineNum = (int) $lineNum;

    $method = "GET";
    $sourceLine = "";
    if (is_file($root . "/" . $file)) {
        $fileLines = @file($root . "/" . $file, FILE_IGNORE_NEW_LINES) ?: [];
        $sourceLine = (string) ($fileLines[$lineNum - 1] ?? "");
    }
    if (preg_match("/\\bpost(Json)?\\(|\\bapiPost|\\bzenaPost|->post\\(/i", $sourceLine)) $method = "POST";
    elseif (preg_match("/\\bput(Json)?\\(|\\bapiPut|\\bzenaPut|->put\\(/i", $sourceLine)) $method = "PUT";
    elseif (preg_match("/\\bpatch(Json)?\\(|\\bapiPatch|\\bzenaPatch|->patch\\(/i", $sourceLine)) $method = "PATCH";
    elseif (preg_match("/\\bdelete(Json)?\\(|\\bapiDelete|\\bzenaDelete|->delete\\(/i", $sourceLine)) $method = "DELETE";

    $candidate = null;
    $candidateUnnamed = false;
    $needle = "/" . trim($path, "/");
    foreach ($routes as $route) {
        $uri = "/" . $route["uri"];
        if ($uri === $needle || preg_match($route["regex"], $needle) === 1) {
            if ($route["method"] !== "" && strpos($route["method"], $method) === false) {
                continue;
            }
            if ($route["name"] !== "") {
                $candidate = $route["name"];
                break;
            }
            $candidateUnnamed = true;
        }
    }

    $suggested = "none";
    $fixPath = "delete test or mark intentional and move to allow_orphan_routes";
    if ($candidate !== null) {
        $suggested = $candidate;
        $fixPath = "migrate test to named route " . $candidate;
    } elseif ($candidateUnnamed) {
        $suggested = "unnamed existing route";
        $fixPath = "add missing route name, then migrate test to named route";
    } elseif ($class === "LEGACY") {
        $fixPath = "gate with RUN_LEGACY_TESTS and replace/delete after coverage migration";
    }

    $out[] = sprintf(
        "%s | CLASS=%s | METHOD=%s | PATH=/%s | SUGGESTED_ROUTE=%s | SSOT_FIX_PATH=%s",
        $fileLine,
        $class,
        $method,
        $path,
        $suggested,
        $fixPath
    );
}
sort($out);
file_put_contents($outFile, implode(PHP_EOL, $out) . (count($out) ? PHP_EOL : ""));
' "$ORPHAN_TMP" "storage/app/ssot/routes.json" "$LEGACY_ENRICHED_TMP"

{
  echo "# SSOT Obsolete Tests Report"
  echo ""
  echo "## Summary Counts"
  echo ""
  echo "| Metric | Count |"
  echo "| --- | ---: |"
  echo "| raw_user_create | $(wc -l < "$RAW_USER_TMP" | tr -d ' ') |"
  echo "| raw_model_create_feature | $(wc -l < "$RAW_MODEL_FEATURE_TMP" | tr -d ' ') |"
  echo "| raw_model_create_integration | $(wc -l < "$RAW_MODEL_INTEGRATION_TMP" | tr -d ' ') |"
  echo "| hardcoded_api_paths | $(wc -l < "$HARD_TMP" | tr -d ' ') |"
  echo "| denylist_hits | $(wc -l < "$DENY_TMP" | tr -d ' ') |"
  echo "| skip_inventory | $(wc -l < "$SKIP_INVENTORY_TMP" | tr -d ' ') |"
  echo ""

  echo "## RAW CREATE TOP OFFENDERS"
  echo ""
  echo "### raw_user_create (top 15 files)"
  if [[ -s "$RAW_USER_TOP_TMP" ]]; then
    echo "| File | Hits |"
    echo "| --- | ---: |"
    while IFS='|' read -r file hits; do
      [[ -z "$file" ]] && continue
      echo "| $file | $hits |"
    done < "$RAW_USER_TOP_TMP"
  else
    echo "No raw_user_create offenders detected."
  fi
  echo ""

  echo "### raw_model_create_feature (top 15 files)"
  if [[ -s "$RAW_MODEL_FEATURE_TOP_TMP" ]]; then
    echo "| File | Hits |"
    echo "| --- | ---: |"
    while IFS='|' read -r file hits; do
      [[ -z "$file" ]] && continue
      echo "| $file | $hits |"
    done < "$RAW_MODEL_FEATURE_TOP_TMP"
  else
    echo "No raw_model_create_feature offenders detected."
  fi
  echo ""

  echo "## SKIP INVENTORY"
  echo ""
  if [[ -s "$SKIP_INVENTORY_TMP" ]]; then
    echo "| Class::method | Group | Reason Token |"
    echo "| --- | --- | --- |"
    while IFS='|' read -r method group reason; do
      [[ -z "$method" ]] && continue
      echo "| $method | ${group#group=} | ${reason#reason=} |"
    done < "$SKIP_INVENTORY_TMP"
  else
    echo "No skip inventory entries detected."
  fi
  echo ""

  echo "## New Violations vs Baseline"
  echo ""
  echo "| Check | Current | New vs Baseline |"
  echo "| --- | ---: | ---: |"
  while IFS='|' read -r label current added; do
    [[ -z "$label" ]] && continue
    echo "| $label | $current | $added |"
  done < "$NEW_VS_BASE_TMP"
  echo ""

  if [[ -s "$SKIP_VIOLATIONS_TMP" ]]; then
    echo "### Skip Contract Violations"
    echo ""
    echo '```text'
    cat "$SKIP_VIOLATIONS_TMP"
    echo '```'
    echo ""
  fi

  echo "## LEGACY"
  echo ""
  if [[ -s "$LEGACY_ENRICHED_TMP" ]] || [[ -s "$DENY_TMP" ]]; then
    echo '```text'
    cat "$LEGACY_ENRICHED_TMP" || true
    if [[ -s "$DENY_TMP" ]]; then
      echo ""
      echo "[denylist hits]"
      while IFS= read -r hit; do
        [[ -z "$hit" ]] && continue
        endpoint="$(echo "$hit" | rg -o -m1 "/api[^'\" )]*" || true)"
        if [[ -n "$endpoint" ]]; then
          echo "$hit | CLASS=LEGACY | SSOT_FIX_PATH=delete or migrate denied endpoint (allowlist only if intentional)"
        else
          echo "$hit | CLASS=LEGACY | SSOT_FIX_PATH=delete or migrate denied endpoint"
        fi
      done < "$DENY_TMP"
    fi
    echo '```'
  else
    echo "No legacy/orphan references detected."
  fi
  echo ""

  echo "## EXPERIMENTAL"
  echo ""
  if [[ -s "$EXPERIMENTAL_TMP" ]]; then
    echo '```text'
    cat "$EXPERIMENTAL_TMP"
    echo '```'
  else
    echo "No experimental/debug test routes detected."
  fi
  echo ""

  echo "## SLOW"
  echo ""
  if [[ -s "$SLOW_TMP" ]]; then
    echo '```text'
    cat "$SLOW_TMP"
    echo '```'
  else
    echo "No slow/stress/load gated tests detected."
  fi
  echo ""

  echo "## Suggested Actions"
  echo ""
  echo "1. LEGACY: gate with \`RUN_LEGACY_TESTS=1\` and add \`@group legacy\`; delete only after coverage replacement."
  echo "2. EXPERIMENTAL: migrate to named routes or isolate behind explicit opt-in env flags."
  echo "3. SLOW: keep behind \`RUN_SLOW_TESTS/RUN_STRESS_TESTS/RUN_LOAD_TESTS\`; trim duplicated scenarios."
  echo "4. For active API tests, migrate hardcoded \`/api/*\` calls to named routes."
} > "$REPORT"

cp "$REPORT" "$CANONICAL_REPORT"

echo "SSOT obsolete report written to $REPORT"
echo "Canonical SSOT report synced to $CANONICAL_REPORT"
