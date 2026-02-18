#!/usr/bin/env bash
set -euo pipefail

github_actions=${GITHUB_ACTIONS:-}
github_step_summary=${GITHUB_STEP_SUMMARY:-}

route_count=0
rg_count=0
total_violations=0

violations_file=$(mktemp -t docs-lint.XXXXXX 2>/dev/null || mktemp)
trap 'rm -f "$violations_file"' EXIT

is_github_actions() {
  [ "${github_actions:-}" = "1" ] || [ "${github_actions:-}" = "true" ]
}

open_group() {
  if is_github_actions; then
    echo "::group::Docs lint"
  fi
}

close_group() {
  if is_github_actions; then
    echo "::endgroup::"
  fi
}

sanitize_snippet() {
  printf '%s' "$1" | tr '\n' ' ' | tr '\t' ' ' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' | cut -c1-200
}

emit_annotation() {
  file=$1
  line=$2
  label=$3
  snippet=$4
  echo "::error file=${file},line=${line}::Docs lint: ${label} — ${snippet}"
}

record_violation() {
  rule_label=$1
  path=$2
  line=$3
  raw_content=$4

  snippet=$(sanitize_snippet "$raw_content")
  if [ -z "$snippet" ]; then
    snippet="(empty line)"
  fi

  printf '%s\t%s\t%s\n' "$path" "$line" "$snippet" >> "$violations_file"
  total_violations=$((total_violations + 1))

  if is_github_actions; then
    emit_annotation "$path" "$line" "$rule_label" "$snippet"
  fi
}

append_job_summary() {
  [ -n "$github_step_summary" ] || return 0

  {
    echo "## Docs lint summary"
    if [ "$total_violations" -gt 0 ]; then
      echo "- ❌ docs-lint FAIL (${total_violations} violation(s))"
    else
      echo "- ✅ docs-lint PASS (0 violations)"
    fi
    echo "- Rule results:"
    print_rule_summary "php artisan route:list --columns" "$route_count"
    print_rule_summary "vendor/bin/rg" "$rg_count"
    if [ "$total_violations" -gt 0 ]; then
      echo "- Violations (first 20):"
      head -n 20 "$violations_file" | while IFS= read -r entry; do
        file=$(printf '%s' "$entry" | cut -f1)
        line=$(printf '%s' "$entry" | cut -f2)
        snippet=$(printf '%s' "$entry" | cut -f3-)
        printf '  - %s:%s — %s\n' "$file" "$line" "$snippet"
      done
      if [ "$total_violations" -gt 20 ]; then
        echo "  - ...and $((total_violations - 20)) more"
      fi
    fi
  } >> "$github_step_summary"
}

print_rule_summary() {
  label=$1
  count=$2
  status=PASS
  if [ "$count" -gt 0 ]; then
    status=FAIL
  fi
  printf '  - %s: %s (%s violation(s))\n' "$label" "$status" "$count"
}

print_rule_result() {
  label=$1
  count=$2
  status=PASS
  if [ "$count" -gt 0 ]; then
    status=FAIL
  fi
  printf ' - %s: %s (%s violation(s))\n' "$label" "$status" "$count"
}

scan_route_columns() {
  file=$1
  grep -nF -- "php artisan route:list" "$file" 2>/dev/null || true
}

scan_vendor_rg() {
  file=$1
  grep -nF -- "vendor/bin/rg" "$file" 2>/dev/null || true
}

open_group
echo "docs-lint: scanning tracked Markdown files for unsupported commands..."

while IFS= read -r md_file; do
  [ -z "$md_file" ] && continue
  [ -f "$md_file" ] || continue

  while IFS= read -r match_line; do
    line_number=${match_line%%:*}
    line_content=${match_line#*:}
    if printf '%s' "$line_content" | grep -q -- '--columns'; then
      route_count=$((route_count + 1))
      record_violation "php artisan route:list --columns" "$md_file" "$line_number" "$line_content"
    fi
  done < <(scan_route_columns "$md_file")

  while IFS= read -r match_line; do
    line_number=${match_line%%:*}
    line_content=${match_line#*:}
    rg_count=$((rg_count + 1))
    record_violation "vendor/bin/rg" "$md_file" "$line_number" "$line_content"
  done < <(scan_vendor_rg "$md_file")
done < <(git ls-files -- '*.md')

printf '\nDocs lint results:\n'
print_rule_result "php artisan route:list --columns" "$route_count"
print_rule_result "vendor/bin/rg" "$rg_count"

if [ "$total_violations" -gt 0 ]; then
  printf '\nViolations (%s):\n' "$total_violations"
  while IFS= read -r entry; do
    file=$(printf '%s' "$entry" | cut -f1)
    line=$(printf '%s' "$entry" | cut -f2)
    snippet=$(printf '%s' "$entry" | cut -f3-)
    printf '  - %s:%s — %s\n' "$file" "$line" "$snippet"
  done < "$violations_file"
else
  echo
  echo "Docs lint found no forbidden tooling usage."
fi

printf '\n'
if [ "$total_violations" -gt 0 ]; then
  printf '❌ docs-lint FAIL (%s violations)\n' "$total_violations"
  exit_code=1
else
  printf '✅ docs-lint PASS (0 violations)\n'
  exit_code=0
fi

append_job_summary
close_group

exit "$exit_code"
