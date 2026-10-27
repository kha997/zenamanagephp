# GAP-088 — Evidence: Docker builds fail when pecl.php.net is flaky

Base: `e14bdaa0b0b9439032ee64e81fb23094234cc093` (main after GAP-087).

## Failures seen

Docker Security Scan builds `Dockerfile.prod`. It failed twice on PR #360 (GAP-087) at the same `RUN` step,
`Dockerfile.prod:55-79`:

| Head | Message from `pecl install redis` | Result on re-run |
|---|---|---|
| `ef8121a4` | `No releases available for package "pecl.php.net/redis"` | green |
| `518907a0` | `Package "redis" Version "6.3.0" does not have REST xml available` | green |

- Both failures came after a stall of about 22 seconds in the PECL step.
- The same tree passed on other heads of the same PR: `5150c0ea` and `f72d44b5`.
- So the failure follows pecl.php.net's availability, not the code. The build has no retry, and one bad
  response fails the whole image build.

## Where redis is installed from PECL

| File | Line | Command | Version | Built by |
|---|---|---|---|---|
| `Dockerfile.prod` | 76 | `pecl install redis` inside the extension `RUN` | unpinned (6.3.0 today) | `code-quality-security.yml` (every PR and push), `automated-deployment.yml` ×4, `release-management.yml` ×2 |
| `Dockerfile.websocket` | 37 | `pecl install redis && docker-php-ext-enable redis` | unpinned | `release-management.yml` ×1 |
| `Dockerfile` | 43 | `pecl install redis && docker-php-ext-enable redis` | unpinned | `docker-compose.prod.yml`, `docker-compose.loadbalancer.yml` (no workflow) |

`pecl install redis` without a version always takes the newest release. Builds on different days can
therefore get different phpredis versions.

## Impact

- **PRs:** one PECL failure makes Docker Security Scan red, which blocks the PR until someone re-runs the job.
  Main is affected the same way.
- **Releases and deploys:** they would fail the same way when PECL is flaky.

## Notes for Gate 2

Possible approaches to compare:
- retry the PECL install with a back-off;
- pin the phpredis version;
- build phpredis from a pinned source tarball instead of pecl.php.net.

Gate 2 must also decide whether to change all three Dockerfiles or only `Dockerfile.prod`.
