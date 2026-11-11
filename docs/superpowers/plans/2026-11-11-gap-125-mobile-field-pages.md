---
work_id: GAP-125
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-125/02-design.md
---

# GAP-125 — Phone layout for the field pages: implementation plan

Executes Gate 2 C-1, D-1 (`docs/owner-decisions/GAP-125/02-design.md`). Only files in the allowlist change.

1. **Red test** `tests/Feature/Mobile/FieldPagesMobileLayoutTest.php`:
   - diary cards with the submit / approve forms by status;
   - inspection cards;
   - NCR cards;
   - the desktop tables still present;
   - the inspection detail's collapsible sections (`data-phone-collapse`) and "Thông tin" marked first, with the forms
     intact.
2. **Partials:**
   - `site-diaries/_cards`, `inspections/_cards`, `inspections/_ncr-cards`, placed in `operator-phone-only`;
   - the tables wrapped in `operator-wide-only`.
3. **Inspection detail:**
   - `operator-phone-first` on "Thông tin";
   - `<details open data-phone-collapse>` sections;
   - a small inline script closes them below 768 px.
4. **CSS** in `operator.css`.
5. **Green;** full suite; lints.
6. **Playwright:**
   - iPhone 13: no overflowing table; inspection detail height;
   - desktop: before / after pixel compare.
7. **Gate 3 packet.**
