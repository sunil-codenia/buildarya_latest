@extends('layouts.frontend')

@section('title', 'Modules — Buildarya Construction Management Platform')

@section('content')
    <!-- Modules Hero -->
    <section class="pt-28 pb-16 bg-bg border-b border-border relative overflow-hidden animate-fade-in">
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-2xl">
                <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-4 block">Modules</span>
                <h1 class="font-display text-4xl sm:text-5xl text-fg leading-tight mb-5">
                    Powerful modules for<br />
                    <span class="italic text-teal-gradient">modern contractors</span>
                </h1>
                <p class="text-base text-fg-muted leading-relaxed mb-8 max-w-lg">
                    Buildarya includes seven core modules designed to handle all daily operations on a construction site.
                </p>
                <div class="flex items-center gap-4">
                    <a href="{{ url('/contact') }}" class="btn-accent inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold shadow-accent">
                        Book Free Demo
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Modules Grid -->
    <section class="py-16 bg-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @php
                    $modulesData = [
                        [
                            'name' => 'Sites & Users',
                            'tag' => 'Administration',
                            'desc' => 'Set up multiple sites, add team members, and control access permissions easily.',
                            'capabilities' => ['Create unlimited sites', 'Manage team members', 'Assign role-based access', 'Site-specific dashboards'],
                            'color' => 'primary',
                            'icon' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M3 21h18M3 7v1M21 7v1M9 21V7M15 21V7M3 7h18M3 3h18v4H3V3z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                        ],
                        [
                            'name' => 'Expenses',
                            'tag' => 'Finance',
                            'desc' => 'Record all site costs — labour, materials, transport, and miscellaneous.',
                            'capabilities' => ['Daily expense logging', 'Categorize by cost type', 'Attach receipts', 'Expense summaries'],
                            'color' => 'accent',
                            'icon' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                        ],
                        [
                            'name' => 'Material Purchase',
                            'tag' => 'Procurement',
                            'desc' => 'Log purchase orders, vendor details, and material receipts accurately.',
                            'capabilities' => ['Record POs per site', 'Vendor tracking', 'Receipt confirmation', 'Purchase history'],
                            'color' => 'primary',
                            'icon' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                        ],
                        [
                            'name' => 'Stock Management',
                            'tag' => 'Inventory',
                            'desc' => 'Track material inventory at each site. See what is available and what has been used.',
                            'capabilities' => ['Current stock levels', 'Material consumption logs', 'Inter-site transfers', 'Low stock alerts'],
                            'color' => 'accent',
                            'icon' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                        ],
                        [
                            'name' => 'Billing',
                            'tag' => 'Finance',
                            'desc' => 'Create and manage client bills tied to site progress and completed work.',
                            'capabilities' => ['Site-linked billing', 'Track payment status', 'Record receipts', 'Client history'],
                            'color' => 'primary',
                            'icon' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                        ],
                        [
                            'name' => 'Machinery',
                            'tag' => 'Equipment',
                            'desc' => 'Log machinery usage, track rental costs, and maintain basic equipment records.',
                            'capabilities' => ['Site machinery logs', 'Daily usage hours', 'Rental cost tracking', 'Maintenance notes'],
                            'color' => 'accent',
                            'icon' => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                        ],
                    ];
                @endphp

                @foreach($modulesData as $mod)
                    <div class="rounded-3xl border border-border bg-white p-7 hover:shadow-card hover:border-primary/20 transition-all duration-200 flex flex-col">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-5 {{ $mod['color'] === 'accent' ? 'bg-accent/10 text-accent-dark' : 'bg-primary/10 text-primary' }}">
                            {!! $mod['icon'] !!}
                        </div>
                        <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-2 block">{{ $mod['tag'] }}</span>
                        <h2 class="font-display text-xl font-semibold text-fg mb-3">{{ $mod['name'] }}</h2>
                        <p class="text-sm text-fg-muted leading-relaxed mb-6">{{ $mod['desc'] }}</p>
                        <ul class="space-y-2.5 flex-1 mb-6">
                            @foreach($mod['capabilities'] as $cap)
                                <li class="flex items-start gap-2.5">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="mt-0.5 flex-shrink-0 {{ $mod['color'] === 'accent' ? 'text-accent-dark' : 'text-primary' }}">
                                        <path d="M3 8l3.5 3.5L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span class="text-xs text-fg-muted">{{ $cap }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="h-0.5 w-12 rounded-full {{ $mod['color'] === 'accent' ? 'bg-accent' : 'bg-primary' }}"></div>
                    </div>
                @endforeach
            </div>

            <div class="mt-16 text-center">
                <p class="text-sm text-fg-muted mb-5">Want to see all modules in action? Book a free demo.</p>
                <a href="{{ url('/contact') }}" class="btn-accent inline-flex items-center gap-2 px-8 py-3.5 rounded-xl text-sm font-semibold shadow-accent hover:shadow-lg transition-all duration-200">
                    Book Free Demo
                </a>
            </div>
        </div>
    </section>
@endsection
