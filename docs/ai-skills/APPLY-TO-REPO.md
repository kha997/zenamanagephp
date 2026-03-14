# Apply This Pack to the Live Repo

This artifact is laid out as if it already lives at the repo root.

## Option A — copy the tree into your repo

From your local machine, place the contents of this package at the root of the repo so that:

- `docs/ai-skills/README.md`
- `docs/ai-skills/skills-index.yaml`
- `docs/ai-skills/<skill-name>/SKILL.md`

land exactly under the repo's `docs/` directory.

## Option B — create a dedicated branch

Suggested branch name:

`chore/skills-pack-v1`

Suggested flow:

1. create the branch from `main`
2. copy this `docs/ai-skills/` tree into the repo
3. review the diff carefully
4. open a PR with scope limited to the skill pack

## Recommended review checklist

- No production code changed
- No tests or baselines changed
- All skill files are inside `docs/ai-skills/`
- Skill names and index entries match exactly
- Repo laws are consistent across all skills
- UNKNOWN runtime facts remain marked UNKNOWN
