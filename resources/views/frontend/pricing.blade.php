@extends('layouts.frontend')

@section('title', 'Pricing — Buildarya Construction Management')
@section('description', 'Clear, transparent pricing plans for Indian construction contractors, builders, and MEP firms.')

@section('content')
    <!-- Pricing Hero -->
    <section class="py-16 bg-gradient-to-b from-teal-50/50 via-slate-50 to-white text-center border-b border-slate-200/80">
        <div class="max-w-4xl mx-auto px-4 space-y-4">
            <span class="text-xs uppercase tracking-widest font-extrabold text-teal-700 bg-teal-50 border border-teal-200 px-3.5 py-1 rounded-full inline-block">
                TRANSPARENT PRICING
            </span>
            <h1 class="font-display text-3xl sm:text-5xl font-extrabold text-slate-900">
                Simple Plans For Contractors
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 font-medium max-w-xl mx-auto">
                No setup fees. Scale your active sites with predictable monthly costs.
            </p>
        </div>
    </section>

    @php
        // Ensure we have Enterprise plan
        $hasEnterprise = false;
        foreach($plans as $p) {
            if (strtolower($p['name']) == 'enterprise') {
                $hasEnterprise = true;
            }
        }
        
        if (!$hasEnterprise) {
            $plans[] = [
                'id' => 'enterprise-static',
                'name' => 'Enterprise',
                'price' => null,
                'strikethroughPrice' => null,
                'isCustom' => true,
                'maxUsers' => null,
                'maxSites' => null,
                'billingCycle' => '',
                'description' => 'Custom solutions for larger teams.',
                'moduleNames' => ['Site Bills', 'Cost Category', 'Attendance Management', 'Task Management', 'Expense', 'Contacts', 'Documents', 'Site & User Management', 'Materials', 'Payment Vouchers', 'Sales', 'Machinery', 'Assets', 'Invoices', 'Support Tickets']
            ];
        }

        $allFeatures = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'note' => 'Free', 'db_names' => ['Dashboard']],
            ['key' => 'site_users', 'label' => 'Site & Users', 'note' => 'Free', 'db_names' => ['Site & User Management']],
            ['key' => 'cost_category', 'label' => 'Cost Category', 'note' => 'Free', 'db_names' => ['Cost Category']],
            ['key' => 'expense', 'label' => 'Expense Log', 'db_names' => ['Expense']],
            ['key' => 'material', 'label' => 'Material Procurement', 'db_names' => ['Materials']],
            ['key' => 'manage_stock', 'label' => 'Manage Stock Balance', 'db_names' => ['Materials']],
            ['key' => 'site_bills', 'label' => 'Sequential Site Bills', 'db_names' => ['Site Bills']],
            ['key' => 'machinery', 'label' => 'Machinery & Equipment', 'db_names' => ['Machinery', 'Machinery Management']],
            ['key' => 'assets', 'label' => 'Assets Log', 'db_names' => ['Assets', 'Asset Management']],
            ['key' => 'sales', 'label' => 'Sales & Billing', 'db_names' => ['Sales', 'Sales Management']],
            ['key' => 'payment_vouchers', 'label' => 'Payment Vouchers', 'db_names' => ['Payment Vouchers']],
            ['key' => 'document_management', 'label' => 'Document Repository', 'db_names' => ['Documents', 'Document Management']],
            ['key' => 'contact_management', 'label' => 'Contact Management', 'db_names' => ['Contacts', 'Contact Management']],
            ['key' => 'attendance_labour', 'label' => 'Attendance & Labour', 'db_names' => ['Attendance Management']],
            ['key' => 'invoices', 'label' => 'Client Invoices', 'db_names' => ['Invoices']],
            ['key' => 'support_tickets', 'label' => 'Support Tickets', 'note' => 'Free', 'db_names' => ['Support Tickets']],
            ['key' => 'management', 'label' => 'System Management', 'note' => 'Free', 'db_names' => ['System Management']],
        ];
    @endphp

    <!-- Pricing Cards Grid -->
    <section class="py-16 bg-slate-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
                @foreach($plans as $plan)
                    @php
                        $isEnterprise = !isset($plan['price']) || $plan['price'] === null;
                        $planPrice = $plan['price'];
                        $displayPrice = $isEnterprise ? 'Custom' : '₹' . number_format($planPrice);
                        $strikethrough = !$isEnterprise && isset($plan['strikethroughPrice']) ? '₹' . number_format($plan['strikethroughPrice']) : null;
                        $period = $isEnterprise ? '' : '/month';
                        $usersLabel = $isEnterprise ? 'Unlimited' : ($plan['maxUsers'] . ' Users');
                        $sitesLabel = $isEnterprise ? 'Unlimited' : ($plan['maxSites'] . ' Sites');
                        $ctaLabel = $isEnterprise ? 'Contact Sales' : 'Get Started';
                        $highlight = !$isEnterprise && (strtolower($plan['name']) == 'growth');
                    @endphp

                    <div class="relative bg-white rounded-3xl p-8 border flex flex-col justify-between transition-all {{ $highlight ? 'border-2 border-teal-500 shadow-xl' : 'border-slate-200 shadow-soft' }}">
                        @if($highlight)
                            <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-teal-600 text-white text-[10px] font-extrabold uppercase tracking-widest px-3 py-1 rounded-full shadow-sm">
                                POPULAR CHOICE
                            </span>
                        @endif

                        <div>
                            <div class="mb-6 pb-6 border-b border-slate-100">
                                <h2 class="font-display text-2xl font-extrabold text-slate-900 capitalize mb-1">{{ $plan['name'] }}</h2>
                                <div class="flex items-baseline gap-1.5 mb-3">
                                    @if($strikethrough)
                                        <span class="text-sm line-through text-slate-400 font-semibold mr-1">{{ $strikethrough }}</span>
                                    @endif
                                    <span class="text-4xl font-extrabold text-slate-900 font-display">{{ $displayPrice }}</span>
                                    <span class="text-xs font-semibold text-slate-500">{{ $period }}</span>
                                </div>
                                <div class="text-xs font-bold text-slate-600 space-x-3 bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span>✓ {{ $sitesLabel }}</span>
                                    <span>•</span>
                                    <span>✓ {{ $usersLabel }}</span>
                                </div>
                            </div>

                            <!-- Highlights -->
                            <ul class="space-y-3 mb-8 text-xs font-semibold text-slate-700">
                                @php $cnt = 0; @endphp
                                @foreach($allFeatures as $feature)
                                    @php
                                        $included = false;
                                        if (isset($feature['note']) && $feature['note'] == 'Free') {
                                            $included = true;
                                        } else {
                                            foreach ($plan['moduleNames'] as $mName) {
                                                foreach ($feature['db_names'] as $dbName) {
                                                    if (strtolower(trim($mName)) == strtolower(trim($dbName))) {
                                                        $included = true;
                                                        break 2;
                                                    }
                                                }
                                            }
                                        }
                                    @endphp
                                    @if($included && $cnt < 6)
                                        <li class="flex items-center gap-2">
                                            <span class="text-teal-600 font-bold">✓</span>
                                            <span>{{ $feature['label'] }}</span>
                                        </li>
                                        @php $cnt++; @endphp
                                    @endif
                                @endforeach
                            </ul>
                        </div>

                        <div>
                            @if($isEnterprise)
                                <a href="{{ url('/contact') }}" class="block text-center py-3.5 rounded-xl text-xs font-extrabold border border-slate-200 text-slate-800 hover:border-teal-400">
                                    {{ $ctaLabel }}
                                </a>
                            @else
                                <button type="button" 
                                        onclick="openLeadModal('{{ $plan['name'] }}', {{ $planPrice }})" 
                                        class="w-full text-center py-3.5 rounded-xl text-xs font-extrabold uppercase tracking-wider {{ $highlight ? 'btn-amber' : 'btn-primary' }}">
                                    {{ $ctaLabel }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Lead Modal Backups & Multi-Step Container (Strict Parity) -->
    <div id="lead-modal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm hidden" aria-modal="true" role="dialog">
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh] border border-slate-100 animate-fade-in">
            <button type="button" id="close-lead-modal" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 transition z-10">
                ✕
            </button>

            <!-- STEP 1: FORM -->
            <form id="lead-form" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                <div class="px-6 pt-6 pb-4 border-b border-slate-100 flex-shrink-0 bg-slate-50">
                    <h2 class="text-base font-extrabold text-slate-900 font-display">
                        Subscribe to Buildarya
                    </h2>
                    <div class="mt-1 inline-flex items-center gap-1.5 px-3 py-0.5 bg-teal-50 border border-teal-200 rounded-full">
                        <span id="modal-plan-name" class="text-xs font-bold text-teal-700 capitalize">Starter Plan</span>
                        <span id="modal-plan-price" class="text-xs text-teal-600 font-semibold">— ₹750/month</span>
                    </div>
                </div>
                
                <div class="px-6 py-4 space-y-3.5 flex-1 overflow-y-auto min-h-0">
                    <div id="lead-error" class="hidden p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold"></div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Full Name *</label>
                            <input type="text" id="lead-name" required placeholder="Rajesh Sharma" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Email Address *</label>
                            <input type="email" id="lead-email" required placeholder="rajesh@company.com" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Password *</label>
                            <input type="password" id="lead-password" required placeholder="Create password" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Phone</label>
                            <input type="tel" id="lead-phone" placeholder="+91 98765 43210" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Company</label>
                            <input type="text" id="lead-company" placeholder="Sharma Constructions" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Company UID</label>
                            <input type="text" id="lead-company-uid" readonly placeholder="Auto-generated" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 text-xs font-mono cursor-not-allowed">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">State *</label>
                            <select id="lead-state" required class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                                <option value="" disabled selected>Select State</option>
                                @foreach([
                                    "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana",
                                    "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur",
                                    "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu",
                                    "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal", "Andaman and Nicobar Islands",
                                    "Chandigarh", "Dadra and Nagar Haveli and Daman and Diu", "Delhi", "Jammu and Kashmir", "Ladakh",
                                    "Lakshadweep", "Puducherry"
                                ] as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">GST Number</label>
                            <input type="text" id="lead-gst" placeholder="GST number (optional)" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Address</label>
                            <textarea id="lead-address" placeholder="Address" rows="1.5" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500 resize-none"></textarea>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Message</label>
                            <textarea id="lead-message" placeholder="Remarks" rows="1.5" class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <div class="px-6 pb-6 pt-3 flex gap-3 border-t border-slate-100 flex-shrink-0 bg-white">
                    <button type="button" id="cancel-lead-modal" class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" id="submit-lead-btn" class="flex-1 py-2.5 rounded-xl btn-primary text-white text-xs font-bold shadow-md">
                        <span class="btn-spinner hidden mr-1">...</span>
                        <span class="btn-text">Continue to Payment</span>
                    </button>
                </div>
            </form>

            <!-- STEP 2: PAYING -->
            <div id="lead-paying" class="px-8 py-12 flex flex-col items-center text-center hidden">
                <h3 class="text-base font-bold text-slate-900 mb-2">Opening Payment Gateway...</h3>
                <p class="text-slate-500 text-xs">Complete payment in the Razorpay popup window.</p>
                <div id="paying-error" class="hidden mt-4 p-3 rounded-xl bg-red-50 text-red-700 text-xs font-semibold w-full text-left"></div>
            </div>

            <!-- STEP 3: SUCCESS -->
            <div id="lead-success" class="px-8 py-10 flex flex-col items-center text-center hidden">
                <h3 class="text-xl font-bold text-slate-900 mb-2 font-display">
                    Welcome, <span id="success-user-name">User</span>! 🎉
                </h3>
                <p class="text-slate-600 text-xs mb-6 font-medium">
                    Your <strong id="success-plan-name">Starter Plan</strong> is now active.
                </p>
                <a href="{{ url('/login') }}" class="w-full py-3 rounded-xl btn-primary text-white font-extrabold text-xs">
                    Login to Dashboard
                </a>
                <button id="success-close-btn" class="w-full mt-2 py-2 text-slate-500 text-xs font-bold">
                    Close
                </button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    let currentPlanName = '';
    let currentPlanPrice = 0;
    let debounceTimer;

    const modal = document.getElementById('lead-modal');
    const formStep = document.getElementById('lead-form');
    const payingStep = document.getElementById('lead-paying');
    const successStep = document.getElementById('lead-success');

    const nameInput = document.getElementById('lead-name');
    const emailInput = document.getElementById('lead-email');
    const passwordInput = document.getElementById('lead-password');
    const phoneInput = document.getElementById('lead-phone');
    const companyInput = document.getElementById('lead-company');
    const companyUidInput = document.getElementById('lead-company-uid');
    const stateSelect = document.getElementById('lead-state');
    const gstInput = document.getElementById('lead-gst');
    const addressInput = document.getElementById('lead-address');
    const messageInput = document.getElementById('lead-message');

    const leadError = document.getElementById('lead-error');
    const payingError = document.getElementById('paying-error');

    function openLeadModal(planName, planPrice) {
        currentPlanName = planName;
        currentPlanPrice = planPrice;

        document.getElementById('modal-plan-name').innerText = planName + ' Plan';
        document.getElementById('modal-plan-price').innerText = '— ₹' + planPrice.toLocaleString('en-IN') + '/month';

        formStep.classList.remove('hidden');
        payingStep.classList.add('hidden');
        successStep.classList.add('hidden');
        leadError.classList.add('hidden');
        payingError.classList.add('hidden');

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeLeadModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        formStep.reset();
    }

    document.getElementById('close-lead-modal').addEventListener('click', closeLeadModal);
    document.getElementById('cancel-lead-modal').addEventListener('click', closeLeadModal);
    document.getElementById('success-close-btn').addEventListener('click', closeLeadModal);

    companyInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const companyName = companyInput.value.trim();
        if (!companyName) {
            companyUidInput.value = '';
            return;
        }
        debounceTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/api/pricing/next-uid?name=${encodeURIComponent(companyName)}`);
                const data = await res.json();
                if (data.uid) companyUidInput.value = data.uid;
            } catch (err) { console.error('UID error:', err); }
        }, 400);
    });

    function loadRazorpay() {
        return new Promise((resolve) => {
            if (window.Razorpay) { resolve(true); return; }
            const s = document.createElement('script');
            s.src = 'https://checkout.razorpay.com/v1/checkout.js';
            s.onload = () => resolve(true);
            s.onerror = () => resolve(false);
            document.body.appendChild(s);
        });
    }

    formStep.addEventListener('submit', async (e) => {
        e.preventDefault();

        const name = nameInput.value.trim();
        const email = emailInput.value.trim();
        const password = passwordInput.value.trim();
        const phone = phoneInput.value.trim();
        const company = companyInput.value.trim();
        const companyUid = companyUidInput.value.trim();
        const state = stateSelect.value;
        const gst = gstInput.value.trim();
        const address = addressInput.value.trim();
        const message = messageInput.value.trim();

        if (!name || !email || !password || !state) {
            leadError.innerText = 'Please fill in all required fields.';
            leadError.classList.remove('hidden');
            return;
        }

        leadError.classList.add('hidden');
        const submitBtn = document.getElementById('submit-lead-btn');
        submitBtn.disabled = true;

        try {
            const leadRes = await fetch('/api/pricing/lead', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({
                    name, email, phone, company, message,
                    planName: currentPlanName, planPrice: currentPlanPrice,
                    password, companyUid, gstNumber: gst, address, state
                })
            });

            const leadData = await leadRes.json();
            if (!leadRes.ok) throw new Error(leadData.error || 'Could not save your details.');

            const savedLeadId = String(leadData.leadId);

            formStep.classList.add('hidden');
            payingStep.classList.remove('hidden');

            const razorpayLoaded = await loadRazorpay();
            if (!razorpayLoaded) throw new Error('Could not load Razorpay SDK.');

            const orderRes = await fetch('/api/pricing/create-order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ amount: currentPlanPrice, currency: 'INR', receipt: `pricing_${savedLeadId}_${Date.now()}` })
            });

            const order = await orderRes.json();
            if (!orderRes.ok) throw new Error(order.error || 'Failed to create payment order.');

            const options = {
                key: '{{ env("RAZORPAY_KEY_ID", "rzp_test_T0dSOhqB0vAipt") }}',
                amount: order.amount,
                currency: order.currency,
                name: 'Buildarya',
                description: `${currentPlanName} Plan — ₹${currentPlanPrice}/month`,
                order_id: order.id,
                prefill: { name: name, email: email, contact: phone },
                theme: { color: '#0D9488' },
                handler: async function (response) {
                    try {
                        const convertRes = await fetch('/api/pricing/convert', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({
                                leadId: savedLeadId, planName: currentPlanName, planPrice: currentPlanPrice,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature
                            })
                        });

                        const convertData = await convertRes.json();
                        if (!convertRes.ok) throw new Error(convertData.error || 'Failed to activate.');

                        document.getElementById('success-user-name').innerText = name.split(' ')[0];
                        document.getElementById('success-plan-name').innerText = currentPlanName + ' Plan';
                        payingStep.classList.add('hidden');
                        successStep.classList.remove('hidden');

                    } catch (convErr) {
                        payingError.innerText = convErr.message || 'Payment success but activation failed.';
                        payingError.classList.remove('hidden');
                    }
                },
                modal: {
                    ondismiss: function () {
                        payingStep.classList.add('hidden');
                        formStep.classList.remove('hidden');
                    }
                }
            };

            const rzp = new window.Razorpay(options);
            rzp.open();

        } catch (err) {
            payingStep.classList.add('hidden');
            formStep.classList.remove('hidden');
            leadError.innerText = err.message || 'Something went wrong.';
            leadError.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
        }
    });
</script>
@endsection
