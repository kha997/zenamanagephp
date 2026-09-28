---
work_id: OWN-2026-013
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-09-26-own-2026-013-gap054-post-release-reconciliation.md
  plan: null
  branch: docs/OWN-2026-013-gap054-post-release-reconciliation
  pr: https://github.com/kha997/zenamanagephp/pull/320
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
  created_at: "2026-09-26T20:26:14+07:00"
  updated_at: "2026-09-28T10:00:00+07:00"
generated_by: agent
---

## Owner Summary

Sổ theo dõi lỗ hổng vận hành đang ghi sai trạng thái của GAP-054 (vẫn ghi "chưa
bắt đầu" dù đã phát hành), và chưa ghi hai lỗ hổng mới phát hiện trong lúc làm
GAP-054. Đề nghị cho phép thiết kế một lần đối soát sổ để sửa ba điểm này.

## Vấn đề vận hành

1. **GAP-054 ghi sai trạng thái.** Thay đổi đã được Owner duyệt, đưa vào bản
   chính thức và kiểm tra tự động đều đạt, nhưng sổ vẫn ghi "đã cho phép triển
   khai, chưa bắt đầu". Không thể sửa dòng này ngay trong GAP-054: việc sửa sổ
   làm thay đổi "dấu vân tay" của đúng phiên bản Owner đã duyệt, khiến hệ thống
   tự coi quyết định phát hành là hết hiệu lực. Cùng tình huống này với GAP-052
   đã được xử lý bằng một việc đối soát riêng (OWN-2026-011).
2. **Nút "xoá bộ nhớ đệm" trong trang quản trị** vẫn xoá sạch toàn bộ bộ nhớ đệm
   — cùng tác hại mà GAP-054 vừa loại bỏ khỏi việc tự động: một lần bấm là bộ
   đếm chặn dò mật khẩu của mọi khách hàng về 0.
3. **Một số script vận hành cũ** (31 chỗ trong 11 file — đã đính chính
   2026-09-28, bản đầu ghi thiếu còn 13 chỗ/8 file) đưa mật khẩu cơ sở dữ liệu
   lên dòng lệnh; 4 chỗ dùng tài khoản quản trị cơ sở dữ liệu (`root`) với mật
   khẩu dự phòng viết sẵn. Các script này không nằm trên đường triển khai chính
   thức; riêng `docker-manage.sh` được workflow `automated-deployment.yml` gọi
   khi deploy production — workflow đó hiện không hoạt động (không có release,
   thiếu secrets, lần chạy cuối 2026-07-07) nhưng vẫn là đường tự động.

## Người dùng bị ảnh hưởng

Điểm 1: chủ doanh nghiệp và đội vận hành (đọc sổ sẽ hiểu sai). Điểm 2: mọi
khách hàng mỗi khi quản trị viên bấm nút. Điểm 3: mọi khách hàng nếu một script
cũ được chạy trên máy chủ dùng chung.

## Bằng chứng

Đội kỹ thuật đã kiểm tra trên đúng phiên bản chính thức hiện tại: đối chiếu
trạng thái phát hành của GAP-054, chạy thử để thấy việc sửa sổ dưới GAP-054 làm
"dấu vân tay" thay đổi, xác nhận đường dẫn của nút xoá bộ nhớ đệm đang hoạt động
và chỉ quản trị viên dùng được, và liệt kê từng dòng script có vấn đề. Chi tiết
nằm trong evidence audit liên kết ở frontmatter.

## Tác động nếu không xử lý

Sổ tiếp tục báo sai tiến độ; hai lỗ hổng mới không có người chịu trách nhiệm và
dễ bị quên.

## Phạm vi đề xuất

Nếu Owner duyệt, Gate 2 thiết kế đúng một lần sửa sổ: chuyển dòng GAP-054 sang
"đã giải quyết" kèm bằng chứng phát hành, và thêm hai dòng mới GAP-055 (nút xoá
bộ nhớ đệm) và GAP-056 (mật khẩu trong script cũ) ở trạng thái "đang mở, chưa
bắt đầu Gate 1". Chỉ ghi nhận, không sửa lỗi.

## Loại trừ rõ ràng

- Không sửa nút xoá bộ nhớ đệm, không sửa hay xoá script, không đổi mật khẩu.
- Không đổi bất kỳ hồ sơ đã duyệt nào của GAP-054.
- Không deploy, không bật bộ việc tự động.
- GAP-055 và GAP-056 sau này mỗi việc đi qua Gate 1 riêng.

## Khả năng hoàn tác

Chỉ sửa tài liệu; hoàn tác bằng cách quay lại phiên bản trước của sổ.

## Đề xuất

Đề xuất Owner phê duyệt chuyển sang Gate 2 cho lần đối soát sổ nêu trên.

## Decision Needed

Owner chọn một: Approve to proceed to design (Gate 2) / Request more
information / Decline / Defer.

## What the owner is NOT being asked to decide

Owner chưa được yêu cầu duyệt cách sửa nút xoá bộ nhớ đệm hay các script, chưa
duyệt nội dung chính xác của dòng sổ, Gate 2, merge hay phát hành.
