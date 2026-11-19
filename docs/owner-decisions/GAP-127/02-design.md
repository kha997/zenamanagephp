---
work_id: GAP-127
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-11-18-gap-127-dependency-advisories-evidence.md
  plan: null
  branch: docs/GAP-127-dependency-advisories
  pr: https://github.com/kha997/zenamanagephp/pull/419
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-20T10:05:00+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-20, verbatim: 'APPROVE GAP-127 Gate 2 L-1 P-1'. Typed directly by the Owner in this agent session. Reviewed design head: 27edc8ae5eb0240f0ab138a8643d277637d8f64c. Approves L-1 (laravel/framework ^12.71.0, lock updates laravel/framework only, 12.69.3 -> 12.73.0) and P-1 (new .npm-audit-ignore with the single reasoned GHSA-fc5q-39xc-vjpf line), one PR / one Gate 3, with the exact allowlist in this packet; not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-20T09:45:00+07:00"
  updated_at: "2026-11-20T12:00:00+07:00"
generated_by: agent
---

# GAP-127 — Security Vulnerability Scan green again: Gate 2 design

## OWNER GATE 2: APPROVED — L-1, P-1

Owner approved L-1 and P-1 in-session on 2026-11-20. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Anh/chị chọn **2 điểm**:

1. **Laravel** — đề xuất **L-1**: chỉ nâng riêng gói `laravel/framework` từ 12.69.3 lên **12.73.0** (bản 12.x mới nhất,
   đã sửa lỗi), và ghi trong `composer.json` là tối thiểu 12.71.0 để không ai vô tình quay lại bản lỗi.
   Các thư viện khác giữ nguyên.
2. **picomatch** — đề xuất **P-1**: ghi **một ngoại lệ có lý do** vào file mới `.npm-audit-ignore`, vì **chưa có bản
   sửa nào** và thư viện này chỉ chạy lúc build giao diện, không có trên máy chủ. Gỡ ngoại lệ khi picomatch ra bản sửa.

**Phát hành:** một PR, một Gate 3. Deploy chỉ sau Gate 3, qua `Production Deployment` theo `docs/runbooks/go-live.md`.

## Điểm 1 — laravel/framework (GHSA-63hm-g96r-2m2q, mức thấp)

| Phương án | Nội dung |
|---|---|
| **L-1 (đề xuất)** | `composer require laravel/framework:^12.71.0 --no-update` rồi `composer update laravel/framework` (không kèm phụ thuộc). Thử trước (dry-run trên `main` hiện tại): **chỉ** `laravel/framework` 12.69.3 → 12.73.0 thay đổi trong `composer.lock`. |
| L-2 | `composer update laravel/framework --with-dependencies`: thêm 26 gói khác (Symfony 7.4.x vá lỗi, Carbon 3.15, Monolog 3.13, phpdotenv 5.8…) và 1 gói mới (`symfony/polyfill-time`). Rủi ro thay đổi lớn hơn, không cần cho cảnh báo này. |
| L-3 | Ghi ngoại lệ vào `composer.json` `config.audit.ignore`. Không đề xuất: bản sửa có sẵn và nằm trong `^12.0`. |

## Điểm 2 — picomatch (GHSA-fc5q-39xc-vjpf, mức cao)

| Phương án | Nội dung |
|---|---|
| **P-1 (đề xuất)** | Tạo `.npm-audit-ignore` với đúng một dòng: `GHSA-fc5q-39xc-vjpf # <lý do>` (định dạng GAP-086). Không đổi `package.json` / `package-lock.json`. |
| P-2 | Hạ cấp `vite` 8.3.4 → 6.2.7 và `laravel-vite-plugin` 3.2.0 → 0.2.4 như `npm audit` gợi ý. Không đề xuất: hạ 2 bản lớn, có thể hỏng build, và chỉ né đường dẫn chứ không sửa lỗi. |
| P-3 | Dùng `overrides` trong `package.json` để ép picomatch lên bản khác. Không làm được: mọi bản đã phát hành (≤ 4.0.7) đều bị ảnh hưởng. |

Dòng ngoại lệ đề xuất (một dòng):

```
GHSA-fc5q-39xc-vjpf # picomatch ReDoS; no patched release (<=4.0.7 affected, checked 2026-11-20); dev-only via vite/laravel-vite-plugin at build time, node_modules not deployed; globs come from our config, not users (GAP-127). Remove when picomatch ships a fix.
```

Ngoại lệ này chỉ bỏ qua đúng mã GHSA này. Lỗ hổng high/critical khác của npm vẫn làm CI đỏ như hiện nay. Danh sách ngoại
lệ được in trong job summary mỗi lần chạy (đã có từ GAP-086), nên ai xem CI cũng thấy.

**Gỡ ngoại lệ sau này:** khi picomatch phát hành bản sửa, một PR nhỏ cập nhật `package-lock.json` (`npm update picomatch`
hoặc theo vite) và xoá dòng này. Việc theo dõi đó **không** nằm trong GAP-127; nếu Owner muốn, ghi thành một mục trong
`OPERATIONAL_GAP_REGISTER.md`.

## Files (allowlist)

- `composer.json` (chỉ dòng `laravel/framework`)
- `composer.lock` (chỉ `laravel/framework` và hash nội dung)
- `.npm-audit-ignore` (mới, một dòng)
- `docs/owner-decisions/GAP-127/*`, `docs/audits/2026-11-18-gap-127-dependency-advisories-evidence.md`,
  `docs/superpowers/plans/2026-11-20-gap-127-dependency-advisories.md`

Không đổi code sản phẩm, test, hay workflow CI. File khác cần đổi sẽ hỏi Owner trước và ghi thành bổ sung.

## Owner amendment 1 (approved 2026-11-20): P-1 dropped

Owner decision in-session on 2026-11-20, verbatim: 'Drop P-1'. Answer to the agent's question
'Amend Gate 2 to drop P-1 (no .npm-audit-ignore), since the advisory was withdrawn?'.

Reason: GitHub withdrew GHSA-fc5q-39xc-vjpf on 2026-11-18 15:38 UTC (`withdrawn_at` on the advisory).
`npm audit --package-lock-only` on this branch reports 0 vulnerabilities, and the latest `main` run
(44601911747, 2026-11-19) fails only on `composer audit`.

Effect: `.npm-audit-ignore` is **not** created; it is removed from the allowlist. L-1 is unchanged.

## Owner amendment 2 (approved 2026-11-20): option 2-A, test mocks

Owner decision in-session on 2026-11-20, verbatim: 'Approve 2-A (fix mocks)'. Answer to the agent's question
'Amendment 2: which option for the 5 tests that break on Laravel 12.73.0?', where 2-A was "stay on 12.73.0 and add
`DB::shouldReceive('getConnections')->andReturn([])` to the 4 test files; no assertion removed or loosened, no product
code change" and 2-B was "lock 12.72.0, no test change".

Reason: Laravel 12.73.0 (not 12.71.0 or 12.72.0) adds a teardown step in
`Foundation/Testing/Concerns/InteractsWithTestCaseLifecycle.php` that calls `getConnections()` on the `db` instance.
Tests that replace it with a strict Mockery mock via `DB::shouldReceive(...)` then fail with
`BadMethodCallException: ... getConnections(), but no expectations were specified`. Exactly 5 tests went from pass to
error (local full-suite comparison, and Unit / Feature / `test` jobs on PR head a7eb64d9).

Added to the allowlist, only to add that expectation where `DB` is mocked:

- `tests/Unit/Dashboard/DashboardServiceTest.php`
- `tests/Unit/Services/ServicesTest.php`
- `tests/Feature/Dashboard/DashboardApiTest.php`
- `tests/Feature/Deployment/ProductionReadinessEndpointTest.php`

## Ngoài phạm vi

- Nâng Laravel lên 13, hay nâng các thư viện khác (L-2).
- Đổi cách CI chấm điểm cảnh báo.
- Tự động nhắc gỡ ngoại lệ picomatch.
- Deploy.

## Verification required at Gate 3

- `composer audit` exit 0; `npm audit` sau khi trừ ngoại lệ không còn high/critical.
- Job **Security Vulnerability Scan** xanh trên đúng head của PR.
- Toàn bộ Unit + Feature + Integration: kết quả không đổi so với `main` (cùng số test, không test nào mới đỏ).
- `npm run build` vẫn chạy được (build không đổi vì `package-lock.json` không đổi).
- Diff chỉ gồm các file trong allowlist; `composer.lock` chỉ đổi `laravel/framework`.

## Rollback

Revert PR: `composer.lock` về 12.69.3 và xoá `.npm-audit-ignore`. Không có migration, không đổi dữ liệu.

## Decision Needed

Owner chọn: `APPROVE GAP-127 Gate 2 L-1 P-1` (hoặc phương án khác cho từng điểm) / Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, deploy.
