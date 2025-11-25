<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLO Violation Alert</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: {{ $violation['severity'] === 'critical' ? '#dc3545' : ($violation['severity'] === 'warning' ? '#ffc107' : '#17a2b8') }};
            color: white;
            padding: 20px;
            border-radius: 5px 5px 0 0;
            text-align: center;
        }
        .content {
            background: #f8f9fa;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 5px 5px;
        }
        .metric {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid {{ $violation['severity'] === 'critical' ? '#dc3545' : ($violation['severity'] === 'warning' ? '#ffc107' : '#17a2b8') }};
        }
        .metric-label {
            font-weight: bold;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 5px 0;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #666;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-critical {
            background: #dc3545;
            color: white;
        }
        .badge-warning {
            background: #ffc107;
            color: #333;
        }
        .badge-info {
            background: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔔 SLO Violation Alert</h1>
        <p>{{ $severity }} Severity</p>
    </div>

    <div class="content">
        <div class="metric">
            <div class="metric-label">Category</div>
            <div class="metric-value">{{ ucfirst($violation['category']) }}</div>
        </div>

        <div class="metric">
            <div class="metric-label">Metric</div>
            <div class="metric-value">{{ $violation['metric'] }}</div>
        </div>

        <div class="metric">
            <div class="metric-label">Current Value</div>
            <div class="metric-value">{{ number_format($violation['value'], 2) }} {{ isset($violation['type']) && $violation['type'] === 'hit_rate' ? '%' : 'ms' }}</div>
        </div>

        <div class="metric">
            <div class="metric-label">Target</div>
            <div class="metric-value">{{ number_format($violation['target'], 2) }} {{ isset($violation['type']) && $violation['type'] === 'hit_rate' ? '%' : 'ms' }}</div>
        </div>

        <div class="metric">
            <div class="metric-label">Percentage of Target</div>
            <div class="metric-value">{{ number_format($violation['percentage'], 1) }}%</div>
        </div>

        <div class="metric">
            <div class="metric-label">Severity</div>
            <div class="metric-value">
                <span class="badge badge-{{ $violation['severity'] }}">{{ $severity }}</span>
            </div>
        </div>

        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 5px;">
            <strong>⚠️ Action Required:</strong>
            <p>This SLO violation requires attention. Please investigate the performance degradation and take appropriate action.</p>
        </div>
    </div>

    <div class="footer">
        <p>Alerted at: {{ $alertedAt }}</p>
        <p>This is an automated alert from ZenaManage SLO Monitoring System.</p>
    </div>
</body>
</html>

