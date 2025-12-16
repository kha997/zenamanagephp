<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payment Certificate - {{ $certificate->code }}</title>
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
            width: 200px;
            font-weight: bold;
            color: #333;
        }
        .info-value {
            display: table-cell;
            color: #000;
        }
        .amounts-section {
            margin: 30px 0;
            width: 100%;
        }
        .amount-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #ccc;
        }
        .amount-label {
            display: table-cell;
            width: 250px;
            font-weight: bold;
            color: #333;
        }
        .amount-value {
            display: table-cell;
            text-align: right;
            font-weight: bold;
            color: #000;
        }
        .amount-row.total {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 12px 0;
            margin-top: 10px;
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
        <h1>PAYMENT CERTIFICATE</h1>
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
            <div class="info-label">Certificate Code:</div>
            <div class="info-value">{{ $certificate->code }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Title:</div>
            <div class="info-value">{{ $certificate->title ?? 'N/A' }}</div>
        </div>
        @if($certificate->period_start && $certificate->period_end)
        <div class="info-row">
            <div class="info-label">Period:</div>
            <div class="info-value">{{ $certificate->period_start->format('Y-m-d') }} to {{ $certificate->period_end->format('Y-m-d') }}</div>
        </div>
        @endif
        <div class="info-row">
            <div class="info-label">Status:</div>
            <div class="info-value">{{ strtoupper($certificate->status) }}</div>
        </div>
    </div>

    <div class="amounts-section">
        <div class="amount-row">
            <div class="amount-label">Amount Before Retention:</div>
            <div class="amount-value">{{ number_format($certificate->amount_before_retention, 2) }} {{ $currency }}</div>
        </div>
        <div class="amount-row">
            <div class="amount-label">Retention Percent:</div>
            <div class="amount-value">
                {{ number_format($certificate->retention_percent_override ?? $contract->retention_percent ?? 0, 2) }}%
            </div>
        </div>
        <div class="amount-row">
            <div class="amount-label">Retention Amount:</div>
            <div class="amount-value">{{ number_format($certificate->retention_amount, 2) }} {{ $currency }}</div>
        </div>
        <div class="amount-row total">
            <div class="amount-label">Amount Payable:</div>
            <div class="amount-value">{{ number_format($certificate->amount_payable, 2) }} {{ $currency }}</div>
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
