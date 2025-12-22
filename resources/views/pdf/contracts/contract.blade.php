<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contract Summary - {{ $contract->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            line-height: 1.4;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        .header h1 {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .header .subtitle {
            font-size: 14px;
            color: #333;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .info-label {
            display: table-cell;
            width: 150px;
            font-weight: bold;
            color: #333;
        }
        .info-value {
            display: table-cell;
            color: #000;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .table th,
        .table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .table td {
            background-color: #fff;
        }
        .table .text-right {
            text-align: right;
        }
        .table .text-center {
            text-align: center;
        }
        .totals {
            margin-top: 20px;
            margin-left: auto;
            width: 300px;
        }
        .total-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }
        .total-label {
            display: table-cell;
            font-weight: bold;
            text-align: right;
            padding-right: 10px;
        }
        .total-value {
            display: table-cell;
            text-align: right;
            font-weight: bold;
        }
        .signature-section {
            margin-top: 50px;
            display: table;
            width: 100%;
        }
        .signature-block {
            display: table-cell;
            width: 50%;
            padding: 20px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRACT SUMMARY</h1>
        <div class="subtitle">{{ $project->name }}</div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <div class="info-label">Contract Code:</div>
            <div class="info-value">{{ $contract->code }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Contract Name:</div>
            <div class="info-value">{{ $contract->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Contractor/Supplier:</div>
            <div class="info-value">{{ $contract->party_name ?? 'N/A' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Contract Type:</div>
            <div class="info-value">{{ $contract->type ?? 'N/A' }}</div>
        </div>
        @if($contract->start_date)
        <div class="info-row">
            <div class="info-label">Start Date:</div>
            <div class="info-value">{{ $contract->start_date->format('Y-m-d') }}</div>
        </div>
        @endif
        @if($contract->end_date)
        <div class="info-row">
            <div class="info-label">End Date:</div>
            <div class="info-value">{{ $contract->end_date->format('Y-m-d') }}</div>
        </div>
        @endif
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div class="info-value">{{ strtoupper($contract->status) }}</div>
        </div>
    </div>

    @if($contract->lines && $contract->lines->count() > 0)
    <table class="table">
        <thead>
            <tr>
                <th style="width: 80px;">Item Code</th>
                <th>Description</th>
                <th style="width: 80px;" class="text-center">Quantity</th>
                <th style="width: 100px;" class="text-right">Unit Price</th>
                <th style="width: 120px;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contract->lines as $line)
            <tr>
                <td>{{ $line->item_code ?? '-' }}</td>
                <td>{{ $line->description }}</td>
                <td class="text-center">{{ number_format($line->quantity, 2) }} {{ $line->unit ?? '' }}</td>
                <td class="text-right">{{ number_format($line->unit_price, 2) }} {{ $currency }}</td>
                <td class="text-right">{{ number_format($line->amount, 2) }} {{ $currency }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="totals">
        <div class="total-row">
            <div class="total-label">Base Amount:</div>
            <div class="total-value">{{ number_format($contract->base_amount ?? 0, 2) }} {{ $currency }}</div>
        </div>
        @if($contract->vat_percent)
        <div class="total-row">
            <div class="total-label">VAT ({{ number_format($contract->vat_percent, 2) }}%):</div>
            <div class="total-value">{{ number_format($contract->total_amount_with_vat ?? 0, 2) }} {{ $currency }}</div>
        </div>
        @endif
        <div class="total-row">
            <div class="total-label">Current Amount:</div>
            <div class="total-value">{{ number_format($contract->current_amount ?? $contract->base_amount ?? 0, 2) }} {{ $currency }}</div>
        </div>
    </div>

    <div class="signature-section">
        <div class="signature-block">
            <div class="signature-line">
                <strong>Contractor/Supplier</strong>
            </div>
        </div>
        <div class="signature-block">
            <div class="signature-line">
                <strong>Owner/Client</strong>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Generated on {{ $generated_at }} by ZenaManage</p>
    </div>
</body>
</html>
