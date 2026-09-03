@extends('layouts.frontend')

@section('title', 'Buildarya — Premier Construction MIS & Site Management Platform')
@section('description', 'Buildarya helps Indian contractors manage sites, daily expenses, material stock, labour attendance, and billing in one unified system.')

@section('content')
    <!-- Hero Section -->
    <section class="relative pt-16 pb-24 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-teal-50/80 via-slate-50 to-white overflow-hidden animate-fade-in">
        <!-- Ambient Mesh Blur Background Glows -->
        <div class="ambient-glow bg-teal-400 -top-20 -left-20 w-96 h-96"></div>
        <div class="ambient-glow bg-amber-400 top-1/3 -right-20 w-[450px] h-[450px]"></div>

        <div class="max-w-7xl mx-auto relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                
                <!-- Left Hero Copy -->
                <div class="lg:col-span-6 space-y-6 text-center lg:text-left">
                    <div>
                        <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-teal-300 bg-white text-teal-800 text-xs font-black shadow-sm">
                            <span class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-pulse"></span>
                            #1 Site Management MIS for Indian Contractors
                        </span>
                    </div>

                    <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-extrabold leading-[1.1] text-slate-900 tracking-tight">
                        Manage Your Construction Sites With <span class="text-gradient-teal">Zero Cash Leakage</span>
                    </h1>

                    <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-xl mx-auto lg:mx-0 font-medium">
                        Track site petty cash, material stock arrivals, contractor labour attendance, and sequential billing in one unified, simple system.
                    </p>

                    <!-- CTAs -->
                    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                        <a href="{{ url('/contact') }}" class="btn-amber px-8 py-4 rounded-2xl text-xs font-black uppercase tracking-wider flex items-center gap-2 shadow-amber-glow">
                            Book Free Live Demo
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </a>
                        <a href="{{ url('/features') }}" class="px-7 py-4 rounded-2xl text-xs font-extrabold border border-slate-200 bg-white text-slate-700 hover:border-teal-500 hover:text-teal-700 transition-all shadow-sm">
                            Explore Features
                        </a>
                    </div>

                    <!-- Clean Live Key Stats -->
                    <div class="pt-6 border-t border-slate-200/80 grid grid-cols-3 gap-4 max-w-md mx-auto lg:mx-0 text-left">
                        <div class="p-3 bg-white/80 rounded-2xl border border-slate-200/60 shadow-sm">
                            <div class="text-xl font-black text-slate-900 font-display">3,500+</div>
                            <div class="text-[11px] text-slate-500 font-bold">Active Sites</div>
                        </div>
                        <div class="p-3 bg-white/80 rounded-2xl border border-slate-200/60 shadow-sm">
                            <div class="text-xl font-black text-teal-700 font-display">₹150Cr+</div>
                            <div class="text-[11px] text-slate-500 font-bold">Expenses Tracked</div>
                        </div>
                        <div class="p-3 bg-white/80 rounded-2xl border border-slate-200/60 shadow-sm">
                            <div class="text-xl font-black text-amber-600 font-display">99.8%</div>
                            <div class="text-[11px] text-slate-500 font-bold">Supervisor Ease</div>
                        </div>
                    </div>
                </div>

                <!-- Right Interactive Live Feature Preview Card -->
                <div class="lg:col-span-6">
                    <div class="relative rounded-3xl bg-white border border-slate-200 p-4 sm:p-6 shadow-2xl overflow-hidden group">
                        <!-- Header Bar -->
                        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100 text-xs text-slate-500 font-bold">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-rose-400"></span>
                                <span class="w-3 h-3 rounded-full bg-amber-400"></span>
                                <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                                <span class="ml-2 font-mono text-slate-600 text-[11px]">buildarya.app/site-402</span>
                            </div>
                            <span class="bg-teal-50 text-teal-700 border border-teal-200 px-3 py-0.5 rounded-full text-[10px] uppercase font-black tracking-wider">LIVE CONNECTED</span>
                        </div>

                        <!-- Main Preview Container -->
                        <div class="relative rounded-2xl overflow-hidden aspect-[16/10] bg-slate-900">
                            <img src="{{ asset('frontend/assets/images/AB8C90EF-A174-4354-ADAA-7D90CD82C1E4-1775986258792.jpg') }}" class="w-full h-full object-cover opacity-85 group-hover:scale-105 transition-transform duration-700" alt="Construction Site Management">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/20 to-transparent"></div>

                            <!-- Floating Stat Widget Top Left -->
                            <div class="absolute top-4 left-4 bg-white/95 backdrop-blur-md border border-slate-100 rounded-2xl p-3.5 shadow-xl max-w-[200px] animate-float-slow">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span class="text-[10px] font-black uppercase text-teal-700">Site #402 Logged</span>
                                </div>
                                <div class="text-lg font-black text-slate-900 font-display">₹4,25,800</div>
                                <div class="text-[10px] text-slate-500 font-medium">Daily Petty Cash & Material</div>
                            </div>

                            <!-- Floating Alert Bottom Right -->
                            <div class="absolute bottom-4 right-4 bg-slate-900/90 backdrop-blur-md border border-amber-500/30 rounded-2xl p-3.5 shadow-xl text-white max-w-[210px]">
                                <div class="flex items-center gap-1.5 mb-1 text-amber-400 text-xs font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z" stroke-width="2.5"/></svg>
                                    <span class="text-[11px] font-black uppercase">Approval Alert</span>
                                </div>
                                <p class="text-[11px] text-slate-300 leading-tight font-medium">
                                    Cement PO #1084 Approved by PM.
                                </p>
                            </div>
                        </div>

                        <!-- Dynamic Mini Feature Selector Pills -->
                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div class="bg-teal-50/60 border border-teal-200/80 rounded-2xl p-3 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-teal-600 text-white flex items-center justify-center font-bold flex-shrink-0 shadow-teal">
                                    ₹
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Petty Cash Log</div>
                                    <div class="text-[10px] text-teal-700 font-semibold">Photo Receipts Attached</div>
                                </div>
                            </div>
                            <div class="bg-amber-50/60 border border-amber-200/80 rounded-2xl p-3 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold flex-shrink-0 shadow-amber">
                                    📦
                                </div>
                                <div>
                                    <div class="text-xs font-bold text-slate-900">Stock Control</div>
                                    <div class="text-[10px] text-amber-700 font-semibold">Steel & Cement Balance</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Contractor Categories Marquee Ticker -->
    <div class="bg-slate-900 text-white py-4 overflow-hidden border-y border-slate-800">
        <div class="animate-marquee whitespace-nowrap flex items-center gap-8 text-xs font-bold uppercase tracking-widest text-slate-300">
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-teal-400"></span> Residential Builders</span>
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Civil Infrastructure Contractors</span>
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-teal-400"></span> Commercial Complex Developers</span>
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span> MEP & Electrical Contractors</span>
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-teal-400"></span> Highway & Road Builders</span>
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Industrial Turnkey Projects</span>
            <!-- Duplicate for infinite smooth scroll loop -->
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-teal-400"></span> Residential Builders</span>
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Civil Infrastructure Contractors</span>
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-teal-400"></span> Commercial Complex Developers</span>
            <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span> MEP & Electrical Contractors</span>
        </div>
    </div>

    <!-- Core Features Bento Grid -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-16 space-y-3">
                <span class="text-xs uppercase tracking-widest font-black text-teal-700 bg-teal-50 border border-teal-200 px-4 py-1.5 rounded-full inline-block">
                    CORE CAPABILITIES
                </span>
                <h2 class="font-display text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-tight">
                    Everything Built Around <span class="text-gradient-teal">Ground Reality</span>
                </h2>
                <p class="text-sm text-slate-600 font-medium">
                    Eliminate chaotic WhatsApp groups, lost paper bills, and unverified petty cash expenditures.
                </p>
            </div>

            <!-- Bento Box Grid -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                
                <!-- Bento Card 1: Daily Expense & Audit (8 cols) -->
                <div class="md:col-span-8 bg-gradient-to-br from-slate-50 to-white rounded-3xl p-8 sm:p-10 border border-slate-200 shadow-soft hover:shadow-card hover:border-teal-500 transition-all duration-300 relative overflow-hidden group">
                    <div class="w-14 h-14 rounded-2xl bg-teal-600 text-white flex items-center justify-center mb-6 shadow-teal">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="text-[11px] font-black uppercase tracking-widest text-teal-700 mb-2 block">SITE FINANCE & AUDIT</span>
                    <h3 class="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 mb-3">Daily Expense & Petty Cash Tracking</h3>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mb-6 font-medium max-w-xl">
                        Supervisors log petty cash payments, vendor receipts, and transport costs on mobile with instant receipt photos. Head office approves payments in seconds.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs font-extrabold text-slate-700">
                        <div class="flex items-center gap-2 p-3 bg-white rounded-xl border border-slate-200/80 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-teal-500"></span> Instant Receipt Photo Attachment
                        </div>
                        <div class="flex items-center gap-2 p-3 bg-white rounded-xl border border-slate-200/80 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-teal-500"></span> Categorized Cost Heads
                        </div>
                        <div class="flex items-center gap-2 p-3 bg-white rounded-xl border border-slate-200/80 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-teal-500"></span> Real-Time Approval Workflows
                        </div>
                        <div class="flex items-center gap-2 p-3 bg-white rounded-xl border border-slate-200/80 shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-teal-500"></span> Multi-Site Expense Summaries
                        </div>
                    </div>
                </div>

                <!-- Bento Card 2: Material & Inventory (4 cols) -->
                <div class="md:col-span-4 bg-gradient-to-br from-amber-50/50 to-white rounded-3xl p-8 border border-slate-200 shadow-soft hover:shadow-card hover:border-amber-500 transition-all duration-300 flex flex-col justify-between">
                    <div>
                        <div class="w-14 h-14 rounded-2xl bg-amber-500 text-white flex items-center justify-center mb-6 shadow-amber">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <span class="text-[11px] font-black uppercase tracking-widest text-amber-700 mb-2 block">MATERIAL CONTROL</span>
                        <h3 class="font-display text-xl font-extrabold text-slate-900 mb-3">Inventory & Purchase Orders</h3>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium mb-6">
                            Monitor stock levels of steel, cement, bricks, and sand from PO creation to site consumption. Eliminate site theft and over-ordering.
                        </p>
                    </div>
                    <div class="p-3.5 bg-amber-100/70 border border-amber-300/80 rounded-2xl text-[11px] text-amber-900 font-bold flex items-center justify-between">
                        <span>Low Stock Alert</span>
                        <span class="bg-amber-600 text-white px-2 py-0.5 rounded-lg font-mono text-[10px]">Cement: 12 Bags</span>
                    </div>
                </div>

                <!-- Bento Card 3: Labour & Workforce (4 cols) -->
                <div class="md:col-span-4 bg-white rounded-3xl p-8 border border-slate-200 shadow-soft hover:shadow-card hover:border-teal-500 transition-all duration-300">
                    <div class="w-14 h-14 rounded-2xl bg-teal-50 border border-teal-100 text-teal-700 flex items-center justify-center mb-6 font-bold shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="text-[11px] font-black uppercase tracking-widest text-teal-700 mb-2 block">WORKFORCE</span>
                    <h3 class="font-display text-xl font-extrabold text-slate-900 mb-3">Labour Attendance Logs</h3>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Log contractor-wise labour headcount, in/out timestamps, and photo verification to prevent phantom billing.
                    </p>
                </div>

                <!-- Bento Card 4: Site Billing (4 cols) -->
                <div class="md:col-span-4 bg-white rounded-3xl p-8 border border-slate-200 shadow-soft hover:shadow-card hover:border-amber-500 transition-all duration-300">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-100 text-amber-700 flex items-center justify-center mb-6 font-bold shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="text-[11px] font-black uppercase tracking-widest text-amber-700 mb-2 block">BILLING & RA BILLS</span>
                    <h3 class="font-display text-xl font-extrabold text-slate-900 mb-3">Sequential Site Bills</h3>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Generate formatted bill numbers, manage client party ledgers, and export PDF billing statements with work items.
                    </p>
                </div>

                <!-- Bento Card 5: Machinery & Assets (4 cols) -->
                <div class="md:col-span-4 bg-white rounded-3xl p-8 border border-slate-200 shadow-soft hover:shadow-card hover:border-teal-500 transition-all duration-300">
                    <div class="w-14 h-14 rounded-2xl bg-teal-50 border border-teal-100 text-teal-700 flex items-center justify-center mb-6 font-bold shadow-sm">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke-linecap="round" stroke-linejoin="round"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="text-[11px] font-black uppercase tracking-widest text-teal-700 mb-2 block">MACHINERY</span>
                    <h3 class="font-display text-xl font-extrabold text-slate-900 mb-3">Equipment Running Hours</h3>
                    <p class="text-xs text-slate-600 leading-relaxed font-medium">
                        Log JCB, crane, and concrete mixer usage hours, diesel intake, maintenance schedules, and rental charges.
                    </p>
                </div>

            </div>
        </div>
    </section>

    <!-- Interactive Cost Savings Calculator Section -->
    <section class="py-20 bg-slate-900 text-white relative overflow-hidden">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-2xl mx-auto mb-12 space-y-3">
                <span class="text-xs uppercase tracking-widest font-black text-amber-400 bg-amber-500/10 border border-amber-500/30 px-4 py-1.5 rounded-full inline-block">
                    ESTIMATED SAVINGS CALCULATOR
                </span>
                <h2 class="font-display text-3xl sm:text-4xl font-extrabold text-white">
                    Calculate Your Monthly Cash Savings
                </h2>
                <p class="text-xs sm:text-sm text-slate-300 font-medium">
                    See how much unverified site cash leakages you can eliminate with Buildarya.
                </p>
            </div>

            <div class="bg-slate-800/90 border border-slate-700/80 rounded-3xl p-8 sm:p-10 shadow-2xl backdrop-blur-md">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
                    <div class="md:col-span-7 space-y-6">
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-black uppercase text-slate-300">Number of Active Construction Sites:</label>
                                <span id="site-count-val" class="font-display text-2xl font-black text-amber-400">5 Sites</span>
                            </div>
                            <input type="range" id="site-range" min="1" max="25" value="5" class="w-full h-2.5 bg-slate-700 rounded-lg appearance-none cursor-pointer accent-amber-400">
                        </div>

                        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-700 text-xs text-slate-300 space-y-2">
                            <div class="flex justify-between">
                                <span>Est. Average Petty Cash per Site:</span>
                                <span class="font-bold text-white">₹1,50,000 / mo</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Unverified Leakage Rate (Without MIS):</span>
                                <span class="font-bold text-rose-400">~ 4.5%</span>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-5 bg-gradient-to-br from-teal-900/90 to-teal-950 border border-teal-500/40 rounded-2xl p-6 text-center space-y-3">
                        <div class="text-[10px] font-black uppercase tracking-widest text-teal-300">ESTIMATED MONTHLY SAVINGS</div>
                        <div id="savings-amount" class="font-display text-3xl sm:text-4xl font-black text-amber-300">₹33,750 / mo</div>
                        <p class="text-[11px] text-teal-100/80 leading-snug font-medium">
                            Saved across 5 sites by verifying every cash slip and stock delivery on mobile.
                        </p>
                        <a href="{{ url('/contact') }}" class="btn-amber inline-block w-full py-3 rounded-xl text-xs font-black uppercase tracking-wider shadow-amber">
                            Start Saving Today
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Clean Customer Testimonials -->
    <section class="py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-xl mx-auto mb-14 space-y-2">
                <span class="text-xs uppercase tracking-widest font-black text-teal-700 bg-teal-50 border border-teal-200 px-4 py-1.5 rounded-full inline-block">
                    TESTIMONIALS
                </span>
                <h2 class="font-display text-3xl sm:text-4xl font-extrabold text-slate-900">
                    Trusted By Top Contractors
                </h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-soft flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1 text-amber-400 mb-4 text-sm">★★★★★</div>
                        <p class="text-xs text-slate-700 leading-relaxed font-medium mb-6 italic">
                            "Before Buildarya, we had no idea how much cash site supervisors were spending daily. Now every bill has a photo attached and approved from head office before payment."
                        </p>
                    </div>
                    <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                        <div class="w-10 h-10 rounded-full bg-teal-700 text-white font-black flex items-center justify-center font-display text-xs">RS</div>
                        <div>
                            <div class="text-xs font-bold text-slate-900">Rajesh Shinde</div>
                            <div class="text-[10px] text-slate-500 font-medium">Shinde Constructions, Pune</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-soft flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1 text-amber-400 mb-4 text-sm">★★★★★</div>
                        <p class="text-xs text-slate-700 leading-relaxed font-medium mb-6 italic">
                            "The material stock tracking feature saved us from buying 400 extra bags of cement across 3 sites. We could transfer stock between sites with simple logs."
                        </p>
                    </div>
                    <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                        <div class="w-10 h-10 rounded-full bg-amber-600 text-white font-black flex items-center justify-center font-display text-xs">VK</div>
                        <div>
                            <div class="text-xs font-bold text-slate-900">Vikram Kumar</div>
                            <div class="text-[10px] text-slate-500 font-medium">VK Infra & Developers, Delhi NCR</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-soft flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-1 text-amber-400 mb-4 text-sm">★★★★★</div>
                        <p class="text-xs text-slate-700 leading-relaxed font-medium mb-6 italic">
                            "Buildarya's mobile app is so easy that even our non-tech site mistry can log daily labour and material receipts without any training."
                        </p>
                    </div>
                    <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                        <div class="w-10 h-10 rounded-full bg-teal-700 text-white font-black flex items-center justify-center font-display text-xs">AN</div>
                        <div>
                            <div class="text-xs font-bold text-slate-900">Anand Naidu</div>
                            <div class="text-[10px] text-slate-500 font-medium">Naidu Civil Projects, Hyderabad</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bottom High-Converting CTA Banner -->
    <section class="py-20 bg-gradient-to-r from-teal-800 via-teal-900 to-slate-900 text-white relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 text-center relative z-10 space-y-6">
            <span class="inline-block px-4 py-1.5 rounded-full bg-amber-400/20 border border-amber-400/40 text-amber-300 text-xs font-black uppercase tracking-widest">
                READY TO MODERNISER YOUR SITES?
            </span>
            <h2 class="font-display text-3xl sm:text-5xl font-extrabold leading-tight">
                Take Full Control of Your Construction Sites Today
            </h2>
            <p class="text-xs sm:text-sm text-slate-200 max-w-xl mx-auto font-medium">
                Book a free 15-minute live demonstration with our construction technology specialists.
            </p>
            <div class="pt-2">
                <a href="{{ url('/contact') }}" class="btn-amber px-9 py-4 rounded-2xl text-xs font-black uppercase tracking-wider inline-block shadow-amber-glow">
                    Book Free Demo Session Now
                </a>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
    // Interactive ROI Calculator Logic
    const range = document.getElementById('site-range');
    const countVal = document.getElementById('site-count-val');
    const savingsVal = document.getElementById('savings-amount');

    if (range && countVal && savingsVal) {
        range.addEventListener('input', () => {
            const sites = parseInt(range.value);
            countVal.innerText = sites + (sites === 1 ? ' Site' : ' Sites');
            
            // Formula: sites * 1.5L petty cash * 4.5% leakage saved
            const monthlySavings = sites * 150000 * 0.045;
            savingsVal.innerText = '₹' + Math.round(monthlySavings).toLocaleString('en-IN') + ' / mo';
        });
    }
</script>
@endsection
