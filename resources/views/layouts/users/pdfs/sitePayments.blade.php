<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .title { font-size: 18px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Site Payments Report</div>
        <div>Site: {{ $site_name }}</div>
        @if($start_date && $end_date)
            <div>Period: {{ $start_date }} to {{ $end_date }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Remark</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $payment)
            <tr>
                <td>{{ $payment->date }}</td>
                <td>{{ $payment->remark }}</td>
                <td>{{ number_format($payment->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
