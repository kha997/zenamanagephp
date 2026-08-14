---
work_id: GAP-011
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: null
  plan: null
  branch: docs/GAP-011-debug-route-cleanup-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/260
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: "docs/owner-decisions/GAP-011/03-release.md"
superseded_by: null
timestamps:
  created_at: "2026-08-14T18:08:09+07:00"
  updated_at: "2026-08-14T18:08:09+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "The single previously-blocked mandatory criterion is now verified. GAP-035 (duplicate route-name collision blocking php artisan route:cache application-wide) merged to main 2026-08-14 with Owner Gate 3 approval. GAP-011's branch was reconciled against the new main (clean auto-merge, no conflicts — GAP-035's route renames and GAP-011's _debug removal touch disjoint lines) at 0f74ce86, then the cached-production-absence test (previously markTestSkipped with a BLOCKED reason) was re-run: route:cache now succeeds under production, and the assertion that the cached route table contains zero _debug/* routes and no /debug/{path?} wildcard now runs and PASSES for real, not a skip. The obsolete skip-guard branch and its stale docblock were removed as dead code (only reachable if route:cache failed, which it no longer does), along with the matching skipped-tests baseline entry. Full DebugRouteBoundaryInvariantTest suite: 11/11 pass, 61 assertions. Consumer/documentation regression (DebugRouteDocumentationInvariantTest, LegacyDebugRootRedirectTest): 11/11 pass. Cross-check that GAP-035's own guard still passes unmodified on this merged branch: 7/7 pass. Full local suite: 2186 tests, 7 failures — all in Tests\\Feature\\Dashboard\\DashboardApiTest, all the same pre-existing Illuminate\\Cache\\RedisStore::publish() undefined-method flake observed independently before this branch existed (local-Redis-availability/timing-dependent, not deterministic — a GAP-035 verification run on the same day showed 0 failures), unrelated to routes or to GAP-011/GAP-035 changes, not fixed under GAP-011. All exact-head CI checks are green on PR #260 at this SHA, including Owner Governance Lint and test-routes-guardrails."
technical_evidence:
  subject_sha: "2b1a256e5f245391bad25b897f3850fdcfdece3f"
  implementation_tree_digest: "cef922bed9f586518529c89a873cacc8a34c2ec5ef9fdda102a9002631424ede"
  verified_pr_head_sha: "2b1a256e5f245391bad25b897f3850fdcfdece3f"
  verified_at: "2026-08-14T18:08:09+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary
Việc dọn dẹp route `_debug/*` (GAP-011) đã hoàn tất từ trước và chỉ còn thiếu đúng một bằng chứng kỹ thuật: xác nhận rằng sau khi Laravel "đóng gói sẵn" (cache) bảng route để chạy production, không còn route `_debug/*` nào lọt vào bảng đó. Bằng chứng này trước đây không thể lấy được vì một lỗi kỹ thuật khác (GAP-035, không liên quan tới GAP-011) làm cho toàn bộ thao tác "đóng gói route" bị hỏng trên cả hệ thống. GAP-035 nay đã được owner duyệt và merge vào main. Sau khi GAP-011 đối chiếu lại với main mới, bằng chứng còn thiếu đó nay đã lấy được và đạt yêu cầu. Sẵn sàng phát hành, chờ quyết định.

## Gói quyết định phát hành — GAP-011: Ranh giới bảo vệ route gỡ lỗi (`_debug/*`)

**1. Vấn đề đã xảy ra là gì?**
Các route gỡ lỗi nội bộ (`_debug/*`, dùng để hỗ trợ đội kỹ thuật) trước đây bị khai báo rải rác ở nhiều nơi trong mã nguồn, không có một ranh giới bảo vệ rõ ràng, khiến việc kiểm soát "route nào chỉ chạy ở môi trường phát triển, route nào không được lọt vào production" khó xác minh và dễ sai sót khi có thay đổi trong tương lai.

**2. Người dùng nào bị ảnh hưởng?**
Không có người dùng cuối bị ảnh hưởng trực tiếp — đây là việc củng cố an toàn nội bộ, không phải tính năng hiển thị.

**3. Bây giờ hệ thống hoạt động thế nào?**
Toàn bộ route `_debug/*` còn lại (2 route) và redirect tương thích cũ (1 route) được gom về đúng một nơi khai báo duy nhất (`routes/debug.php`), chỉ được nạp ở môi trường phát triển/kiểm thử, luôn đi kèm một lớp kiểm tra quyền truy cập riêng. Có một bộ kiểm tra tự động vĩnh viễn đảm bảo nếu sau này ai đó vô tình khai báo route `_debug/*` ở nơi khác, hoặc quên lớp kiểm tra quyền, hệ thống kiểm thử sẽ báo lỗi ngay lập tức.

**4. Rủi ro nào đã được đóng lại?**
Rủi ro route gỡ lỗi nội bộ vô tình lọt vào production (kể cả sau khi bảng route được "đóng gói" để tăng tốc) đã được đóng lại và xác minh đầy đủ ở cả hai trạng thái: chưa đóng gói và đã đóng gói.

**5. Đã kiểm thử những gì?**
Toàn bộ 11 kiểm tra ranh giới bảo vệ đều đạt, gồm kiểm tra bảng route sau khi đóng gói cho production không còn route `_debug/*` nào — đây là kiểm tra trước đây không chạy được (do phụ thuộc GAP-035), nay đã chạy thật và đạt. Kiểm tra tài liệu liên quan và luồng đăng nhập nhanh dùng để demo đều đạt. Bộ kiểm thử toàn hệ thống đã chạy lại; có 7 lỗi xuất hiện nhưng đều thuộc một vấn đề đã biết từ trước, không liên quan tới route hay tới GAP-011/GAP-035 (một hàm cache Redis không tồn tại trên máy phát triển cục bộ, phụ thuộc vào việc Redis có đang chạy đúng lúc kiểm thử hay không) — không sửa dưới GAP-011.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Không đổi hành vi hay đường dẫn của bất kỳ route nghiệp vụ nào. Không đụng tới việc đổi tên route đã xử lý riêng ở GAP-035.

**7. Rủi ro còn lại là gì?**
Không có rủi ro mất/lộ dữ liệu. Rủi ro còn lại thấp và thuần kỹ thuật.

**8. Có thể hoàn tác không?**
Có — có thể quay lại phiên bản trước an toàn.

**9. Đề xuất của đội kỹ thuật:** Phát hành (Approve).

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Không được yêu cầu mở pull request kỹ thuật, đọc nhật ký kiểm tra tự động, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh; owner chỉ quyết định có phát hành hay không.
