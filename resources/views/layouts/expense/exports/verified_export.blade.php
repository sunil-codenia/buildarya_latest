<table>
    <thead>
        <tr>
            <th colspan="11" style="text-align: center; font-size: 16px; font-weight: bold;">Verified Expenses Report</th>
        </tr>
        <tr>
            <th colspan="11" style="text-align: center;">Generated on: {{ date('d-m-Y H:i:s') }}</th>
        </tr>
        <tr><th></th></tr>
        <tr style="background-color: {{ $color ?? '#49c5b6' }}; color: #ffffff;">
            <th>#</th>
            <th>Date</th>
            <th>Party Name</th>
            <th>Head</th>
            <th>Particular</th>
            <th>Amount</th>
            <th>User</th>
            <th>Site</th>
            <th>Location</th>
            <th>Remark</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($expenses as $index => $exp)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $exp->date }}</td>
            <td>{{ $exp->party_name }}</td>
            <td>{{ $exp->head }}</td>
            <td>{{ $exp->particular }}</td>
            <td>{{ $exp->amount }}</td>
            <td>{{ $exp->user }}</td>
            <td>{{ $exp->site }}</td>
            <td>{{ $exp->location }}</td>
            <td>{{ $exp->remark }}</td>
            <td>{{ $exp->status }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
