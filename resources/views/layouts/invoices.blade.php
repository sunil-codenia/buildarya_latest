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
                            $latestInvoice = null;
                            foreach ($invoices as $inv) {
                                if (!empty($inv['subscription_end_date'])) {
                                    $latestInvoice = $inv;
                                    break;
                                }
                            }
                            $hasNextPayment = false;
                            if ($latestInvoice) {
                                $hasNextPayment = true;
                                $nextPaymentDate = \Carbon\Carbon::parse($latestInvoice['subscription_start_date'])->format('d M Y');
                                $nextDueDate = \Carbon\Carbon::parse($latestInvoice['subscription_end_date'])->format('d M Y');
                                $nextAmountBase = (float)($latestInvoice['subscription_amount'] ?? $latestInvoice['amount']);
                                $billingCycle = strtolower($latestInvoice['billing_cycle'] ?? 'monthly');
                                $cycleMultiplier = 1;
                                if ($billingCycle === 'yearly' || $billingCycle === 'annually') {
                                    $cycleMultiplier = 12;
                                } elseif ($billingCycle === 'quarterly') {
                                    $cycleMultiplier = 3;
                                }

                                $extraUsers = isset($company->extra_users) ? (int)$company->extra_users : 0;
                                $extraSites = isset($company->extra_sites) ? (int)$company->extra_sites : 0;
                                $addonAmount = (($extraUsers * 100) + ($extraSites * 200)) * $cycleMultiplier;
                                // The Shaarvik subscription amount already includes active addons, so we just use the base.
                                $nextAmount = $nextAmountBase;
                                
                                $nextPlan = $latestInvoice['subscription_plan'] ?? 'SaaS Subscription';

                                // Calculate new billing cycle dates
                                $newStartDateRaw = $latestInvoice['subscription_end_date'];
                                $newStartDateCarbon = \Carbon\Carbon::parse($newStartDateRaw);

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
                                        <tr style="background-color: #fffbeb;" id="pending-invoice-row">
                                            <td>{{ $rowIndex++ }}</td>
                                            <td>
                                                <strong>PRO-{{ \Carbon\Carbon::parse($newStartDate)->format('Ym') }}-{{ str_pad($latestInvoice['subscription_id'] ?? rand(1000,9999), 4, '0', STR_PAD_LEFT) }}</strong>
                                                <div style="font-size: 10px; color: #777; margin-top: 3px;">Proforma</div>
                                            </td>
                                            <td>
                                                <strong id="display-plan-name">{{ $nextPlan }} (Next Payment)</strong>
                                                @if($extraUsers > 0 || $extraSites > 0)
                                                    <div style="font-size: 11px; margin-top: 5px; color: #555;" id="addons-list-container">
                                                        @if($extraUsers > 0)
                                                            <div id="addon-user-item">+ <span class="addon-user-count">{{ $extraUsers }}</span> Extra User(s) (<span id="addon-user-price-text">₹{{ $extraUsers * 100 * $cycleMultiplier }}</span>) 
                                                                <button class="btn btn-xs btn-danger remove-addon-btn" data-type="user" style="padding: 0 4px; font-size: 10px; margin-left: 5px;" title="Remove 1 User"><i class="zmdi zmdi-minus"></i> Remove</button>
                                                            </div>
                                                        @endif
                                                        @if($extraSites > 0)
                                                            <div id="addon-site-item">+ <span class="addon-site-count">{{ $extraSites }}</span> Extra Site(s) (<span id="addon-site-price-text">₹{{ $extraSites * 200 * $cycleMultiplier }}</span>)
                                                                <button class="btn btn-xs btn-danger remove-addon-btn" data-type="site" style="padding: 0 4px; font-size: 10px; margin-left: 5px;" title="Remove 1 Site"><i class="zmdi zmdi-minus"></i> Remove</button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td>{{ $nextPaymentDate }}</td>
                                            <td><strong class="text-danger" id="display-due-date">{{ $nextDueDate }}</strong></td>
                                            <td class="text-right" id="display-amount">₹{{ number_format($nextAmount, 2) }}</td>
                                            <td class="text-right text-success">₹0.00</td>
                                            <td class="text-right text-danger" id="display-balance">₹{{ number_format($nextAmount, 2) }}</td>
                                            <td>
                                                <span class="badge badge-warning" style="font-size: 11px; padding: 4px 8px; text-transform: uppercase;">
                                                    Pending
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" 
                                                   class="btn btn-warning btn-round btn-sm waves-effect pay-now-btn" 
                                                   style="font-weight: bold; width: 100%;"
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
                                                <button type="button" 
                                                   class="btn btn-info btn-round btn-sm waves-effect change-plan-btn" 
                                                   style="font-weight: bold; margin-top: 5px; display: block; width: 100%; background-color: #3b2f54; border: none; color: white;"
                                                   data-toggle="modal"
                                                   data-target="#changePlanModal"
                                                   title="Upgrade/Downgrade Subscription Plan">
                                                    <i class="zmdi zmdi-swap"></i> Upgrade/Downgrade
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
                                            <td>
                                                <strong>{{ $invoice['plan_name'] ?? ($invoice['subscription_plan'] ?? 'SaaS Subscription') }}</strong>
                                                @if(!empty($invoice['notes']))
                                                    <div style="font-size: 11px; margin-top: 5px; color: #555;">
                                                        {!! nl2br(e($invoice['notes'])) !!}
                                                    </div>
                                                @endif
                                            </td>
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
                                                   title="Download PDF"
                                                   style="color: white !important;">
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

@section('models')
    <!-- Upgrade / Downgrade Plan Modal -->
    <div class="modal fade" id="changePlanModal" tabindex="-1" role="dialog" style="z-index: 1060; overflow-y: auto;">
        <div class="modal-dialog modal-dialog-centered" role="document" style="margin: 30px auto; max-height: calc(100vh - 60px);">
            <div class="modal-content" style="border-radius: 16px; overflow: visible; border: none; box-shadow: 0 15px 35px rgba(0,0,0,0.2); display: flex; flex-direction: column; max-height: calc(100vh - 60px);">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #1f1b2d 0%, #3b2f54 100%); border: none; padding: 20px;">
                    <h5 class="modal-title font-weight-bold" style="display: flex; align-items: center; gap: 10px; margin: 0; color: white;">
                        <i class="zmdi zmdi-swap text-info" style="font-size: 24px;"></i> Upgrade / Downgrade Plan
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 20px; top: 20px; outline: none; background: none; border: none; opacity: 0.8;">
                        <span aria-hidden="true" style="font-size: 1.5rem; color: white;">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 25px; overflow-y: auto; flex: 1 1 auto;">
                    <style>
                        /* Modal scroll & z-index fixes */
                        #changePlanModal {
                            overflow-y: auto !important;
                        }
                        #changePlanModal .modal-dialog {
                            margin: 30px auto !important;
                            display: flex;
                            align-items: flex-start;
                            min-height: calc(100% - 60px);
                        }
                        #changePlanModal .modal-content {
                            max-height: calc(100vh - 60px);
                            display: flex;
                            flex-direction: column;
                            background-color: #ffffff;
                            color: #2c3e50;
                        }
                        #changePlanModal .modal-body {
                            background-color: #f8f9fa;
                            overflow-y: auto !important;
                            -webkit-overflow-scrolling: touch;
                        }
                        #changePlanModal .modal-header {
                            flex-shrink: 0;
                        }
                        #changePlanModal .modal-footer {
                            flex-shrink: 0;
                            background-color: #ffffff;
                            border-top: 1px solid #eee;
                        }
                        .modal-backdrop + .modal-backdrop {
                            display: none;
                        }
                        .plan-card {
                            border: 2px solid #e9ecef !important;
                            border-radius: 12px !important;
                            transition: all 0.3s ease !important;
                            cursor: pointer !important;
                            position: relative;
                            background: #ffffff;
                        }
                        .plan-card h4 {
                            color: #1f1b2d !important;
                        }
                        .plan-card p {
                            color: #666666 !important;
                        }
                        .plan-card .plan-price-text {
                            color: #3b2f54 !important;
                        }
                        .plan-card .plan-price-period {
                            color: #777777 !important;
                        }
                        .plan-card:hover {
                            transform: translateY(-2px);
                            border-color: #3b2f54 !important;
                            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
                        }
                        .plan-card.active {
                            border-color: #3b2f54 !important;
                            background-color: rgba(59, 47, 84, 0.04) !important;
                            box-shadow: 0 8px 25px rgba(59, 47, 84, 0.15) !important;
                        }
                        .plan-card.active::after {
                            content: '\f26b'; /* zmdi-check-circle */
                            font-family: 'Material-Design-Iconic-Font';
                            position: absolute;
                            top: 10px;
                            right: 10px;
                            font-size: 20px;
                            color: #3b2f54;
                        }
                        .cycle-btn {
                            flex: 1;
                            border: 1px solid #ced4da;
                            background: #ffffff;
                            color: #333333;
                            padding: 10px;
                            text-align: center;
                            border-radius: 8px;
                            font-weight: bold;
                            cursor: pointer;
                            transition: all 0.2s;
                        }
                        .cycle-btn:hover {
                            border-color: #3b2f54;
                            color: #3b2f54;
                        }
                        .cycle-btn.active {
                            background: #3b2f54 !important;
                            color: #ffffff !important;
                            border-color: #3b2f54 !important;
                        }
                        .price-breakdown-box {
                            background-color: #ffffff;
                            border-radius: 8px;
                            border: 1px solid #e9ecef;
                            padding: 15px;
                            text-align: left;
                        }
                        .price-breakdown-box h6 {
                            border-bottom: 1px solid #eee;
                            padding-bottom: 8px;
                            margin-top: 0;
                            color: #1f1b2d;
                        }
                        .price-breakdown-box span {
                            color: #555555;
                        }
                        .price-breakdown-box strong {
                            color: #222222;
                        }

                        /* =========================================
                           DARK MODE OVERRIDES (body.menu_dark)
                        ========================================= */
                        body.menu_dark #changePlanModal .modal-content {
                            background-color: #1e1e2d !important;
                            border: 1px solid #2d2d3f !important;
                            color: #ffffff !important;
                        }
                        body.menu_dark #changePlanModal .modal-body {
                            background-color: #14141f !important;
                            color: #ffffff !important;
                        }
                        body.menu_dark #changePlanModal label.text-muted {
                            color: #a0a0b8 !important;
                        }
                        body.menu_dark #changePlanModal .plan-card {
                            background-color: #222232 !important;
                            border-color: #323248 !important;
                        }
                        body.menu_dark #changePlanModal .plan-card:hover {
                            border-color: #764ba2 !important;
                        }
                        body.menu_dark #changePlanModal .plan-card.active {
                            background-color: #2b2640 !important;
                            border-color: #9c88ff !important;
                        }
                        body.menu_dark #changePlanModal .plan-card.active::after {
                            color: #9c88ff !important;
                        }
                        body.menu_dark #changePlanModal .plan-card h4 {
                            color: #ffffff !important;
                        }
                        body.menu_dark #changePlanModal .plan-card p {
                            color: #a0a0b8 !important;
                        }
                        body.menu_dark #changePlanModal .plan-card .plan-price-text {
                            color: #ffffff !important;
                        }
                        body.menu_dark #changePlanModal .plan-card .plan-price-period {
                            color: #a0a0b8 !important;
                        }
                        body.menu_dark #changePlanModal .cycle-btn {
                            background-color: #222232 !important;
                            border-color: #323248 !important;
                            color: #e0e0e0 !important;
                        }
                        body.menu_dark #changePlanModal .cycle-btn:hover {
                            border-color: #9c88ff !important;
                            color: #ffffff !important;
                        }
                        body.menu_dark #changePlanModal .cycle-btn.active {
                            background-color: #764ba2 !important;
                            border-color: #764ba2 !important;
                            color: #ffffff !important;
                        }
                        body.menu_dark #changePlanModal .price-breakdown-box {
                            background-color: #222232 !important;
                            border-color: #323248 !important;
                            color: #ffffff !important;
                        }
                        body.menu_dark #changePlanModal .price-breakdown-box h6 {
                            border-bottom-color: #323248 !important;
                            color: #ffffff !important;
                        }
                        body.menu_dark #changePlanModal .price-breakdown-box span {
                            color: #b0b0c8 !important;
                        }
                        body.menu_dark #changePlanModal .price-breakdown-box strong {
                            color: #ffffff !important;
                        }
                        body.menu_dark #changePlanModal .price-breakdown-box hr {
                            border-top-color: #323248 !important;
                        }
                        body.menu_dark #changePlanModal .modal-footer {
                            background-color: #1e1e2d !important;
                            border-top-color: #2d2d3f !important;
                        }
                        body.menu_dark #changePlanModal .cancel-btn {
                            background-color: #2a2a3c !important;
                            border-color: #38384f !important;
                            color: #e0e0e0 !important;
                        }
                    </style>
                    
                    <div class="form-group mb-4" style="text-align: left;">
                        <label class="font-weight-bold text-muted mb-2 d-block">Select Plan</label>
                        <div class="row">
                            @foreach($plans as $plan)
                                @php
                                    $lowerName = strtolower($plan['name']);
                                @endphp
                                @if($lowerName === 'starter' || $lowerName === 'growth')
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 plan-card" data-plan-id="{{ $plan['id'] }}" data-name="{{ $plan['name'] }}" data-price="{{ $plan['price'] }}">
                                            <div class="card-body p-3 text-center">
                                                <h4 class="font-weight-bold mb-1" style="margin-top: 5px;">{{ ucfirst($plan['name']) }}</h4>
                                                <p class="text-sm mb-3">Up to {{ $plan['maxUsers'] ?? 0 }} users & {{ $plan['maxSites'] ?? 0 }} sites</p>
                                                <h3 class="font-weight-bold mb-0 plan-price-text" style="font-size: 1.6rem;">
                                                    ₹{{ number_format($plan['price']) }}<span class="plan-price-period" style="font-size: 0.9rem; font-weight: normal;"> / mo</span>
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    
                    <div class="form-group mb-4" style="text-align: left;">
                        <label class="font-weight-bold text-muted mb-2 d-block">Billing Cycle</label>
                        <div class="d-flex" style="gap: 10px; display: flex;">
                            <div class="cycle-btn active" data-cycle="monthly">Monthly</div>
                            <div class="cycle-btn" data-cycle="yearly">Yearly (Save more)</div>
                        </div>
                    </div>

                    <div class="p-3 mb-3 price-breakdown-box">
                        <h6 class="font-weight-bold border-b pb-2 mb-2">Price Breakdown</h6>
                        <div class="d-flex justify-content-between mb-2" style="display: flex; justify-content: space-between;">
                            <span>Base Plan Price:</span>
                            <strong id="breakdown-base-price">₹0.00</strong>
                        </div>
                        @if($extraUsers > 0)
                            <div class="d-flex justify-content-between mb-2" style="display: flex; justify-content: space-between;">
                                <span>+ {{ $extraUsers }} Extra User(s):</span>
                                <strong id="breakdown-extra-users">₹0.00</strong>
                            </div>
                        @endif
                        @if($extraSites > 0)
                            <div class="d-flex justify-content-between mb-2" style="display: flex; justify-content: space-between;">
                                <span>+ {{ $extraSites }} Extra Site(s):</span>
                                <strong id="breakdown-extra-sites">₹0.00</strong>
                            </div>
                        @endif
                        <hr style="margin: 10px 0;">
                        <div class="d-flex justify-content-between mb-2" style="display: flex; justify-content: space-between; font-size: 1.1rem;">
                            <strong>Total Amount:</strong>
                            <strong class="text-success" id="breakdown-total-price">₹0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between" style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                            <span>Next Due Date:</span>
                            <strong id="breakdown-new-due-date">-</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0" style="padding: 15px 25px; display: flex; justify-content: space-between; flex-shrink: 0;">
                    <button type="button" class="btn btn-neutral btn-round cancel-btn" data-dismiss="modal" style="font-weight: bold; border-radius: 20px; margin: 0; padding: 8px 20px;">Cancel</button>
                    <button type="button" class="btn btn-primary btn-round" id="confirm-plan-change-btn" style="font-weight: bold; border-radius: 20px; background-color: #3b2f54; border: none; color: white; margin: 0; padding: 8px 20px;">Apply Plan</button>
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

            $(document).on('click', '.remove-addon-btn', function() {
                var type = $(this).data('type');
                if (confirm('Are you sure you want to remove 1 extra ' + type + '? Your next billing amount will be updated.')) {
                    $.ajax({
                        url: '{{ url("/invoices/remove-addon") }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            type: type
                        },
                        success: function(res) {
                            if (res.success) {
                                showAlert("Success", res.message, "success");
                            }
                        },
                        error: function(xhr) {
                            var errMsg = 'Failed to remove addon.';
                            if (xhr.responseJSON && xhr.responseJSON.error) {
                                errMsg = xhr.responseJSON.error;
                            }
                            showAlert("Error", errMsg, "error");
                        }
                    });
                }
            });

            // ========================================================
            // Upgrade / Downgrade Plan Modal Logic
            // ========================================================
            @if($hasNextPayment)
            var changePlan = {
                selectedPlanId: null,
                selectedPlanName: '',
                selectedPlanPrice: 0,
                selectedCycle: '{{ $latestInvoice["billing_cycle"] ?? "monthly" }}',
                extraUsers: {{ $extraUsers ?? 0 }},
                extraSites: {{ $extraSites ?? 0 }},
                currentPlanId: '{{ $latestInvoice["saas_plan_id"] ?? "" }}',
                currentCycle: '{{ $latestInvoice["billing_cycle"] ?? "monthly" }}',
                currentStartDate: '{{ $newStartDate ?? "" }}',
            };

            // Initialize modal on open: pre-select current plan and cycle
            $('#changePlanModal').on('show.bs.modal', function() {
                $('#changePlanModal').appendTo('body');
                var currentId = changePlan.currentPlanId;
                var currentCycle = changePlan.currentCycle;
                
                // Pre-select current plan card
                $('.plan-card').removeClass('active');
                var $currentCard = $('.plan-card[data-plan-id="' + currentId + '"]');
                if ($currentCard.length) {
                    $currentCard.addClass('active');
                    changePlan.selectedPlanId = currentId;
                    changePlan.selectedPlanName = $currentCard.data('name');
                    changePlan.selectedPlanPrice = parseFloat($currentCard.data('price'));
                } else {
                    // Select first card if current not found
                    var $first = $('.plan-card').first();
                    if ($first.length) {
                        $first.addClass('active');
                        changePlan.selectedPlanId = $first.data('plan-id');
                        changePlan.selectedPlanName = $first.data('name');
                        changePlan.selectedPlanPrice = parseFloat($first.data('price'));
                    }
                }

                // Pre-select current cycle
                $('.cycle-btn').removeClass('active');
                var normCycle = currentCycle.toLowerCase();
                if (normCycle === 'yearly' || normCycle === 'annually') {
                    $('.cycle-btn[data-cycle="yearly"]').addClass('active');
                    changePlan.selectedCycle = 'yearly';
                } else {
                    $('.cycle-btn[data-cycle="monthly"]').addClass('active');
                    changePlan.selectedCycle = 'monthly';
                }

                recalcBreakdown();
            });

            // Plan card selection
            $(document).on('click', '.plan-card', function() {
                $('.plan-card').removeClass('active');
                $(this).addClass('active');
                changePlan.selectedPlanId = $(this).data('plan-id');
                changePlan.selectedPlanName = $(this).data('name');
                changePlan.selectedPlanPrice = parseFloat($(this).data('price'));
                recalcBreakdown();
            });

            // Billing cycle selection
            $(document).on('click', '.cycle-btn', function() {
                $('.cycle-btn').removeClass('active');
                $(this).addClass('active');
                changePlan.selectedCycle = $(this).data('cycle');
                recalcBreakdown();
            });

            // Recalculate the breakdown in the modal
            function recalcBreakdown() {
                var cycle = changePlan.selectedCycle;
                var multiplier = 1;
                if (cycle === 'yearly' || cycle === 'annually') {
                    multiplier = 12;
                } else if (cycle === 'quarterly') {
                    multiplier = 3;
                }

                var basePlanTotal = changePlan.selectedPlanPrice * multiplier;
                var extraUserTotal = changePlan.extraUsers * 100 * multiplier;
                var extraSiteTotal = changePlan.extraSites * 200 * multiplier;
                var grandTotal = basePlanTotal + extraUserTotal + extraSiteTotal;

                $('#breakdown-base-price').text('₹' + basePlanTotal.toLocaleString('en-IN', {minimumFractionDigits: 2}));
                if ($('#breakdown-extra-users').length) {
                    $('#breakdown-extra-users').text('₹' + extraUserTotal.toLocaleString('en-IN', {minimumFractionDigits: 2}));
                }
                if ($('#breakdown-extra-sites').length) {
                    $('#breakdown-extra-sites').text('₹' + extraSiteTotal.toLocaleString('en-IN', {minimumFractionDigits: 2}));
                }
                $('#breakdown-total-price').text('₹' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2}));

                // Calculate new end date from current start date
                var newEndDate = calculateEndDate(changePlan.currentStartDate, cycle);
                $('#breakdown-new-due-date').text(formatDateForDisplay(newEndDate));
            }

            function calculateEndDate(startDateStr, cycle) {
                if (!startDateStr) return '';
                var d = new Date(startDateStr + 'T00:00:00');
                if (isNaN(d.getTime())) return '';
                
                if (cycle === 'yearly' || cycle === 'annually') {
                    d.setFullYear(d.getFullYear() + 1);
                } else if (cycle === 'quarterly') {
                    d.setMonth(d.getMonth() + 3);
                } else {
                    d.setMonth(d.getMonth() + 1);
                }
                return d.toISOString().slice(0, 10); // YYYY-MM-DD
            }

            function formatDateForDisplay(dateStr) {
                if (!dateStr) return '-';
                var d = new Date(dateStr + 'T00:00:00');
                if (isNaN(d.getTime())) return '-';
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                return d.getDate().toString().padStart(2, '0') + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
            }

            // Apply Plan button: update the pending invoice row and Pay Now button
            $('#confirm-plan-change-btn').on('click', function() {
                if (!changePlan.selectedPlanId) {
                    showAlert('Error', 'Please select a plan.', 'error');
                    return;
                }

                var cycle = changePlan.selectedCycle;
                var multiplier = 1;
                if (cycle === 'yearly' || cycle === 'annually') {
                    multiplier = 12;
                } else if (cycle === 'quarterly') {
                    multiplier = 3;
                }

                var basePlanTotal = changePlan.selectedPlanPrice * multiplier;
                var extraUserTotal = changePlan.extraUsers * 100 * multiplier;
                var extraSiteTotal = changePlan.extraSites * 200 * multiplier;
                var newTotalAmount = basePlanTotal + extraUserTotal + extraSiteTotal;
                var newEndDate = calculateEndDate(changePlan.currentStartDate, cycle);
                var planDisplayName = changePlan.selectedPlanName;

                // Determine if this is upgrade, downgrade, or same
                var changeType = '';
                if (String(changePlan.selectedPlanId) !== String(changePlan.currentPlanId)) {
                    changeType = changePlan.selectedPlanPrice > 0 ? ' (Plan Change)' : '';
                } else if (cycle !== changePlan.currentCycle) {
                    changeType = ' (Cycle Change)';
                }

                // Update display elements on the invoice row
                $('#display-plan-name').text(planDisplayName + changeType + ' (Next Payment)');
                $('#display-amount').text('₹' + newTotalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2}));
                $('#display-balance').text('₹' + newTotalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2}));
                $('#display-due-date').text(formatDateForDisplay(newEndDate));

                // Update addon price text if visible
                if ($('#addon-user-price-text').length) {
                    $('#addon-user-price-text').text('₹' + extraUserTotal.toLocaleString('en-IN'));
                }
                if ($('#addon-site-price-text').length) {
                    $('#addon-site-price-text').text('₹' + extraSiteTotal.toLocaleString('en-IN'));
                }

                // Update the Pay Now button's data attributes
                var $payBtn = $('.pay-now-btn');
                $payBtn.data('amount', newTotalAmount);
                $payBtn.data('plan-id', changePlan.selectedPlanId);
                $payBtn.data('cycle', cycle);
                $payBtn.data('end-date', newEndDate);
                $payBtn.data('plan-name', planDisplayName);

                // Also update the raw attributes for jQuery .data() caching
                $payBtn.attr('data-amount', newTotalAmount);
                $payBtn.attr('data-plan-id', changePlan.selectedPlanId);
                $payBtn.attr('data-cycle', cycle);
                $payBtn.attr('data-end-date', newEndDate);
                $payBtn.attr('data-plan-name', planDisplayName);

                // Close modal
                $('#changePlanModal').modal('hide');

                // Show confirmation
                showAlert('Plan Updated', 
                    'Your plan has been changed to ' + planDisplayName + ' (' + cycle + '). New amount: ₹' + newTotalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2}) + '. Click "Pay Now" to complete the payment.', 
                    'info');
            });
            @endif
        });
    </script>
@endsection
