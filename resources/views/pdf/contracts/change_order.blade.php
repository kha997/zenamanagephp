<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Change Order - {{ $changeOrder->code }}</title>
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
        <h1>CHANGE ORDER</h1>
        <div class="subtitle">{{ $project->name }}</div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <div class="info-label">Project:</div>
            <div class="info-value">{{ $project->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Contract:</div>
            <div class="info-value">{{ $contract->code }} - {{ $contract->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Change Order Code:</div>
            <div class="info-value">{{ $changeOrder->code }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Title:</div>
            <div class="info-value">{{ $changeOrder->title }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Reason:</div>
            <div class="info-value">{{ $changeOrder->reason ?? 'N/A' }}</div>
        </div>
        @if($changeOrder->effective_date)
        <div class="info-row">
            <div class="info-label">Effective Date:</div>
            <div class="info-value">{{ $changeOrder->effective_date->format('Y-m-d') }}</div>
        </div>
        @endif
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div class="info-value">{{ strtoupper($changeOrder->status) }}</div>
        </div>
    </div>

    @if($changeOrder->lines && $changeOrder->lines->count() > 0)
    <table class="table">
        <thead>
            <tr>
                <th style="width: 80px;">Item Code</th>
                <th>Description</th>
                <th style="width: 100px;" class="text-center">Qty Delta</th>
                <th style="width: 100px;" class="text-right">Unit Price Delta</th>
                <th style="width: 120px;" class="text-right">Amount Delta</th>
            </tr>
        </thead>
        <tbody>
            @foreach($changeOrder->lines as $line)
            <tr>
                <td>{{ $line->item_code ?? '-' }}</td>
                <td>{{ $line->description }}</td>
                <td class="text-center">
                    @if($line->quantity_delta !== null)
                        {{ $line->quantity_delta >= 0 ? '+' : '' }}{{ number_format($line->quantity_delta, 2) }} {{ $line->unit ?? '' }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-right">
                    @if($line->unit_price_delta !== null)
                        {{ $line->unit_price_delta >= 0 ? '+' : '' }}{{ number_format($line->unit_price_delta, 2) }} {{ $currency }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-right">
                    {{ $line->amount_delta >= 0 ? '+' : '' }}{{ number_format($line->amount_delta, 2) }} {{ $currency }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="totals">
        <div class="total-row">
            <div class="total-label">Total Amount Delta:</div>
            <div class="total-value">
                {{ $changeOrder->amount_delta >= 0 ? '+' : '' }}{{ number_format($changeOrder->amount_delta, 2) }} {{ $currency }}
            </div>
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
