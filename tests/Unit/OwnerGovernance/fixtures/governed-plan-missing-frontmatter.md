# A plan file with NO governance frontmatter at all, for a non-legacy work item.

This must fail --enforce-gate-ordering with rule 'missing-governance-frontmatter',
not be silently treated as legacy just because it has no recognizable ID in its
filename (its filename here is deliberately generic, matching neither the
GAP-NNN nor OWN-YYYY-NNN pattern, to prove filename-regex fallback does not
rescue a document that should have had frontmatter and didn't).
