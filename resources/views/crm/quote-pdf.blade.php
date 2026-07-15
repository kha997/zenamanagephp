<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Báo giá - {{ $quote->quote_number }}</title>
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
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 72px;
            font-weight: bold;
            color: rgba(220, 38, 38, 0.12);
            z-index: 100;
            pointer-events: none;
            letter-spacing: 12px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    @if ($quote->status === 'draft')
        <div class="watermark">BẢN NHÁP</div>
    @endif

    <h1>BẢNG BÁO GIÁ</h1>
    <p style="text-align: center;">Số: {{ $quote->quote_number }} — Rev {{ $quote->revision_no }}</p>

    <div class="section">
        <table class="info-table">
            <tr><td class="label">Khách hàng:</td><td>{{ $account?->display_name ?? '—' }}</td></tr>
            <tr><td class="label">Cơ hội:</td><td>{{ $opportunity?->opportunity_name ?? '—' }}</td></tr>
            @if ($quote->valid_until)
                <tr><td class="label">Hiệu lực đến:</td><td>{{ $quote->valid_until->format('d/m/Y') }}</td></tr>
            @endif
            <tr><td class="label">Trạng thái:</td><td>{{ $quote->status }}</td></tr>
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
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $index => $line)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $line->code ?? '—' }}</td>
                        <td>{{ $line->name }}</td>
                        <td>{{ $line->unit }}</td>
                        <td class="num">{{ number_format($line->quantity, 3, ',', '.') }}</td>
                        <td class="num">{{ number_format($line->unit_price, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($line->amount, 0, ',', '.') }}</td>
                        <td>{{ $line->price_note ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight: bold;">
                    <td colspan="6" class="num">TỔNG CỘNG:</td>
                    <td class="num">{{ number_format($quote->subtotal, 0, ',', '.') }} VNĐ</td>
                    <td></td>
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
            <p><strong>ĐẠI DIỆN NHÀ CUNG CẤP</strong></p>
            <p>(Ký, ghi rõ họ tên)</p>
        </div>
        <div class="signature-box">
            <p>&nbsp;</p>
        </div>
        <div class="signature-box">
            <p><strong>ĐẠI DIỆN KHÁCH HÀNG</strong></p>
            <p>(Ký, ghi rõ họ tên)</p>
        </div>
    </div>

    <div class="footer">
        <p>Nguồn dữ liệu: ZenaManage — Hệ thống quản lý dự án</p>
    </div>
</body>
</html>
