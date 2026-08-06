---
work_id: OWN-2026-004
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: fix/OWN-2026-004-gap-subidentifier-governance
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-06T15:46:36+07:00"
  owner_response_reference: "ChatGPT project conversation — explicit owner approval of the GAP sub-identifier governance compatibility correction on 2026-08-06"
  reconciliation_required: true
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-06T15:46:36+07:00"
  updated_at: "2026-08-06T15:46:36+07:00"
generated_by: agent
---

## OWNER GATE 2: APPROVED

The owner approves the exact canonical GAP identity pattern described in this packet.

This approval changes repository governance tooling only. It does not change product behavior. It does not approve GAP-010b's business request. It does not approve implementation of GAP-010b. It preserves all existing canonical IDs.

## Owner Summary
Đây là thiết kế chi tiết (không phải triển khai) cho việc mở rộng mẫu định danh GAP trong công cụ quản trị Owner Control Layer, để công cụ chấp nhận đúng các mã con gap chính thức đã tồn tại (ví dụ GAP-010b, GAP-014c) mà không đổi tên chúng và không nới lỏng cho bất kỳ chuỗi tuỳ ý nào khác.

## Mẫu định danh GAP chính thức (chốt, không thay đổi)

```text
GAP-[0-9]{3}[a-z]?
```

Nghĩa là:
- đúng 3 chữ số;
- theo sau bởi 0 hoặc đúng 1 chữ cái thường a-z;
- hậu tố chữ HOA bị từ chối;
- hậu tố nhiều ký tự bị từ chối;
- hậu tố có dấu câu bị từ chối;
- mã cha như `GAP-010` vẫn hợp lệ;
- mã con chính thức như `GAP-010b` trở nên hợp lệ.

Mẫu này là chính xác và ràng buộc — không được mở rộng thêm cho chuỗi tuỳ ý hay nhiều ký tự hậu tố.

## Ví dụ được chấp nhận / bị từ chối

| Chuỗi | Kết quả | Lý do |
|---|---|---|
| `GAP-010` | Chấp nhận | Mã cha, đúng 3 chữ số, không hậu tố |
| `GAP-010a` | Chấp nhận | Mã con hợp lệ, đúng 1 chữ cái thường |
| `GAP-010b` | Chấp nhận | Mã con hợp lệ |
| `GAP-014c` | Chấp nhận | Mã con hợp lệ |
| `GAP-999z` | Chấp nhận | Mã con hợp lệ, biên trên của 3 chữ số và bảng chữ cái |
| `GAP-10` | Từ chối | Thiếu 1 chữ số (không đúng 3 chữ số) |
| `GAP-010B` | Từ chối | Chữ HOA không được phép |
| `GAP-010bb` | Từ chối | Nhiều hơn 1 ký tự hậu tố |
| `GAP-010-b` | Từ chối | Có dấu gạch ngang phụ, không đúng mẫu |
| `GAP-0010` | Từ chối | 4 chữ số, không đúng 3 chữ số |
| `GAP-0010b` | Từ chối | 4 chữ số + hậu tố, không đúng mẫu |
| `GAP-010_` | Từ chối | Ký tự gạch dưới không được phép |
| `GAP-010/` | Từ chối | Ký tự dấu gạch chéo không được phép |

## Ba nơi phải sửa đồng bộ

Cả 3 nơi sau đây định nghĩa hoặc trích xuất Work-ID độc lập với nhau — nếu chỉ sửa 1 hoặc 2 nơi, hệ thống sẽ mất nhất quán (ví dụ: hồ sơ mới có thể tạo được nhưng CI vẫn cắt cụt Work-ID về dạng cha, làm sai lệch bằng chứng gắn với đúng mã con):

1. **Schema chuẩn** (`docs/owner-governance/packet-schema.yml`) — nơi `owner_governance_lint.php` đọc `work_id_pattern` để kiểm tra cấu trúc từng hồ sơ.
2. **Trích xuất Gate-3-before-ready** (`scripts/ci/check-gate3-before-ready.sh`) — đọc thân PR để tìm Work-ID, phải nhận diện trọn vẹn `GAP-010b`, không được cắt cụt còn `GAP-010`.
3. **Trích xuất Evidence Freshness** (`.github/workflows/owner-governance-lint.yml`) — cùng logic trích xuất Work-ID, phải nhất quán với 2 nơi trên.

Chỉ sửa phần GAP trong regex ở cả 3 nơi. Không đụng tới các mẫu ZMC, WP, hay OWN.

## Kiểm chứng bắt buộc trước khi triển khai (TDD)
Viết test thất bại trước khi sửa, chứng minh: mẫu chấp nhận đúng 5 chuỗi hợp lệ liệt kê trên, từ chối đúng 8 chuỗi không hợp lệ liệt kê trên, và cả 2 đường trích xuất CI nhận diện trọn vẹn `GAP-010b` (không cắt cụt về `GAP-010`).

## Rollback
Hoàn tác bằng cách revert đúng PR sửa công cụ quản trị này. Không có dữ liệu, route, hay quyền hạn nào bị ảnh hưởng.
