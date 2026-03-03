#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 3 ]]; then
  echo "Usage: $0 <report-path> <label> <command...>" >&2
  exit 2
fi

REPORT_PATH="$1"
LABEL="$2"
shift 2

STDERR_PATH="${REPORT_PATH%.json}.stderr.log"

set +e
"$@" >"${REPORT_PATH}" 2>"${STDERR_PATH}"
STATUS=$?
set -e

REPORT_PATH="${REPORT_PATH}" STDERR_PATH="${STDERR_PATH}" LABEL="${LABEL}" STATUS="${STATUS}" php -r '
$reportPath = getenv("REPORT_PATH");
$stderrPath = getenv("STDERR_PATH");
$label = getenv("LABEL");
$status = (int) getenv("STATUS");

$raw = is_file($reportPath) ? file_get_contents($reportPath) : false;
$decoded = null;
$hasValidJson = false;

if ($raw !== false && trim($raw) !== "") {
    $decoded = json_decode($raw, true);
    $hasValidJson = json_last_error() === JSON_ERROR_NONE;
}

if ($hasValidJson) {
    exit(0);
}

$stderrLines = is_file($stderrPath)
    ? array_slice(file($stderrPath, FILE_IGNORE_NEW_LINES) ?: [], -50)
    : [];

$payload = [
    "tool" => $label,
    "status" => $status === 0 ? "ok_without_json_output" : "failed",
    "exit_code" => $status,
    "stderr_log" => basename($stderrPath),
];

if ($raw !== false && trim($raw) !== "") {
    $payload["raw_output_excerpt"] = substr($raw, 0, 4000);
}

if ($stderrLines !== []) {
    $payload["stderr_excerpt"] = $stderrLines;
}

file_put_contents(
    $reportPath,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
);
'

exit "${STATUS}"
