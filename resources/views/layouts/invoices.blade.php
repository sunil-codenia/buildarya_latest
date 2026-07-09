@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'SaaS Subscription Invoices'])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card">
                <div class="header">
                    <h2><strong>SaaS Invoices</strong> List &nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Manage and download your SaaS subscription and product invoices.</div>
                    </h2>
                </div>
                <div class="body">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if ($error)
                        <div class="alert alert-warning">
                            <strong>Notice:</strong> {{ $error }}
                        </div>
                    @endif

                    @if(empty($invoices))
                        <div class="text-center p-5" style="padding: 40px 0;">
                            <i class="zmdi zmdi-file-text text-muted" style="font-size: 48px; opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                            <p class="text-muted" style="font-size: 16px;">No invoices found for your company.</p>
                        </div>
                    @else
                        @php
                            $latestInvoice = $invoices[0];
                            $hasNextPayment = false;
                            if (!empty($latestInvoice['subscription_end_date'])) {
                                $hasNextPayment = true;
                                $nextPaymentDate = \Carbon\Carbon::parse($latestInvoice['subscription_start_date'])->format('d M Y');
                                $nextDueDate = \Carbon\Carbon::parse($latestInvoice['subscription_end_date'])->format('d M Y');
                                $nextAmount = (float)($latestInvoice['subscription_amount'] ?? $latestInvoice['amount']);
                                $nextPlan = $latestInvoice['subscription_plan'] ?? 'SaaS Subscription';

                                // Calculate new billing cycle dates
                                $newStartDateRaw = $latestInvoice['subscription_end_date'];
                                $newStartDateCarbon = \Carbon\Carbon::parse($newStartDateRaw);
                                $billingCycle = $latestInvoice['billing_cycle'] ?? 'monthly';
                                if ($billingCycle === 'yearly') {
                                    $newEndDateCarbon = (clone $newStartDateCarbon)->addYear();
                                } elseif ($billingCycle === 'quarterly') {
                                    $newEndDateCarbon = (clone $newStartDateCarbon)->addMonths(3);
                                } else {
                                    $newEndDateCarbon = (clone $newStartDateCarbon)->addMonth();
                                }
                                $newStartDate = $newStartDateCarbon->format('Y-m-d');
                                $newEndDate = $newEndDateCarbon->format('Y-m-d');
                            }
                        @endphp
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered" id="saasInvoicesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Invoice No</th>
                                        <th>Description</th>
                                        <th>Invoice Date</th>
                                        <th>Due Date</th>
                                        <th class="text-right">Amount</th>
                                        <th class="text-right">Paid</th>
                                        <th class="text-right">Balance</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $rowIndex = 1; @endphp
                                    @if ($hasNextPayment)
                                        <tr style="background-color: #fffbeb;">
                                            <td>{{ $rowIndex++ }}</td>
                                            <td><span class="text-muted">-</span></td>
                                            <td><strong>{{ $nextPlan }} (Next Payment)</strong></td>
                                            <td>{{ $nextPaymentDate }}</td>
                                            <td><strong class="text-danger">{{ $nextDueDate }}</strong></td>
                                            <td class="text-right">₹{{ number_format($nextAmount, 2) }}</td>
                                            <td class="text-right text-success">₹0.00</td>
                                            <td class="text-right text-danger">₹{{ number_format($nextAmount, 2) }}</td>
                                            <td>
                                                <span class="badge badge-warning" style="font-size: 11px; padding: 4px 8px; text-transform: uppercase;">
                                                    Pending
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" 
                                                   class="btn btn-warning btn-round btn-sm waves-effect pay-now-btn" 
                                                   style="font-weight: bold;"
                                                   data-amount="{{ $nextAmount }}"
                                                   data-sub-id="{{ $latestInvoice['subscription_id'] }}"
                                                   data-plan-id="{{ $latestInvoice['saas_plan_id'] ?? '' }}"
                                                   data-cycle="{{ $latestInvoice['billing_cycle'] ?? 'monthly' }}"
                                                   data-start-date="{{ $newStartDate }}"
                                                   data-end-date="{{ $newEndDate }}"
                                                   data-client-name="{{ $latestInvoice['client']['name'] ?? '' }}"
                                                   data-client-email="{{ $latestInvoice['client']['email'] ?? '' }}"
                                                   data-plan-name="{{ $nextPlan }}"
                                                   title="Pay Subscription via Razorpay">
                                                    <i class="zmdi zmdi-card"></i> Pay Now
                                                </button>
                                            </td>
                                        </tr>
                                    @endif

                                    @foreach($invoices as $invoice)
                                        @php
                                            $st = strtolower($invoice['status'] ?? 'pending');
                                            $badgeClass = 'badge-warning';
                                            if ($st === 'paid') $badgeClass = 'badge-success';
                                            elseif ($st === 'overdue') $badgeClass = 'badge-danger';
                                            elseif ($st === 'cancelled') $badgeClass = 'badge-default';
                                        @endphp
                                        <tr>
                                            <td>{{ $rowIndex++ }}</td>
                                            <td><strong>{{ $invoice['invoice_number'] }}</strong></td>
                                            <td>{{ $invoice['subscription_plan'] ?? 'SaaS Subscription' }}</td>
                                            <td>{{ \Carbon\Carbon::parse($invoice['invoice_date'])->format('d M Y') }}</td>
                                            <td><span class="text-muted">-</span></td>
                                            <td class="text-right">₹{{ number_format((float)($invoice['final_amount'] ?? $invoice['amount']), 2) }}</td>
                                            <td class="text-right text-success">₹{{ number_format((float)($invoice['paid_amount'] ?? 0), 2) }}</td>
                                            <td class="text-right text-danger">₹{{ number_format((float)($invoice['balance_amount'] ?? 0), 2) }}</td>
                                            <td>
                                                <span class="badge {{ $badgeClass }}" style="font-size: 11px; padding: 4px 8px; text-transform: uppercase;">
                                                    {{ $invoice['status'] }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ url('/invoices/' . $invoice['id'] . '/download') }}" 
                                                   class="btn btn-primary btn-round btn-sm waves-effect" 
                                                   title="Download PDF">
                                                    <i class="zmdi zmdi-download"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        $(document).ready(function() {
            if ($('#saasInvoicesTable').length) {
                $('#saasInvoicesTable').DataTable({
                    responsive: true,
                    "order": [],
                    "oLanguage": {
                        "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                        "sSearchPlaceholder": "Search Invoices...",
                        "sLengthMenu": "Results :  _MENU_",
                    }
                });
            }

            function showAlert(title, text, type) {
                if (typeof swal !== 'undefined') {
                    swal({
                        title: title,
                        text: text,
                        icon: type,
                        button: "OK"
                    }).then(function() {
                        if (type === 'success') {
                            window.location.reload();
                        }
                    });
                } else {
                    alert(title + ': ' + text);
                    if (type === 'success') {
                        window.location.reload();
                    }
                }
            }

            $(document).on('click', '.pay-now-btn', function() {
                var $btn = $(this);
                var amount = $btn.data('amount');
                var subscriptionId = $btn.data('sub-id');
                var planId = $btn.data('plan-id');
                var cycle = $btn.data('cycle');
                var startDate = $btn.data('start-date');
                var endDate = $btn.data('end-date');
                var clientName = $btn.data('client-name');
                var clientEmail = $btn.data('client-email');
                var planName = $btn.data('plan-name');

                if (!subscriptionId) {
                    showAlert("Error", "Missing subscription information. Please contact support.", "error");
                    return;
                }

                $btn.prop('disabled', true).html('<i class="zmdi zmdi-hc-fw zmdi-refresh zmdi-hc-spin"></i> Processing...');

                $.ajax({
                    url: '{{ url("/invoices/create-razorpay-order") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        amount: amount
                    },
                    success: function(orderData) {
                        var options = {
                            key: orderData.key_id,
                            amount: orderData.amount,
                            currency: orderData.currency,
                            name: 'Buildarya Technologies',
                            description: 'Subscription Renewal: ' + planName,
                            order_id: orderData.id,
                            handler: function(response) {
                                $btn.html('<i class="zmdi zmdi-hc-fw zmdi-refresh zmdi-hc-spin"></i> Finalizing...');
                                $.ajax({
                                    url: '{{ url("/invoices/finalize-payment") }}',
                                    method: 'POST',
                                    data: {
                                        _token: '{{ csrf_token() }}',
                                        razorpay_payment_id: response.razorpay_payment_id,
                                        razorpay_order_id: response.razorpay_order_id,
                                        razorpay_signature: response.razorpay_signature,
                                        subscriptionId: subscriptionId,
                                        newPlanId: planId,
                                        billingCycle: cycle,
                                        startDate: startDate,
                                        endDate: endDate,
                                        amount: amount
                                    },
                                    success: function(finalizeResult) {
                                        showAlert("Payment Successful!", "Your subscription has been renewed and the invoice has been emailed to you.", "success");
                                    },
                                    error: function(xhr) {
                                        $btn.prop('disabled', false).html('<i class="zmdi zmdi-card"></i> Pay Now');
                                        var errMsg = 'Failed to renew subscription on the billing server.';
                                        if (xhr.responseJSON && xhr.responseJSON.error) {
                                            errMsg = xhr.responseJSON.error;
                                        }
                                        showAlert("Verification Error", errMsg, "error");
                                    }
                                });
                            },
                            prefill: {
                                name: clientName,
                                email: clientEmail
                            },
                            theme: {
                                color: '#FF9800'
                            },
                            modal: {
                                ondismiss: function() {
                                    $btn.prop('disabled', false).html('<i class="zmdi zmdi-card"></i> Pay Now');
                                }
                            }
                        };
                        var rzp = new Razorpay(options);
                        rzp.open();
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).html('<i class="zmdi zmdi-card"></i> Pay Now');
                        var errMsg = 'Failed to create payment order.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errMsg = xhr.responseJSON.error;
                        }
                        showAlert("Order Error", errMsg, "error");
                    }
                });
            });
        });
    </script>
@endsection
