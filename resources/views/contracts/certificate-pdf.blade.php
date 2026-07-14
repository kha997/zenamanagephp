<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Biên bản nghiệm thu khối lượng - Kỳ {{ $certificate->period_no }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; margin: 40px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 20px; margin-bottom: 8px; }
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
    <h1>BIÊN BẢN NGHIỆM THU KHỐI LƯỢNG</h1>
    <p style="text-align: center;">Kỳ {{ $certificate->period_no }}: {{ \Carbon\Carbon::parse($certificate->period_from)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($certificate->period_to)->format('d/m/Y') }}</p>

    <div class="section">
        <table class="info-table">
            <tr><td class="label">Doanh nghiệp:</td><td>{{ $tenantName }}</td></tr>
            <tr><td class="label">Khách hàng:</td><td>{{ $contract->client_name ?? '—' }}</td></tr>
            <tr><td class="label">Hợp đồng:</td><td>{{ $contract->code }} — {{ $contract->title }}</td></tr>
            <tr><td class="label">Giá trị hợp đồng:</td><td>{{ number_format((float) $contract->total_value, 0, ',', '.') }} {{ $contract->currency }}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>Chi tiết khối lượng thực hiện kỳ này</h2>
        <table>
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Hạng mục</th>
                    <th>ĐVT</th>
                    <th class="num">KL.HĐ</th>
                    <th class="num">KL.TK</th>
                    <th class="num">KL.Kỳ này</th>
                    <th class="num">Đơn giá</th>
                    <th class="num">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lineSummaries as $boqLineId => $summary)
                    @php $boqItem = $boqLinesById[$boqLineId] ?? null; @endphp
                    @if ($summary['this_qty'] > 0)
                        <tr>
                            <td>{{ $boqItem->code ?? '—' }}</td>
                            <td>{{ $boqItem->name ?? '—' }}</td>
                            <td>{{ $boqItem->unit ?? '—' }}</td>
                            <td class="num">{{ number_format($summary['contract_qty'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($summary['prev_qty'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($summary['this_qty'], 0, ',', '.') }}</td>
                            <td class="num">{{ $summary['unit_price'] !== null ? number_format($summary['unit_price'], 0, ',', '.') : '—' }}</td>
                            <td class="num">{{ number_format($summary['amount_this_period'], 0, ',', '.') }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight: bold;">
                    <td colspan="7" class="num">Tổng kỳ này</td>
                    <td class="num">{{ number_format($certificate->total_this_period, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="section">
        <h2>Tổng kết khấu trừ</h2>
        <table class="info-table">
            <tr>
                <td class="label">Giá trị khối lượng kỳ này:</td>
                <td>{{ number_format($certificate->total_this_period, 0, ',', '.') }} {{ $contract->currency }}</td>
            </tr>
            @if ((float) $contract->retention_percent > 0)
                <tr>
                    <td class="label">− Giữ lại ({{ $contract->retention_percent }}%):</td>
                    <td>− {{ number_format($certificate->retention_amount, 0, ',', '.') }} {{ $contract->currency }}</td>
                </tr>
            @endif
            @if ((float) $certificate->advance_deduction > 0)
                <tr>
                    <td class="label">− Thu hồi tạm ứng:</td>
                    <td>− {{ number_format($certificate->advance_deduction, 0, ',', '.') }} {{ $contract->currency }}</td>
                </tr>
            @endif
            <tr style="font-weight: bold; border-top: 2px solid #1e293b;">
                <td class="label">= Đề nghị thanh toán:</td>
                <td>{{ number_format($certificate->net_payable, 0, ',', '.') }} {{ $contract->currency }}</td>
            </tr>
            <tr>
                <td class="label">Bằng chữ:</td>
                <td>{{ $amountInWords }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Nguồn dữ liệu: ZenaManage — Hệ thống quản lý dự án</p>
    </div>
</body>
</html>
