#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p scripts/ssot/baselines

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

HARD_BASE="scripts/ssot/baselines/hardcoded_api_paths.txt"
DENY_BASE="scripts/ssot/baselines/denylist_hits.txt"
RAW_BASE="scripts/ssot/baselines/raw_user_create.txt"
RAW_MODEL_BASE="scripts/ssot/baselines/raw_model_create.txt"
RAW_MODEL_FEATURE_BASE="scripts/ssot/baselines/raw_model_create_feature.txt"
RAW_MODEL_INTEGRATION_BASE="scripts/ssot/baselines/raw_model_create_integration.txt"
RAW_MODEL_ZENA_BASE="scripts/ssot/baselines/raw_model_create_zena.txt"
SKIP_BASE="scripts/ssot/baselines/skipped_tests_baseline.txt"
DENY_FILE="scripts/ssot/denylist_endpoints.txt"
ALLOW_FILE="scripts/ssot/allowlist_endpoints.txt"
UPDATE_BASELINES="${SSOT_UPDATE_BASELINES:-0}"

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

  local raw_file="$TMP_DIR/hardcoded_raw.txt"

  # Strict SSOT check: API v1/Zena hardcoded paths in Feature Api/Zena suites only.
  rg -n -S "['\"]/api/(v1|zena)/" \
    tests/Feature/Api tests/Feature/Zena 2>/dev/null \
    | rg -v "ssot-allow-hardcode" \
    | sort -u > "$raw_file" || true

  while IFS= read -r line; do
    [[ -z "$line" ]] && continue

    # Ignore non-request contexts (e.g., regex fixtures / docs strings).
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
}

collect_denylist_hits() {
  local out_file="$1"
  : > "$out_file"

  [[ -f "$DENY_FILE" ]] || return 0

  while IFS= read -r pattern; do
    [[ -z "$pattern" || "$pattern" =~ ^# ]] && continue
    rg -n -F "$pattern" tests 2>/dev/null || true
  done < "$DENY_FILE" | sort -u | while IFS= read -r line; do
    [[ -z "$line" ]] && continue

    local matched_path
    matched_path="$(echo "$line" | rg -o -m1 "/api[^'\" )]*" || true)"
    if [[ -n "$matched_path" ]] && is_allowlisted_path "$matched_path"; then
      continue
    fi

    local deny_pattern
    deny_pattern="$(echo "$line" | rg -o -m1 "/api[^'\" )]*" || true)"
    if [[ -n "$deny_pattern" ]] && is_allowlisted_path "$deny_pattern"; then
      continue
    fi

    echo "$line"
  done | sort -u > "$out_file"
}

collect_raw_user() {
  local out_file="$1"
  : > "$out_file"

  rg -n -S "User::create\(" tests/Feature tests/Integration 2>/dev/null \
    | rg -v "ssot-allow-raw-user" \
    | sort -u > "$out_file" || true
}

collect_raw_model_create() {
  local out_file="$1"
  : > "$out_file"

  rg -n -S "[A-Z][A-Za-z0-9_]*::create\(" tests/Feature/Api 2>/dev/null \
    | rg -v "Factory::create\(" \
    | rg -v "ssot-allow-raw-model" \
    | rg -v "User::create\(" \
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

collect_raw_model_create_zena() {
  local out_file="$1"
  : > "$out_file"

  rg -n -S "(User|Tenant|Role|Permission|Project)::create\(" tests/Feature/Zena 2>/dev/null \
    | rg -v "ssot-allow-raw-model-zena" \
    | sort -u > "$out_file" || true
}

collect_skip_sources() {
  local out_file="$1"
  : > "$out_file"

  rg -n -S "markTestSkipped\(|\$this->markTestSkipped\(|->markTestSkipped\(" tests 2>/dev/null \
    | sort -u > "$out_file" || true

  # Optional Pest-style fluent skips; ignore collection pagination like ->skip(1).
  rg -n -S "->skip\(" tests 2>/dev/null \
    | rg -e "test\(|it\(|->group\(" \
    | sort -u >> "$out_file" || true

  sort -u -o "$out_file" "$out_file"
}

ensure_baseline() {
  local base_file="$1"
  [[ -f "$base_file" ]] || : > "$base_file"
}

check_with_baseline() {
  local label="$1"
  local current_file="$2"
  local baseline_file="$3"
  local fail=0

  ensure_baseline "$baseline_file"

  if [[ "$UPDATE_BASELINES" == "1" ]]; then
    cp "$current_file" "$baseline_file"
    echo "[ssot] baseline updated: $baseline_file"
    return 0
  fi

  local new_file="$TMP_DIR/new_${label}.txt"
  comm -13 "$baseline_file" "$current_file" > "$new_file" || true

  if [[ -s "$new_file" ]]; then
    echo ""
    echo "[ssot] NEW ${label} violations (not in baseline):"
    cat "$new_file"
    fail=1
  fi

  return $fail
}

check_exact_baseline() {
  local label="$1"
  local current_file="$2"
  local baseline_file="$3"
  local fail=0

  ensure_baseline "$baseline_file"

  local current_count baseline_count
  current_count="$(wc -l < "$current_file" | tr -d ' ')"
  baseline_count="$(wc -l < "$baseline_file" | tr -d ' ')"

  if [[ "$current_count" != "$baseline_count" ]]; then
    echo ""
    echo "[ssot] ${label} count mismatch: current=${current_count}, baseline=${baseline_count}"
    fail=1
  fi

  local missing_file="$TMP_DIR/missing_${label}.txt"
  local extra_file="$TMP_DIR/extra_${label}.txt"
  comm -23 "$baseline_file" "$current_file" > "$missing_file" || true
  comm -13 "$baseline_file" "$current_file" > "$extra_file" || true

  if [[ -s "$missing_file" ]]; then
    echo ""
    echo "[ssot] ${label} entries missing from current inventory:"
    cat "$missing_file"
    fail=1
  fi

  if [[ -s "$extra_file" ]]; then
    echo ""
    echo "[ssot] ${label} entries missing from baseline:"
    cat "$extra_file"
    fail=1
  fi

  return $fail
}

HARD_CUR="$TMP_DIR/hardcoded.txt"
DENY_CUR="$TMP_DIR/denylist.txt"
RAW_CUR="$TMP_DIR/raw_user.txt"
RAW_MODEL_CUR="$TMP_DIR/raw_model.txt"
RAW_MODEL_FEATURE_CUR="$TMP_DIR/raw_model_feature.txt"
RAW_MODEL_INTEGRATION_CUR="$TMP_DIR/raw_model_integration.txt"
RAW_MODEL_ZENA_CUR="$TMP_DIR/raw_model_zena.txt"
SKIP_SOURCES_CUR="$TMP_DIR/skip_sources.txt"
SKIP_INVENTORY_CUR="$TMP_DIR/skip_inventory.txt"
SKIP_VIOLATIONS_CUR="$TMP_DIR/skip_contract_violations.txt"

collect_hardcoded "$HARD_CUR"
collect_denylist_hits "$DENY_CUR"
collect_raw_user "$RAW_CUR"
collect_raw_model_create "$RAW_MODEL_CUR"
collect_raw_model_feature "$RAW_MODEL_FEATURE_CUR"
collect_raw_model_integration "$RAW_MODEL_INTEGRATION_CUR"
collect_raw_model_create_zena "$RAW_MODEL_ZENA_CUR"
collect_skip_sources "$SKIP_SOURCES_CUR"

php scripts/ssot/collect_skip_inventory.php \
  --tests-dir tests \
  --sources-out "$SKIP_SOURCES_CUR" \
  --inventory-out "$SKIP_INVENTORY_CUR" \
  --violations-out "$SKIP_VIOLATIONS_CUR"

status=0
check_with_baseline "hardcoded_api_paths" "$HARD_CUR" "$HARD_BASE" || status=1
check_with_baseline "denylist_hits" "$DENY_CUR" "$DENY_BASE" || status=1
check_with_baseline "raw_user_create" "$RAW_CUR" "$RAW_BASE" || status=1
check_with_baseline "raw_model_create" "$RAW_MODEL_CUR" "$RAW_MODEL_BASE" || status=1
check_with_baseline "raw_model_create_feature" "$RAW_MODEL_FEATURE_CUR" "$RAW_MODEL_FEATURE_BASE" || status=1
check_with_baseline "raw_model_create_integration" "$RAW_MODEL_INTEGRATION_CUR" "$RAW_MODEL_INTEGRATION_BASE" || status=1
check_with_baseline "raw_model_create_zena" "$RAW_MODEL_ZENA_CUR" "$RAW_MODEL_ZENA_BASE" || status=1
check_exact_baseline "skipped_tests_inventory" "$SKIP_INVENTORY_CUR" "$SKIP_BASE" || status=1

if [[ -s "$SKIP_VIOLATIONS_CUR" ]]; then
  echo ""
  echo "[ssot] Skip contract violations:"
  cat "$SKIP_VIOLATIONS_CUR"
  status=1
fi

if [[ "$status" -ne 0 ]]; then
  exit 1
fi

echo "SSOT test lint passed (no new violations beyond baseline)."
