<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Attendance Report - {{ $companyName ?? 'BuildArya' }}</title>
    <style>
        @page {
            margin: 20px 25px 25px 25px;
            size: a4 landscape;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.4;
            background-color: #ffffff;
        }
        .header-box {
            width: 100%;
            background-color: #0f172a;
            color: #ffffff;
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 15px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: middle;
        }
        .company-title {
            font-size: 20px;
            font-weight: bold;
            color: #ffffff;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .report-subtitle {
            font-size: 12px;
            color: #10b981;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .header-meta {
            text-align: right;
            font-size: 10px;
            color: #cbd5e1;
            line-height: 1.6;
        }
        .header-meta strong {
            color: #ffffff;
        }
        .summary-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .summary-card {
            width: 25%;
            padding: 8px 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            text-align: center;
        }
        .summary-card.green {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
        }
        .summary-card.red {
            background-color: #fef2f2;
            border-color: #fecaca;
        }
        .summary-card.amber {
            background-color: #fffbeb;
            border-color: #fde68a;
        }
        .card-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .card-value {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
        }
        .card-value.green { color: #15803d; }
        .card-value.red { color: #b91c1c; }
        .card-value.amber { color: #b45309; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .data-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-align: left;
            padding: 7px 8px;
            border: 1px solid #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            font-size: 9.5px;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            color: #334155;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 3px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-present {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .status-absent {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .status-halfday {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .status-leave {
            background-color: #e0e7ff;
            color: #3730a3;
            border: 1px solid #c7d2fe;
        }
        .footer-note {
            margin-top: 15px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 9px;
            color: #94a3b8;
            width: 100%;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table td {
            font-size: 9px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <div class="header-box">
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <div class="company-title">{{ $companyName ?? 'BuildArya Construction' }}</div>
                    <div class="report-subtitle">Official Attendance Report &bull; {{ $activeSiteName ?? 'All Sites' }}</div>
                </td>
                <td class="header-meta" style="width: 40%;">
                    <div><strong>Report Period:</strong> {{ $reportPeriod }}</div>
                    <div><strong>Generated On:</strong> {{ $generatedAt }}</div>
                    <div><strong>Generated By:</strong> {{ $generatedBy ?? 'BuildArya AI' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Summary Stats KPI Strip -->
    <table class="summary-bar">
        <tr>
            <td class="summary-card">
                <div class="card-label">Total Check-Ins</div>
                <div class="card-value">{{ $stats['total_entries'] ?? count($attendanceLogs) }}</div>
            </td>
            <td class="summary-card green">
                <div class="card-label">Present Logs</div>
                <div class="card-value green">{{ $stats['present'] ?? 0 }}</div>
            </td>
            <td class="summary-card red">
                <div class="card-label">Absent Logs</div>
                <div class="card-value red">{{ $stats['absent'] ?? 0 }}</div>
            </td>
            <td class="summary-card amber">
                <div class="card-label">Contractor Labour Count</div>
                <div class="card-value amber">{{ $stats['labour_count'] ?? 0 }}</div>
            </td>
        </tr>
    </table>

    <!-- Attendance Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%; text-align: center;">#</th>
                <th style="width: 9%;">Date</th>
                <th style="width: 20%;">Employee / Contractor Name</th>
                <th style="width: 12%;">Role / Username</th>
                <th style="width: 14%;">Site Location</th>
                <th style="width: 9%; text-align: center;">Status</th>
                <th style="width: 8%; text-align: center;">Check-In</th>
                <th style="width: 8%; text-align: center;">Check-Out</th>
                <th style="width: 6%; text-align: center;">Labour</th>
                <th style="width: 10%;">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($attendanceLogs as $idx => $log)
                @php
                    $statusStr = strtolower($log->status ?? 'present');
                    $badgeClass = 'status-present';
                    if (strpos($statusStr, 'absent') !== false) {
                        $badgeClass = 'status-absent';
                    } elseif (strpos($statusStr, 'half') !== false) {
                        $badgeClass = 'status-halfday';
                    } elseif (strpos($statusStr, 'leave') !== false) {
                        $badgeClass = 'status-leave';
                    }

                    $inTime = !empty($log->in_time) ? date('h:i A', strtotime($log->in_time)) : '--';
                    $outTime = !empty($log->out_time) ? date('h:i A', strtotime($log->out_time)) : '--';
                    $logDate = !empty($log->date) ? date('d M Y', strtotime($log->date)) : '--';
                @endphp
                <tr>
                    <td style="text-align: center; font-weight: bold; color: #64748b;">{{ $idx + 1 }}</td>
                    <td style="font-weight: 500;">{{ $logDate }}</td>
                    <td>
                        <strong style="color: #0f172a;">{{ $log->user_name ?? 'N/A' }}</strong>
                    </td>
                    <td style="color: #64748b;">{{ $log->user_username ?? '--' }}</td>
                    <td>{{ $log->site_name ?? 'Head Office' }}</td>
                    <td style="text-align: center;">
                        <span class="status-badge {{ $badgeClass }}">{{ $log->status ?? 'Present' }}</span>
                    </td>
                    <td style="text-align: center; font-family: monospace;">{{ $inTime }}</td>
                    <td style="text-align: center; font-family: monospace;">{{ $outTime }}</td>
                    <td style="text-align: center; font-weight: bold; color: {{ ($log->labour_count ?? 0) > 0 ? '#b45309' : '#94a3b8' }};">
                        {{ ($log->labour_count ?? 0) > 0 ? $log->labour_count : '0' }}
                    </td>
                    <td style="color: #64748b;">{{ $log->remarks ?? '--' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center; padding: 25px; color: #64748b; font-style: italic;">
                        No attendance records found for the selected period ({{ $reportPeriod }}).
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer Section -->
    <div class="footer-note">
        <table class="footer-table">
            <tr>
                <td style="text-align: left;">
                    BuildArya Construction Management ERP &bull; Official Digital Attendance Ledger
                </td>
                <td style="text-align: right;">
                    Report generated for {{ $activeSiteName ?? 'Head Office' }}
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
