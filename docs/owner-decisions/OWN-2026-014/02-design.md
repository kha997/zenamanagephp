---
work_id: OWN-2026-014
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-29-own-2026-014-gap053-057-post-release-reconciliation.md
  plan: null
  branch: docs/OWN-2026-014-gap053-057-post-release-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/325
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-29T22:42:42+07:00"
  updated_at: "2026-09-29T22:42:42+07:00"
generated_by: agent
---

# OWN-2026-014 — GAP-053/055/056/057 post-release register reconciliation: Gate 2 design

## OWNER GATE 2: AWAITING OWNER DECISION

## Owner Summary

Sửa sổ ở đúng 6 dòng: ghi 4 việc vừa phát hành là "đã giải quyết" (thêm mới 2,
sửa 2) và thêm 2 lỗi mới GAP-058, GAP-059; kèm một bản ghi đối soát riêng.
Không sửa code, không sửa hồ sơ đã duyệt nào. Đề xuất **Phương án B** — giống
OWN-2026-011 và OWN-2026-013.

## So sánh phương án

| Phương án | Nội dung | Kết luận |
|---|---|---|
| A. Chỉ sửa register | 6 dòng; provenance chỉ nằm trong ô register | Hợp lệ nhưng yếu hơn B |
| **B. Register + bản ghi đối soát riêng** | 6 dòng + `docs/audits/2026-09-29-own-2026-014-reconciliation-record.md`, cả hai trong digest OWN-2026-014 | **Đề xuất** — cùng tiền lệ OWN-2026-011/013 |
| C. Append vào Gate-3 packet của từng GAP | Mutate bằng chứng lịch sử, bị loại khỏi digest OWN | Loại |

## Thiết kế: Phương án B

Implementation được thay đổi đúng hai file nội dung:

1. `OPERATIONAL_GAP_REGISTER.md` — sáu thay đổi:
   - **Tier 1**, thêm dòng GAP-053 ngay sau GAP-045.
   - **Tier 2**, sửa dòng GAP-055 và GAP-056; thêm GAP-057 ngay sau GAP-056,
     rồi GAP-059 ngay sau GAP-057.
   - **Tier 6**, thêm dòng GAP-058 ngay sau GAP-021.
2. `docs/audits/2026-09-29-own-2026-014-reconciliation-record.md` — bản ghi mới
   với governed frontmatter:

   ```yaml
   ---
   work_id: OWN-2026-014
   owner_governance_version: 1
   owner_gate_2_record: docs/owner-decisions/OWN-2026-014/02-design.md
   ---
   ```

Sau đó Gate 3 ở `docs/owner-decisions/OWN-2026-014/03-release.md`. Không file
nào khác đổi.

### Contract các dòng RESOLVED (GAP-053, 055, 056, 057)

Cột Status bắt đầu chính xác bằng `**RESOLVED (verified 2026-09-29)**`, theo
sau: `Released to main via PR #<n> at squash SHA <merge-sha>`; subject và
digest Gate-3 đã duyệt (GAP-053: của v2, kèm ghi chú v1 superseded); câu
`neither is recomputed or rebound by OWN-2026-014`; `Not deployed`. Cột bằng
chứng giữ nguồn hiện có (nếu có) và thêm đường dẫn Gate 1/2/3 packet, evidence
audit, URL PR, reconciliation record. Giá trị chính xác lấy từ bảng "Release
facts" của Gate-1 evidence; không rút gọn SHA/digest.

Ghi chú bắt buộc theo dòng:

- **GAP-055:** nút "Clear" trên trang bảo trì là mô phỏng (đính chính Gate 1
  tại Gate 2); Redis cache flush chỉ chạm DB 1, session/lock ở DB 0 theo cấu
  hình mặc định (đính chính evidence GAP-054).
- **GAP-056:** 32 chỗ (không phải 31; `scripts/setup-replication.sh:29`); gỡ
  thêm 2 mặc định `"password"`; **câu hỏi vận hành còn mở**: nếu
  `setup-production.sh`/`docker-manage.sh` từng chạy trên máy thật thì phải
  dọn `/usr/local/bin/zenamanage-backup`, `backups/*/production.env` và đổi mật
  khẩu DB — ngoài repo, chưa làm.
- **GAP-057:** readiness của 409 nằm ở `error.details.data.readiness`
  (middleware `error.envelope`).
- **GAP-053:** Gate 3 v1 (subject `ff825fb9`, digest `8b25a50d…`) superseded
  bởi v2 sau khi làm mới base.

### Contract dòng mới GAP-058 (Tier 6)

- Tiêu đề: API huy hiệu thanh bên luôn trả 500 — `BadgeController` thiếu
  `use Illuminate\Http\Request`, `BadgeService` thiếu `use App\Models\User`.
- Status chính xác: `**OPEN (verified 2026-09-29) — registered by OWN-2026-014; Gate 1 not started**`.
- Bằng chứng: `app/Http/Controllers/Api/BadgeController.php:5-6,13`;
  `app/Services/BadgeService.php` (imports; `?User` type-hints);
  `resources/views/components/sidebar.blade.php:209`; GAP-055 probe
  (`docs/audits/2026-09-29-gap-055-http-cache-flush-evidence.md`, surface C).
  Ghi chú: sau GAP-055, sửa import không còn mở global cache flush.

### Contract dòng mới GAP-059 (Tier 2)

- Tiêu đề: mật khẩu SMTP truyền trên dòng lệnh.
- Status chính xác như GAP-058.
- Bằng chứng: `scripts/configure-production-smtp.sh:176`;
  `app/Console/Commands/ConfigureSMTP.php:21`; GAP-056 evidence mục "Related,
  out of scope".

### Contract bản ghi đối soát

Bảng merge facts 4 PR (SHA, thời điểm, actor, subject, digest); post-merge CI
8/8 mỗi SHA, kèm lần chạy lại Staging Smoke trên `18cc0f79`; không có
`production.yml` run; SHA-256 các Gate-3 packet đã duyệt của 4 GAP (bằng nhau
giữa `origin/main` và subject); căn cứ GAP-058/059; câu hỏi vận hành GAP-056;
ghi nhận workflow hằng ngày a11y/perf đỏ từ trước (không ghi sổ). Không tự nhận
là Gate packet.

## Allowlist và digest

| File | Trong digest OWN-2026-014? |
|---|---|
| `OPERATIONAL_GAP_REGISTER.md` | Có |
| `docs/audits/2026-09-29-own-2026-014-reconciliation-record.md` | Có |
| Gate-1 evidence OWN-2026-014, `01-request.md`, `02-design.md` | Có |
| `docs/owner-decisions/OWN-2026-014/03-release.md` | Không (self-exclusion) |
| Gate packets của GAP-053/055/056/057 | 03 bị loại (cross-work); 01/02 có nhưng byte-identical |

Nhánh được làm mới với `origin/main` bằng merge commit trước khi tính digest.

## Verification trước Gate 3

Diff chỉ gồm allowlist; `git diff --check`; governance lint + gate ordering;
`tests/Unit/OwnerGovernance`; SHA-256 packet lịch sử không đổi; required CI
xanh exact head; digest canonical.

## Rollback

Revert commit thường; không force-push.

## Explicit Exclusions

Sửa GAP-058/059; điều tra workflow a11y/perf; vào máy chủ, đổi mật khẩu;
deploy.

## Decision Needed

Owner chọn: Approve Phương án B / Approve Phương án A / Request changes /
Decline.

## What the owner is NOT being asked to decide

Không duyệt cách sửa GAP-058/059, Gate 3, merge hay deployment.
