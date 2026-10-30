---
work_id: GAP-026
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-30-gap-026-production-slack-evidence.md
  plan: docs/superpowers/plans/2026-10-30-gap-026-production-slack.md
  branch: docs/GAP-026-production-slack
  pr: https://github.com/kha997/zenamanagephp/pull/390
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-30T07:46:58+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-30T07:46:58+07:00"
  updated_at: "2026-10-30T07:46:58+07:00"
generated_by: agent
---

# GAP-026 — Production Slack notifications: Gate 2 design

## OWNER GATE 2: AWAITING OWNER

## Owner Summary

Sửa `production.yml` để 2 bước báo Slack **thực sự chạy** khi có secret `SLACK_WEBHOOK_URL` (kênh Zena), giữ nguyên secret
và nội dung tin. Gỡ "đóng băng" trong tài liệu.

**Owner cần chọn theo loại secret** (xem trong GitHub → Settings → Secrets and variables → Actions):
- Nếu `SLACK_WEBHOOK_URL` nằm ở tab **Repository secrets** → **Phương án A**.
- Nếu nằm trong **Environments → production** → **Phương án B**.

## So sánh phương án

| Phương án | Nội dung | Khi nào dùng |
|---|---|---|
| **A. Khai báo biến ở cấp job** | Job `deploy` và job `notify` có `env: SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}`; bỏ khai báo trùng ở cấp bước | Secret ở cấp repository (khuyến nghị nếu đúng) |
| B. Như A + job `notify` chạy trong `environment: production` | Job `notify` đọc được secret của environment | Secret ở environment `production`. Lưu ý: nếu environment có người duyệt bắt buộc, job `notify` cũng phải chờ duyệt |
| C. Giữ nguyên | Không sửa | Kênh Zena tiếp tục không nhận thông báo production |

## Thiết kế (A; B thêm một dòng)

### 1. `.github/workflows/production.yml`

- Job `deploy`: thêm `env:` cấp job với `SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}`; bước "Escalate
  breaking-migration recovery requirement" bỏ khối `env:` riêng (điều kiện `if` giữ nguyên chữ).
- Job `notify`: tương tự cho bước "Notify truthful deployment state". **Phương án B** thêm `environment: production` cho
  job này.
- Không đổi: secret, `webhook`, `payload`, các bước deploy / SSH / migration / rollback.

### 2. Test (đỏ trước / xanh sau)

Thêm vào `tests/Feature/Deployment/DeploymentGuardTest.php`: đọc YAML `production.yml`; với mọi bước có `if` chứa
`env.SLACK_WEBHOOK_URL`, biến `SLACK_WEBHOOK_URL` phải được khai báo ở `env` cấp workflow hoặc cấp job của job đó (không chỉ
ở cấp bước), và bằng `${{ secrets.SLACK_WEBHOOK_URL }}`. Phương án B: thêm kiểm tra job `notify` có
`environment: production`.

### 3. Tài liệu

- `docs/change-proposals/production-slack-webhook-freeze-decision.md`: thêm mục "Resolved (GAP-026, 2026-10-30)" — Owner
  xác nhận `SLACK_WEBHOOK_URL` trỏ kênh Zena (`C0BBAL5SZM4`), giữ secret này; đóng băng kết thúc.
- `docs/audits/production-slack-webhook-evidence.md`: thêm dòng cập nhật đích = kênh Zena (xác nhận của Owner) và lỗi điều
  kiện đã sửa.

### 4. Files (allowlist)

`.github/workflows/production.yml`; `tests/Feature/Deployment/DeploymentGuardTest.php`; 2 tài liệu ở mục 3; plan
`docs/superpowers/plans/2026-10-30-gap-026-production-slack.md`; packet và evidence GAP-026.

## Verification required at Gate 3

Đỏ trước / xanh sau; `DeploymentGuardTest` và toàn bộ suite không có lỗi mới; YAML hợp lệ (`actionlint` nếu có, hoặc
parse YAML); governance / docs lint; CI trên đúng head xanh. Workflow production chỉ chạy tay nên PR này không kích hoạt nó —
lần deploy production kế tiếp mới là kiểm chứng thật (ghi rõ ở Gate 3).

## Rollback

Revert squash commit.

## Explicit Exclusions

Đổi secret hay kênh; 2 workflow khác; bước deploy; thông báo nghiệp vụ lên Slack hay Zalo (Work ID riêng); sổ GAP (lần đối
soát sau); chạy deploy.

## Decision Needed

Owner chọn: `APPROVE GAP-026 Gate 2 Option A` (secret ở repository) / `Option B` (secret ở environment production) /
Option C / Request changes / Decline.

## What the owner is NOT being asked to decide

Chưa duyệt Gate 3, merge hay chạy deploy production.
