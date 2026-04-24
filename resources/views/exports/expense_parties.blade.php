<!DOCTYPE html>
<html>
<head>
    <title>Expense Parties Report</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .header { text-align: center; margin-bottom: 20px; }
    </style>
</head>
<html>
<body>
    <div class="header">
        <h2>Expense Parties Report</h2>
        <p>Generated on: {{ date('d-m-Y H:i:s') }}</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Address</th>
                <th>PAN No</th>
                <th>Site</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr>
                <td>{{ $row->id }}</td>
                <td>{{ $row->name }}</td>
                <td>{{ $row->address }}</td>
                <td>{{ $row->pan_no }}</td>
                <td>{{ $row->site }}</td>
                <td>{{ $row->status }}</td>
                <td>{{ $row->create_datetime }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
