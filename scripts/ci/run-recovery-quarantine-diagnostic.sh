#!/usr/bin/env bash
# UAT-RECOVERY-01 — NON-BLOCKING diagnostic for the two quarantined tests.
# Runs ONLY the group `uat-recovery-01-quarantine` (a CLI --group overrides the phpunit.xml exclusion), prints the
# CURRENT failures and writes a report. It always exits 0 so it can never gate a pipeline; the report states whether the
# quarantined tests still fail (expected) or have started to pass (quarantine can then be removed — see QUARANTINE.md).
# Usage: scripts/ci/run-recovery-quarantine-diagnostic.sh [output-file]
set -uo pipefail
cd "$(dirname "$0")/../.."
OUT="${1:-storage/logs/recovery-quarantine-diagnostic.txt}"
mkdir -p "$(dirname "$OUT")"
{
  echo "UAT-RECOVERY-01 quarantine diagnostic — $(date -u +%Y-%m-%dT%H:%M:%SZ) — $(git rev-parse --short HEAD 2>/dev/null)"
  echo "Command: ./vendor/bin/phpunit --group uat-recovery-01-quarantine --testdox"
  echo "------------------------------------------------------------"
  ./vendor/bin/phpunit --group uat-recovery-01-quarantine --testdox 2>&1 | grep -vE 'Warning|Referenced from|Reason:|^$'
  echo "------------------------------------------------------------"
} | tee "$OUT"
echo "Expected today: 2 tests, 2 failures (open Owner decisions O-2/O-8). Report: $OUT"
exit 0
