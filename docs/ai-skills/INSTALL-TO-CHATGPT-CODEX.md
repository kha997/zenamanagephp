# Install into ChatGPT / Codex

This repo directory is the **versioned source of truth** for the skill pack.
To make the skills usable in product runtime, export or upload them from your local machine into the relevant OpenAI product.

## Practical model

1. Keep the canonical skill text in this repo.
2. Review changes to skills through normal Git PR flow.
3. Upload or recreate the skill pack in ChatGPT Skills and/or Codex.
4. Re-install or update the product-side skills whenever the repo-side source changes materially.

## Why this split exists

The repo is the best place to preserve:
- history
- diffs
- rationale
- review discipline
- team-shared source of truth

The product runtime is where skills are actually installed and invoked.

## Update discipline

When a skill changes in the repo:
- note the exact changed skill(s)
- summarize the behavior change in the PR
- after merge, update the product-installed copy
- keep a short changelog in the PR or release note if the skill materially changes assistant behavior
