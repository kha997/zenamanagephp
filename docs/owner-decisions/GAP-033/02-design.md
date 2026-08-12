---
work_id: GAP-033
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-12-gap033-document-approver-assignment-design.md
  plan: null
  branch: docs/GAP-033-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/258
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-12T21:35:50+07:00"
  owner_response_reference: "Owner explicit Gate 2 approval in-session on 2026-08-12: 'Hướng C, đồng ý.' — approves the business model (Option C, hybrid) only. The three business-rule sub-questions (Câu hỏi 1/2/3 below) were NOT answered by this response and remain open; no answer to them is inferred."
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-12T21:26:08+07:00"
  updated_at: "2026-08-12T21:35:50+07:00"
generated_by: agent
---

## Gate 2 Owner Decision

**APPROVED — Hướng C (kết hợp: mặc định theo quản lý dự án + có thể chỉ định riêng khi cần).** Đây là mô hình nghiệp vụ được chọn cho GAP-033.

**Còn để ngỏ, CHƯA được trả lời, CẦN có trước khi có thể lên kế hoạch triển khai (Gate 3):**
- Câu hỏi 1 — Ai được quyền gán/đổi người duyệt cho một tài liệu?
- Câu hỏi 2 — Được gán làm người duyệt có tự động được quyền duyệt luôn không, hay vẫn phải có quyền duyệt riêng?
- Câu hỏi 3 — Khi tài liệu bị mở lại để sửa, người được gán trước đó có giữ nguyên không?

Không có câu trả lời nào trong 3 câu trên được suy đoán hay tự ý chọn thay Owner. Gate 2 được ghi nhận là APPROVED cho việc chọn Hướng C; việc lên kế hoạch triển khai chi tiết (Gate 3) cho các cơ chế phụ thuộc vào 3 câu hỏi này sẽ cần một xác nhận ràng buộc riêng (binding clarification) trước khi có thể bắt đầu, theo đúng tiền lệ đã áp dụng cho GAP-032.

**Nguồn gốc quyết định:** ghi nhận nội bộ repository dựa trên phản hồi rõ ràng của Owner trong phiên làm việc ngày 2026-08-12. `trust_level: claimed_repo_record`.

## Owner Summary

GAP-033 đề xuất cách hệ thống xác định "tài liệu này ai phải duyệt" thay vì chỉ biết "ai có quyền duyệt trong dự án". Có 3 hướng khác nhau (A/B/C bên dưới) và 3 câu hỏi nhỏ cần Owner quyết định — không có câu nào đúng/sai tuyệt đối, đều là lựa chọn về cách vận hành phù hợp với thực tế công ty.

## Lựa chọn hướng giải pháp (Owner chọn 1 trong 3)

**Hướng A — Chỉ định thủ công cho từng tài liệu.**
Mỗi tài liệu có thể được gán riêng cho một người cụ thể (giống cách hệ thống đang làm với "Báo cáo không phù hợp - NCR" — đã có sẵn, đang hoạt động thật). Nếu không gán, tài liệu vẫn hoạt động như hiện tại (ai có quyền duyệt trong dự án cũng duyệt được).
- Ưu điểm: linh hoạt nhất, tài liệu đặc biệt có thể gán đúng người phù hợp (VD bản vẽ kết cấu gán cho kỹ sư kết cấu thay vì quản lý dự án).
- Nhược điểm: phải nhớ gán thủ công cho từng tài liệu, nếu quên thì tài liệu đó sẽ không xuất hiện trong danh sách việc cần làm cá nhân của ai cả (dù vẫn duyệt được bình thường qua trang tài liệu).

**Hướng B — Mặc định theo quản lý dự án, không cần thao tác gì thêm.**
Mọi tài liệu trong một dự án tự động có "người duyệt mặc định" là quản lý dự án đó — không cần ai làm gì thêm, hệ thống đã biết sẵn ai là quản lý dự án.
- Ưu điểm: không tốn công sức nào, hoạt động ngay lập tức cho mọi dự án đã có quản lý.
- Nhược điểm: không thể gán tài liệu cho người khác ngoài quản lý dự án — mọi tài liệu trong một dự án luôn về tay đúng một người.

**Hướng C — Kết hợp: mặc định theo quản lý dự án, có thể chỉ định riêng khi cần (đề xuất của đội kỹ thuật).**
Mặc định giống Hướng B (tự động, không tốn công), nhưng cho phép chỉ định riêng cho từng tài liệu khi cần (giống Hướng A) — chỉ định riêng sẽ ưu tiên hơn mặc định.
- Ưu điểm: kết hợp cả hai — đa số tài liệu tự động có người phụ trách mà không cần làm gì, các trường hợp đặc biệt vẫn gán riêng được.
- Nhược điểm: cách vận hành có hai lớp (chỉ định riêng → nếu không có thì lấy mặc định) hơi phức tạp hơn một chút so với hai hướng còn lại, cần giải thích rõ cho người dùng.

## Ba câu hỏi cần Owner quyết định (áp dụng cho cả 3 hướng, quan trọng nhất nếu chọn Hướng A hoặc C)

**Câu hỏi 1 — Ai được quyền gán/đổi người duyệt cho một tài liệu?**
☐ (a) Bất kỳ ai được phép sửa tài liệu đó (diện rộng hơn, giống quyền sửa thông tin tài liệu hiện tại)
☐ (b) Chỉ quản lý dự án hoặc Admin (diện hẹp hơn, kiểm soát chặt hơn)

**Câu hỏi 2 — Được gán làm người duyệt có tự động được quyền duyệt luôn không, hay vẫn phải có quyền duyệt riêng?**
Đây là câu hỏi quan trọng nhất về mặt phân quyền. Hệ thống hiện tại (ở nghiệp vụ NCR) đang cho phép: người được gán thì duyệt được luôn, kể cả khi người đó thường ngày không có quyền duyệt gì cả.
☐ (a) Giống NCR — người được gán thì duyệt được luôn (linh hoạt hơn, nhưng có thể mở rộng "ai duyệt được gì" ra ngoài nhóm vai trò hiện tại mỗi khi có người gán ai đó)
☐ (b) Vẫn phải có quyền duyệt riêng — gán tên chỉ là "ưu tiên/nhắc việc cho đúng người", không tự cấp quyền mới (an toàn hơn, nhưng nếu gán nhầm cho người chưa có quyền thì việc gán đó "không có tác dụng" cho tới khi người đó cũng được cấp quyền duyệt)

**Câu hỏi 3 — Khi tài liệu bị "mở lại để sửa" (sau khi đã duyệt/từ chối), người được gán trước đó có giữ nguyên không?**
☐ (a) Giữ nguyên — vẫn là người đó phụ trách cho lần nộp duyệt tiếp theo, trừ khi có ai đổi lại
☐ (b) Xoá — trở về trạng thái "chưa gán ai", phải gán lại nếu muốn chỉ định riêng cho lần tới

## Trước / Sau

**Trước:**
1. Tài liệu chờ duyệt chỉ có một thông tin: "ai có quyền duyệt tài liệu trong dự án/tenant này thì duyệt được."
2. Không có cách nào biết trước "tài liệu này ai nên là người xử lý" — chỉ biết sau khi có người thực sự bấm duyệt.
3. Tài liệu chờ duyệt không thể xuất hiện trong danh sách việc cần làm cá nhân của bất kỳ ai.

**Sau (nếu chọn Hướng C — đề xuất):**
1. Mỗi tài liệu có một "người duyệt được chỉ định" — mặc định là quản lý dự án, có thể đổi riêng cho từng tài liệu nếu cần.
2. Tài liệu chưa từng được chỉnh sửa liên quan tới việc gán người duyệt vẫn hoạt động giống hệt như trước — không có gì bị phá vỡ.
3. Hệ thống có thể (ở một bước làm sau, không nằm trong phạm vi GAP-033) hiển thị đúng tài liệu đang chờ đúng người trong danh sách việc cần làm hôm nay của người đó.

## Vai trò bị ảnh hưởng

- **Quản lý dự án:** tiếp tục là người duyệt mặc định như thực tế đang diễn ra hôm nay (không có gì thay đổi bắt buộc), nhưng nay được ghi nhận tường minh trong hệ thống thay vì ngầm định qua quyền vai trò.
- **Người tạo/nộp tài liệu:** có thể (tuỳ câu trả lời Câu hỏi 1) chỉ định riêng người duyệt cho tài liệu cụ thể nếu cần.
- **Người được chỉ định làm người duyệt riêng cho một tài liệu:** tuỳ câu trả lời Câu hỏi 2, có thể duyệt được tài liệu đó dù không có vai trò Quản lý dự án/Admin, hoặc phải có thêm quyền duyệt mới duyệt được.

## Được phép / Không được phép

- Được phép: xem/đổi người duyệt được chỉ định cho một tài liệu (tuỳ ai được phép làm việc này theo Câu hỏi 1).
- Không được phép: gán người duyệt cho tài liệu của dự án/tenant khác (giữ nguyên nguyên tắc cách ly dữ liệu khách hàng hiện tại — không thay đổi).
- Không thay đổi: cách duyệt/từ chối tài liệu hiện tại (GAP-031/032) — GAP-033 chỉ thêm "ai nên là người bấm", không đổi "bấm thế nào".

## Trạng thái và bước tiếp theo

GAP-033 không thêm trạng thái mới cho tài liệu — Vòng đời (Nháp/Đang xem xét/Đã phát hành/Đã lưu trữ) và quy trình duyệt (Chưa nộp/Chờ duyệt/Đã duyệt/Bị từ chối) từ GAP-032 giữ nguyên. GAP-033 chỉ thêm một thông tin đi kèm: "ai được chỉ định phụ trách" — không phải một trạng thái, không đổi luồng chuyển trạng thái nào đã có.

## Ngoại lệ

- Tài liệu chưa từng được ai gán người duyệt riêng, và dự án của tài liệu đó cũng chưa có quản lý dự án: hoạt động giống hệt hôm nay, không có người phụ trách cụ thể, ai có quyền duyệt chung vẫn duyệt được.
- Nhiều người cùng duyệt một tài liệu theo trình tự (VD phải qua 2 người mới coi là duyệt xong): KHÔNG nằm trong phạm vi GAP-033 — chỉ có một người duyệt được chỉ định cho mỗi tài liệu.

## Hành vi người dùng nhìn thấy

Trên trang tài liệu, người có quyền sẽ thấy thêm một chỗ để xem/chọn "người duyệt được chỉ định" (mặc định hiển thị quản lý dự án nếu chưa ai chọn riêng). Không có thay đổi nào khác trên giao diện duyệt/từ chối hiện tại.

## Kịch bản chấp nhận

1. Một tài liệu mới tạo trong dự án đã có quản lý dự án, không ai gán riêng → hệ thống nhận diện đúng quản lý dự án đó là người phụ trách mặc định.
2. Một tài liệu được chỉ định riêng cho một người cụ thể → hệ thống nhận diện đúng người đó là người phụ trách, kể cả khi khác với quản lý dự án.
3. Một tài liệu cũ (tạo trước khi có GAP-033), chưa từng bị đụng tới → tiếp tục duyệt được bình thường bởi bất kỳ ai có quyền duyệt chung, không có gì thay đổi.
4. Người dùng ở dự án/tenant khác không thể gán mình hoặc người khác làm người duyệt cho tài liệu không thuộc dự án/tenant của họ.
5. (Tuỳ câu trả lời Câu hỏi 3) Sau khi một tài liệu bị mở lại để sửa, người phụ trách hoặc vẫn giữ nguyên, hoặc trở về "chưa gán ai" — đúng theo lựa chọn của Owner.

## Loại trừ phạm vi

- Không thiết kế hay triển khai màn hình/thông báo "việc cần làm hôm nay" cho tài liệu — đó là bước riêng, sau GAP-033.
- Không xử lý GAP-030 (vấn đề tương tự ở RFI) — vấn đề song song, tách biệt.
- Không cho phép nhiều người duyệt cùng lúc/theo trình tự cho một tài liệu.
- Không có cột dữ liệu, migration, hay mã nguồn nào được tạo ở gói quyết định này — đó là việc của Gate 3 trở đi, chỉ được phép làm sau khi có phê duyệt Gate 2.

## Decision Needed

Owner chọn: (1) một trong 3 Hướng A/B/C, và (2) một lựa chọn cho mỗi Câu hỏi 1/2/3 ở trên — hoặc chọn: Yêu cầu chỉnh sửa thiết kế / Từ chối.

**Quyết định của chủ doanh nghiệp về hướng giải pháp:** ☐ Hướng A  ☐ Hướng B  ☑ Hướng C (đề xuất)  ☐ Yêu cầu chỉnh sửa thiết kế  ☐ Từ chối

**Lưu ý:** Câu hỏi 1/2/3 ở trên CHƯA được Owner trả lời trong quyết định này — xem "Gate 2 Owner Decision" ở đầu file.

## What the owner is NOT being asked to decide

Chủ doanh nghiệp không được yêu cầu chọn tên cột dữ liệu, có cần tạo bảng mới hay không, cách viết mã kiểm tra quyền, hay bất kỳ chi tiết kỹ thuật triển khai nào — những việc đó thuộc Gate 3 trở đi. Chủ doanh nghiệp cũng không được yêu cầu quyết định về GAP-030 hay về việc triển khai "Việc cần làm hôm nay" — đó là các quyết định nghiệp vụ riêng. Ở đây chỉ cần quyết định: chọn hướng nào trong 3 hướng, và trả lời 3 câu hỏi nghiệp vụ ở trên.
