<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice['invoice_number'] }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
            font-size: 13px;
            line-height: 1.5;
        }
        .header-bg {
            background-color: #0f172a;
            color: #ffffff;
            padding: 30px 20px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .title {
            font-size: 26px;
            font-weight: bold;
            margin: 0 0 5px 0;
            color: #ffffff;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 11px;
            color: #cbd5e1;
            margin: 0;
        }
        .provider-details {
            text-align: right;
        }
        .provider-name {
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        .provider-info {
            font-size: 10px;
            color: #cbd5e1;
            margin: 0;
        }
        .infobar-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .infobar-table td {
            padding: 12px 15px;
            vertical-align: top;
            width: 25%;
        }
        .info-label {
            font-size: 9px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .info-val {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .status-paid {
            background-color: #dcfce7;
            color: #15803d;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #b45309;
        }
        .status-overdue {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .status-cancelled {
            background-color: #f1f5f9;
            color: #475569;
        }
        .client-section {
            margin: 25px 0;
        }
        .client-label {
            font-size: 10px;
            font-weight: bold;
            color: #3b82f6;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .client-name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 5px 0;
        }
        .client-details {
            font-size: 11px;
            color: #475569;
            margin: 0;
            line-height: 1.4;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        .items-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            padding: 8px 10px;
            border: 1px solid #0f172a;
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .items-table tr.alt td {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .totals-table {
            width: 280px;
            float: right;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .totals-table td {
            padding: 6px 10px;
        }
        .total-row {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: bold;
        }
        .total-row td {
            padding: 10px;
        }
        .footer {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            font-style: italic;
            line-height: 1.4;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>

    <div class="header-bg">
        <table class="header-table">
            <tr>
                <td>
                    <div class="title">Invoice</div>
                    <div class="subtitle">#{{ $invoice['invoice_number'] }}</div>
                </td>
                <td class="provider-details">
                    <div class="provider-name">{{ $invoice['provider']['name'] ?? 'Shaarvik Technologies LLP' }}</div>
                    <div class="provider-info">support@shaarvik.com</div>
                    <div class="provider-info">Shaarvik Construction Platform</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="infobar-table">
        <tr>
            <td>
                <div class="info-label">Invoice Date</div>
                <div class="info-val">{{ \Carbon\Carbon::parse($invoice['invoice_date'])->format('d M Y') }}</div>
            </td>
            <td>
                <div class="info-label">Due Date</div>
                <div class="info-val">{{ \Carbon\Carbon::parse($invoice['due_date'])->format('d M Y') }}</div>
            </td>
            <td>
                <div class="info-label">Payment Mode</div>
                <div class="info-val">{{ strtoupper($invoice['gateway'] ?? 'MANUAL') }}</div>
            </td>
            <td>
                <div class="info-label">Status</div>
                <div class="info-val" style="margin-top: 4px;">
                    @php
                        $st = strtolower($invoice['status']);
                        $badgeClass = 'status-pending';
                        if ($st === 'paid') $badgeClass = 'status-paid';
                        elseif ($st === 'overdue') $badgeClass = 'status-overdue';
                        elseif ($st === 'cancelled') $badgeClass = 'status-cancelled';
                    @endphp
                    <span class="status-badge {{ $badgeClass }}">{{ $invoice['status'] }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="client-section">
        <div class="client-label">Bill To</div>
        <div class="client-name">{{ $invoice['client']['name'] ?? 'Client Company' }}</div>
        <div class="client-details">
            @if(!empty($invoice['client']['email']))
                Email: {{ $invoice['client']['email'] }}<br>
            @endif
            @if(!empty($invoice['client']['phone']))
                Phone: +91 {{ $invoice['client']['phone'] }}<br>
            @endif
            @if(!empty($invoice['client']['address']))
                Address: {{ $invoice['client']['address'] }}<br>
            @endif
            @if(!empty($invoice['client']['gst']))
                <strong>GST NO: {{ $invoice['client']['gst'] }}</strong>
            @endif
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;" class="text-center">#</th>
                <th style="width: 52%;" class="text-left">Description</th>
                <th style="width: 10%;" class="text-center">Qty</th>
                <th style="width: 15%;" class="text-right">Rate</th>
                <th style="width: 15%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td class="text-left">
                    <strong>{{ $invoice['subscription_plan'] ?? 'SaaS Subscription Plan' }}</strong>
                </td>
                <td class="text-center">1</td>
                <td class="text-right">₹{{ number_format((float)$invoice['amount'], 2) }}</td>
                <td class="text-right">₹{{ number_format((float)$invoice['amount'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="clearfix">
        <table class="totals-table">
            <tr>
                <td class="text-left" style="color: #64748b; font-weight: bold;">Subtotal:</td>
                <td class="text-right" style="font-weight: bold;">₹{{ number_format((float)$invoice['amount'], 2) }}</td>
            </tr>
            @if($invoice['discount'] > 0)
            <tr>
                <td class="text-left" style="color: #64748b; font-weight: bold;">Discount:</td>
                <td class="text-right" style="color: #b91c1c; font-weight: bold;">-₹{{ number_format((float)$invoice['discount'], 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="text-left">TOTAL AMOUNT:</td>
                <td class="text-right">₹{{ number_format((float)$invoice['final_amount'], 2) }}</td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 40px; font-size: 11px; color: #64748b;">
        <strong>Payment Information:</strong><br>
        Method: {{ strtoupper($invoice['gateway'] ?? 'MANUAL') }}<br>
        @if(!empty($invoice['transaction_id']))
            Transaction ID: {{ $invoice['transaction_id'] }}<br>
        @endif
        Please quote the invoice number on all bank transactions.
    </div>

    <div class="footer">
        Thank you for choosing Shaarvik Technologies.<br>
        This is an electronically generated invoice.
    </div>

</body>
</html>
