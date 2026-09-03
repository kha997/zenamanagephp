#!/usr/bin/env bash
# GAP-049 disposable queue-canary drill: proves a REAL async worker process
# completes a REAL queued job. Not run by CI. Run manually against a
# disposable environment with a real database/redis queue connection.
set -euo pipefail

QUEUE_CONNECTION="${1:-database}"

echo "[drill] Starting queue:work worker in the background (connection=${QUEUE_CONNECTION})..."
php artisan queue:work "$QUEUE_CONNECTION" --once --timeout=15 &
WORKER_PID=$!

echo "[drill] Running deploy:queue-canary..."
set +e
php artisan deploy:queue-canary --timeout=15
RESULT=$?
set -e

wait "$WORKER_PID" 2>/dev/null || true

if [ "$RESULT" -eq 0 ]; then
  echo "[drill] PASS — a real worker processed the probe job."
else
  echo "[drill] FAIL — exit code ${RESULT}. If QUEUE_CONNECTION=sync was used, this is expected (by design) and does not count as evidence."
fi

exit "$RESULT"
