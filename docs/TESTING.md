# Testing helpers

## Docs lint

Run `./scripts/ci/docs-lint.sh` to ensure documentation and CI scripts avoid deprecated route:list column flags and direct ripgrep calls from `./vendor/bin`. The script prints offending lines and exits non-zero whenever those patterns reappear, keeping automated docs checks portable.

Always run `./scripts/ci/docs-lint.sh` before committing markdown changes so automation and local work stay aligned.
