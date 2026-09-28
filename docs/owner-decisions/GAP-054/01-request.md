---
work_id: GAP-054
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-09-26-gap-054-scheduler-production-safety-evidence.md
  plan: null
  branch: docs/GAP-054-scheduler-production-safety-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/318
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-09-26T08:50:17+07:00"
  owner_response_reference: "Owner decision in-session on 2026-09-26: 'duyệt và sang Gate 2' (approve and proceed to Gate 2). Approval is bound to exact reviewed Draft PR #318 head fb354b666b33cd5fe63fd699f623c1b54a7f9263, based on canonical main adacc5cc5fb8a08353cc90576076724e45e6e8bc. This approves the Gate-1 problem/evidence and authorizes proceeding to a separately prepared Gate 2; it does not approve an implementation, Ready state, merge, release, deployment, environment change, or credential rotation."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-26T08:27:38+07:00"
  updated_at: "2026-09-26T08:50:17+07:00"
generated_by: agent
---

## Owner Decision History — Gate 1 — APPROVED

Owner approved GAP-054 Gate 1 in-session on 2026-09-26 ("duyệt và sang Gate
2"), bound to exact reviewed Draft PR #318 head
`fb354b666b33cd5fe63fd699f623c1b54a7f9263` and canonical base
`adacc5cc5fb8a08353cc90576076724e45e6e8bc`. The approved finding is the five
verified scheduler/backup production-safety defects recorded in the linked
evidence audit. This decision authorizes a separately prepared Gate 2 only. It
does not authorize implementation, an implementation plan, Ready state, merge,
release, deployment, any scheduler/environment change, or credential rotation.

## Owner Summary

Hệ thống có một bộ việc tự động chạy định kỳ (sao lưu, dọn dẹp, bảo trì). Bộ
việc này đang tắt theo mặc định, nhưng cấu hình triển khai production bằng
Docker của chính dự án lại bật nó lên. Khi bật, một số việc trong đó gây hại
thật: bản sao lưu hằng ngày chứa toàn bộ mật khẩu và khoá bí mật của hệ thống,
mật khẩu cơ sở dữ liệu bị lộ trong lúc sao lưu, lớp chống dò mật khẩu bị xoá
trắng mỗi đêm, và bản sao lưu thực tế chỉ giữ được khoảng 2 ngày thay vì 30.

## Vấn đề vận hành

Khi bật chế độ chạy tự động, hệ thống đăng ký 13 việc định kỳ. Đội kỹ thuật đã
chạy thật và xác nhận:

1. Hai việc được lên lịch **không hề tồn tại**, nên thất bại mỗi ngày. Một
   trong số đó được đặt tên như việc dọn phiên đăng nhập hết hạn, gây hiểu lầm
   là việc dọn dẹp đang diễn ra.
2. Việc "bảo trì bộ nhớ đệm" lúc 2 giờ sáng **xoá sạch toàn bộ bộ nhớ đệm**,
   kéo theo: bộ đếm chặn người dò mật khẩu (đăng nhập, cổng khách hàng, lời
   mời) về 0 mỗi đêm; người đang đăng nhập qua tài khoản liên kết vào đúng lúc
   đó bị lỗi; và khoá chống chạy trùng của chính các việc tự động bị gỡ. Phiên
   đăng nhập của người dùng thì **không** bị ảnh hưởng.
3. Bản sao lưu đầy đủ lúc 1 giờ sáng **chép nguyên file bí mật của hệ thống**
   (khoá mã hoá, mật khẩu cơ sở dữ liệu, khoá các dịch vụ bên ngoài) vào từng
   bản sao lưu. Ai có được một bản sao lưu là có tất cả bí mật production.
4. Cả hai cách sao lưu cơ sở dữ liệu đều **đưa mật khẩu cơ sở dữ liệu lên dòng
   lệnh**, nên bất kỳ tiến trình nào trên máy chủ cũng nhìn thấy trong lúc sao
   lưu; mật khẩu chứa ký tự đặc biệt còn làm sao lưu thất bại.
5. Hệ thống tạo 5 bản sao lưu mỗi ngày nhưng chỉ giữ tối đa 10 bản, nên thực tế
   **chỉ giữ được khoảng 2 ngày** (thiết lập ghi 30 ngày). Nơi lưu bản sao lưu
   được cấu hình cũng bị bỏ qua: bản sao lưu luôn nằm chung máy, chung ổ với dữ
   liệu cần bảo vệ.

## Người dùng bị ảnh hưởng

Chưa thấy bằng chứng nào cho thấy có máy chủ production đã từng bật bộ việc tự
động, nên chưa ghi nhận người dùng thật bị ảnh hưởng. Rủi ro sẽ thành thật
ngay khi vận hành viên làm theo hướng dẫn của chính dự án: file cấu hình mẫu
ghi "bật lên ở production", cấu hình Docker production bật sẵn, và nhiều tài
liệu cài đặt hướng dẫn cài lịch chạy mỗi phút. Khi đó bị ảnh hưởng là: mọi
khách hàng (lộ bí mật chung của hệ thống), người dùng đăng nhập (mất lớp chống
dò mật khẩu), và chủ doanh nghiệp (tưởng có sao lưu 30 ngày nhưng thực tế chỉ
có 2 ngày).

## Bằng chứng

Đội kỹ thuật chạy trên đúng phiên bản chính thức hiện tại: khi tắt, hệ thống
báo không có việc nào; khi bật, liệt kê đủ 13 việc. Hai việc không tồn tại được
gọi thử và đều báo lỗi "không có lệnh này". Các chỗ xoá bộ nhớ đệm, chép file
bí mật, đưa mật khẩu lên dòng lệnh và giới hạn 10 bản sao lưu đều được chỉ
đúng file và dòng. Tài liệu bằng chứng cũng ghi nhận một nhận định trong bằng
chứng của GAP-049 ("không có việc định kỳ nào để chạy") là chưa chính xác với
cách hệ thống này khởi động; quyết định đã duyệt của GAP-049 không bị mở lại.
Chi tiết file, dòng, lệnh chạy và kết quả nằm trong evidence audit liên kết ở
frontmatter.

## Tác động nếu không xử lý

Nếu triển khai production theo đường Docker hoặc theo tài liệu cài đặt mà
không sửa trước: bí mật production nằm trong mọi bản sao lưu hằng ngày; mật
khẩu cơ sở dữ liệu lộ trên máy chủ; kẻ dò mật khẩu được "làm lại từ đầu" mỗi
đêm; và khi cần khôi phục dữ liệu cũ hơn 2 ngày thì không còn bản nào. Nếu
triển khai theo đường SSH mà GAP-049 đã chọn thì bộ việc tự động không chạy,
nên hệ thống không tự sao lưu và bước kiểm tra sẵn sàng phát hành về sao lưu
sẽ không bao giờ đạt.

## Phạm vi đề xuất

Nếu Owner duyệt Gate 1, Gate 2 sẽ thiết kế cách làm cho bộ việc tự động an
toàn trước khi được phép bật ở production. Cụ thể: bỏ hoặc thay các việc không
tồn tại; không xoá toàn bộ bộ nhớ đệm nữa; sao lưu không chứa file bí mật và
không lộ mật khẩu; giữ bản sao lưu đúng thời hạn đã cấu hình và đúng nơi đã cấu
hình; và thống nhất việc bộ việc tự động bật hay tắt giữa hai cách triển khai.
Thiết kế có thể tham khảo một nhánh làm dở từ tháng 7 (chưa từng qua cổng nào)
nhưng không mặc nhiên áp dụng nó.

## Loại trừ rõ ràng

- Không sửa code, cấu hình, lịch chạy, sao lưu hay tài liệu cài đặt tại Gate 1.
- Không bật hoặc tắt bộ việc tự động trên bất kỳ máy chủ nào, không đổi biến
  môi trường production, không deploy.
- Không đổi mật khẩu hay khoá nào: chưa có bằng chứng bản sao lưu chứa bí mật
  đã rời khỏi máy chủ production.
- Không chọn cách triển khai (Docker hay SSH) thay cho Owner; không mở lại
  quyết định GAP-049.
- Không đưa nhánh làm dở tháng 7 vào main.
- Theo dõi hàng đợi, thu thập số liệu, tối ưu cơ sở dữ liệu và thời hạn lưu
  nhật ký hệ thống chỉ được ghi nhận, chưa được kiểm tra lại, không tự động
  nằm trong phạm vi.

## Đề xuất

Đề xuất Owner phê duyệt chuyển sang Gate 2 để thiết kế điều kiện an toàn bắt
buộc cho bộ việc tự động và sao lưu, trước khi bất kỳ môi trường production
nào được bật bộ việc này.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Owner chưa được yêu cầu duyệt cách sửa, chọn cách triển khai, đổi mật khẩu,
hay duyệt Gate 2, merge, release hoặc deploy. Gate 1 chỉ hỏi liệu các lỗ hổng
đã được chứng minh này có đáng chuyển sang thiết kế cách xử lý hay không.
