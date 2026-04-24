@extends('layouts.frontend')

@section('title', 'Features — Buildarya Construction Management')

@section('content')
    <!-- Features Hero -->
    <section class="pt-28 pb-16 bg-bg border-b border-border relative overflow-hidden animate-fade-in">
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-2xl">
                <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-4 block">Features</span>
                <h1 class="font-display text-4xl sm:text-5xl text-fg leading-tight mb-5">
                    What Buildarya<br />
                    <span class="italic text-teal-gradient">actually does</span>
                </h1>
                <p class="text-base text-fg-muted leading-relaxed mb-8 max-w-lg">
                    Five core features built around the real needs of Indian construction businesses. No extras, no fluff.
                </p>
                <a href="{{ url('/contact') }}" class="btn-accent inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold shadow-accent transition-all duration-200">
                    Book Free Demo
                </a>
            </div>
        </div>
    </section>

    <!-- Features Detail -->
    <section class="py-16 bg-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">
            @php
                $featuresData = [
                    [
                        'id' => 'expense',
                        'title' => 'Expense Management',
                        'tag' => 'Finance',
                        'desc' => 'Track and manage daily site expenses in one place. Record cash payments, vendor bills, and labour costs without losing track.',
                        'details' => [
                            'Log daily cash and card expenses per site',
                            'Categorize by type — labour, material, transport, miscellaneous',
                            'Attach receipts and notes to each expense entry',
                            'View expense summaries by date or site',
                            'Track who recorded each entry'
                        ],
                        'image' => "https://images.unsplash.com/photo-1460925895917-afdab827c52f",
                        'accent' => 'primary'
                    ],
                    [
                        'id' => 'material',
                        'title' => 'Material Tracking',
                        'tag' => 'Inventory',
                        'desc' => 'Monitor purchase and usage of materials across your construction sites. Know exactly what was ordered, received, and consumed.',
                        'details' => [
                            'Record material purchase orders and receipts',
                            'Track stock levels at each site',
                            'Log material consumption against work done',
                            'View purchase history per vendor',
                            'Export material records for review'
                        ],
                        'image' => "https://images.unsplash.com/photo-1581444957407-470e1264856f",
                        'accent' => 'accent'
                    ],
                    [
                        'id' => 'site',
                        'title' => 'Site & Team Management',
                        'tag' => 'Operations',
                        'desc' => 'Manage multiple construction sites and your workforce from a single dashboard. Keep each site\'s data separate and organized.',
                        'details' => [
                            'Create and manage multiple site profiles',
                            'Add team members and assign roles',
                            'Control access — who sees which site',
                            'View all site activity from one dashboard',
                            'Keep contractor and subcontractor records'
                        ],
                        'image' => "https://img.rocket.new/generatedImages/rocket_gen_img_1610c55e9-1764720226721.png",
                        'accent' => 'primary'
                    ],
                    [
                        'id' => 'docs',
                        'title' => 'Document Management',
                        'tag' => 'Documents',
                        'desc' => 'Store and organize project documents for easy access. Keep drawings, contracts, approvals, and site photos in one place.',
                        'details' => [
                            'Upload and attach documents to each site',
                            'Organize by document type or date',
                            'Access documents from any device',
                            'Share specific files with team members',
                            'Keep approval and compliance records organized'
                        ],
                        'image' => "https://img.rocket.new/generatedImages/rocket_gen_img_1760a238a-1772690320969.png",
                        'accent' => 'accent'
                    ],
                    [
                        'id' => 'org',
                        'title' => 'Smart Organization',
                        'tag' => 'System',
                        'desc' => 'The system helps you structure your data so you can find what you need quickly and keep records consistent across your team.',
                        'details' => [
                            'Consistent data entry format across the team',
                            'Search and filter across sites and records',
                            'Organized view by date, site, or category',
                            'No data gets lost or duplicated',
                            'Easy to review records during audits'
                        ],
                        'image' => "https://img.rocket.new/generatedImages/rocket_gen_img_10b31d26e-1767257076418.png",
                        'accent' => 'primary'
                    ]
                ];
            @endphp

            @foreach($featuresData as $i => $feature)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                    <div class="flex flex-col justify-between h-full {{ $i % 2 === 1 ? 'lg:order-2' : '' }}">
                        <div>
                            <span class="text-2xs uppercase tracking-widest font-bold mb-3 block {{ $feature['accent'] === 'accent' ? 'text-accent-dark' : 'text-primary' }}">
                                {{ $feature['tag'] }}
                            </span>
                            <h2 class="font-display text-3xl sm:text-4xl text-fg mb-4 leading-tight">
                                {{ $feature['title'] }}
                            </h2>
                            <p class="text-base text-fg-muted leading-relaxed mb-8 border-l-2 border-primary/25 pl-4">
                                {{ $feature['desc'] }}
                            </p>
                            <ul class="space-y-3 mb-8">
                                @foreach($feature['details'] as $d)
                                    <li class="flex items-start gap-3">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="mt-0.5 flex-shrink-0 {{ $feature['accent'] === 'accent' ? 'text-accent-dark' : 'text-primary' }}">
                                            <path d="M3 8l3.5 3.5L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <span class="text-sm text-fg-muted">{{ $d }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <a href="{{ url('/contact') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-primary border-b border-primary/40 pb-0.5 hover:border-primary transition-colors w-fit">
                            Book a Demo to See This
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </a>
                    </div>

                    <div class="rounded-3xl overflow-hidden border border-border shadow-card aspect-[4/3] bg-bg-surface {{ $i % 2 === 1 ? 'lg:order-1' : '' }}">
                        <img src="{{ $feature['image'] }}" class="w-full h-full object-cover hover:scale-105 transition-transform duration-700" alt="{{ $feature['title'] }}">
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
