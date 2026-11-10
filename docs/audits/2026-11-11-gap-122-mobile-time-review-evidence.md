# GAP-122 — Phone layout for "Duyệt giờ": discovery evidence

Date: 2026-11-11. Base: `main` at `41e87a441477db5c23938a86ad6a1cd8b8ecf5c0` (after GAP-121).

## 1. Method

- **App:** run locally on SQLite with built assets.
- **Demo data:**
  - a PM (`ks@demo.test`) of one project;
  - two workers;
  - six submitted entries in the current week on that project (timer and manual, with and without notes).
- **Device:** Playwright emulating an iPhone 13 (390 × 844 CSS px). Desktop reference: 1440 × 900.
- **Page:** `/app/time/review` (`resources/views/time/review.blade.php`, GAP-112).
- **Output:** screenshots plus measured page geometry. The screenshots were taken in the session scratchpad and are not
  committed; the numbers are below.

## 2. What GAP-118 already gives this page

The page uses `layouts/operator`, so below 1024px it already has:
- the "Menu" button instead of the full sidebar;
- the compact top bar;
- no horizontal page scroll: page width = viewport width.

GAP-118 converted only "Việc của tôi" and "Bảng giờ" to phone cards. "Duyệt giờ" still renders its table.

## 3. Findings (phone)

### F-1 — The table is cut off

- 7 columns: tick, person, date, task, project, duration, note.
- Scroll width **492 px** in a **276 px** box.
- Visible: tick, person, date, task (names and task labels wrap one word per line).
- **Off-screen: project, duration and note** — exactly what the reviewer needs to decide. The only cue is a sideways
  scroll inside the card.

### F-2 — The page is long

- Six entries make the page **1,533 px** tall (2.3 phone screens).
- Each row is about 200 px high because of the wrapping.

### F-3 — Small tick boxes

- The tick boxes are **16 × 16 px**.
- The usual touch target is about 44 px.

### F-4 — Bulk actions only, everything ticked by default

- Every row starts ticked.
- One form holds both actions: "Duyệt các dòng đã chọn" and "Lý do từ chối" + "Từ chối".
- The buttons sit at the bottom, about **1,300 px** down.
- To reject one entry on a phone, the reviewer must:
  1. scroll through the list and untick every other row (16 px boxes);
  2. scroll to the bottom;
  3. type a reason;
  4. press "Từ chối".
- A missed untick rejects or approves the wrong entries.

### F-5 — Week navigation buttons wrap

"← Tuần trước", "Tuần sau →" and "Bảng giờ của tôi" each wrap to two lines on the phone. They still work.

### F-6 — Desktop

- The table fits: 1030 px in 1030 px.
- No issue found; it should stay unchanged.

## 4. Behaviour that must not change

- `TimeReviewPageController::act`:
  - takes `ids[]`, `action` (approve / reject), `reason` (required for reject) and `week`;
  - `TimeReviewService` checks each entry with `canReview` and the month lock.
- The month-end reminder (GAP-120) links to `app.time.review?week=…`.

## 5. Open points for Gate 2

- **Card layout.** One card per entry below 768px, using the GAP-118 classes: person, date, task, project, duration
  (prominent), note and badges.
- **Per-entry actions** on the card ("Duyệt" / "Từ chối" with a reason), in addition to or instead of the bulk form, and
  whether rows stay ticked by default on the phone.
- **Grouping by person,** with a subtotal per person (helps the reviewer check one worker's week at a glance).
- **Touch sizes** for the tick boxes and buttons, and the week navigation wrap.

## 6. Exclusions

- Review rules, permissions, the month lock.
- The desktop table.
- Other pages.
- Deploy.
