---
work_id: OWN-2026-021
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-29-own-2026-021-register-reconciliation.md
  plan: null
  branch: docs/OWN-2026-021-register-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/388
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-29T22:18:12+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-29, verbatim: 'APPROVE OWN-2026-021 Gate 2 Option A'. Reviewed design head: 2be5b2e9e9476fd4d08aedb0a13ff138e358bc2a. Approves Option A and its exact allowlist (OPERATIONAL_GAP_REGISTER.md: GAP-021/098/018/020/014c/030 resolved in place, GAP-014 parent resolved, GAP-015 partially resolved, new rows GAP-099/100/101, deploy notes, header line; docs/roadmap/canonical-roadmap.md status section; reconciliation record docs/audits/2026-10-29-own-2026-021-reconciliation-record.md); no GAP-* packet edit; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-29T22:20:00+07:00"
  updated_at: "2026-10-29T22:18:12+07:00"
generated_by: agent
---

# OWN-2026-021 — Register and roadmap reconciliation: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-29 against reviewed design head
`2be5b2e9e9476fd4d08aedb0a13ff138e358bc2a`. This authorizes only the bounded implementation defined by this packet; it
does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Sửa sổ GAP và mục "việc còn mở" của roadmap sau 10 PR #378 – #387. **Chỉ sửa tài liệu**, không đổi code. Đề xuất
**Phương án A**.

Điểm Owner cần quyết: **GAP-015** mới làm phần màn hình thực hiện (Phương án A của nó); phần soạn mẫu chưa làm. Đề xuất ghi
`PARTIALLY RESOLVED` và để phần soạn mẫu là việc tuỳ chọn trong roadmap.

## So sánh phương án

| Phương án | GAP-015 | Kết luận |
|---|---|---|
| **A. GAP-015 = PARTIALLY RESOLVED** | Ghi rõ đã phát hành màn hình thực hiện; còn mở phần soạn mẫu | **Đề xuất** — đúng thực tế, không tạo Work ID mới |
| B. GAP-015 = RESOLVED + dòng mới cho phần soạn mẫu | Cần đặt Work ID mới ngay | Không cần lúc này; tạo khi Owner chọn làm |
| C. Giữ GAP-015 OPEN | — | Không đề xuất: bỏ sót phần đã phát hành |

## Thiết kế: Phương án A

### 1. `OPERATIONAL_GAP_REGISTER.md`

Cùng contract với OWN-2026-019/020. Trạng thái RESOLVED: `**RESOLVED (verified 2026-10-29)** — Released to main via PR
#N at squash SHA <full>. Owner-bound Gate-3 approval: implementation subject <full>, implementation-tree digest <full>;
neither is recomputed or rebound by OWN-2026-021. Not deployed.` Subject và digest lấy nguyên văn từ
`docs/owner-decisions/<ID>/03-release.md` (`technical_evidence.subject_sha`, `owner_decision_binding`); squash SHA từ
`git log origin/main`. Ghi chú cũ giữ lại, đánh dấu "trước OWN-2026-021".

**Sửa tại chỗ:**

| Dòng | PR | Squash SHA | Subject | Digest |
|---|---|---|---|---|
| GAP-021 | #378 | `c1420a3e9b41c94c55fd57465db3ede105dfb660` | `072336afd22ce8e2d3258912b67c860bf6058e55` | `fa9cb97abbf20b5c996c854e7f44c83369510b27477c155359c00ff9192b16f3` |
| GAP-098 | #379 | `8dd80a8422a9cc6da945c5fa0db5b0bec3850482` | `2d478c15e5ce83f9f636aabe58715bdfee8453c0` | `ceff94aace46fbc3cfcd0d6070a83852b2e6f00b7022133284204af3354b0e72` |
| GAP-018 | #381 | `8a676dcc9b8dc0f0a5d593afc147b64c70e5d962` | `aa586f9508b5c137753226172ba30e7ff834fe2f` | `b06ebe8a1fca01f4b7abe86b486bedb2d84390e340d67d11852e87a8c0e082be` |
| GAP-020 | #382 | `01595a7f2df94e2a9eb26d4c97824a306bbb30f8` | `06e23307c8d9f0aa4556a3a0c98320e931f8c31d` | `b19c6573077bfe9c71c403a9f8932b22b85b215b1f1b2fb4823273afa35e14a8` |
| GAP-014c | #385 | `23a909459b8161c613bdceabc6d52029b33d440c` | `7581d465bda59cfd45b8769560b1cf71cf7d87e9` | `b23105c8da1a93c7b6f31ec48aebd358260b56f400279b1f262f71935ba17ffc` |
| GAP-030 | #386 | `2e3ba4b860f1525aa5712fd477614189c9a101f0` | `c73fcf717c2b83e83d3b8abc6d865fe29e128bf8` | `8ab4ed924a03d7fbd5fdb2c22b2c0a403e3f2a3ae6b9d56a999db8cea3a20bf3` |
| GAP-015 → **PARTIALLY RESOLVED** | #387 | `60c3921d07ebeec9e3c1acc639fd76e6826e56d5` | `3940e6d5a5f5e601f86d9c260fe898f577877693` | `b466c1025c8b72c730690591fad81e5eba5b735d5d37f13ca2f8507b3a890b6e` |

- **GAP-014 (cha)** → `RESOLVED`; ghi chú: 014a, 014b, 014c đều đã đóng.
- **GAP-015** ghi chú: đã phát hành màn hình thực hiện WorkInstance (Gate 2 Phương án A); màn hình soạn WorkTemplate chưa
  làm (việc tuỳ chọn, cần Gate 1 riêng).
- Ghi chú deploy (chưa chạy): GAP-014c `deploy:migrate`; GAP-030 chạy lại `ZenaPermissionsSeeder`,
  `ZenaAdminRolePermissionSeeder`, `ZenaProjectManagerRolePermissionSeeder`; GAP-098 `V1PermissionGapSeeder`; GAP-015
  chạy lại `ZenaProjectManagerRolePermissionSeeder`.

**Dòng mới (RESOLVED, "Registered retroactively by OWN-2026-021"):**

| ID | Tiêu đề | Tier | PR | Squash SHA | Subject | Digest |
|---|---|---|---|---|---|---|
| GAP-099 | v1 project update/delete hỏng; route change-request v1 chưa bao giờ chạy | 2 | #380 | `0207062843857e7e974a77137261da2e29e6a549` | `3e2bef6495f535b0f8d7e74e4cd32ba5723154a6` | `7354a216c1fc2fc0030eaf62ebda4c4588ede8070dc9ecd997fb237527356a0b` |
| GAP-100 | Test để lại dữ liệu giữa các test; thêm guard toàn bộ suite | 7 | #383 | `170b7eea44a35e9d2be5fe15e50159f1fc3f14d1` | `984d9abdd327de002e946e69eb799e1dd3316e44` | `a8a2eb3bdb9aa771c63d0dc92efd27b19bd37b6dd3a2dea4bac163bb367e169f` |
| GAP-101 | symfony/mime CVE-2026-45070 | 2 | #384 | `1b0e4f4d85d0db3f2150448968fdabf28334ad05` | `cb4e02209438eb07b50b4c005004c2093010e607` | `f236f0633bdbb1c083f6f315a7192051e131b3782c9c0f5e48a59beefee1658d` |

**Đầu sổ:** "Đối soát gần nhất: OWN-2026-021 (2026-10-29)".

### 2. Bản ghi đối soát

`docs/audits/2026-10-29-own-2026-021-reconciliation-record.md` (front matter `work_id: OWN-2026-021`,
`owner_governance_version: 1`, `owner_gate_2_record: docs/owner-decisions/OWN-2026-021/02-design.md`): mỗi dòng sửa / mới
với PR, squash SHA, subject, digest, và SHA-256 của `03-release.md` của từng GAP trên `origin/main` (không bị đổi bởi
Work ID này).

### 3. `docs/roadmap/canonical-roadmap.md`

Đổi tiêu đề mục trạng thái và "Last updated" sang 2026-10-29. Thêm vào "Released": GAP-021, 098, 099, 018, 020, 100,
101, 014c, 030, 015 (Phương án A). Thay "Open work, proposed order" bằng:

1. GAP-019 (layout `universal-frame` không route nào dùng).
2. GAP-026 (bị chặn: chủ kênh Slack ngoài repo).
3. Tuỳ chọn, mỗi việc cần Gate 1 riêng: màn hình soạn WorkTemplate (phần còn lại của GAP-015); mở rộng Ngân quỹ ngoài
   S1–S6; Project OPPM / One-Page (issue #248, PR nháp #257).

## Allowlist và digest

`OPERATIONAL_GAP_REGISTER.md`; `docs/roadmap/canonical-roadmap.md`; bản ghi đối soát mới; Gate-1 evidence và 01/02 của
OWN-2026-021 (trong digest); `03-release.md` tự loại. Không sửa packet GAP-* nào.

## Verification trước Gate 3

Diff chỉ gồm allowlist; governance lint (gồm gate ordering) và docs lint; `tests/Unit/OwnerGovernance`; SHA-256 các packet
GAP-* không đổi; mọi SHA trong sổ khớp `git log origin/main` và packet; exact-head CI xanh.

## Rollback

Revert squash commit.

## Explicit Exclusions

Sửa code / test / workflow; sửa packet GAP-*; đổi trạng thái GAP-019, GAP-026; sửa `backlog.yaml`; GitHub issue / PR;
deploy, migration, seeder.

## Decision Needed

Owner chọn: `APPROVE OWN-2026-021 Gate 2 Option A` (khuyến nghị) / Option B / Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Không duyệt Gate 3, merge hay deploy; không chọn việc tiếp theo (thứ tự chỉ là đề xuất).
