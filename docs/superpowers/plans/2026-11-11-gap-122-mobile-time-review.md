---
work_id: GAP-122
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-122/02-design.md
---

# GAP-122 — Phone layout for "Duyệt giờ": implementation plan

Executes Gate 2 L-1, A-1, N-1 (`docs/owner-decisions/GAP-122/02-design.md`). Only files in the packet's allowlist change.

1. **Red test** `tests/Feature/Mobile/TimeReviewMobileLayoutTest.php`:
   - cards per submitted entry, grouped by person with a total per person;
   - per-card approve / reject forms carrying exactly that entry's `ids[]`;
   - a per-person "approve all" form with that person's ids;
   - the desktop table is still there;
   - one-line week navigation;
   - posting a card's approve form approves only that entry;
   - reject without a reason is refused.
2. **Partial** `resources/views/time/_review-cards.blade.php`, plus the wrapper changes in `time/review.blade.php`:
   - wrap the table form in `operator-wide-only`;
   - put the cards in `operator-phone-only`, outside that form;
   - phone week bar.
3. **CSS:** group header and week bar classes in `resources/css/operator.css`.
4. **Green;** then `TimeReviewTest`, the full suite, lints.
5. **Playwright** on the demo app:
   - phone (iPhone 13): no overflowing table, touch targets ≥ 44px;
   - desktop 1440px: compare the screenshot with the pre-change one.
6. **Gate 3 packet.**
