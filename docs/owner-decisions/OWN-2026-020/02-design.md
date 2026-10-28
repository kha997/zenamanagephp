---
work_id: OWN-2026-020
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-28-own-2026-020-register-roadmap-reconciliation.md
  plan: null
  branch: docs/OWN-2026-020-register-roadmap-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/377
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-28T16:23:20+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-28, verbatim: 'APPROVE OWN-2026-020 Gate 2 Option A'. Reviewed design head: e0f6922b27fe8a8c6e543f77c7edf8494ddfd618. Approves Option A and its exact allowlist (OPERATIONAL_GAP_REGISTER.md: 13 retroactive RESOLVED rows, GAP-012/013/014b/065 resolved in place, GAP-014 parent and GAP-031 notes, header line; docs/roadmap/canonical-roadmap.md status section; reconciliation record docs/audits/2026-10-28-own-2026-020-reconciliation-record.md; after merge close issue #244 and draft PR #245 with a comment, #248/#257 untouched); no GAP-* packet edit; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-28T12:47:52+07:00"
  updated_at: "2026-10-28T16:23:20+07:00"
generated_by: agent
---

# OWN-2026-020 — Register and roadmap reconciliation: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-28 against reviewed design head
`e0f6922b27fe8a8c6e543f77c7edf8494ddfd618`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Sửa sổ GAP, ghi chú GAP-031, thêm phần "Tình trạng 28/10/2026" vào đầu roadmap, và sau khi merge thì đóng 2 mục GitHub đã
xong. **Chỉ sửa tài liệu**, không đổi code. Đề xuất **Phương án A**.

Điểm Owner cần quyết:

1. **GAP-031:** đề xuất **không sửa hồ sơ** (đây là ví dụ mẫu của quy trình, nằm trong danh sách miễn trừ), chỉ ghi vào
   sổ rằng Owner đã duyệt Gate 3 trong mô tả PR #238 và PR đã merge (`29f7ea5e`).
2. **GitHub:** đóng issue #244 (Ngân quỹ S1–S6 đã xong) và PR nháp #245 (bản spec Ngân quỹ cũ, đã được thay bằng thiết kế
   GAP-037); **giữ nguyên** issue #248 và PR nháp #257 (One-Page, việc tương lai).

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| **A. Sổ + ghi chú GAP-031 + roadmap + đóng #244/#245** | như thiết kế dưới đây | **Đề xuất** |
| B. Như A, nhưng tạo `GAP-031/03-release-v3.md` ghi lại lần duyệt cũ | Hồ sơ "khớp" hơn | Không đề xuất: lời duyệt gốc nằm ngoài repo (ChatGPT), không có văn bản nguyên văn; hồ sơ mẫu có SHA/digest giả nên không thể ràng buộc thật |
| C. Chỉ sửa sổ | Không roadmap, không GitHub | Roadmap và issue #244 tiếp tục lệch |

## Thiết kế: Phương án A

### 1. `OPERATIONAL_GAP_REGISTER.md`

**Dòng mới (RESOLVED)** — cùng contract với OWN-2026-019: `| GAP-0xx | <tiêu đề ngắn> | **RESOLVED (verified
2026-10-28)** — Released to main via PR #N at squash SHA <full>. Owner-bound Gate-3 approval: implementation subject
<full>, implementation-tree digest <full>; neither is recomputed or rebound by OWN-2026-020. Not deployed. | Gate 1/2/3
packets; PR URL | Registered retroactively by OWN-2026-020. |`. Subject và digest lấy nguyên văn từ
`technical_evidence` của `docs/owner-decisions/<ID>/03-release.md`; squash SHA từ `git log origin/main`.

| ID | PR | Squash SHA | Tier |
|---|---|---|---|
| GAP-035 (route name trùng chặn `route:cache`) | #261 | `ed4444e15249ffaac067d76d4f48c82945e4b6bc` | 7 |
| GAP-037 (Ngân quỹ: thiết kế/schema v17, chỉ tài liệu) | #263 | `dbe662972755493f675970e30022083622a9f066` | 5 |
| GAP-038 (Ngân quỹ: CHECK constraint thật trong DB) | #265 | `cb5cb893d92b8ef0534672d7f5c7bfe35062eb64` | 5 |
| GAP-039 (CI trung thực SQLite / MySQL thật) | #268 | `87a4307fdcf8117d8cac4b11c2cb27cb637ada5a` | 7 |
| GAP-046 (nền tảng Service Line) | #292 | `87bb7d36128f878d8b6291705fed2c4262b11819` | 3 |
| GAP-047 (Owner Governance Lint, 2 lỗi) | #291 | `f36cfaf7dc4100ed41cc7f7262ba81fdef7bd99a` | 7 |
| GAP-048 (phân loại CRM: UX và cổng) | #295 | `a790758978dc9ded439a823860ecdb1ee8e9bee9` | 3 |
| GAP-049 (làm chắc quy trình deploy production) | #302 | `475e30eeb549042649b3871d175225fff80bdb11` | 2 |
| GAP-050 (cô lập test bất biến MySQL) | #305 | `9585eed9b701cd37a7188789d1754206545ad274` | 7 |
| GAP-094 (Ngân quỹ S4b-1 thanh toán qua tuyến) | #373 | `de952191e9f533cec95ea6c0ecdf960ef7f83a0c` | 5 |
| GAP-095 (Ngân quỹ S4b-2 truy vết thanh toán HĐ) | #374 | `c7b8efffa06a4b81ca98134b1870ebde3bd59cc9` | 5 |
| GAP-096 (Ngân quỹ S5 tạm ứng và quyết toán) | #375 | `129f3f390704a23447229a72cf5d057c9d0174c6` | 5 |
| GAP-097 (Ngân quỹ S6 dashboard và báo cáo) | #376 | `2b336bced728c5bc0c77ec185ac946e8a10964bf` | 5 |

Ghi chú riêng trong cột cuối: GAP-037 chỉ tài liệu; GAP-049 merge với một ngoại lệ CI MySQL-parity do Owner cho phép (ghi
trong hồ sơ của nó), và "Not deployed" vẫn đúng.

**Dòng sửa tại chỗ** (ghi chú cũ giữ lại, đánh dấu "trước OWN-2026-020"):

- **GAP-012** → RESOLVED — PR #370, squash `588daf8e1a881d500e12801572933cd025de4d8e`, subject/digest từ packet.
- **GAP-013** → RESOLVED — PR #371, squash `681043875ecda4c0cd19a19908c2dfbb41031194`.
- **GAP-014b** → RESOLVED — PR #372, squash `740b84500a805805bea4f9a81758ea6ea2691a30`.
- **GAP-065** → RESOLVED — PR #338, squash `2fa819d0bbe240174c5ada93a1f8b5f84664a3d4`.
- **GAP-014 (cha)** → giữ `PARTIALLY RESOLVED`; ghi chú: 014a, 014b đã đóng, chỉ còn GAP-014c.
- **GAP-031** → trạng thái giữ RESOLVED; thêm ghi chú: Gate 3 do Owner duyệt (ghi trong mô tả PR #238, head
  `b11c8c3a87e4f50d6a4c9e82b52368a26e667b37`), merge 2026-08-05 thành `29f7ea5e1a46b8d8bb60c2c8c0a8c1dda23cf101`;
  `03-release-v2.md` vẫn ghi "chờ Owner" vì là ví dụ mẫu miễn trừ, không sửa.
- Dòng mô tả đầu sổ: thêm câu "Đối soát gần nhất: OWN-2026-020 (2026-10-28)".

### 2. Bản ghi đối soát

`docs/audits/2026-10-28-own-2026-020-reconciliation-record.md` (front matter `work_id: OWN-2026-020`,
`owner_governance_version: 1`, `owner_gate_2_record: docs/owner-decisions/OWN-2026-020/02-design.md`): với mỗi dòng mới
và dòng sửa — PR, squash SHA, subject, digest; SHA-256 của `03-release*.md` trên `origin/main` (hồ sơ lịch sử không bị
đổi bởi Work ID này). Kèm bảng GitHub dự kiến (mục 4).

### 3. `docs/roadmap/canonical-roadmap.md`

Thêm một mục **"Status as of 2026-10-28"** ngay dưới phần đầu, đổi "Last updated" sang 2026-10-28. Nội dung:

- Đã phát hành từ 03/2026 (tóm tắt theo nhóm, có Work ID): Ngân quỹ S1–S6; thông báo CR / Submittal / NCR; dọn route chết;
  bảo mật phụ thuộc và cổng CI; Docker / deploy; quản trị Owner.
- Việc còn mở, theo thứ tự đề xuất: GAP-021, 018, 020 → 10 file test cùng kiểu GAP-065 + chặn rò dữ liệu cho cả bộ test →
  GAP-014c → GAP-015, 019, 026, 030 → mở rộng Ngân quỹ (phần ngoài phạm vi S1–S6) hoặc One-Page (#248).
- Ghi rõ: các mục bên dưới là bối cảnh 03/2026, giữ làm lịch sử.

`docs/roadmap/backlog.yaml` **không sửa** (29/29 mục `done`; việc mới theo register).

### 4. GitHub (chỉ sau khi PR này merge)

- Issue #244: comment liệt kê GAP-063 … 097 đã phát hành, rồi **đóng (completed)**.
- PR nháp #245: comment "spec đã được thay bằng thiết kế GAP-037 v17 trên main; nhánh giữ nguyên", rồi **đóng không
  merge**. Không xoá nhánh.
- Issue #248, PR nháp #257: **không đụng**.

## Allowlist và digest

`OPERATIONAL_GAP_REGISTER.md`; `docs/roadmap/canonical-roadmap.md`; bản ghi đối soát mới; Gate-1 evidence và 01/02 của
OWN-2026-020 (trong digest); `03-release.md` tự loại. Không sửa packet GAP-* nào.

## Verification trước Gate 3

Diff chỉ gồm allowlist; governance lint (gồm gate ordering) và docs lint; `tests/Unit/OwnerGovernance`; SHA-256 các packet
lịch sử không đổi; mọi SHA trong sổ khớp `git log origin/main`; exact-head CI xanh.

## Rollback

Revert squash commit; mở lại #244/#245 nếu cần.

## Explicit Exclusions

Sửa code / test / workflow; sửa packet GAP-* (kể cả GAP-031); đổi trạng thái các dòng OPEN khác (014c, 015, 018 … 021, 026,
030); sửa `backlog.yaml`; đụng #248/#257; xoá nhánh; deploy.

## Decision Needed

Owner chọn: `APPROVE OWN-2026-020 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge hay deploy; không chọn việc tiếp theo trong roadmap (thứ tự chỉ là đề xuất).
