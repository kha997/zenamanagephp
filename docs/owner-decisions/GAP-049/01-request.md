---
work_id: GAP-049
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-09-03-gap-049-production-readiness-evidence.md
  plan: null
  branch: worktree-agent-a8ae472e9023bf843
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-03T03:05:00Z"
  owner_response_reference: "GAP-049 Gate 1 Round 1 (relayed via coordinator session, reviewed exact PR head 456fc625ee90b425046ac01755efee20bffcc354 of PR #300, canonical main at review time 0872ac856932193a037ce30f00050179374811af): 'Owner Gate-1 Round 1 decision: CHANGES REQUESTED on PR #300 (pre-correction head 456fc625ee90b425046ac01755efee20bffcc354). The overall problem statement is ACCEPTED as directionally correct — do NOT redo the audit from scratch, do NOT start Gate 2, do NOT write an implementation plan, do NOT touch deployment workflows/app code/secrets/infra, do NOT deploy, do NOT self-approve. This round is narrowly about evidence truthfulness and deployment-surface reconciliation in the two existing docs.' Owner directed 8 corrections, addressed in the Round-2 re-presentation (secret-contract precision for deploy.yml; full re-read of automated-deployment.yml's 5-job surface; fixed A/B/C/D deployment-surface taxonomy; corrected backup evidence; per-workflow rollback split; evidence-bounded 'never deployed' wording after a 669-run Actions history review; corrected migration-isolation finding; Gate-2 recommendation relabeled as a design candidate). | GAP-049 Gate 1 Round 2 (relayed via coordinator session, reviewed exact PR head 7bf506ba737e4d9f127020341c4e44bf09190b01 of PR #300, canonical main at review time 0872ac856932193a037ce30f00050179374811af): 'Owner Gate-1 Round 2 decision: CHANGES REQUESTED on PR #300 (head was 7bf506ba). Round-1's 8 corrections are accepted and must stay in place — dont reopen them except where this new deploy.sh evidence requires wording reconciliation. This is narrowly scoped: no Gate 2, no implementation plan, no workflow/app/infra/secret changes, no deploy.' Owner directed 5 further corrections (numbered 9-13), addressed in this Round-3 re-presentation: (9) the repo-root deploy.sh (the exact script deploy.yml's host runs post-git-pull, distinct from the unreferenced scripts/deploy.sh) was read in full and reclassified as Category C only, never also Category D. (10) concrete deploy.sh hazards were documented from the actual file content: (a) set -e is present; (b) DB_PASSWORD is required via a bash :?-guard and is never provisioned by deploy.yml's SSH action, so successful execution requires an external/pre-provisioned host environment contract that is currently unproven; (c) npm run production is invoked but package.json defines no production script (only build/dev/preview/etc.), so this path cannot currently complete against main as written, stated as fact; (d) the script restarts php8.1-fpm while composer.json requires ^8.2, a confirmed stale host assumption; (e) php artisan db:seed --force runs unconditionally, correcting the prior Gate-1 statement that no deploy mechanism seeds production — DatabaseSeeder's chain was read and characterized as idempotent (firstOrCreate-keyed) but demo/mock-data-producing, specifically creating a fixed-email admin account with a hardcoded password; (f) the scripts in-checkout cp -r . backup/ then rm -rf backup on success was precisely characterized as providing no durable, off-host, or database-inclusive protection, explicitly distinguished from docker-manage.sh backup; (g) the scripts php artisan health:check and non-fatal curl were checked — health:check is a real registered Artisan command, though a naming collision with a second command class (HealthCheckMonitor) declaring the same signature was found and recorded as an unresolved ambiguity, not asserted as a boot failure. (11) the A/B/C/D taxonomy was corrected to be mutually exclusive: root deploy.sh is C only; docker-compose.prod.yml and docker-manage.sh are C only; scripts/deploy.sh and scripts/backup-*.sh remain D only, each verified via git grep across .github/ to have zero references. (12) rollback wording now states the higher-level truthful conclusion no authoritative, proven production-safe rollback path currently exists alongside the preserved per-workflow breakdown, replacing remaining repo-wide no-rollback phrasing. (13) deploy.yml was reassessed as a legacy/deprecation candidate for Gate 2 (not a co-equal design option) given the deploy.sh evidence in (10), with Gate 2 directed to focus its architecture comparison on production.yml vs automated-deployment.yml, still framed as a Gate-2 design INPUT, not an Owner architecture decision. Correction scope strictly bounded to docs/audits/2026-09-03-gap-049-production-readiness-evidence.md and this file; no app/**, src/**, routes/**, .github/workflows/**, database/**, config/** change authorized or made. PR #300 remains Draft throughout; this packet remains gate_status: awaiting_owner / owner_decision.value: none pending a fresh Owner Gate-1 decision on this Round-3 re-presentation."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-03T01:18:13Z"
  updated_at: "2026-09-03T03:05:00Z"
generated_by: agent
---

## Owner Decision History — Round 2 — CHANGES REQUESTED (permanent record, never erased)

**Owner Gate 1 Round 2 decision: CHANGES REQUESTED** (not a rejection — reviewed exact PR head `7bf506ba737e4d9f127020341c4e44bf09190b01`, canonical main `0872ac856932193a037ce30f00050179374811af`). Round 1's 8 corrections were reaffirmed as accepted and not reopened. Full verbatim directive preserved in this file's frontmatter `decision_provenance.owner_response_reference` above. Five further corrections (9–13) were directed, arising from a direct read of the repo-root `deploy.sh` (previously not read in full), and are addressed in this Round-3 re-presentation (see the audit document's now-corrected §1's Category C/D tables and new "Root `deploy.sh`" hazard subsection, §6, §7, §10, §13, §14, §16): (9) root `deploy.sh` reclassified as Category C only (not also D); (10) concrete `deploy.sh` hazards documented — `set -e` present; unprovisioned `DB_PASSWORD`; missing `npm run production` script (path cannot currently complete against `main`); stale `php8.1-fpm` vs. `composer.json`'s `^8.2`; unconditional `db:seed --force` (corrects the prior "no deploy mechanism seeds production" statement) producing an idempotent but hardcoded-password demo admin account; a weak in-checkout "backup" with no durable/off-host protection, distinguished from `docker-manage.sh`'s real backup; a real `health:check` Artisan command with an unresolved naming collision; (11) the A/B/C/D taxonomy corrected to be mutually exclusive; (12) rollback wording corrected to state the higher-level truthful conclusion alongside the preserved per-workflow breakdown; (13) `deploy.yml` reassessed as a legacy/deprecation candidate for Gate 2, not a co-equal option. This Round 2 record is preserved permanently and must not be removed by any future revision.

---

## Owner Decision History — Round 1 — CHANGES REQUESTED (permanent record, never erased)

**Owner Gate 1 Round 1 decision: CHANGES REQUESTED** (not a rejection — reviewed exact PR head `456fc625ee90b425046ac01755efee20bffcc354`, canonical main `0872ac856932193a037ce30f00050179374811af`). The overall problem statement was **accepted as directionally correct**; the correction was narrowly scoped to evidence truthfulness and deployment-surface reconciliation. Full verbatim directive preserved in this file's frontmatter `decision_provenance.owner_response_reference` above. Eight corrections were directed and are addressed in the Round-2 re-presentation (see the audit document's history): (1) `deploy.yml`'s secret contract was wrongly generalized as matching `production.yml`'s 4 secrets; (2) `automated-deployment.yml` was materially under-described (its full staging/production/rollback/blue-green/canary/backup surface); (3) the deployment-surface count was inconsistent across the document; (4) "no workflow runs a backup" was factually wrong; (5) "no rollback exists" was an over-broad global claim; (6) "never deployed" was asserted without exhaustive evidence; (7) a migration-locking guarantee was claimed without proof; (8) the Gate-2 architecture recommendation risked reading as pre-approved. This Round 1 record is preserved permanently and must not be removed by any future revision.

---

## Owner Summary

ZENA has no proven real production deployment: across an exhaustive review of all available Actions run history (669 runs total) for the three workflows that could reach a real host, zero runs show any real deployment step executing with a non-skipped conclusion — every one reports a misleadingly green (or occasionally a misleadingly job-level-failed-with-zero-steps) result while the actual deploy logic is gated behind secrets/conditions that were never satisfied. This request asks the Owner to confirm the problem is real and worth solving, and to authorize a Gate-2 design phase for a truthful, recoverable path to a first controlled deployment.

## Vấn đề vận hành

Không ai có thể phân biệt "chưa cấu hình deploy" với "đã thử và thất bại" chỉ bằng cách nhìn CI: cả 3 workflow có thể chạm tới host thật (`production.yml`, `deploy.yml`, `automated-deployment.yml`) đều báo xanh (hoặc đôi khi báo lỗi ở cấp job nhưng không có bước thật nào chạy) trong khi bước deploy thật luôn bị `skipped` — đã kiểm tra toàn bộ 669 lần chạy Actions còn lưu lại, không phải suy đoán từ vài lần gần nhất. Ba workflow này còn mâu thuẫn nhau về đường dẫn host (`/var/www/zena` vs `/var/www/zenamanage` vs `/opt/zenamanage`), tên secret (`PRODUCTION_USER` vs `PRODUCTION_USERNAME`), và hợp đồng health-check (đầy đủ / hoàn toàn không có / đa điểm). Endpoint `/api/health` mà `production.yml` dùng để xác nhận thành công là dữ liệu cứng (hardcoded), không thật sự kiểm tra database/cache/queue.

## Người dùng bị ảnh hưởng

- Chủ dự án / Owner: đang tin tưởng nhầm rằng vì CI xanh nên hệ thống "đã sẵn sàng deploy" hoặc "đã deploy", trong khi thực tế chưa từng deploy thật lần nào.
- Đội vận hành tương lai (operator đầu tiên dùng ZENA thật): sẽ là người đầu tiên gặp mọi lỗ hổng chưa được phát hiện trong quy trình deploy (không có rollback, không có atomic release, health check giả).
- Bất kỳ ai sau này đọc lịch sử CI và kết luận sai rằng production đã từng chạy thành công.

## Bằng chứng

Bằng chứng đầy đủ, có trích dẫn file/dòng cụ thể và kết quả `gh run view --json jobs` trực tiếp (không suy đoán), nằm trong `docs/audits/2026-09-03-gap-049-production-readiness-evidence.md`. Tóm tắt các điểm cốt lõi:

1. Rà soát toàn bộ 269 lần chạy `production.yml` + 249 lần chạy `deploy.yml` + 151 lần chạy `automated-deployment.yml` (669 lần, toàn bộ lịch sử Actions còn lại qua GitHub API) cho thấy **0 lần** bất kỳ bước deploy thật nào (SSH git-pull, Docker build/push, `docker-compose exec`, `artisan migrate`, backup, health/smoke/performance check, rollback, blue-green, canary) có kết luận khác `skipped`.
2. Đọc trực tiếp nội dung `routes/api.php` cho thấy handler `/health` (đường dẫn chính xác mà `production.yml` gọi `curl -f $PRODUCTION_URL/api/health`) trả về chuỗi cứng `'database' => 'connected'`, không gọi bất kỳ kiểm tra thật nào.
3. Ba workflow (`production.yml`, `deploy.yml`, `automated-deployment.yml`) mâu thuẫn nhau về đường dẫn host (`/var/www/zena` vs `/var/www/zenamanage` vs `/opt/zenamanage`), tên secret (`PRODUCTION_USER` vs `PRODUCTION_USERNAME`), và hợp đồng health-check — không có quyết định nào ghi nhận cơ chế nào là chính thức. `docker-compose.prod.yml` (12 service) được `automated-deployment.yml` gọi tới thật (không phải hạ tầng chết như bản trước từng ghi nhầm), nhưng chưa từng thấy bằng chứng chạy thật.
4. `automated-deployment.yml` có gọi `./docker-manage.sh backup` (bản trước ghi nhầm là "không workflow nào chạy backup") — đọc toàn bộ `create_backup()`/`restore_backup()` cho thấy cả backup lẫn restore đều được cài đặt đầy đủ, nhưng bước gọi backup này luôn `skipped` trong mọi lần chạy đã kiểm tra.
5. `automated-deployment.yml` có job `rollback` với code thật (`git reset --hard HEAD~1`), nhưng đọc kỹ cho thấy đây là rollback chỉ ở mức code, không có bước hạ migration nào — nếu lần deploy trước có migration tiến, rollback này để lại schema và code lệch pha nhau, không an toàn theo mặc định; job này cũng chưa từng thực thi thật lần nào.
6. Đọc toàn bộ `deploy.sh` ở gốc repo (script thật mà `deploy.yml` chạy trên host sau `git pull`) phát hiện: lệnh `npm run production` không tồn tại trong `package.json` hiện tại (chỉ có `build`/`dev`/`preview`...) — với `set -e`, script này **chắc chắn dừng lỗi ở bước đó**, tức là đường deploy này hiện không thể chạy hết được với `main` hiện tại; script đòi hỏi biến môi trường `DB_PASSWORD` mà `deploy.yml` không hề truyền vào; script khởi động lại `php8.1-fpm` trong khi `composer.json` yêu cầu `^8.2`; và — sửa lại một phát biểu Gate-1 trước đó — script này **có** chạy `php artisan db:seed --force` vô điều kiện, tạo ra một tài khoản admin với mật khẩu hardcode (`password`) nếu từng chạy thật trên production.

## Tác động nếu không xử lý

Nếu triển khai thật mà không sửa các lỗ hổng này trước: một lần deploy lỗi giữa chừng (composer/npm/migration fail) sẽ để lại code và schema ở trạng thái nửa vời, không có cách rollback đã định nghĩa, không có chế độ bảo trì (`artisan down`), và endpoint health-check sẽ vẫn báo "khỏe mạnh" ngay cả khi database/cache/queue thực sự đã hỏng — nghĩa là đội vận hành có thể tin production đang chạy tốt trong khi nó không hề.

## Phạm vi đề xuất

GAP-049 sẽ (qua các Gate tiếp theo, không phải Gate này) chọn MỘT cơ chế deploy làm chính thức, thiết kế lại nó để có atomic release + rollback thật, sửa health-check để kiểm tra thật, xác lập hợp đồng biến môi trường production đầy đủ, và thiết kế (không triển khai) một chuỗi smoke-test chấp nhận cho lần deploy thật đầu tiên.

## Loại trừ rõ ràng

- Không đụng đến ngữ nghĩa nghiệp vụ CRM/Lead/Opportunity/Quote/Contract/Project/Service-Line — nếu chuỗi smoke-test chấp nhận cần chạm vào các phần đó, việc đó phải tách thành Work ID riêng qua Design Dependency Preflight.
- Không tái mở phạm vi GAP-042 (RBAC production fidelity) — đã đóng và release.
- Gate 1 này không đề xuất giải pháp kỹ thuật, không chọn kiến trúc, không cấu hình secret thật, không deploy thật, không viết implementation plan.

## Đề xuất

Đội đề xuất: xử lý ngay ở Gate 2 (không defer), vì rủi ro "CI xanh giả" đang tồn tại ngay bây giờ trên `main` và có thể khiến bất kỳ ai — kể cả Owner — hiểu sai trạng thái deploy thật của hệ thống.

## Decision Needed

Owner chọn một: Approve để tiến sang Gate 2 (thiết kế kiến trúc deploy + hardening) / Yêu cầu thay đổi phạm vi bằng chứng / Từ chối.

## What the owner is NOT being asked to decide

Owner không được yêu cầu chọn kiến trúc deploy cụ thể (VPS đơn/Docker Compose/registry), không được yêu cầu cung cấp host/domain/credentials ngay bây giờ, không được yêu cầu phê duyệt bất kỳ thay đổi code/workflow/migration nào — Gate 1 này chỉ hỏi liệu vấn đề có thật và đáng giải quyết hay không, và liệu phạm vi bằng chứng trong tài liệu audit có đủ và đúng hay không.
