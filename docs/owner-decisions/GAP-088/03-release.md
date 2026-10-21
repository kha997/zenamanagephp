---
work_id: GAP-088
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
  spec: docs/audits/2026-10-21-gap-088-pecl-retry-evidence.md
  plan: docs/superpowers/plans/2026-10-21-gap-088-pecl-retry.md
  branch: docs/GAP-088-pecl-retry
  pr: https://github.com/kha997/zenamanagephp/pull/361
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-21T18:23:52+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-21, verbatim: 'APPROVE GAP-088 Gate 3'. Given after the packet was presented at PR head a68788055a3f39b64a7dc0443b0983072ef78adb (implementation CI 34/34 green at b87dc768); bound to implementation subject b87dc768a6c16b1447b72d243034c0473275fd2f and implementation-tree digest ab28ecafcec3aed0964dc09231a144ad18453c7239cd8b5035ed986a43999dcb (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-21T18:08:21+07:00"
  updated_at: "2026-10-21T18:23:52+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-088 at subject b87dc768. 08b72254 (Gate 2 Option A): Dockerfile.prod (inside the extension RUN), Dockerfile.websocket and Dockerfile replace 'pecl install redis' with a 5-attempt loop around 'pecl install redis-6.3.0' that waits 15/30/45/60 s between attempts, logs 'pecl install redis-6.3.0 failed (attempt N/5)' and exits 1 after the fifth failure; the rest of each RUN is unchanged. Local check: the loop under dash with a stub pecl failing 0/2/4/5/9 times made 1/3/5/5/5 calls, slept 15..60 s as designed and exited 0/0/0/1/1. Temporary proof (f6b9a24b, d861603d, 3a687ed6; reverted by d68a51d6, whose tree is identical to 08b72254): Dockerfile.prod asking for redis-0.0.1 turned Docker Security Scan red after attempts 1/5..5/5 with 15/30/45/60 s waits; a temporary workflow built Dockerfile.websocket and php -m listed redis, but only after adding linux-headers and $PHPIZE_DEPS in the CI workspace. Pre-existing on main, unrelated to GAP-088 and disclosed: Dockerfile.websocket fails to compile the sockets extension (linux/sock_diag.h missing) and its apk add lacks $PHPIZE_DEPS; Dockerfile copies docker/php/php-fpm.conf (missing; the file is docker/php-fpm.conf), and with that worked around its docker-php-ext-install step (line 32) still fails, so its retry loop could not be run in CI. Owner amendment ('Chấp nhận + GAP riêng (Recommended)') accepts the local dash check plus the identical loop proven in the other two files as verification for Dockerfile, and leaves the pre-existing breakages to GAP-089. b87dc768 records that amendment (docs only). Exact-head CI 34/34 green at b87dc768, including Docker Security Scan (Dockerfile.prod built with redis-6.3.0, smoke tests and Trivy)."
technical_evidence:
  base_sha: "e14bdaa0b0b9439032ee64e81fb23094234cc093"
  subject_sha: "b87dc768a6c16b1447b72d243034c0473275fd2f"
  implementation_tree_digest: "ab28ecafcec3aed0964dc09231a144ad18453c7239cd8b5035ed986a43999dcb"
  verified_pr_head_sha: "b87dc768a6c16b1447b72d243034c0473275fd2f"
  verified_at: "2026-10-21T18:08:21+07:00"
owner_decision_binding:
  implementation_tree_digest: "ab28ecafcec3aed0964dc09231a144ad18453c7239cd8b5035ed986a43999dcb"
  decision_recorded_at: "2026-10-21T18:23:52+07:00"
---

# GAP-088 — Gate 3 release decision (pecl retry and pin)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-21 ("APPROVE GAP-088 Gate 3"), bound to implementation subject
`b87dc768a6c16b1447b72d243034c0473275fd2f` and implementation-tree digest `ab28ecafcec3aed0964dc09231a144ad18453c7239cd8b5035ed986a43999dcb`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Build Docker hỏng ngẫu nhiên khi pecl.php.net chập chờn,
vì Dockerfile tải redis một lần, không thử lại, và không ghim phiên bản (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A):**

| Commit | Thay đổi |
|---|---|
| `08b72254` | 3 Dockerfile cài `redis-6.3.0`; nếu lỗi thì thử lại tối đa 5 lần, chờ 15/30/45/60 giây |
| `f6b9a24b`, `d861603d`, `3a687ed6` | Commit tạm để kiểm chứng; `d68a51d6` hoàn tác cả ba |
| `b87dc768` | Ghi Owner amendment (chỉ tài liệu) |

Không đổi extension, base image hay workflow nào khác.

**3. Bằng chứng** — base `e14bdaa0`; subject `b87dc768a6c16b1447b72d243034c0473275fd2f`;
digest `ab28ecafcec3aed0964dc09231a144ad18453c7239cd8b5035ed986a43999dcb`.

- Chạy thử trên máy bằng `dash`, với `pecl` giả lỗi 0, 2, 4, 5 và 9 lần: kết quả
  đúng thiết kế. Thành công ngay khi còn lượt thử; dừng sau đúng 5 lần và báo lỗi khi
  hết lượt.
- Phép thử đỏ trên CI: `Dockerfile.prod` cài một phiên bản không tồn tại. Log cho thấy
  thử đủ 5/5 lần, chờ đúng 15/30/45/60 giây, rồi mới đỏ.
- `Dockerfile.websocket` build được và `php -m` có `redis`. Để build được, phải tạm
  thêm `linux-headers` và `$PHPIZE_DEPS` trong không gian làm việc của CI (xem mục 4).
- CI chạy đúng trên commit `b87dc768`: 34/34 xanh, kể cả Docker Security Scan.
  Bước này build `Dockerfile.prod` với redis-6.3.0 rồi chạy smoke test và Trivy.

**4. Công khai** (theo Owner amendment)

Hai Dockerfile **vốn đã không build được trên main**, vì những lỗi có từ trước
GAP-088:
- `Dockerfile.websocket`:
  - thiếu `linux-headers`, nên extension `sockets` không biên dịch được;
  - thiếu `$PHPIZE_DEPS`, mà PECL cần.
- `Dockerfile` (gốc):
  - copy `docker/php/php-fpm.conf`, file không tồn tại (file thật ở `docker/php-fpm.conf`);
  - bước `docker-php-ext-install` (dòng 32) cũng hỏng.
  - Hai lỗi này xảy ra trước bước redis, nên vòng thử lại trong file này **chưa chạy
    được trên CI**. Nó chỉ được kiểm chứng bằng hai cách: chạy thử trên máy bằng
    `dash`, và đoạn mã giống hệt đã chạy thật ở 2 file kia.

Sẽ sửa hai file này ở GAP-089.

**5. Ảnh hưởng**

- Docker Security Scan và build release/deploy chịu được PECL lỗi tạm thời. Mỗi
  lần build chờ thêm tối đa 2,5 phút khi PECL lỗi.
- Các bản build đều dùng cùng phiên bản phpredis 6.3.0.

**6. Rủi ro còn lại** — Thấp:
- Nếu PECL hỏng lâu hơn khoảng 2,5 phút, build vẫn đỏ.
- Muốn nâng phpredis thì phải sửa số phiên bản ở 3 file.
- Vòng lặp trong `Dockerfile` chưa chạy trên CI (xem mục 4).

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-088 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không sửa các lỗi có sẵn của `Dockerfile` và
`Dockerfile.websocket` (để GAP-089).
