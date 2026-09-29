---
work_id: GAP-056
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_correction_or_defer
references:
  spec: docs/audits/2026-09-29-gap-056-script-mysql-password-evidence.md
  plan: docs/superpowers/plans/2026-09-29-gap-056-script-mysql-password-implementation.md
  branch: docs/GAP-056-script-mysql-password-argv
  pr: https://github.com/kha997/zenamanagephp/pull/323
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
  created_at: "2026-09-29T08:30:49+07:00"
  updated_at: "2026-09-29T08:30:49+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Option-1 implementation at subject 1f573a55: shell-script architecture guard RED at base (34 offenders) and GREEN; helper and setup-production tests RED without the fix and GREEN; 217 governance/architecture/GAP-056 tests and DeploymentGuardTest green locally; bash -n clean on all 12 touched scripts; all 33 exact-head PR checks green; diff exactly the Gate-2 allowlist; canonical digest computed at subject."
technical_evidence:
  base_sha: "c32a7ddb31995ac7dd00886ceca94a0c5f63d3ca"
  subject_sha: "1f573a55cc49c70e2bf47002d2124f4b6fd9bf78"
  implementation_tree_digest: "b1441886ad1d74506a4b37a62ddd35f879f8b430289a65dab1d105d1d72844e5"
  verified_pr_head_sha: "1f573a55cc49c70e2bf47002d2124f4b6fd9bf78"
  verified_at: "2026-09-29T08:30:49+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

# GAP-056 — Gate 3 release decision

## OWNER GATE 3: AWAITING OWNER DECISION

## Gói quyết định phát hành

**1. Vấn đề là gì?** Script vận hành đưa mật khẩu cơ sở dữ liệu lên dòng lệnh;
script cài đặt máy chủ lưu mật khẩu dạng chữ thường trong file ai cũng đọc
được; bản sao lưu chứa file bí mật (Gate 1).

**2. Sau thay đổi:**

- Helper `scripts/lib/mysql-credentials.sh`: option file 0600 tạo bằng
  `printf` (không lộ qua dòng lệnh), tự xoá khi thoát, fail-closed khi thiếu
  user/password; client trong container qua `docker cp`; client root dựng
  option file **bên trong container** từ `MYSQL_ROOT_PASSWORD` của chính nó.
- **32** chỗ `-p<mật khẩu>` trong 11 script đã thay; không còn `root_password`.
- `setup-production.sh`: mật khẩu chỉ ở `/etc/zenamanage/backup.cnf`
  (root, 0600); script cron không chứa bí mật, 0700, nằm trong crontab của root;
  thư mục sao lưu 0700; `tar` loại `.env`.
- `docker-manage.sh`: không chép `production.env` vào bản sao lưu.
- Test kiến trúc quét mọi file `.sh` git theo dõi, không miễn trừ.

**3. Khác biệt so với Gate 1/2 (cần Owner biết)**

- **32 chỗ, không phải 31:** `scripts/setup-replication.sh:29` dùng biến chữ
  thường `-p"$password"`, regex Gate 1 chỉ bắt tên biến chữ hoa.
- **Thêm ngoài chữ của thiết kế:** gỡ 2 mật khẩu mặc định `"password"`
  (`scripts/backup-system.sh:14`, `scripts/setup-replication.sh:12`) và chặn
  mẫu này trong test. Cùng mục tiêu "không bao giờ dùng mật khẩu mặc định", cùng
  file trong allowlist.
- **Sửa lỗi logic của chính thiết kế:** script cron chỉ root đọc được nên phải
  cài vào **crontab của root** (thiết kế giữ crontab user → cron sẽ không chạy
  được); và `crontab` rỗng khiến `grep -v` trả 1 làm `set -e` bỏ qua việc ghi
  lịch — lỗi có sẵn ở code cũ, test bắt được, đã sửa bằng `|| true`.
- **Script giám sát** (`monitor-system.sh`, `performance-monitor.sh`): thiếu
  mật khẩu thì bỏ qua phép đo DB thay vì dừng toàn bộ giám sát.

**4. Bằng chứng kỹ thuật**

- Base `c32a7ddb`; subject `1f573a55cc49c70e2bf47002d2124f4b6fd9bf78`; digest
  `b1441886ad1d74506a4b37a62ddd35f879f8b430289a65dab1d105d1d72844e5`.
- `NoCommandLineDatabasePasswordTest`: base 34 vi phạm (32 argv + 2 mặc
  định) → 0.
- `tests/Feature/GAP056/MysqlCredentialsHelperTest.php` (6 test, bash thật
  với client giả): argv không có mật khẩu (gồm ký tự `"`, `\`, `#`), option
  file 0600, bị xoá cả khi client lỗi, thiếu mật khẩu → không gọi client; bản
  root không lộ mật khẩu ra host. Không có helper → đỏ.
- `tests/Feature/GAP056/SetupProductionBackupScriptTest.php`: script sinh ra
  không chứa mật khẩu, 0700, `bash -n` hợp lệ; option file 0600; thư mục
  0700; `--exclude='.env'`; dòng cron vào crontab root. Code cũ → đỏ.
- Local: 217 test (OwnerGovernance, Architecture, GAP-056) + DeploymentGuardTest
  17 test xanh; `bash -n` sạch 12 script; governance lint PASS.
- CI exact head `1f573a55`: 33/33 pass.

**5. Ngoài phạm vi** — Đổi mật khẩu; dọn `/usr/local/bin/zenamanage-backup` hay
`backups/*/production.env` trên máy chủ đã chạy bản cũ (câu hỏi Gate 1 vẫn
mở); mật khẩu SMTP trên dòng lệnh; xoá script; deploy.

**6. Rủi ro còn lại** — Thấp–trung bình: các script không có kiểm thử chạy thật
với MySQL/Docker (chỉ client giả); lần chạy thật đầu tiên trên máy chủ nên được
theo dõi. Mật khẩu trong container dùng escape POSIX; mật khẩu có ký tự xuống
dòng không được hỗ trợ.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, đổi mật khẩu, hay dọn máy chủ.
