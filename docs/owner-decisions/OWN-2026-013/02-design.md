---
work_id: OWN-2026-013
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-26-own-2026-013-gap054-post-release-reconciliation.md
  plan: null
  branch: docs/OWN-2026-013-gap054-post-release-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/320
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
  created_at: "2026-09-28T23:17:58+07:00"
  updated_at: "2026-09-28T23:17:58+07:00"
generated_by: agent
---

# OWN-2026-013 — GAP-054 post-release register reconciliation: Gate 2 design

## OWNER GATE 2: AWAITING OWNER DECISION

## Owner Summary

Sửa sổ theo dõi lỗ hổng ở đúng 3 dòng: chuyển GAP-054 sang "đã giải quyết",
thêm GAP-055 (nút xoá bộ nhớ đệm trong trang quản trị) và GAP-056 (mật khẩu cơ
sở dữ liệu lộ trên dòng lệnh ở 31 chỗ / 11 script), kèm một bản ghi đối soát
riêng. Không sửa code, script, hay bất kỳ hồ sơ đã duyệt nào của GAP-054. Đề
xuất **Phương án B** — cùng cách đã dùng cho GAP-052 (OWN-2026-011).

## Sự thật và ràng buộc bất biến

- GAP-054 được merge qua PR #318 tại squash SHA
  `a473298e2fc6aabada1b41291ec5478fee7b73c3` (2026-09-26T12:46:22Z, `kha997`),
  approved head `be72d6e5d183f3e30ce94b56c0a7d8cdd3a6f7d7`. Release execution
  record merge qua PR #319 tại `5441bc2e9c4c48b2f0c5feac2a0118d5cfcb3c58`.
- Gate-3 approval lịch sử của GAP-054 ràng buộc implementation subject
  `cafa0a983ae1478e7d8b091141df3b55a5726fb4` và implementation-tree digest
  `7108b2925e8c8300207afecb7994e2c300cd9d9778746670112c8a5c6c29cce7`. Không
  recompute, rebind hay sửa.
- `docs/owner-decisions/GAP-054/{01-request,02-design,03-release}.md` phải giữ
  byte-identical với `origin/main` (SHA-256 lần lượt `3f75250d…9ab`,
  `3411622b…f1d`, `70130792…5a6`).
- Không có production deployment của GAP-054; scheduler chưa bật trên host nào;
  `production.yml` không có run nào tại merge SHA.
- Sửa dòng GAP-054 dưới Work ID GAP-054 làm digest đổi từ `7108b292…` sang
  `2f507d45…` và bị `check-evidence-freshness.sh` coi là quyết định STALE — lý
  do tồn tại của Work ID riêng này (Gate-1 evidence).

## So sánh phương án

| Phương án | Nội dung | Provenance | Kết luận |
|---|---|---|---|
| A. Chỉ sửa register (3 dòng) | Register trong digest OWN-2026-013 | Sự thật phát hành chỉ nằm rải rác trong ô register + Gate packets của OWN | Hợp lệ nhưng yếu hơn B |
| **B. Register (3 dòng) + bản ghi đối soát riêng trong `docs/audits/`** | Cả hai file là blob thường, nằm trong digest OWN-2026-013 | Có bản ghi immutable tách bạch merge/CI/no-deploy facts và căn cứ GAP-055/056 | **Đề xuất** — giống OWN-2026-011 Phương án 3 |
| C. Append vào `GAP-054/03-release.md` | Packet Gate-3 của work item khác bị loại khỏi digest OWN | Mutate bằng chứng lịch sử mà không digest nào ràng buộc | Loại |

## Thiết kế: Phương án B

Implementation được phép thay đổi đúng hai file nội dung:

1. `OPERATIONAL_GAP_REGISTER.md` — chỉ ba thay đổi, đều trong Tier 2:
   - sửa dòng GAP-054;
   - thêm dòng GAP-055 ngay sau GAP-054;
   - thêm dòng GAP-056 ngay sau GAP-055.
2. `docs/audits/2026-09-28-own-2026-013-gap054-post-release-reconciliation-record.md`
   — bản ghi mới, factual, có governed-document frontmatter:

   ```yaml
   ---
   work_id: OWN-2026-013
   owner_governance_version: 1
   owner_gate_2_record: docs/owner-decisions/OWN-2026-013/02-design.md
   ---
   ```

Sau đó Gate 3 tại `docs/owner-decisions/OWN-2026-013/03-release.md` (lifecycle
packet bắt buộc, không phải nội dung thứ ba). Không file nào khác được đổi.

### Contract dòng GAP-054

- Cột `Status` bắt đầu bằng chính xác `**RESOLVED (verified 2026-09-28)**`,
  theo sau là đoạn:

  > Released to main via PR #318 at squash SHA
  > a473298e2fc6aabada1b41291ec5478fee7b73c3; release execution record merged
  > via PR #319 at 5441bc2e9c4c48b2f0c5feac2a0118d5cfcb3c58. GAP-054's
  > historical Gate-3 approval remains bound to implementation subject
  > cafa0a983ae1478e7d8b091141df3b55a5726fb4 and implementation-tree digest
  > 7108b2925e8c8300207afecb7994e2c300cd9d9778746670112c8a5c6c29cce7; neither
  > is recomputed or rebound by OWN-2026-013. Not deployed; scheduler not
  > enabled on any host. Out-of-scope follow-ups registered as GAP-055 and
  > GAP-056.

  Câu lỗi thời "implementation authorized, not started" bị thay; phần
  discovery (`schedule:list`, GAP-049 §4 correction, prior art) được giữ.
- Cột bằng chứng giữ nguyên nguồn hiện có và thêm: Gate 3
  `docs/owner-decisions/GAP-054/03-release.md`; PR #318 và #319 (URL đầy đủ);
  reconciliation record ở mục 2.

### Contract dòng GAP-055

- Tiêu đề: admin "clear cache" vẫn gọi `Cache::flush()` — cùng tác hại GAP-054
  Defect 2 (reset `throttle:` counters, OIDC state, overlap locks mọi tenant).
- Status chính xác: `**OPEN (verified 2026-09-28) — registered by OWN-2026-013;
  Gate 1 not started**`.
- Bằng chứng: `app/Http/Controllers/Admin/MaintenanceController.php:283-292`;
  `routes/web.php:194` (live, middleware `RoleBasedAccessControlMiddleware:admin`);
  non-live `App\Http\Controllers\PerformanceController::clearCaches()`
  (`routes/legacy/api_v1.php:38`) và `Api\Admin\PerformanceController::clearCaches()`
  (`routes/web.php:347`, commented); Gate-1 evidence OWN-2026-013.

### Contract dòng GAP-056

- Tiêu đề: 31 chỗ trong 11 shell script truyền mật khẩu MySQL qua `-p…` trên
  dòng lệnh; 4 chỗ dùng `root` với fallback viết sẵn `root_password`.
- Status chính xác: `**OPEN (verified 2026-09-28) — registered by OWN-2026-013;
  Gate 1 not started**`.
- Bằng chứng: bảng file:line trong mục "Correction (2026-09-28)" của Gate-1
  evidence; `.github/workflows/automated-deployment.yml:227` gọi
  `./docker-manage.sh backup` (dormant: không có release, gated on secrets,
  lần chạy cuối 2026-07-07T04:14:10Z); đường chính thức `production.yml` →
  `scripts/deploy/backup.sh:19` không truyền mật khẩu.

### Contract bản ghi đối soát

Ghi tách bạch: merge facts PR #318/#319 (SHA, thời điểm, actor); historical
subject/digest GAP-054 không đổi; post-merge CI trên `a473298e` xanh; không có
`production.yml` run tại merge SHA, scheduler chưa bật; SHA-256 ba packet
GAP-054 không đổi; căn cứ và phạm vi GAP-055/GAP-056; đây chỉ là đối soát hành
chính, không phải approval/reapproval/implementation/deployment của GAP-054
hay fix của GAP-055/056. Không tự nhận là Gate packet.

## File allowlist và digest

| File | Vai trò | Trong digest OWN-2026-013? |
|---|---|---|
| `OPERATIONAL_GAP_REGISTER.md` | Nội dung đối soát | Có |
| `docs/audits/2026-09-28-own-2026-013-…-record.md` | Bản ghi provenance | Có |
| `docs/audits/2026-09-26-own-2026-013-…-reconciliation.md` | Gate-1 evidence (đã có) | Có |
| `docs/owner-decisions/OWN-2026-013/01-request.md`, `02-design.md` | Gate 1/2 | Có |
| `docs/owner-decisions/OWN-2026-013/03-release.md` | Gate 3 tương lai | Không (self-reference exclusion) |
| `docs/owner-decisions/GAP-054/*` | Lịch sử, không đổi | 03 bị loại (cross-work); 01/02 có nhưng byte-identical |

Nhánh sẽ được cập nhật với `origin/main` bằng merge commit (không rebase,
không force-push) trước khi tính digest Gate 3, để cây tại subject khớp cây
sau squash-merge.

## Verification trước Gate 3

1. Diff so với `origin/main` chỉ gồm OWN-2026-013 packets, Gate-1 evidence và
   đúng hai file nội dung; `git diff --check` PASS.
2. Owner Governance Lint (structural + `--enforce-gate-ordering`) PASS.
3. SHA-256 ba packet GAP-054 bằng giá trị ghi ở trên.
4. Facts PR #318/#319 và no-deploy query khớp bản ghi.
5. Digest tính bằng `owner_governance_compute_implementation_tree_digest()` với
   `OWN-2026-013`, khớp `technical_evidence` Gate 3; register và bản ghi mới
   nằm trong manifest.
6. Required checks trên exact head xanh (Owner Governance Lint, Routes
   Guardrails).

## Rollback

Revert commit thường (trước merge) hoặc revert squash commit (sau merge). Không
force-push, không xoá packet lịch sử.

## Explicit Exclusions

- Không sửa `MaintenanceController`, `PerformanceController`, bất kỳ script,
  workflow, credential hay mật khẩu nào.
- Không sửa `docs/owner-decisions/GAP-054/*`.
- Không deploy, không bật scheduler.
- GAP-055, GAP-056 mỗi việc cần Gate 1 riêng.

## Decision Needed

Owner chọn: Approve Phương án B / Approve Phương án A / Request changes /
Decline.

## What the owner is NOT being asked to decide

Không duyệt cách sửa GAP-055/GAP-056, không duyệt Gate 3, merge, release hay
deployment.
