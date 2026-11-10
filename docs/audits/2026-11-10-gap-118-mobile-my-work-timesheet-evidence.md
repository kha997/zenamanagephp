# GAP-118 — Phone layout for "Việc của tôi" and "Bảng giờ": discovery evidence

Date: 2026-11-10. Base: `main` at `153709a8ebf451e49c9b3283739ac91c31be222b` (after GAP-117).

## 1. Method

- **App:** run locally on SQLite with built assets (`npm run build`).
- **Demo data:** one site engineer (`task.view`, `project.view`), two projects and four tasks assigned to them.
- **Device:** Playwright emulating an iPhone 13 (390 × 844 CSS px).
- **Pages:** `/app/my-work` and `/app/time` (`time/timesheet`).
- **Output:** screenshots plus measured page geometry. The screenshots were taken in the session scratchpad and are not
  committed; the numbers are below.

## 2. Findings

### F-1 — The whole menu sits above the content on every page

Both pages use `layouts/operator`. At ≤1024px:
- the sidebar becomes `position: static; width: 100%` (`resources/css/operator.css`, `@media (max-width: 1024px)`);
- the full menu (12 entries for this user) is rendered above the page;
- the page content starts **1,050 px** from the top on both pages, more than one full phone screen;
- the whole first screen is menu, and the user must scroll past it to reach the timer.

### F-2 — The top bar takes a lot of space

The top bar is sticky. On a phone it shows:
- the line "Z.E.N.A — Procurement operator surface";
- the page title;
- an empty search box squeezed to a small square;
- the user name wrapped over three lines;
- "Đăng xuất".

It stays about 120 px tall on every scroll position.

### F-3 — Wide tables are cut off

| Page | Table | Observed on the phone |
| --- | --- | --- |
| Bảng giờ | "Các dòng giờ" (8 columns) | Scroll width **547 px** in a **308 px** box. Duration, status, note and the edit / delete actions are off-screen; the only cue is a sideways scroll inside the card. |
| Việc của tôi | `_workload-items-table` | Columns squeezed to one or two words per line. The due date is cut off at the right edge. |

### F-4 — The "Start timer" buttons are far down

- On "Việc của tôi" the "Bắt đầu" (start timer) buttons are in a separate "Ghi giờ" card below the task table.
- On a phone that card starts at about **1,950 px** (menu + table).
- The buttons are small inline links with the task name squeezed next to them.

### F-5 — What already works

- No horizontal page scroll: page width = viewport width = 390 px.
- The viewport meta tag is set.
- Forms stack into one column: "Đồng hồ", "Nhập giờ tay".
- The daily-total strip (T2–CN) fits.

## 3. Scope notes for Gate 2

- `layouts/operator` is shared by about 106 pages. Any menu or top-bar change (F-1, F-2) affects all of them on small
  screens. Desktop (>1024px) should stay unchanged.
- Main daily users on a phone:
  - site staff: timer, own tasks;
  - PM: approving hours (`time/review`), a later candidate.
- No JavaScript framework is in the layout today; it uses small vanilla scripts and Tailwind/CSS.

## 4. Open points for Gate 2

- **Menu on a phone:**
  - collapsed behind a "Menu" button;
  - or a bottom tab bar with the few daily pages plus "Thêm";
  - or both.
- **Top bar:** compact on a phone (title + menu button + user initials); search in the menu or hidden.
- **Tables on these two pages:**
  - card list on a phone (one card per task / time entry);
  - or keep the table with a visible scroll cue.
- **Timer:** a "Bắt đầu" button on each task card, and a sticky "Đang ghi giờ … Dừng" bar while a timer runs.
- Whether "Duyệt giờ" (PM review) is in this Work ID or later.
- How to test:
  - feature tests on the markup;
  - Playwright screenshots / geometry checks at 390px in the test suite or CI.

## 5. Exclusions

- Native app.
- Offline mode.
- PWA / push notifications.
- Pages other than the agreed ones, beyond the shared layout.
- Desktop redesign.
- Deploy.
