<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Phụ lục hợp đồng - Bảng khối lượng - {{ $contract->code }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; margin: 40px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { padding: 6px 8px; border: 1px solid #cbd5e1; text-align: left; }
        th { background: #f1f5f9; font-weight: bold; }
        td.num { text-align: right; }
        .section { margin-bottom: 20px; }
        .info-table td { border: none; padding: 4px 0; }
        .info-table td.label { font-weight: bold; width: 35%; }
        .footer { margin-top: 40px; font-size: 11px; color: #64748b; }
        .signature-grid { display: flex; justify-content: space-between; margin-top: 40px; }
        .signature-box { width: 30%; text-align: center; font-size: 11px; }
    </style>
</head>
<body>
    <h1>PHỤ LỤC HỢP ĐỒNG — BẢNG KHỐI LƯỢNG</h1>
    <p style="text-align: center;">Số HĐ: {{ $contract->code }}</p>

    <div class="section">
        <table class="info-table">
            <tr><td class="label">Dự án:</td><td>{{ $contract->project->name ?? '—' }}</td></tr>
            <tr><td class="label">Khách hàng:</td><td>{{ $contract->client_name ?? '—' }}</td></tr>
            <tr><td class="label">Giá trị hợp đồng:</td><td>{{ number_format((float) $contract->total_value, 0, ',', '.') }} {{ $contract->currency }}</td></tr>
        </table>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Mã</th>
                    <th>Hạng mục</th>
                    <th>ĐVT</th>
                    <th class="num">Khối lượng</th>
                    <th class="num">Đơn giá</th>
                    <th class="num">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lineItems as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->code ?? '—' }}</td>
                        <td>{{ $item->name ?? '—' }}</td>
                        <td>{{ $item->unit ?? '—' }}</td>
                        <td class="num">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                        <td class="num">{{ $item->unit_price !== null ? number_format($item->unit_price, 0, ',', '.') : '—' }}</td>
                        <td class="num">{{ $item->unit_price !== null ? number_format($item->quantity * $item->unit_price, 0, ',', '.') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight: bold;">
                    <td colspan="6" class="num">TỔNG GIÁ TRỊ:</td>
                    <td class="num">{{ number_format($total, 0, ',', '.') }} {{ $contract->currency }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="section">
        <table class="info-table">
            <tr>
                <td class="label">Bằng chữ:</td>
                <td>{{ $amountInWords }}</td>
            </tr>
        </table>
    </div>

    <div class="signature-grid">
        <div class="signature-box">
            <p><strong>ĐẠI DIỆN BÊN A</strong></p>
            <p>(Ký, ghi rõ họ tên)</p>
        </div>
        <div class="signature-box">
            <p>&nbsp;</p>
        </div>
        <div class="signature-box">
            <p><strong>ĐẠI DIỆN BÊN B</strong></p>
            <p>(Ký, ghi rõ họ tên)</p>
        </div>
    </div>

    <div class="footer">
        <p>Nguồn dữ liệu: ZenaManage — Hệ thống quản lý dự án</p>
    </div>
</body>
</html>
