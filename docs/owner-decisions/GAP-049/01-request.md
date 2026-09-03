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
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-03T01:18:13Z"
  updated_at: "2026-09-03T01:18:13Z"
generated_by: agent
---

## Owner Summary

ZENA has no proven real production deployment: the only workflow that could reach a real host (`production.yml`) reports "success" on every push to `main`, but its actual deploy and health-check steps are silently `skipped` because the 4 required secrets were never configured — verified directly against the live GitHub Actions run history, not inferred. This request asks the Owner to confirm the problem is real and worth solving, and to authorize a Gate-2 design phase for a truthful, recoverable path to a first controlled deployment.

## Vấn đề vận hành

Không ai có thể phân biệt "chưa cấu hình deploy" với "đã thử và thất bại" chỉ bằng cách nhìn CI: workflow `Production Deployment` báo xanh (`success`) trên mọi lần push vào `main`, nhưng bước deploy thật và bước health-check thật đều bị `skipped` vì 4 secret bắt buộc (`PRODUCTION_HOST`, `PRODUCTION_USER`, `PRODUCTION_SSH_KEY`, `PRODUCTION_URL`) chưa từng được cấu hình. Ngoài ra repo hiện có 3 cơ chế deploy khác nhau, mâu thuẫn nhau (SSH bare-metal 2 phiên bản với đường dẫn host khác nhau, và Docker Compose/GHCR chưa từng chạy), và endpoint `/api/health` mà chính workflow deploy dùng để xác nhận thành công là dữ liệu cứng (hardcoded), không thật sự kiểm tra database/cache/queue.

## Người dùng bị ảnh hưởng

- Chủ dự án / Owner: đang tin tưởng nhầm rằng vì CI xanh nên hệ thống "đã sẵn sàng deploy" hoặc "đã deploy", trong khi thực tế chưa từng deploy thật lần nào.
- Đội vận hành tương lai (operator đầu tiên dùng ZENA thật): sẽ là người đầu tiên gặp mọi lỗ hổng chưa được phát hiện trong quy trình deploy (không có rollback, không có atomic release, health check giả).
- Bất kỳ ai sau này đọc lịch sử CI và kết luận sai rằng production đã từng chạy thành công.

## Bằng chứng

Bằng chứng đầy đủ, có trích dẫn file/dòng cụ thể và kết quả `gh run view --json jobs` trực tiếp (không suy đoán), nằm trong `docs/audits/2026-09-03-gap-049-production-readiness-evidence.md`. Tóm tắt các điểm cốt lõi:

1. `gh run view 33633749009 --json jobs` (lần chạy `Production Deployment` gần nhất trên `main`) cho thấy step `Deploy to production: skipped` và `Health check: skipped`, trong khi job tổng thể vẫn báo `success`.
2. Đọc trực tiếp nội dung `routes/api.php` cho thấy handler `/health` (đường dẫn chính xác mà `production.yml` gọi `curl -f $PRODUCTION_URL/api/health`) trả về chuỗi cứng `'database' => 'connected'`, không gọi bất kỳ kiểm tra thật nào.
3. Ba workflow khác nhau (`production.yml`, `deploy.yml`, `automated-deployment.yml`) mô tả ba mô hình host không tương thích nhau (`/var/www/zena` vs `/var/www/zenamanage` vs container tại `/opt/zenamanage`), không có quyết định nào ghi nhận cơ chế nào là chính thức.
4. `docker-compose.prod.yml` định nghĩa đầy đủ 12 service (kể cả `backup`, `prometheus`, `grafana`) nhưng không workflow nào từng gọi tới nó.
5. Không có bằng chứng nào trong repo cho thấy một lần restore từ backup đã từng được thực hiện thành công.

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
