<!DOCTYPE html>
<html>
<head>
    <title>Document Management Report</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #2d3748; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #5b21b6; padding-bottom: 15px; }
        .header h1 { font-size: 20px; color: #5b21b6; margin: 0 0 5px 0; text-transform: uppercase; font-weight: 700; }
        .header p { margin: 0; color: #718096; font-size: 11px; }
        
        .stats-container { margin-bottom: 25px; background: #f7fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; }
        .stats-table { width: 100%; border: none; margin: 0; }
        .stats-table td { border: none; padding: 4px 8px; font-size: 11px; }
        .stats-label { font-weight: bold; color: #4a5568; }
        .stats-value { color: #5b21b6; font-weight: bold; }
        
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #e2e8f0; padding: 8px 6px; text-align: left; font-size: 10px; }
        table.data-table th { background-color: #5b21b6; color: #ffffff; text-transform: uppercase; font-weight: bold; font-size: 9px; letter-spacing: 0.5px; }
        table.data-table tr:nth-child(even) { background-color: #f8fafc; }
        
        .status-badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 9px; text-transform: uppercase; }
        .status-approved { background-color: #c6f6d5; color: #22543d; }
        .status-pending { background-color: #feebc8; color: #744210; }
        
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center; font-size: 9px; color: #a0aec0; border-top: 1px solid #edf2f7; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BuildArya Document Management Report</h1>
        <p>Generated on: {{ date('d-m-Y H:i:s') }} | Connection: {{ strtoupper($connection) }}</p>
    </div>

    @if(isset($stats))
    <div class="stats-container">
        <table class="stats-table">
            <tr>
                <td class="stats-label" width="15%">Storage Consume:</td>
                <td class="stats-value" width="18%">{{ $stats['storage_consume'] }}</td>
                <td class="stats-label" width="12%">Total Files:</td>
                <td class="stats-value" width="18%">{{ $stats['total_files'] }}</td>
                <td class="stats-label" width="15%">Total Images:</td>
                <td class="stats-value" width="22%">{{ $stats['total_images'] }}</td>
            </tr>
            <tr>
                <td class="stats-label">Total PDFs:</td>
                <td class="stats-value">{{ $stats['total_pdfs'] }}</td>
                <td class="stats-label">Other Files:</td>
                <td class="stats-value">{{ $stats['total_others'] }}</td>
                <td class="stats-label">Report Scope:</td>
                <td class="stats-value" style="color: #4a5568;">{{ ucfirst($type) }} Documents</td>
            </tr>
        </table>
    </div>
    @endif

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">ID</th>
                <th width="20%">Name</th>
                <th width="10%">Date</th>
                <th width="20%">Particulars</th>
                <th width="20%">Meta Filters</th>
                <th width="10%">Status</th>
                <th width="15%">Creator</th>
            </tr>
        </thead>
        <tbody>
            @if(count($data) > 0)
                @foreach($data as $row)
                <tr>
                    <td>{{ $row->id }}</td>
                    <td style="font-weight: bold; color: #2d3748;">{{ $row->name }}</td>
                    <td>{{ date('d-m-Y', strtotime($row->date)) }}</td>
                    <td>{{ $row->particular ?: 'N/A' }}</td>
                    <td style="color: #4a5568; font-style: italic; font-size: 9px;">{{ $row->filter ?: 'No tags' }}</td>
                    <td>
                        <span class="status-badge {{ $row->status == 'Approved' ? 'status-approved' : 'status-pending' }}">
                            {{ $row->status }}
                        </span>
                    </td>
                    <td>{{ $row->creator_name ?: 'System' }}</td>
                </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="7" style="text-align: center; color: #a0aec0; padding: 20px;">No documents found matching the scope.</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        BuildArya Systems Management | Page 1 of 1
    </div>
</body>
</html>
