---
work_id: OWN-2026-007
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-08-10-gap-010c-reproduction-evidence.md
  plan: null
  branch: docs/OWN-2026-007-post-p1-gap-register-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/255
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-10T09:56:05+07:00"
  owner_response_reference: "OWN-2026-007 Gate 2 approval via owner instruction 2026-08-10T11:20:03+07:00"
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-10T09:56:05+07:00"
  updated_at: "2026-08-10T11:20:03+07:00"
generated_by: agent
---

## OWNER GATE 2: APPROVED

Owner decision: APPROVE (commit 0c796570e523da57225148eef8905dcb21a58880 / PR #255).

Thiết kế chi tiết Gate 2 cho OWN-2026-007, trình bày taxonomy mới và trạng thái đề xuất cho GAP-010, GAP-010b, GAP-010c và GAP-034. Owner phê duyệt thiết kế để tiến hành cập nhật `OPERATIONAL_GAP_REGISTER.md`.

## Phạm vi Gate 2

Chỉ xử lý trạng thái trong `OPERATIONAL_GAP_REGISTER.md`. Không đổi mã nguồn, không tạo implementation plan, không chỉnh GAP-032/GAP-033, không merge/release.

## Taxonomy đề xuất

| Trạng thái chính xác | Ngữ nghĩa glossary | Được dùng khi | Bằng chứng yêu cầu | Terminal? | Có thể mở lại? |
|---|---|---|---|---|---|
| `RESOLVED (verified)` | confirmed defect existed and was fixed/verified. | Lỗi thật, có patch/khắc phục, đã qua kiểm tra | Diff thay đổi + kết quả kiểm tra | Có | Không — trừ khi tái phát hiện bằng chứng mới bắt buộc mở lại (cần Gate 1 mới) |
| `CLOSED — NOT REPRODUCED (verified)` | suspected/candidate defect was investigated on a defined baseline, current defect could not be demonstrated, and no remediation implementation is claimed. | Nghi vấn đã kiểm tra kỹ, không tái hiện được, không còn hành động cần làm | Báo cáo tái hiện (số ca, baseline, kết quả) + lý do không tái hiện | Có | Có — nếu bằng chứng mới xuất hiện cho thấy lỗi có thể tái hiện trên baseline khác hoặc điều kiện khác (cần Gate 1 mới) |
| `CLOSED (verified)` | parent/cluster terminal state: all children are terminal and no actionable child remains, regardless of whether individual children terminated as RESOLVED or CLOSED — NOT REPRODUCED. | Mục cha với tất cả dòng con đã đạt trạng thái terminal | Bằng chứng terminal của từng dòng con | Có | Có — nếu một dòng con bị mở lại (cần Gate 1 mới) |
| `OPEN (verified)` | Lỗi thật đã xác nhận, chưa sửa, cần ưu tiên | Lỗi đang mở trên code path đang hoạt động | Trích dẫn code live + mô tả rủi ro | Không | Có — sẽ đóng khi sửa xong và xác minh |
| `REOPENED FOR REPRODUCTION` | Một phát hiện mới/tiềm ẩn cần bước tái hiện lỗi thật trước khi xác nhận là gap | Tìm thấy dấu hiệu khả nghi nhưng chưa có bước tái hiện nào | Mô tả surface khả nghi + lý do nghi ngờ | Không | Có — sẽ chuyển sang RESOLVED hoặc CLOSED sau tái hiện |
| `PARTIALLY RESOLVED (verified)` | Mục cha có ít nhất 1 dòng con còn mở/actionable; không thể gọi toàn bộ là RESOLVED | Mục cha với trạng thái con khác nhau, ít nhất 1 con chưa đóng | Bằng chứng của từng dòng con | Không | Có — khi tất cả con đều đóng thì chuyển sang trạng thái cha phù hợp |

### Quy tắc không chồng chéo

- `RESOLVED (verified)` **không bao giờ** dùng cho phát hiện không tái hiện được. Nếu không có patch, không phải RESOLVED.
- `CLOSED — NOT REPRODUCED (verified)` **không bao giờ** dùng cho lỗi đã sửa. Nếu có patch, đó là RESOLVED.
- Hai trạng thái trên là terminal và loại trừ lẫn nhau.

## Bảng đối chiếu trạng thái đề xuất

| ID | Nội dung | Trạng thái hiện tại trong sổ đăng ký | Trạng thái đề xuất | Ghi chú |
|---|---|---|---|---|
| GAP-010 | Cụm lỗi nhỏ: CSV formula injection, lộ secret qua flash message, OOM khi export, lệch timezone Gantt | `PARTIALLY RESOLVED (verified)` | `CLOSED (verified)` | Tất cả 3 dòng con đã đạt trạng thái terminal; không còn hành động nào cần làm cho cụm này |
| GAP-010a | Đường xuất báo cáo chính thức: CSV formula injection + lộ secret qua flash message | `RESOLVED (verified 2026-08-06)` | `RESOLVED (verified)` | Giữ nguyên — đã Gate 3 approved, đã merge PR #253 |
| GAP-010b | Đường xuất CSV cũ (`ExportController::generateCsv()`): formula injection + OOM | `OPEN (verified 2026-08-06)` | `RESOLVED (verified)` | Giữ nguyên — đã Gate 3 approved, đã merge PR #253 |
| GAP-010c | Nghi vấn lệch múi giờ Gantt — `/schedule` | `REOPENED FOR REPRODUCTION (2026-08-06)` | `CLOSED — NOT REPRODUCED (verified 2026-08-10)` | Tái hiện 8 ca, SHIFTED=0, không cần remediation |
| GAP-034 | Đường xuất CSV task/project thiếu tenant isolation | `GATE 1 APPROVED — GATE 2 PENDING (2026-08-07)` | `RESOLVED (verified)` | Giữ nguyên — đã Gate 3 approved, đã merge PR #253 |

## GAP-010c — chi tiết phân loại

- **Trạng thái chính xác:** `CLOSED — NOT REPRODUCED (verified 2026-08-10)`
- **Lý do:** 8 ca kiểm tra trên baseline `1325c0e6`, tất cả `SHIFTED=0`. Không có chuyển đổi múi giờ phía client trên `/schedule`. Schema `tasks.start_date`/`end_date` là `DATE` theo migration. Không có remediation nào được claim.
- **Bằng chứng EEL:** `docs/audits/2026-08-10-gap-010c-reproduction-evidence.md`
- **Terminal:** Có
- **Có thể mở lại:** Có, nếu bằng chứng mới xuất hiện (cần Gate 1 mới)

## GAP-010 cha — chi tiết phân loại

- **Trạng thái chính xác:** `CLOSED (verified)`
- **Lý do:** Cả 3 dòng con đã đạt trạng thái terminal:
  - GAP-010a = RESOLVED (có patch, đã xác minh)
  - GAP-010b = RESOLVED (có patch, đã xác minh)
  - GAP-010c = CLOSED — NOT REPRODUCED (không có patch, điều tra xong, không tái hiện được)
- Không còn dòng con nào ở trạng thái `OPEN`, `REOPENED`, hay `PARTIALLY RESOLVED`.
- Trạng thái cha mô tả: "cụm này đã đóng hoàn toàn, không còn hành động nào còn lại" — không tự động gọi là RESOLVED vì GAP-010c không có patch.
- Trạng thái con giữ nguyên ngữ nghĩa riêng: GAP-010a/b là RESOLVED, GAP-010c là CLOSED — NOT REPRODUCED.

## GAP-010b và GAP-034

Hai gap này đã có Gate 3 approved và merge trong PR #253. Trạng thái `RESOLVED (verified)` là chính xác và không cần thiết kế lại.

## Bảo toàn lịch sử

- Không xoá hay ghi đè bất kỳ ghi chú audit gốc nào.
- GAP-010c giữ nguyên đầy đủ ngữ cảnh: phát hiện múi giờ gốc, mở lại để tái hiện, commit bình thường hóa lịch sử `63afc21f`, kết quả tái hiện 2026-08-10.
- Các trạng thái cũ được ghi lại trong phần mô tả dòng (nếu cần), không xóa khỏi sổ đăng ký.

## Tiêu chí chấp nhận

1. Taxonomy mới có đủ trạng thái để biểu diễn chính xác 4 hàng GAP-010/GAP-010b/GAP-010c/GAP-034 mà không chồng chéo ngữ nghĩa.
2. GAP-010c được ghi `CLOSED — NOT REPRODUCED (verified)` với bằng chứng EEL liên kết.
3. GAP-010 cha được ghi `CLOSED (verified)` vì không còn dòng con actionable.
4. GAP-010b và GAP-034 giữ nguyên `RESOLVED (verified)`.
5. Không chỉnh `OPERATIONAL_GAP_REGISTER.md` ở bước này.
6. Không tạo implementation plan.
7. Toàn bộ có thể hoàn tác bằng revert đúng một commit tài liệu.

## Rollback

Hoàn tác bằng revert commit tài liệu Gate 2 (nếu owner từ chối). Không có tác dụng phụ nào.

## Loại trừ phạm vi

Gate 2 này KHÔNG bao gồm:
- chỉnh `OPERATIONAL_GAP_REGISTER.md`;
- mã nguồn production;
- kiểm thử;
- migration;
- thay đổi route;
- implementation plan;
- Gate 3 packet;
- merge/release;
- GAP-032;
- GAP-033.

## Câu hỏi Owner (trả lời trong thiết kế)

1. **Trạng thái mới dùng để làm gì?**
   Dùng để ghi nhận một nghi vấn đã được điều tra kỹ trên baseline xác định nhưng không thể tái hiện — tách biệt hoàn toàn với lỗi đã sửa.

2. **Khác `RESOLVED` ở đâu?**
   `RESOLVED` yêu cầu có patch/khắc phục và xác minh lại. `CLOSED — NOT REPRODUCED` không có patch, chỉ có báo cáo điều tra kết luận không tái hiện được.

3. **GAP-010c sẽ được ghi thế nào?**
   `CLOSED — NOT REPRODUCED (verified 2026-08-10)`, tham chiếu `docs/audits/2026-08-10-gap-010c-reproduction-evidence.md`. Giữ nguyên lịch sử: phát hiện gốc, mở lại, commit `63afc21f`, kết quả 2026-08-10.

4. **GAP-010 cha sẽ được ghi thế nào?**
   `CLOSED (verified)`. Tất cả 3 dòng con đã terminal. Trạng thái con giữ nguyên cách kết thúc riêng (RESOLVED cho a/b, CLOSED — NOT REPRODUCED cho c).

5. **GAP-010b/GAP-034 thay đổi ra sao?**
   Không thay đổi. Cả hai giữ nguyên `RESOLVED (verified)` dựa trên Gate 3 approved và PR #253 đã merge.

6. **Điều gì hoàn toàn không thay đổi?**
   Không chỉnh `OPERATIONAL_GAP_REGISTER.md`. Không đổi mã nguồn, test, migration, route. Không tạo implementation plan. Không bắt đầu GAP-032.

7. **Sau khi Owner duyệt Gate 2 thì chính xác file nào mới được phép chỉnh?**
   Chỉ `OPERATIONAL_GAP_REGISTER.md` (tài liệu đăng ký gap). Mọi thay đổi khác đều nằm ngoài phạm vi và cần quyết định owner riêng.

## Decision Needed

Owner chọn một trong: **Phê duyệt để tiến hành cập nhật tài liệu (sửa `OPERATIONAL_GAP_REGISTER.md` đúng như bảng trên — không phải mã nguồn)** / **Yêu cầu chỉnh sửa** / **Từ chối**.

## What the owner is NOT being asked to decide

Owner không được yêu cầu phê duyệt thay đổi mã nguồn, implementation plan, migration, route, hay bất kỳ triển khai gap nào — chỉ quyết định taxonomy và trạng thái đề xuất ở trên có đúng và đủ để cập nhật `OPERATIONAL_GAP_REGISTER.md` hay không.
