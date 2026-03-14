# UNKNOWNS TO VERIFY WITH TERMINAL

These items should be treated as runtime UNKNOWN until Terminal or GitHub proves them.

1. **Which checks are currently required by branch protection**
   - `gh pr view --json statusCheckRollup`

2. **Whether `ci-cd.yml` is still an enforced lane or mostly adjacent/legacy**
   - `gh workflow view ci-cd.yml`
   - `gh workflow view automated-testing.yml`

3. **Which merge strategies are enabled in the repo**
   - `gh repo view --json defaultBranchRef,mergeCommitAllowed,rebaseMergeAllowed,squashMergeAllowed`

4. **Current recent run hotspots**
   - `gh run list --limit 20`

5. **Fresh skip inventory**
   - `php scripts/ssot/collect_skip_inventory.php --tests-dir tests --inventory-out /tmp/skip_inventory.txt`

6. **Fresh orphan-route inventory**
   - `bash scripts/ssot/dump_routes.sh && php scripts/ssot/find_orphan_test_routes.php --report-file=/tmp/orphans.txt`
