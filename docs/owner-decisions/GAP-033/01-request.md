---
work_id: GAP-033
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_more_info_or_decline_or_defer"
references:
  spec: docs/audits/2026-08-12-gap-033-document-approver-assignment-evidence.md
  plan: null
  branch: docs/GAP-033-gate1-prep
  pr: https://github.com/kha997/zenamanagephp/pull/258
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-12T21:12:47+07:00"
  updated_at: "2026-08-12T21:12:47+07:00"
generated_by: agent
---

## Owner Summary

Hiện tại, hệ thống không thể trả lời câu hỏi "tài liệu này ai là người phải duyệt?" — quyền duyệt tài liệu là quyền chung cho cả vai trò (bất kỳ ai giữ vai trò Quản lý dự án hoặc Admin đều duyệt được mọi tài liệu), không phải chỉ định riêng cho một người cụ thể trên một tài liệu cụ thể. Vì vậy tài liệu chờ duyệt không thể xuất hiện trong mục "Việc cần làm hôm nay" cá nhân hoá của bất kỳ ai — không có cách xác định chính xác "tài liệu nào đang chờ TÔI duyệt".

## Vấn đề vận hành

Khi một tài liệu được nộp để duyệt (chuyển sang trạng thái "Chờ duyệt"), hệ thống không ghi nhận ai là người có trách nhiệm ra quyết định trên tài liệu đó — chỉ biết "ai đó có quyền duyệt tài liệu trong dự án/tenant này thì có thể duyệt". Trong thực tế vận hành, điều này có hai hệ quả:

1. **Không ai được nhắc việc riêng.** Vì hệ thống không biết "tài liệu X đang chờ đúng người Y duyệt", tài liệu chờ duyệt không thể hiển thị trong danh sách việc cá nhân của người có trách nhiệm — người đó phải tự nhớ vào kiểm tra trang tài liệu, hoặc chờ ai đó nhắc thủ công (chat, gọi điện).
2. **Trách nhiệm không rõ ràng khi có nhiều người cùng giữ quyền duyệt.** Nếu một dự án có nhiều Quản lý dự án hoặc nhiều người giữ quyền `document.approve`, không ai trong số họ được xem là "người phải xử lý tài liệu này" — dễ dẫn tới tình huống mọi người đều nghĩ người khác sẽ duyệt, tài liệu bị treo lâu hơn cần thiết mà không ai nhận ra.

Đây không phải lỗi bảo mật hay mất dữ liệu — quyền duyệt vẫn hoạt động đúng và có kiểm soát tenant/phân quyền chặt chẽ (đã xác nhận lại trong GAP-031/032). Đây là một khoảng trống vận hành: hệ thống thiếu một khái niệm "người chịu trách nhiệm cụ thể" trên từng tài liệu.

## Người dùng bị ảnh hưởng

- **Người có quyền duyệt tài liệu** (thường là Quản lý dự án, hoặc Admin) — không thể có một danh sách "tài liệu tôi cần duyệt" đáng tin cậy; phải tự tìm trong danh sách tài liệu chung của dự án.
- **Người nộp tài liệu chờ duyệt** — không biết chắc ai sẽ là người xử lý yêu cầu của mình, không có ai để theo dõi tiến độ cùng.
- **Tính năng "Việc cần làm hôm nay" (Today Workspace)** — đã chính thức loại trừ tài liệu chờ duyệt khỏi mục việc cần làm cá nhân hoá, vì không có cách xác định chính xác người phụ trách (xem Bằng chứng bên dưới). Đây là điều kiện tiên quyết còn thiếu để tính năng này bao phủ đầy đủ workflow tài liệu.

## Bằng chứng

- Quyền duyệt tài liệu (`document.approve`) được cấp theo vai trò cho toàn bộ tenant/dự án (vai trò "Quản lý dự án" và "Admin"), không có cơ chế nào so khớp "người đang đăng nhập" với "người được chỉ định cho tài liệu này cụ thể".
- Bản thân bảng dữ liệu tài liệu không có bất kỳ cột nào lưu "người được chỉ định duyệt" — chỉ có các cột ghi nhận ai tạo/tải lên tài liệu, và (từ GAP-031/032) một nhật ký chỉ ghi lại ai đã thực sự bấm duyệt SAU KHI việc duyệt xảy ra — không có nơi nào ghi trước "ai nên là người duyệt".
- Tài liệu thiết kế nội bộ về tính năng "Việc cần làm hôm nay" đã xác nhận và loại trừ chính xác vấn đề này: một workflow chỉ được đưa vào mục việc cần làm cá nhân khi có thể xác định người phụ trách bằng một điều kiện tra cứu cụ thể (một cột dữ liệu trực tiếp) — tài liệu hiện không thỏa điều kiện này.
- Đối chiếu với các phần khác của hệ thống: có ít nhất một nghiệp vụ khác (Báo cáo không phù hợp - NCR) đã có sẵn cơ chế "chỉ định một người cụ thể chịu trách nhiệm xử lý một hồ sơ cụ thể", và việc kiểm tra quyền xử lý hồ sơ đó có so khớp đúng người được chỉ định — cho thấy đây là một mẫu hình đã có tiền lệ trong hệ thống, không phải ý tưởng hoàn toàn mới.
- Chi tiết kỹ thuật đầy đủ (trích dẫn file:dòng) được ghi trong `docs/audits/2026-08-12-gap-033-document-approver-assignment-evidence.md`, không truy vấn dữ liệu sản xuất.

## Tác động nếu không xử lý

Tài liệu chờ duyệt tiếp tục phải được theo dõi thủ công (nhắc miệng, chat, hoặc tự nhớ kiểm tra) thay vì xuất hiện tự động trong danh sách việc cần làm của đúng người. Khi số lượng dự án và tài liệu tăng lên, rủi ro tài liệu bị "treo" lâu mà không ai chủ động nhận trách nhiệm cũng tăng theo. Tính năng "Việc cần làm hôm nay" tiếp tục thiếu một trong những nguồn việc quan trọng nhất của một dự án xây dựng/thiết kế (tài liệu chờ duyệt).

## Phạm vi đề xuất

Xác định xem có nên thêm một cơ chế "người chịu trách nhiệm cụ thể" cho từng tài liệu hay không, và nếu có thì theo hướng nào (một trường "người duyệt được chỉ định" đơn giản trên tài liệu, một quy tắc "người duyệt mặc định theo loại dự án", hay một cơ chế khác) — đây là câu hỏi thiết kế nghiệp vụ dành cho Gate 2, không quyết định ở đây. Gate 1 chỉ xin phép xác nhận: vấn đề này có thật và đáng để thiết kế giải pháp hay không.

## Loại trừ rõ ràng

Yêu cầu Gate 1 này KHÔNG bao gồm và KHÔNG tạo ra:

- Bất kỳ thiết kế giải pháp, cột dữ liệu, bảng, migration, hay thay đổi mã nguồn nào.
- Quyết định về việc "một trường chỉ định đơn giản" hay "quy tắc mặc định theo loại dự án" hay bất kỳ hướng cụ thể nào khác — đó là câu hỏi của Gate 2.
- Bất kỳ thay đổi nào tới `OPERATIONAL_GAP_REGISTER.md` ngoài việc đối chiếu lại trạng thái GAP-032 vừa phát hành (đã thực hiện riêng, không phải một phần của yêu cầu này) — GAP-033 tiếp tục đăng ký là OPEN cho tới khi có quyết định khác.
- Bất kỳ truy vấn hay thao tác nào trên dữ liệu sản xuất.
- Việc triển khai tính năng "Việc cần làm hôm nay" cho tài liệu — đó là một bước riêng, phụ thuộc vào kết quả của GAP-033 nhưng không nằm trong phạm vi của chính GAP-033.
- GAP-030 (vấn đề tương tự nhưng cho RFI) — vấn đề song song, không được gộp vào đây.

## Đề xuất

Xử lý ngay (tiến hành Gate 2 thiết kế nghiệp vụ). Lý do: đây là khoảng trống vận hành thật, có bằng chứng cụ thể, ảnh hưởng trực tiếp tới việc tài liệu có thể được theo dõi đúng người đúng lúc hay không, và đã có tiền lệ kỹ thuật trong hệ thống (mẫu hình NCR) giúp giảm rủi ro thiết kế.

## Decision Needed

Owner chọn một trong: Đồng ý tiến hành Gate 2 thiết kế nghiệp vụ / Yêu cầu thêm thông tin / Từ chối / Hoãn lại.

## What the owner is NOT being asked to decide

Chủ doanh nghiệp không được yêu cầu chọn giải pháp kỹ thuật, cột dữ liệu, bảng, hay bất kỳ cơ chế triển khai cụ thể nào — đó là các câu hỏi của Gate 2 trở đi. Chủ doanh nghiệp cũng không được yêu cầu quyết định về GAP-030 (vấn đề tương tự ở RFI) hay về việc triển khai "Việc cần làm hôm nay" cho tài liệu — đó là các quyết định nghiệp vụ riêng, sẽ được trình bày ở gói quyết định khác nếu cần. Ở đây chỉ cần quyết định một việc: vấn đề "tài liệu không có người duyệt được chỉ định cụ thể" có thật và có đáng để thiết kế giải pháp hay không.
