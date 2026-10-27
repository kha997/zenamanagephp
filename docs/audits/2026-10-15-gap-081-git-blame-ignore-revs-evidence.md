# GAP-081 — Evidence: `git blame` points at the GAP-080 reformat

Base: `40d19ab10f51c77587127f2ab5c477724f4ab7cf` (main after GAP-080).

## Finding

GAP-080 merged as one squash commit, `40d19ab10f51c77587127f2ab5c477724f4ab7cf`
(1709 files, +27740/−23839), of which 1694 PHP files are a mechanical
php-cs-fixer reformat. Since then `git blame` on most PHP lines shows that
commit instead of the change that last altered the code.

The repository has no `.git-blame-ignore-revs`. GitHub's blame view reads that
file from the repository root automatically; locally it is enabled with
`git config blame.ignoreRevsFile .git-blame-ignore-revs`.

## Caveat

The squash commit also contains a few non-formatting changes (fixer config,
code-quality workflow step, `scripts/ci/fetch-pr-changed-files.sh`, the AST
check script, baseline line numbers, GAP-080 docs). Ignoring the commit in
blame attributes those lines to their previous version too; `git log` still
shows them.
