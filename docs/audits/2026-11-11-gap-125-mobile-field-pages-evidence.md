# GAP-125 — Phone layout for the field pages: discovery evidence

Date: 2026-11-11. Base: `main` at `c47261e36ad8e8f832a47521f2ac63618aa5877c` (after OWN-2026-023).

## 1. Method

- **App:** local SQLite demo with built assets.
- **Demo data:** a PM / site engineer with:
  - three site diaries;
  - one QC plan with one inspection;
  - one NCR;
  - project tasks.
- **Device:** Playwright emulating an iPhone 13 (390 × 844 CSS px, content width 308 px inside the card).
- **Measured:** page height, tables wider than their box, elements past the right edge. Screenshots were taken in the
  session scratchpad and are not committed.

## 2. Findings

| Page | Route | Table width / box | Height | Notes |
|---|---|---|---|---|
| Nhật ký công trường (list) | `operator.site-diaries.index` | **900 / 308 px** | 1,158 px | 7 columns. Only Ngày and Dự án fit; "Công việc" is cut mid-word. Nhân lực, trạng thái, người lập and the "Xem" action are off-screen (39 elements past the edge). |
| Kiểm định (list) | `operator.inspections.index` | **515 / 308 px** | 777 px | 6 columns; ngày, trạng thái and actions are off-screen. |
| Kiểm định (detail) | `operator.inspections.show` | NCR table **607 / 308 px** | **3,082 px** (≈ 4.6 screens) | Sections in order: Mô tả → Kết quả → Hoàn tất kiểm định form → Thông tin → NCR table (title wrapped one word per line, status and "Xem" off-screen) → Ghi nhận NCR mới form. The status and inspector are below the long result texts and the completion form. |
| NCR (detail) | `operator.inspections.ncrs.show` | — | 1,957 px | No overflow. |
| Nhật ký (detail) | `operator.site-diaries.show` | — | 1,611 px | No overflow. |
| Nhật ký (create) | `operator.site-diaries.create` | — | 1,932 px | Form stacks to one column; no overflow. |
| Công việc (detail) | `app.tasks.show` | — | 1,286 px | No overflow. |

**Already in place:**
- the operator layout's phone menu, top bar and notification bell (GAP-118 / GAP-124 pattern);
- the card-list classes from GAP-118 / GAP-122 (`operator-phone-only`, `operator-card-list`, `operator-item-card`).

## 3. Open points for Gate 2

- **Cards for the three cut tables:**
  - diary list;
  - inspection list;
  - the NCR table on the inspection detail.
- **Inspection detail on the phone:** what goes first. For example, status and key facts on top, the long texts
  collapsed, and the completion form behind a button.
- **Whether to touch the pages that already fit:**
  - NCR detail;
  - diary detail and create;
  - task detail.

  Their height comes from long content, not from layout faults.

## 4. Exclusions

- Rules, permissions, data.
- Desktop layout.
- Other modules (RFI, submittals, procurement).
- Deploy.
