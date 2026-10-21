---
work_id: GAP-087
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-21-gap-087-actions-node24-evidence.md
  plan: docs/superpowers/plans/2026-10-21-gap-087-actions-node24.md
  branch: docs/GAP-087-actions-node24
  pr: https://github.com/kha997/zenamanagephp/pull/360
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-21T07:56:23+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-21, verbatim: 'APPROVE GAP-087 Gate 2 Option A'. Reviewed design head: f43cd72b7b1b94fbb5aa1009f2199e778acfa29d. Approves Option A and its exact allowlist (uses: lines only in automated-deployment.yml, automated-testing.yml, button-tests.yml, code-quality-security.yml, nightly-matrix.yml, production.yml, release-management.yml, staging-smoke.yml: upload-artifact v4->v7, download-artifact v4->v8, slack-github-action v2.1.1->v4.0.0, build-push-action v5->v7, setup-buildx-action v3->v4, metadata-action v5->v6, login-action v3->v4, codeql-action/upload-sarif v3->v4; temporary proof workflow reverted; docs); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-21T07:49:39+07:00"
  updated_at: "2026-10-21T07:56:23+07:00"
generated_by: agent
---

# GAP-087 — GitHub Actions off Node 20: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-21 against reviewed design head
`f43cd72b7b1b94fbb5aa1009f2199e778acfa29d`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Mình đã đối chiếu từng action giữa bản đang dùng và bản mới, dựa trên
`action.yml` và lịch sử commit của các repo gốc:

- **Input:** mọi input mà workflow của mình đang dùng vẫn có ở bản mới.
  `setup-buildx` bỏ 3 input (`config`, `config-inline`, `install`), nhưng mình
  không dùng input nào trong số đó.
- **Slack v4** đọc payload YAML chặt hơn (js-yaml 5). Mình đã cho js-yaml 5.5.0
  đọc thử cả **9 payload**: tất cả hợp lệ.
- **download-artifact v8** mặc định báo lỗi khi file tải về không khớp mã băm.
  Như vậy an toàn hơn.
- **build-push v6+** thêm phần tóm tắt build vào trang kết quả CI, và tải lên
  thêm một artifact "build record" cho mỗi lần build. Logic build không đổi.

**Phương án A (khuyến nghị):** nâng lên bản mới nhất của mỗi action.

| Action | Từ | Lên |
|---|---|---|
| `upload-artifact` | v4 | v7 |
| `download-artifact` | v4 | v8 |
| Slack | v2.1.1 | v4.0.0 |
| `build-push` | v5 | v7 |
| `setup-buildx` | v3 | v4 |
| `metadata` | v5 | v6 |
| `login` | v3 | v4 |
| CodeQL `upload-sarif` | v3 | v4 |

**Phương án B:** chỉ nâng tới bản đầu tiên chạy Node 24. Khác A ở 3 action:
`upload-artifact` v6 (giống 7 chỗ đang dùng v6), `download-artifact` v7 và
Slack v3.0.0 (đọc YAML như cũ). Nhưng sẽ phải nâng lại sớm hơn.

**Kiểm chứng (cả hai phương án):**

1. `actionlint` không có lỗi mới. Hiện main có 22 cảnh báo cũ, không cái nào
   liên quan đến action.
2. Mọi input trong `with:` phải có trong `action.yml` của bản mới.
3. 9 payload Slack đọc được bằng js-yaml 5.
4. **Commit tạm** thêm một workflow chạy trên PR, gọi `metadata-action` và
   `login-action` (đăng nhập ghcr.io bằng `GITHUB_TOKEN`). Đây là hai action
   duy nhất không có workflow nào của PR chạy tới. Kiểm xong thì hoàn tác.
   Các action còn lại đã được CI của PR chạy:
   - `build-push`, `setup-buildx` và `upload-sarif` chạy trong
     `code-quality-security`;
   - `download-artifact` chạy trong `automated-testing` và `button-tests`.
5. CI chạy đúng trên head cuối phải xanh, và log không còn cảnh báo
   "Node.js 20 is deprecated".

**Phần còn lại không kiểm chứng tự động được:** Slack chỉ chạy khi deploy
hoặc release. Các workflow release, deploy và production chỉ chạy thật ở lần
phát hành tới. Bước Slack nào cũng có `continue-on-error: true`, nên lỗi Slack
không làm hỏng deploy.

Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. **Workflow files**. Only `uses:` lines change, nothing else:
   - `automated-deployment.yml`, `automated-testing.yml`, `button-tests.yml`,
     `code-quality-security.yml`, `nightly-matrix.yml`, `production.yml`,
     `release-management.yml`, `staging-smoke.yml`;
   - `actions/upload-artifact@v4` → `@v7` (18);
   - `actions/download-artifact@v4` → `@v8` (6);
   - `slackapi/slack-github-action@v2.1.1` → `@v4.0.0` (9);
   - `docker/build-push-action@v5` → `@v7` (8);
   - `docker/setup-buildx-action@v3` → `@v4` (7);
   - `docker/metadata-action@v5` → `@v6` (5);
   - `docker/login-action@v3` → `@v4` (5);
   - `github/codeql-action/upload-sarif@v3` → `@v4` (2).
2. **Docs**: plan, this packet, 03-release.

**Not changed:**
- actions that already run on Node 24, including the 7 existing `upload-artifact@v6` uses;
- composite and docker actions;
- every `with:` value, trigger and job.

## Verification

- actionlint 1.7.12 reports no finding beyond the 22 already on main.
- Every `with:` key on a changed step exists in the target version's `action.yml`.
- The 9 Slack payloads, with `${{ }}` replaced by a placeholder, parse with js-yaml 5.5.0 as objects.
- Temporary proof on the PR, reverted afterwards: a workflow on `pull_request` runs
  `docker/metadata-action@v6` and `docker/login-action@v4` (ghcr.io, `GITHUB_TOKEN`,
  `packages: read`).
- Exact-head CI is green, and its logs have no "Node.js 20 is deprecated" warning for the changed actions.
- Governance and docs lints pass.

## Rollback

Revert the squash commit.

## Out of scope

- Running a release, deploy or production workflow.
- Actions already on Node 24.
- Workflow logic.
- Deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-087 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.

## Owner amendment (2026-10-21T07:59:31+07:00)

Owner selection via AskUserQuestion, verbatim: "Sửa trong PR này (Recommended)".

On PR #360, the Security Vulnerability Scan turned red on two high advisories published after
GAP-086 merged: GHSA-8r9p-f939-6h3c and GHSA-q3jh-wrc6-jgq8. Both are in `follow-redirects`
1.16.0, which axios 1.20.0 pulls in. The GAP-086 gate blocks every branch, main included, until
they are fixed. This amendment authorizes one change outside the allowlist:

- `package-lock.json`: the `node_modules/follow-redirects` entry only, 1.16.0 → 1.16.1 (version,
  resolved, integrity). It stays within the range axios declares, and no other entry changes.
  `npm audit fix` was not used because it also rewrote unrelated bundled entries.
