---
work_id: GAP-128
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-18-gap-128-websocket-server-evidence.md
  plan: null
  branch: docs/GAP-128-websocket-server
  pr: https://github.com/kha997/zenamanagephp/pull/418
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-20T04:52:41+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-20, verbatim: 'APPROVE A S-1 H-1' (answer to 'Approve GAP-128 Gate 2 as recommended (A, S-1, H-1)?'). Approves A (remove websocket:serve, its handler and start script, and the zena-websocket restarts/sudo entry from production.yml and the provisioning runbook), S-1 (only the server; other dead realtime code untouched) and H-1 (deploy first, then root removes the placeholder unit and sudo line), one PR / one Gate 3, with the allowlist in this packet; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-20T04:48:19+07:00"
  updated_at: "2026-11-20T05:32:15+07:00"
generated_by: agent
---

# GAP-128 — Websocket server cannot start: Gate 2 design

## OWNER GATE 2: APPROVED — A, S-1, H-1

Owner approved A, S-1 and H-1 in-session on 2026-11-20. This authorizes only the bounded implementation defined by this
packet; it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Anh/chị chọn **3 điểm**:

1. **Làm gì với server websocket** — đề xuất **A**: gỡ hẳn server không chạy được.
2. **Gỡ tới đâu** — đề xuất **S-1**: chỉ gỡ server và chỗ deploy gọi nó; các mã realtime chết khác để yên.
3. **Dọn máy chủ** — đề xuất **H-1**: sau khi bản mới đã deploy xong, người có quyền root xoá dịch vụ giả và quyền sudo thừa.

Người dùng không thấy gì thay đổi. **Phát hành:** một PR, một Gate 3.

## Điểm 1 — Server websocket

| Phương án | Nội dung |
|---|---|
| **A (đề xuất)** | Gỡ server: lệnh `websocket:serve`, handler, script khởi động; bỏ "khởi động lại zena-websocket" khỏi deploy và runbook. |
| B | Thêm `cboden/ratchet` để server chạy. Không trang nào kết nối tới nó (trình duyệt dùng giao thức Pusher, cổng 6001; server dùng giao thức riêng, cổng 8080). |
| C | Để sau, giữ dịch vụ giả `/bin/true`. |

Muốn màn hình tự cập nhật thật là tính năng mới (ví dụ Laravel Reverb + Echo trong các trang operator), cần yêu cầu nghiệp vụ riêng.

## Điểm 2 — Phạm vi gỡ (chỉ khi chọn A)

| Phương án | Nội dung |
|---|---|
| **S-1 (đề xuất)** | Chỉ server và deploy (danh sách file bên dưới). |
| S-2 | S-1 + gỡ cả các mã realtime chết khác: API quản trị `api/websocket/*`, `realtime/*`, `WebSocketService`, `WebSocketClient`, `DashboardRealTimeService`, `config/websocket.php`, kiểm tra sức khoẻ cổng 6001, Echo/`pusher-js` trong `resources/js/bootstrap.js`, và 4 file test của chúng. Lớn hơn nhiều, đụng route API và xoá test — nên tách GAP riêng nếu muốn. |

## Điểm 3 — Dọn máy chủ (chỉ khi chọn A)

| Phương án | Nội dung |
|---|---|
| **H-1 (đề xuất)** | Thứ tự: merge → deploy bằng Production Deployment (bản workflow mới không còn restart `zena-websocket`) → người có root chạy các lệnh trong runbook: `systemctl disable --now zena-websocket`, xoá file unit, `systemctl daemon-reload`, xoá dòng sudo `restart zena-websocket` trong `/etc/sudoers.d/zena-deploy`. Nếu xoá unit **trước** khi deploy bản mới, workflow cũ sẽ hỏng ở bước restart. |
| H-2 | Giữ dịch vụ giả trên máy chủ; chỉ sửa repo. |

Agent không đăng nhập root máy chủ; bước này do Owner hoặc người giữ root làm.

## Thiết kế chi tiết (theo A, S-1, H-1)

- Xoá `app/Console/Commands/WebSocketServerCommand.php`, `app/WebSocket/DashboardWebSocketHandler.php`,
  `scripts/start-websocket-server.sh`.
- `phpstan-baseline.neon`: xoá các mục của hai file trên.
- `.github/workflows/production.yml`: xoá 2 dòng `sudo systemctl restart zena-websocket` (cutover, dòng 247, và tự rollback, dòng 360). Không đổi gì khác.
- `docs/runbooks/gap-049-host-provisioning.md`: bỏ mục "websocket systemd unit" và dòng sudo `restart zena-websocket`; thêm mục "Gỡ dịch vụ zena-websocket cũ" với các lệnh H-1 và thứ tự bắt buộc.
- `docs/runbooks/go-live.md`: thêm một dòng nhắc bước gỡ ở trên cho máy đã dựng trước GAP-128 (nếu cần cho nhất quán).
- Không đổi `composer.json`, route, giao diện, broadcasting.

**Test** `tests/Feature/Deployment/WebSocketServerRetiredTest.php` (mới):
- `Artisan::all()` không có lệnh `websocket:serve`;
- `production.yml` không chứa `zena-websocket`;
- runbook dựng máy không cấp sudo `restart zena-websocket`.

Test phải đỏ trước khi sửa, xanh sau. `DeploymentGuardTest` hiện không kiểm tra websocket nên không đổi.

## Files (allowlist)

- `app/Console/Commands/WebSocketServerCommand.php` (xoá)
- `app/WebSocket/DashboardWebSocketHandler.php` (xoá)
- `scripts/start-websocket-server.sh` (xoá)
- `phpstan-baseline.neon`
- `.github/workflows/production.yml`
- `docs/runbooks/gap-049-host-provisioning.md`, `docs/runbooks/go-live.md`
- `tests/Feature/Deployment/WebSocketServerRetiredTest.php` (mới)
- `docs/owner-decisions/GAP-128/*`, `docs/audits/2026-11-18-gap-128-websocket-server-evidence.md`,
  `docs/superpowers/plans/2026-11-20-gap-128-websocket-server.md`

File khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung. Tài liệu lịch sử (`docs/archive/**`, `docs/DEVELOPER_DOCUMENTATION.md`, plan GAP-049 cũ) không sửa.

## Owner amendment 1 (approved 2026-11-20)

Owner decision in-session on 2026-11-20, verbatim: 'APPROVE amendment 1' (answer to 'Approve GAP-128 amendment 1 (add
phpstan.neon to the file list)?').

Exact-head CI on `6292d506` failed PHPStan (Security Tests, and Code Quality Analysis which stops at the same step):
`phpstan.neon` excludes `app/WebSocket`, which this change deletes, and PHPStan rejects a missing exclude path. Added to
the allowlist, only to remove that entry (and the then-empty `excludePaths:` key):

- `phpstan.neon`

Local PHPStan with the change: exit 0.

## Ngoài phạm vi

- Tính năng realtime.
- Các mã realtime chết ở S-2.
- Deploy và thao tác trên máy chủ (H-1 do người có root làm sau deploy).

## Verification required at Gate 3

- Test mới đỏ trước, xanh sau.
- Toàn bộ Unit + Feature + Integration, PHPStan và các lint xanh ở exact head.
- `git grep zena-websocket` chỉ còn trong tài liệu lịch sử và mục gỡ của runbook.

## Rollback

Revert PR. Nếu đã xoá unit trên máy chủ, dựng lại unit oneshot `/bin/true` và dòng sudo trước khi deploy bản cũ.

## Decision Needed

Owner chọn: `APPROVE GAP-128 Gate 2 A S-1 H-1` (hoặc phương án khác cho từng điểm) / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
