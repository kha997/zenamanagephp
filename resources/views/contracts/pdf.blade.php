<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hợp đồng {{ $contract->code }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td { padding: 6px 0; border-bottom: 1px solid #e2e8f0; }
        td.label { font-weight: bold; width: 40%; }
    </style>
</head>
<body>
    <h1>Hợp đồng {{ $contract->code }}</h1>
    <p>{{ $contract->title }}</p>

    <table>
        <tr><td class="label">Khách hàng</td><td>{{ $contract->client_name ?? '—' }}</td></tr>
        <tr><td class="label">Giá trị hợp đồng</td><td>{{ number_format((float) $contract->total_value, 0, ',', '.') }} {{ $contract->currency }}</td></tr>
        <tr><td class="label">Trạng thái</td><td>{{ $contract->status }}</td></tr>
        <tr><td class="label">Ngày tạo</td><td>{{ optional($contract->created_at)->format('d/m/Y') }}</td></tr>
    </table>
</body>
</html>
