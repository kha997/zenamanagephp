---
work_id: GAP-059
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-30-gap-059-smtp-password-evidence.md
  plan: docs/superpowers/plans/2026-09-30-gap-059-smtp-password-implementation.md
  branch: docs/GAP-059-smtp-password-argv
  pr: https://github.com/kha997/zenamanagephp/pull/327
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-30T16:57:51+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-09-30: 'APPROVE GAP-059 Gate 3'. Given after the packet was presented at PR head 6820754d30427f263130dbc686d80923201f5c09; bound to implementation subject f96aacad33f3f7effaee3ce669c748891fa7b35c and implementation-tree digest 24202c37833e5522d55e9db9f9604685538c0bc955015b795e9073f24e15c3ad (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or credential rotation authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-30T16:56:00+07:00"
  updated_at: "2026-09-30T16:57:51+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Option-1 implementation at subject f96aacad: 11 new tests (7-password dotenv round-trip, --password refusal, script env writer in real bash with argv capture, shell guard) RED at base then GREEN; 222 governance/architecture/GAP-059 tests green locally; bash -n clean; all 33 exact-head PR checks green (first run red only on a PHPStan contract-typed call, fixed); diff exactly the Gate-2 allowlist; canonical digest computed at subject."
technical_evidence:
  base_sha: "943577f75e8e519dfb42109a094a2632a670fd35"
  subject_sha: "f96aacad33f3f7effaee3ce669c748891fa7b35c"
  implementation_tree_digest: "24202c37833e5522d55e9db9f9604685538c0bc955015b795e9073f24e15c3ad"
  verified_pr_head_sha: "f96aacad33f3f7effaee3ce669c748891fa7b35c"
  verified_at: "2026-09-30T16:56:00+07:00"
owner_decision_binding:
  implementation_tree_digest: "24202c37833e5522d55e9db9f9604685538c0bc955015b795e9073f24e15c3ad"
  decision_recorded_at: "2026-09-30T16:57:51+07:00"
---

# GAP-059 — Gate 3 release decision

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-09-30, bound to implementation subject
`f96aacad33f3f7effaee3ce669c748891fa7b35c` and implementation-tree digest `24202c37833e5522d55e9db9f9604685538c0bc955015b795e9073f24e15c3ad`. No deployment or credential
rotation is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Script cài đặt email lộ mật khẩu trên dòng lệnh (2 chỗ),
ghi sai hoặc không ghi được mật khẩu vào `.env`, bước "kiểm tra" ghi đè sai, và
để lại bản sao `.env` ai cũng đọc được (Gate 1, đã tái hiện).

**2. Sau thay đổi:**

- `smtp:configure`: từ chối `--password=` (exit ≠ 0, không ghi gì); đọc mật
  khẩu bằng `--password-stdin`; ghi MAIL_* dạng `KEY="…"` với `\`, `"`, `$`
  được thoát, thay dòng bằng callback (không còn back-reference), ghi qua file
  tạm giữ nguyên quyền `.env`; dùng `environmentFilePath()`; trả mã thoát đúng.
- `configure-production-smtp.sh`: một lời gọi
  `printf … | php artisan smtp:configure --no-interaction --password-stdin …`
  ghi MAIL_*; bỏ bước "kiểm tra" ghi đè; `update_env_var()` dùng `awk` +
  `ENVIRON` (không `sed`, không `.env.bak`, giữ quyền); backup `.env` 0600.
- `run-comprehensive-tests.sh`: dùng `--password-stdin`.

**3. Khác biệt so với Gate 2** — Không có về phạm vi. CI lần đầu (`e510dce1`)
đỏ Code Quality + Security Tests do PHPStan: `$this->laravel` là contract
không có `environmentFilePath()`; sửa bằng `app()->environmentFilePath()`.

**4. Bằng chứng kỹ thuật**

- Base `943577f7`; subject `f96aacad33f3f7effaee3ce669c748891fa7b35c`; digest
  `24202c37833e5522d55e9db9f9604685538c0bc955015b795e9073f24e15c3ad`.
- `tests/Feature/GAP059/ConfigureSmtpWritesEnvSafelyTest.php` (8): 7 mật khẩu
  (`ab/cd`, `ab&cd`, `pl"q`, `p$1w`, `a b#c`, backslash, `x${HOME}y`) đọc
  lại bằng `Dotenv\Parser\Parser` đúng nguyên văn, dòng khác và quyền 0640 giữ
  nguyên; `--password=` bị từ chối, `.env` không đổi.
- `tests/Feature/GAP059/SmtpScriptEnvWriterTest.php` (3): `update_env_var`
  chạy bash thật với `awk`/`sed` bọc ghi argv — giá trị khó đọc lại đúng,
  không có trong argv, không `.env.bak`, giữ quyền; script không có
  `--password=`, dùng pipe `--password-stdin`; backup dưới `umask 077`.
- `tests/Architecture/NoCommandLineSmtpPasswordTest.php`: base báo 2 vi phạm
  (`configure-production-smtp.sh:176`, `run-comprehensive-tests.sh:194`) → 0.
- Local: 222 test (OwnerGovernance, Architecture, GAP-059) xanh; `bash -n` 2
  script sạch.
- CI exact head `f96aacad`: 33/33 pass.

**5. Ngoài phạm vi** — Đổi mật khẩu SMTP thật; `php artisan cache:clear` còn
trong script (ghi nhận); `run-comprehensive-tests.sh` vẫn ghi cấu hình SMTP thử
vào `.env` (script test, ghi nhận); deploy.

**6. Rủi ro còn lại** — Thấp. Ai đang gọi `smtp:configure --password=` sẽ bị
từ chối (không caller nào khác trong repo); script chưa chạy với SMTP thật
(test dùng file tạm).

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay đổi mật khẩu.
