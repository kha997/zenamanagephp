#!/usr/bin/env bash
set -euo pipefail

# OWN-2026-005: fetches the COMPLETE, verified list of files changed by a
# pull request, as a lossless JSON array, and writes it to stdout.
#
# `gh pr view --json files` is backed by a GraphQL `files(first: 100)`
# query and silently truncates at 100 entries for larger PRs — a file
# changed beyond that cutoff would never be seen, and could be
# misclassified as part of a "design-only" diff it never actually belonged
# to. This script instead uses the paginated REST API
# (`GET /repos/{owner}/{repo}/pulls/{pull_number}/files`, via
# `gh api --paginate`, which auto-merges every page into one combined JSON
# array) and cross-checks the fetched count against the PR's own
# authoritative `changedFiles` scalar (a direct GraphQL Int field, not
# subject to the same 100-item list cap) before trusting the result.
#
# Fails closed (nonzero exit, no usable JSON on stdout) on ANY of:
#   - the authoritative total count cannot be determined;
#   - the paginated REST fetch itself errors;
#   - the fetched list is empty;
#   - the fetched count does not exactly match the authoritative total.
# A caller must never treat a failure here as "no changed files" or
# silently proceed as if the diff were empty/design-only — see
# owner_governance_lint.php's --changed-files-json handling, which requires
# a valid, non-empty JSON array and fails closed on anything else too.
#
# Usage: PR_NUMBER=<n> GH_REPO=<owner>/<repo> bash scripts/ci/fetch-pr-changed-files.sh

: "${PR_NUMBER:?PR_NUMBER env var required}"
: "${GH_REPO:?GH_REPO env var required (e.g. owner/repo)}"

# stdout and stderr are captured SEPARATELY throughout (never merged with
# 2>&1 into the value being parsed) — a tool printing an incidental warning
# to stderr on an otherwise-successful run must never corrupt the count or
# JSON payload this script parses from stdout.
err_file="$(mktemp)"
trap 'rm -f "$err_file"' EXIT

if ! expected_count="$(gh pr view "$PR_NUMBER" --repo "$GH_REPO" --json changedFiles --jq '.changedFiles' 2>"$err_file")"; then
  echo "::error::fetch-pr-changed-files: could not determine PR #${PR_NUMBER}'s authoritative changed-file count via 'gh pr view --json changedFiles' ($(cat "$err_file")). Failing closed." >&2
  exit 1
fi
if ! [[ "$expected_count" =~ ^[0-9]+$ ]]; then
  echo "::error::fetch-pr-changed-files: PR #${PR_NUMBER}'s changedFiles field did not return an integer ('${expected_count}'). Failing closed." >&2
  exit 1
fi
if [ "$expected_count" -eq 0 ]; then
  echo "::error::fetch-pr-changed-files: PR #${PR_NUMBER} reports 0 total changed files — this is not a legitimate state for an open PR and cannot be proven complete. Failing closed." >&2
  exit 1
fi

if ! fetched_json="$(gh api --paginate "repos/${GH_REPO}/pulls/${PR_NUMBER}/files" --jq '[.[].filename]' 2>"$err_file")"; then
  echo "::error::fetch-pr-changed-files: the paginated REST fetch for PR #${PR_NUMBER}'s changed files failed ($(cat "$err_file")). Failing closed." >&2
  exit 1
fi

if ! fetched_count="$(printf '%s' "$fetched_json" | jq 'length' 2>"$err_file")"; then
  echo "::error::fetch-pr-changed-files: the paginated REST fetch for PR #${PR_NUMBER} did not return valid JSON ($(cat "$err_file")). Failing closed." >&2
  exit 1
fi

if [ "$fetched_count" -eq 0 ]; then
  echo "::error::fetch-pr-changed-files: the paginated REST fetch for PR #${PR_NUMBER} returned an empty file list, but the PR reports ${expected_count} changed file(s) — cannot prove completeness. Failing closed." >&2
  exit 1
fi

if [ "$fetched_count" != "$expected_count" ]; then
  echo "::error::fetch-pr-changed-files: changed-file count mismatch for PR #${PR_NUMBER} — fetched ${fetched_count} file(s) via the paginated REST API but the PR reports ${expected_count} total. The fetch may be incomplete. Failing closed." >&2
  exit 1
fi

printf '%s' "$fetched_json"
