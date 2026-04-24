@extends('layouts.frontend')

@section('title', 'Buildarya — Construction Management for Indian Contractors')

@section('content')
    <!-- Hero Section -->
    <section class="relative min-h-screen pt-16 pb-12 px-4 sm:px-6 lg:px-8 flex items-end overflow-hidden bg-bg animate-fade-in">
        <div class="max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-8 items-end relative z-10">
            <!-- Left: Content -->
            <div class="lg:col-span-5 flex flex-col justify-center pb-8 lg:pb-16">
                <div class="flex items-center gap-2 mb-6">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-primary/20 bg-primary/5 text-xs font-semibold text-primary uppercase tracking-widest">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary inline-block"></span>
                        Built for India
                    </span>
                </div>

                <h1 class="font-display text-4xl sm:text-5xl lg:text-[3.4rem] leading-[1.08] tracking-tight text-fg mb-5">
                    Manage Your<br />
                    <span class="text-teal-gradient italic">Construction Work</span>
                    <br />in One Place
                </h1>

                <p class="text-base text-fg-muted leading-relaxed max-w-md mb-8 border-l-2 border-primary/30 pl-4">
                    Track sites, expenses, materials, and documents with a simple and reliable system designed for construction businesses.
                </p>

                <div class="flex flex-wrap gap-3 mb-10">
                    <a href="{{ url('/contact') }}" class="btn-accent inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold">
                        Book Free Demo
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="shrink-0"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <a href="{{ url('/features') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold border border-border bg-white text-fg hover:border-primary/40 hover:bg-primary/5 transition-all duration-200">
                        View Features
                    </a>
                </div>
            </div>

            <!-- Right: Arch image -->
            <div class="lg:col-span-7 h-[55vh] sm:h-[65vh] lg:h-[88vh] relative">
                <!-- Rotating badge -->
                <div class="absolute top-8 left-4 z-20 pointer-events-none hidden md:block">
                    <div class="relative w-20 h-20 flex items-center justify-center">
                        <svg class="animate-spin-slow w-full h-full" viewBox="0 0 100 100" style="animation: spin 14s linear infinite;">
                            <path id="heroBadgePath" d="M 50,50 m -37,0 a 37,37 0 1,1 74,0 a 37,37 0 1,1 -74,0" fill="transparent" />
                            <text fontSize="9.5" font-family="DM Sans" font-weight="600" letter-spacing="2.2px" fill="#0B6E6E">
                                <textPath href="#heroBadgePath" startOffset="0%">CONSTRUCTION · INDIA · BUILDARYA ·</textPath>
                            </text>
                        </svg>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="absolute text-primary">
                            <path d="M3 21h18M3 7v1M21 7v1M9 21V7M15 21V7M3 7h18M3 3h18v4H3V3z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>

                <div class="absolute inset-0 rounded-t-[10rem] rounded-b-2xl overflow-hidden shadow-2xl border border-border/50">
                    <img src="{{ asset('frontend/assets/images/AB8C90EF-A174-4354-ADAA-7D90CD82C1E4-1775986258792.jpg') }}" class="w-full h-full object-cover object-center" alt="Construction Site">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                </div>

                <div class="absolute bottom-8 right-4 md:right-8 z-20 bg-white/15 backdrop-blur-md border border-white/30 rounded-2xl p-4 text-white max-w-[180px] shadow-xl hidden sm:block">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="w-2 h-2 rounded-full bg-accent animate-pulse"></span>
                        <span class="text-xs font-semibold">Live Tracking</span>
                    </div>
                    <p class="text-[11px] leading-relaxed opacity-80">
                        3 sites active right now across Mumbai, Pune, and Nagpur.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust Line -->
    <section class="py-14 bg-primary relative z-10 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-white">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-px bg-white/10">
                <div class="bg-primary px-8 py-8 flex gap-4 items-start">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 21h18M3 7v1M21 7v1M9 21V7M15 21V7M3 7h18M3 3h18v4H3V3z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm mb-1">Built for Construction</h3>
                        <p class="text-white/65 text-xs leading-relaxed">Workflows that match how contractors actually work.</p>
                    </div>
                </div>
                <div class="bg-primary px-8 py-8 flex gap-4 items-start">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm mb-1">Simple and Practical</h3>
                        <p class="text-white/65 text-xs leading-relaxed">Designed so site supervisors can start on day one.</p>
                    </div>
                </div>
                <div class="bg-primary px-8 py-8 flex gap-4 items-start">
                    <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm mb-1">Daily Site Operations</h3>
                        <p class="text-white/65 text-xs leading-relaxed">Log expenses, track materials, and manage teams.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-white relative z-10 rounded-t-5xl border-t border-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div>
                    <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-3 block">Features</span>
                    <h2 class="font-display text-3xl sm:text-4xl text-fg leading-tight">
                        Everything you need to<br />
                        <span class="italic text-teal-gradient">run your sites</span>
                    </h2>
                </div>
                <a href="{{ url('/features') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary border-b border-primary/40 pb-0.5 hover:border-primary transition-colors">
                    All Features
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <div class="sm:col-span-2 rounded-3xl border border-border bg-bg p-7 hover:shadow-card transition-all duration-300 relative group">
                    <div class="w-11 h-11 rounded-2xl bg-primary/10 flex items-center justify-center text-primary mb-5">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-2 block">Finance</span>
                    <h3 class="font-display text-xl font-semibold text-fg mb-3">Expense Management</h3>
                    <p class="text-sm text-fg-muted leading-relaxed max-w-sm">Track and manage daily site expenses in one place. Record cash payments and labor costs.</p>
                </div>
                <div class="rounded-3xl border border-border bg-bg p-7 hover:shadow-card transition-all duration-300">
                    <div class="w-11 h-11 rounded-2xl bg-accent/10 flex items-center justify-center text-accent-dark mb-5">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-2 block">Inventory</span>
                    <h3 class="font-display text-lg font-semibold text-fg mb-3">Material Tracking</h3>
                    <p class="text-sm text-fg-muted leading-relaxed">Know what was ordered, received, and consumed across all sites.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Problem/Solution -->
    <section class="py-20 bg-bg relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">
                <div>
                    <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-4 block">The Problem</span>
                    <h2 class="font-display text-3xl sm:text-4xl text-fg leading-tight mb-8 text-fg-muted italic">Common Challenges</h2>
                    
                    <div class="space-y-4">
                        <div class="flex gap-4 p-4 rounded-2xl border border-border bg-white shadow-soft">
                            <div class="w-10 h-10 rounded-xl bg-bg-surface flex items-center justify-center text-lg">📱</div>
                            <div>
                                <p class="text-sm font-semibold text-fg mb-0.5">Scattered Across WhatsApp</p>
                                <p class="text-xs text-fg-muted">Records get lost in group chats and manual notes.</p>
                            </div>
                        </div>
                        <div class="flex gap-4 p-4 rounded-2xl border border-border bg-white shadow-soft">
                            <div class="w-10 h-10 rounded-xl bg-bg-surface flex items-center justify-center text-lg">💸</div>
                            <div>
                                <p class="text-sm font-semibold text-fg mb-0.5">High Cash Wastage</p>
                                <p class="text-xs text-fg-muted">Difficult to track where every rupee is being spent.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:pt-12">
                    <span class="text-2xs uppercase tracking-widest font-bold text-primary mb-4 block">The Solution</span>
                    <h2 class="font-display text-3xl sm:text-4xl text-fg leading-tight mb-6">How Buildarya <span class="italic text-teal-gradient">Helps</span></h2>
                    <div class="rounded-3xl border border-primary/15 bg-primary/5 p-6 space-y-3">
                        <div class="flex items-center justify-between py-2 border-b border-primary/10">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full bg-primary"></span>
                                <span class="text-sm font-medium text-fg">Site expenses</span>
                            </div>
                            <span class="text-xs text-primary font-semibold bg-primary/10 px-2.5 py-1 rounded-full">Tracked daily</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full bg-accent"></span>
                                <span class="text-sm font-medium text-fg">Stock usage</span>
                            </div>
                            <span class="text-xs text-primary font-semibold bg-primary/10 px-2.5 py-1 rounded-full">Real-time logs</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="py-20 bg-primary relative z-10 overflow-hidden">
        <div class="absolute inset-0 noise-overlay opacity-[0.05] pointer-events-none"></div>
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h2 class="font-display text-3xl sm:text-5xl text-white mb-8 leading-tight">Ready to modernize your <br/><span class="italic opacity-80">construction site?</span></h2>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ url('/contact') }}" class="btn-accent w-full sm:w-auto px-8 py-4 rounded-xl text-lg font-semibold">Book a Free Session</a>
                <a href="{{ url('/features') }}" class="w-full sm:w-auto px-8 py-4 rounded-xl text-lg font-semibold text-white border border-white/20 hover:bg-white/10 transition-all">Explore Platform</a>
            </div>
        </div>
    </section>

@endsection

@section('scripts')
<style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endsection
