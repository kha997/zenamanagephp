# GAP-080 — Evidence: code-style debt flagged by php-cs-fixer

Base: `5202990de850311d43944d2127bd6b0be21de9d7` (main after GAP-079).
Tool: PHP CS Fixer 3.95.27 with the repository's `.php-cs-fixer.php`
(paths `app`, `config`, `database`, `routes`, `tests`; risky rules allowed).

## Scale

`php-cs-fixer fix --dry-run -v --format=json` (no cache): **1696 of 1776**
PHP files would change — app 815, tests 520, database 326, config 28,
routes 7.

## What the fixes are

Mostly whitespace and layout (files affected):
`blank_line_after_opening_tag` 1170 (`<?php declare(...)` on one line),
`no_whitespace_in_blank_line` 578, `phpdoc_summary` 515 (adds a period),
`trailing_comma_in_multiline` 458, `class_attributes_separation` 405,
`ordered_imports` 386, `single_blank_line_at_eof` 343, `braces` 326,
`no_trailing_whitespace_in_comment` 254, `blank_line_before_statement` 233,
`new_with_parentheses` 228, `class_definition` 225, `no_unused_imports` 220,
and smaller counts of others.

Rules that can change more than layout (risky or content-touching):

| Rule | Files | Effect |
|---|---|---|
| `no_trailing_whitespace_in_string` | 33 | Removes trailing spaces inside string literals (mostly multi-line SQL); changes the runtime string value |
| `octal_notation` | 31 | `0755` → `0o755` (same value; PHP ≥ 8.1, project requires 8.2) |
| `phpdoc_to_comment` | 15 | Turns some `/** */` docblocks into `/* */`; can drop `@var` hints tools read |
| `heredoc_indentation` | 10 | Re-indents heredoc bodies (value unchanged when the closing marker moves too) |
| `no_break_comment` | 8 | Adds `// no break` comments |
| `self_accessor` | 7 | Replaces the class's own name with `self` inside it |
| `assign_null_coalescing_to_coalesce_equal`, `ternary_to_null_coalescing` | 21 | Equivalent shorter syntax |

## Why it matters

- The code-quality bot (GAP-075) reports style issues only for files a PR
  touches; because 95% of files are unformatted, nearly every PR that edits
  PHP gets a style comment for issues it did not introduce (GAP-078 and
  GAP-079 both did).
- There is no CI check that fails on style, so the debt does not shrink.

## Constraints

- A one-shot reformat touches ~1700 files: large diff, `git blame` noise
  (mitigated by `.git-blame-ignore-revs`), and conflicts for any open branch
  (none open at the time of writing).
- Risky rules change string contents or docblocks and need separate review.
