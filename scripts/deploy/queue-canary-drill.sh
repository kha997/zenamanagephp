#!/usr/bin/env bash
# GAP-049 disposable queue-canary drill: proves a REAL async worker process
# completes a REAL queued job. Not run by CI. Run manually against a
# disposable environment with a real database/redis queue connection.
set -euo pipefail

QUEUE_CONNECTION="${1:-database}"

# NOTE: this deliberately does NOT use `queue:work --once`. `--once` checks
# the queue exactly one time and exits immediately if it's empty at that
# instant — which races against deploy:queue-canary's own dispatch (started
# a moment later, below): if the worker's single check lands before the
# probe job is inserted, it exits having seen nothing, and the canary then
# times out even though the queue infrastructure itself is perfectly healthy
# (reproduced directly during GAP-049 Task 11 verification). `--max-jobs=1`
# gives the same "process exactly one job then stop" termination guarantee,
# but by looping/sleeping between empty checks instead of exiting on the
# first one, so the worker is still there when the job actually lands.
echo "[drill] Starting queue:work worker in the background (connection=${QUEUE_CONNECTION})..."
php artisan queue:work "$QUEUE_CONNECTION" --max-jobs=1 --timeout=15 &
WORKER_PID=$!

echo "[drill] Running deploy:queue-canary..."
set +e
php artisan deploy:queue-canary --timeout=15
RESULT=$?
set -e

# The worker exits on its own once it has processed exactly one job
# (--max-jobs=1). Wait briefly for that natural exit; if it's still running
# after the canary already finished (e.g. the canary timed out with no
# worker ever picking up the job), kill it explicitly so this script never
# leaves a background process running past its own exit.
wait "$WORKER_PID" 2>/dev/null || true
if kill -0 "$WORKER_PID" 2>/dev/null; then
  kill "$WORKER_PID" 2>/dev/null || true
fi

if [ "$RESULT" -eq 0 ]; then
  echo "[drill] PASS — a real worker processed the probe job."
else
  echo "[drill] FAIL — exit code ${RESULT}. If QUEUE_CONNECTION=sync was used, this is expected (by design) and does not count as evidence."
fi

exit "$RESULT"
