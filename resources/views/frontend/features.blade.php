@extends('layouts.frontend')

@section('title', 'Features — Buildarya Construction Management')
@section('description', 'Discover how Buildarya enables Indian contractors to track site expenses, material purchases, workforce attendance, project documents, and site operations.')

@section('content')
    <!-- Features Hero -->
    <section class="py-16 bg-gradient-to-b from-teal-50/50 via-slate-50 to-white text-center border-b border-slate-200/80">
        <div class="max-w-4xl mx-auto px-4 space-y-4">
            <span class="text-xs uppercase tracking-widest font-extrabold text-teal-700 bg-teal-50 border border-teal-200 px-3.5 py-1 rounded-full inline-block">
                FEATURE BREAKDOWN
            </span>
            <h1 class="font-display text-3xl sm:text-5xl font-extrabold text-slate-900">
                Built For Site Operational Reality
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 font-medium max-w-2xl mx-auto leading-relaxed">
                Simple, fast workflows tailored for Indian civil contractors, builders, and MEP teams.
            </p>
        </div>
    </section>

    <!-- Clean Feature Cards List -->
    <section class="py-16 bg-slate-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
            @php
                $featuresData = [
                    [
                        'id' => 'expense',
                        'title' => 'Expense & Petty Cash Log',
                        'tag' => 'Site Finance',
                        'desc' => 'Log daily cash and online expenses per site with receipt photos. Instant head office visibility.',
                        'details' => [
                            'Log daily cash & vendor receipts with mobile photos',
                            'Categorize costs by labour, material, and machinery',
                            'Real-time head office approval workflows'
                        ],
                        'image' => "https://images.unsplash.com/photo-1460925895917-afdab827c52f",
                        'color' => 'teal'
                    ],
                    [
                        'id' => 'material',
                        'title' => 'Material & Stock Balance',
                        'tag' => 'Inventory & POs',
                        'desc' => 'Track steel, cement, and aggregates from PO issuance to site consumption. Prevent theft and shortages.',
                        'details' => [
                            'Record Purchase Orders (POs) and delivery receipts',
                            'Monitor stock levels across all active sites',
                            'Inter-site material transfer tracking'
                        ],
                        'image' => "https://images.unsplash.com/photo-1581444957407-470e1264856f",
                        'color' => 'amber'
                    ],
                    [
                        'id' => 'site',
                        'title' => 'Multi-Site & Team Control',
                        'tag' => 'Operations',
                        'desc' => 'Manage multiple construction sites with role-based supervisor permissions.',
                        'details' => [
                            'Configure unlimited site profiles',
                            'Restrict supervisor access to assigned sites',
                            'Multi-site head office summary dashboard'
                        ],
                        'image' => "https://img.rocket.new/generatedImages/rocket_gen_img_1610c55e9-1764720226721.png",
                        'color' => 'teal'
                    ]
                ];
            @endphp

            @foreach($featuresData as $i => $feature)
                <div id="{{ $feature['id'] }}" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center bg-white p-8 rounded-3xl border border-slate-200 shadow-soft">
                    <div class="lg:col-span-6 space-y-4 {{ $i % 2 === 1 ? 'lg:order-2' : '' }}">
                        <span class="text-[11px] uppercase tracking-widest font-extrabold px-3 py-1 rounded-full border {{ $feature['color'] === 'amber' ? 'text-amber-700 bg-amber-50 border-amber-200' : 'text-teal-700 bg-teal-50 border-teal-200' }}">
                            {{ $feature['tag'] }}
                        </span>
                        <h2 class="font-display text-2xl font-extrabold text-slate-900">{{ $feature['title'] }}</h2>
                        <p class="text-xs text-slate-600 leading-relaxed font-medium">{{ $feature['desc'] }}</p>
                        
                        <ul class="space-y-2 text-xs font-semibold text-slate-700 pt-2">
                            @foreach($feature['details'] as $d)
                                <li class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $feature['color'] === 'amber' ? 'bg-amber-500' : 'bg-teal-600' }}"></span>
                                    <span>{{ $d }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="pt-2">
                            <a href="{{ url('/contact') }}" class="text-xs font-bold text-teal-700 hover:underline inline-flex items-center gap-1">
                                Book Demo →
                            </a>
                        </div>
                    </div>

                    <div class="lg:col-span-6 {{ $i % 2 === 1 ? 'lg:order-1' : '' }}">
                        <div class="rounded-2xl overflow-hidden aspect-[16/10] bg-slate-100 border border-slate-200">
                            <img src="{{ $feature['image'] }}" class="w-full h-full object-cover" alt="{{ $feature['title'] }}">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <!-- Simple Bottom Banner -->
    <section class="py-12 bg-white text-center border-t border-slate-200">
        <div class="max-w-2xl mx-auto px-4 space-y-3">
            <h3 class="font-display text-xl font-bold text-slate-900">Need a live demonstration for your team?</h3>
            <a href="{{ url('/contact') }}" class="btn-amber px-6 py-3 rounded-xl text-xs font-extrabold uppercase inline-block">
                Book Free Demo
            </a>
        </div>
    </section>
@endsection
