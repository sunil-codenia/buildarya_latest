@extends('layouts.frontend')

@section('title', 'Pricing — Buildarya Construction Management')

@section('content')
    <!-- Pricing Hero -->
    <section class="pt-28 pb-16 bg-bg border-b border-border relative overflow-hidden animate-fade-in">
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-2xl">
                <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-4 block">Pricing</span>
                <h1 class="font-display text-4xl sm:text-5xl text-fg leading-tight mb-5">
                    Simple pricing for<br />
                    <span class="italic text-teal-gradient">contractors of all sizes</span>
                </h1>
                <p class="text-base text-fg-muted leading-relaxed mb-8 max-w-lg">
                    Three clear plans with no hidden costs. Scale your construction business with the tools you need.
                </p>
            </div>
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
        
        // Add Enterprise statically if not returned by API
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
            ['key' => 'expense', 'label' => 'Expense', 'db_names' => ['Expense']],
            ['key' => 'material', 'label' => 'Material', 'db_names' => ['Materials']],
            ['key' => 'manage_stock', 'label' => 'Manage Stock', 'db_names' => ['Materials']],
            ['key' => 'site_bills', 'label' => 'Site Bills', 'db_names' => ['Site Bills']],
            ['key' => 'machinery', 'label' => 'Machinery', 'db_names' => ['Machinery', 'Machinery Management']],
            ['key' => 'assets', 'label' => 'Assets', 'db_names' => ['Assets', 'Asset Management']],
            ['key' => 'sales', 'label' => 'Sales', 'db_names' => ['Sales', 'Sales Management']],
            ['key' => 'payment_vouchers', 'label' => 'Payment Vouchers', 'db_names' => ['Payment Vouchers']],
            ['key' => 'document_management', 'label' => 'Document Management', 'db_names' => ['Documents', 'Document Management']],
            ['key' => 'contact_management', 'label' => 'Contact Management', 'db_names' => ['Contacts', 'Contact Management']],
            ['key' => 'attendance_labour', 'label' => 'Attendance & Labour', 'db_names' => ['Attendance Management']],
            ['key' => 'invoices', 'label' => 'Invoices', 'db_names' => ['Invoices']],
            ['key' => 'support_tickets', 'label' => 'Support Tickets', 'note' => 'Free', 'db_names' => ['Support Tickets']],
            ['key' => 'management', 'label' => 'Management', 'note' => 'Free', 'db_names' => ['System Management']],
        ];
    @endphp

    <!-- Pricing Plans -->
    <section class="py-16 bg-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                @foreach($plans as $plan)
                    @php
                        $isEnterprise = !isset($plan['price']) || $plan['price'] === null;
                        $planPrice = $plan['price'];
                        $displayPrice = $isEnterprise ? 'Custom' : '₹' . number_format($planPrice);
                        $strikethrough = !$isEnterprise && isset($plan['strikethroughPrice']) ? '₹' . number_format($plan['strikethroughPrice']) : null;
                        $period = $isEnterprise ? '' : '/month';
                        $usersLabel = $isEnterprise ? 'Unlimited' : ($plan['maxUsers'] . ' Active');
                        $sitesLabel = $isEnterprise ? 'Unlimited' : ($plan['maxSites'] . ' Active');
                        $modulePriceLabel = $isEnterprise ? 'All included' : '₹' . number_format($planPrice) . ' @50%';
                        $ctaLabel = $isEnterprise ? 'Contact Us' : 'Get Started';
                        $highlight = !$isEnterprise && (strtolower($plan['name']) == 'growth');
                    @endphp

                    <div class="relative rounded-3xl p-7 flex flex-col {{ $highlight ? 'bg-primary border-2 border-primary shadow-teal text-white' : 'bg-white border border-border hover:shadow-card' }} transition-all duration-300">
                        @if($highlight)
                            <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-accent text-fg text-2xs font-bold uppercase tracking-widest px-3 py-1 rounded-full shadow-accent">
                                Most Popular
                            </span>
                        @endif

                        <div class="mb-6 pb-6 border-b {{ $highlight ? 'border-white/10' : 'border-border' }}">
                            <h2 class="font-display text-xl font-semibold mb-1 capitalize">{{ $plan['name'] }}</h2>
                            <p class="text-xs mb-4 opacity-75">
                                @if($isEnterprise)
                                    For large enterprise contractors
                                @else
                                    Manage up to {{ $plan['maxSites'] }} active sites with {{ $plan['maxUsers'] }} users.
                                @endif
                            </p>
                            <div class="flex items-baseline gap-1.5 flex-wrap">
                                @if($strikethrough)
                                    <span class="text-lg line-through opacity-60 mr-1">{{ $strikethrough }}</span>
                                @endif
                                <span class="text-4xl font-bold font-display">{{ $displayPrice }}</span>
                                <span class="text-sm opacity-60">{{ $period }}</span>
                            </div>
                            
                            <div class="mt-4 space-y-2 text-sm rounded-xl p-3 {{ $highlight ? 'bg-primary-dark/40' : 'bg-bg-surface/60' }}">
                                <div class="flex justify-between">
                                    <span class="opacity-75">Users Limit</span>
                                    <span class="font-medium">{{ $usersLabel }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="opacity-75">Sites Limit</span>
                                    <span class="font-medium">{{ $sitesLabel }}</span>
                                </div>
                                @if(!$isEnterprise)
                                    <div class="pt-2 border-t text-2xs space-y-0.5 opacity-60 {{ $highlight ? 'border-white/15' : 'border-border' }}">
                                        <div>+₹100/extra user/month</div>
                                        <div>+₹200/extra site/month</div>
                                    </div>
                                @endif
                            </div>
                            
                            <p class="text-2xs mt-3 opacity-60">
                                Additional modules: <span class="font-semibold">{{ $modulePriceLabel }}</span>
                            </p>
                        </div>

                        <ul class="space-y-3 flex-1 mb-7">
                            @php
                                $displayedCount = 0;
                            @endphp
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
                                @if($included && $displayedCount < 6)
                                    <li class="flex items-center gap-2.5">
                                        <svg width="15" height="15" viewBox="0 0 16 16" fill="none" class="{{ $highlight ? 'text-accent' : 'text-primary' }}">
                                            <path d="M3 8l3.5 3.5L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span class="text-sm">{{ $feature['label'] }}</span>
                                    </li>
                                    @php $displayedCount++; @endphp
                                @endif
                            @endforeach
                            
                            @if($isEnterprise)
                                <li class="flex items-center gap-2.5">
                                    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" class="text-primary">
                                        <path d="M3 8l3.5 3.5L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span class="text-sm">Priority Support</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" class="text-primary">
                                        <path d="M3 8l3.5 3.5L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span class="text-sm">Custom Reporting</span>
                                </li>
                            @endif
                        </ul>

                        @if($isEnterprise)
                            <a href="{{ url('/contact') }}" class="text-center py-3.5 rounded-xl text-sm font-semibold border border-border hover:border-primary/40 hover:bg-primary/5 text-fg transition-all duration-200">
                                {{ $ctaLabel }}
                            </a>
                        @else
                            <button type="button" 
                                    onclick="openLeadModal('{{ $plan['name'] }}', {{ $planPrice }})" 
                                    class="text-center py-3.5 rounded-xl text-sm font-semibold transition-all duration-200 {{ $highlight ? 'btn-accent shadow-accent' : 'border border-border hover:border-primary/40 hover:bg-primary/5 text-fg' }}">
                                {{ $ctaLabel }}
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Feature comparison table -->
            <div class="mt-16 max-w-5xl mx-auto overflow-x-auto rounded-2xl border border-border bg-white shadow-soft">
                <div class="grid bg-bg-surface/50 border-b border-border" style="grid-template-columns: repeat({{ count($plans) + 1 }}, minmax(180px, 1fr))">
                    <div class="py-4 px-6 text-xs font-bold text-fg-muted uppercase tracking-wider">Module / Feature</div>
                    @foreach($plans as $plan)
                        @php
                            $isGrowth = strtolower($plan['name']) == 'growth';
                        @endphp
                        <div class="py-4 px-6 text-xs font-bold uppercase tracking-wider text-center {{ $isGrowth ? 'text-primary' : 'text-fg-muted' }}">
                            {{ $plan['name'] }}
                        </div>
                    @endforeach
                </div>

                @foreach($allFeatures as $feature)
                    <div class="grid border-b border-border/60 last:border-0 hover:bg-bg/40 transition-colors" style="grid-template-columns: repeat({{ count($plans) + 1 }}, minmax(180px, 1fr))">
                        <div class="py-3.5 px-6 text-sm text-fg flex items-center gap-2">
                            <span>{{ $feature['label'] }}</span>
                            @if(isset($feature['note']))
                                <span class="text-[10px] font-bold text-green-600 bg-green-50 border border-green-200 px-1.5 py-0.5 rounded uppercase tracking-wide">
                                    {{ $feature['note'] }}
                                </span>
                            @endif
                        </div>
                        @foreach($plans as $plan)
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
                                $isGrowth = strtolower($plan['name']) == 'growth';
                            @endphp
                            <div class="py-3.5 px-6 flex items-center justify-center">
                                @if($included)
                                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none" class="{{ $isGrowth ? 'text-primary' : 'text-primary/70' }}">
                                        <path d="M3 8l3.5 3.5L13 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                @else
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="text-fg-subtle opacity-30">
                                        <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="text-center p-6 rounded-2xl bg-white border border-border mt-12">
                <p class="text-sm text-fg-muted">
                    <span class="font-semibold text-fg">Need help choosing?</span> Contact us for a demo and setup assistance. 
                    <a href="mailto:hello@buildarya.in" class="text-primary font-medium hover:underline">hello@buildarya.in</a>
                </p>
            </div>
        </div>
    </section>

    <!-- Lead Modal Backdrop & Container -->
    <div id="lead-modal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" aria-modal="true" role="dialog">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh] animate-fade-in">
            <!-- Close button -->
            <button type="button" id="close-lead-modal" class="absolute top-4 right-4 p-1.5 rounded-lg text-fg-subtle hover:text-fg hover:bg-bg-surface transition-colors z-10" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            <!-- STEP 1: FORM -->
            <form id="lead-form" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                <div class="px-6 pt-6 pb-4 border-b border-border flex-shrink-0">
                    <h2 class="text-lg font-bold text-fg flex items-center gap-2">
                        <span>🏗️</span> Get Started with Buildarya
                    </h2>
                    <div class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 bg-primary/10 border border-primary/20 rounded-full">
                        <span id="modal-plan-name" class="text-sm font-semibold text-primary capitalize">Starter Plan</span>
                        <span id="modal-plan-price" class="text-sm text-primary/80">— ₹750/month</span>
                    </div>
                </div>
                
                <div class="px-6 py-4 space-y-4 flex-1 overflow-y-auto min-h-0">
                    <div id="lead-error" class="hidden flex items-start gap-2 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                        <!-- Error message -->
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-fg mb-1">Full Name <span class="text-primary">*</span></label>
                            <input type="text" id="lead-name" required placeholder="Your full name" class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        </div>
                        
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-fg mb-1">Email Address <span class="text-primary">*</span></label>
                            <input type="email" id="lead-email" required placeholder="you@company.com" class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <span class="text-[10px] text-fg-subtle mt-1 block">Your email will serve as your login User ID.</span>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-fg mb-1">Password <span class="text-primary">*</span></label>
                            <input type="password" id="lead-password" required placeholder="Enter password" class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-fg mb-1">Phone</label>
                            <input type="tel" id="lead-phone" placeholder="+91 98765 43210" class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-fg mb-1">Company</label>
                            <input type="text" id="lead-company" placeholder="Your company" class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-fg mb-1">Company UID</label>
                            <input type="text" id="lead-company-uid" readonly placeholder="Auto-generated" class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg-surface text-fg-muted text-sm cursor-not-allowed">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-fg mb-1">State <span class="text-primary">*</span></label>
                            <select id="lead-state" required class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
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
                            <label class="block text-xs font-semibold text-fg mb-1">GST Number</label>
                            <input type="text" id="lead-gst" placeholder="Enter GST number (optional)" class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-fg mb-1">Address</label>
                            <textarea id="lead-address" placeholder="Enter Company Address" rows="1.5" class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"></textarea>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-fg mb-1">Message <span class="text-fg-subtle font-normal">(optional)</span></label>
                            <textarea id="lead-message" placeholder="Any questions or specific requirements..." rows="1.5" class="w-full px-4 py-2.5 rounded-xl border border-border bg-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none"></textarea>
                        </div>
                    </div>

                    <p class="text-[10px] text-fg-muted text-center mt-2">
                        By continuing you agree to our Terms of Service. Your details are saved securely.
                    </p>
                </div>

                <div class="px-6 pb-6 pt-4 flex gap-3 border-t border-border flex-shrink-0 bg-white">
                    <button type="button" id="cancel-lead-modal" class="flex-1 py-2.5 rounded-xl border border-border text-fg-muted text-sm font-semibold hover:bg-bg-surface transition-colors">
                        Cancel
                    </button>
                    <button type="submit" id="submit-lead-btn" class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white text-sm font-bold transition-all shadow-md">
                        <span class="btn-spinner hidden mr-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        <span class="btn-text">Continue to Payment</span>
                    </button>
                </div>
            </form>

            <!-- STEP 2: PAYING -->
            <div id="lead-paying" class="px-8 py-16 flex flex-col items-center text-center hidden">
                <div class="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center mb-5">
                    <svg class="animate-spin h-8 w-8 text-primary" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-fg mb-2">Opening Payment Gateway</h3>
                <p class="text-fg-muted text-sm">Your details have been saved. Complete the payment in the Razorpay window.</p>
                <div id="paying-error" class="hidden mt-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm w-full text-left"></div>
            </div>

            <!-- STEP 3: SUCCESS -->
            <div id="lead-success" class="px-8 py-12 flex flex-col items-center text-center hidden">
                <div class="w-20 h-20 rounded-full bg-green-50 flex items-center justify-center mb-6">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" class="text-green-500">
                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-fg mb-2">
                    Welcome, <span id="success-user-name">User</span>! 🎉
                </h3>
                <p class="text-fg-muted text-sm mb-1">
                    Your <strong class="text-fg" id="success-plan-name">Starter Plan</strong> is now active.
                </p>
                <p class="text-fg-muted text-sm mb-8">
                    Check your email — we've sent your login credentials and invoice.
                </p>
                <div class="w-full space-y-3">
                    <a href="{{ url('/login') }}" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-primary hover:bg-primary-dark text-white font-bold text-sm transition-colors shadow-sm">
                        Login to Dashboard
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                    <button id="success-close-btn" class="w-full py-2.5 rounded-xl border border-border text-fg-muted text-sm font-medium hover:bg-bg-surface transition-colors">
                        Close
                    </button>
                </div>
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

        // Reset steps
        formStep.classList.remove('hidden');
        payingStep.classList.add('hidden');
        successStep.classList.add('hidden');
        leadError.classList.add('hidden');
        payingError.classList.add('hidden');

        // Show modal
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

    // Auto-generate Company UID
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
                if (data.uid) {
                    companyUidInput.value = data.uid;
                }
            } catch (err) {
                console.error('Failed to generate UID:', err);
            }
        }, 400);
    });

    // Load Razorpay Script Helper
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

    // Submit Lead Form and Trigger Razorpay
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
        submitBtn.querySelector('.btn-spinner').classList.remove('hidden');
        submitBtn.querySelector('.btn-text').innerText = 'Saving...';

        try {
            // 1. Save Lead
            const leadRes = await fetch('/api/pricing/lead', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    name, email, phone, company, message,
                    planName: currentPlanName,
                    planPrice: currentPlanPrice,
                    password, companyUid,
                    gstNumber: gst, address, state
                })
            });

            const leadData = await leadRes.json();
            if (!leadRes.ok) throw new Error(leadData.error || 'Could not save your details.');

            const savedLeadId = String(leadData.leadId);

            // Transition to step 2 (paying)
            formStep.classList.add('hidden');
            payingStep.classList.remove('hidden');

            // 2. Load Razorpay SDK
            const razorpayLoaded = await loadRazorpay();
            if (!razorpayLoaded) throw new Error('Could not load Razorpay SDK.');

            // 3. Create Razorpay Order
            const orderRes = await fetch('/api/pricing/create-order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    amount: currentPlanPrice,
                    currency: 'INR',
                    receipt: `pricing_${savedLeadId}_${Date.now()}`
                })
            });

            const order = await orderRes.json();
            if (!orderRes.ok) throw new Error(order.error || 'Failed to create payment order.');

            // 4. Open Razorpay Modal
            const options = {
                key: '{{ env("RAZORPAY_KEY_ID", "rzp_test_T0dSOhqB0vAipt") }}',
                amount: order.amount,
                currency: order.currency,
                name: 'Buildarya by Shaarvik',
                description: `${currentPlanName} Plan — ₹${currentPlanPrice}/month`,
                order_id: order.id,
                prefill: {
                    name: name,
                    email: email,
                    contact: phone
                },
                theme: {
                    color: '#0B6E6E'
                },
                handler: async function (response) {
                    try {
                        // 5. Convert Lead
                        const convertRes = await fetch('/api/pricing/convert', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                leadId: savedLeadId,
                                planName: currentPlanName,
                                planPrice: currentPlanPrice,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature
                            })
                        });

                        const convertData = await convertRes.json();
                        if (!convertRes.ok) throw new Error(convertData.error || 'Failed to active subscription.');

                        // Render Success Screen
                        document.getElementById('success-user-name').innerText = name.split(' ')[0];
                        document.getElementById('success-plan-name').innerText = currentPlanName + ' Plan';
                        payingStep.classList.add('hidden');
                        successStep.classList.remove('hidden');

                    } catch (convErr) {
                        payingError.innerText = convErr.message || 'Payment was successful but activation failed. Please contact support.';
                        payingError.classList.remove('hidden');
                    }
                },
                modal: {
                    ondismiss: function () {
                        payingStep.classList.add('hidden');
                        formStep.classList.remove('hidden');
                        leadError.innerText = 'Payment was cancelled. You can try again.';
                        leadError.classList.remove('hidden');
                    }
                }
            };

            const rzp = new window.Razorpay(options);
            rzp.on('payment.failed', function (resp) {
                payingStep.classList.add('hidden');
                formStep.classList.remove('hidden');
                leadError.innerText = resp.error.description || 'Payment failed. Please try again.';
                leadError.classList.remove('hidden');
            });
            rzp.open();

        } catch (err) {
            payingStep.classList.add('hidden');
            formStep.classList.remove('hidden');
            leadError.innerText = err.message || 'Something went wrong. Please try again.';
            leadError.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
            submitBtn.querySelector('.btn-spinner').classList.add('hidden');
            submitBtn.querySelector('.btn-text').innerText = 'Continue to Payment';
        }
    });
</script>
@endsection
