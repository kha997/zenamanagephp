# Header Conflict Report

## Potential Conflicts

1.  **Unused `header-standardized.blade.php`:** The `resources/views/components/shared/header-standardized.blade.php` component is marked as "NOT USED" in several documents. This may indicate a configuration issue or an opportunity for removal.

    **Recommendation:** Investigate whether this component is intended to be used. If not, remove it to reduce codebase complexity.

2.  **Multiple Header Variants:** The existence of legacy headers (`Header.tsx`, `header.blade.php`) alongside the standard headers (`HeaderShell.tsx`, `<x-shared.header-standardized>`) indicates a potential conflict and a need for migration.

    **Recommendation:** Prioritize the migration of legacy headers to the standard components.  Remove legacy components after migration.

3.  **Potential "Double Headers":** Pages that include both a Blade header (e.g., via `<x-shared.header>`) and a React header (e.g., mounting `HeaderShell`) may render duplicate header content.

    **Recommendation:**  Identify and eliminate instances of double headers. Ensure that only one header component is rendered per page.

