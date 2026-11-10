---
work_id: GAP-118
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-118/02-design.md
---

# GAP-118 — Phone layout for "Việc của tôi" and "Bảng giờ": implementation plan

Executes Gate 2 N-1, H-1, C-1, T-1, R-2, V-1 (`docs/owner-decisions/GAP-118/02-design.md`). Only files in the packet's
allowlist change.

1. **Red:** add `tests/Feature/Mobile/OperatorMobileLayoutTest.php` and confirm it fails. It covers:
   - the mobile menu toggle with the navigation, search, logout and initials;
   - task cards with a Start button;
   - time-entry cards;
   - the running-timer bar only while a timer runs.
2. **N-1 / H-1:** in `layouts/operator.blade.php` and `operator.css`:
   - add a `<details class="operator-mobile-nav">` toggle in the top bar;
   - add the responsive classes;
   - at ≤1024px, hide the sidebar and the long top-bar parts;
   - desktop stays unchanged.
3. **C-1 / T-1:**
   - add `app/_workload-items-cards.blade.php` and `time/_running-bar.blade.php`;
   - in `app/my-work.blade.php` and `time/timesheet.blade.php`, show the cards below 768px and the tables at 768px and
     wider, and add the running-timer bar.
4. **Green:**
   - the new and existing tests pass, including the "no Alpine" guard;
   - `npm run build` succeeds;
   - Playwright measurements at 390px and 1440px;
   - full suites;
   - static checks.
