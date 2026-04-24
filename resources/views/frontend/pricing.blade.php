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

    <!-- Pricing Plans -->
    <section class="py-16 bg-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                @php
                    $plans = [
                        [
                            'name' => 'Starter',
                            'tagline' => 'For individual contractors',
                            'price' => '₹4,999',
                            'period' => '/month',
                            'desc' => 'Best for managing 1 active site and getting started with digital records.',
                            'features' => [
                                ['text' => '1 Active site', 'included' => true],
                                ['text' => 'Up to 5 users', 'included' => true],
                                ['text' => 'Daily expense logging', 'included' => true],
                                ['text' => 'Report generation', 'included' => true],
                                ['text' => 'Stock management', 'included' => false],
                                ['text' => 'Billing module', 'included' => false],
                            ],
                            'cta' => 'Contact Sales',
                            'highlight' => false,
                            'badge' => ''
                        ],
                        [
                            'name' => 'Growth',
                            'tagline' => 'For growing teams',
                            'price' => '₹9,999',
                            'period' => '/month',
                            'desc' => 'Manage multiple sites with team collaboration and inventory tracking.',
                            'features' => [
                                ['text' => 'Up to 5 Active sites', 'included' => true],
                                ['text' => 'Up to 20 users', 'included' => true],
                                ['text' => 'Expense management', 'included' => true],
                                ['text' => 'Stock management', 'included' => true],
                                ['text' => 'Billing module', 'included' => true],
                                ['text' => 'Machinery tracking', 'included' => false],
                            ],
                            'cta' => 'Get Started',
                            'highlight' => true,
                            'badge' => 'Most Popular'
                        ],
                        [
                            'name' => 'Scale',
                            'tagline' => 'For large enterprises',
                            'price' => 'Custom',
                            'period' => '',
                            'desc' => 'Unlimited sites and users with full feature access and dedicated support.',
                            'features' => [
                                ['text' => 'Unlimited sites', 'included' => true],
                                ['text' => 'Unlimited users', 'included' => true],
                                ['text' => 'All modules included', 'included' => true],
                                ['text' => 'Machinery tracking', 'included' => true],
                                ['text' => 'Priority support', 'included' => true],
                                ['text' => 'Custom reporting', 'included' => true],
                            ],
                            'cta' => 'Contact Us',
                            'highlight' => false,
                            'badge' => ''
                        ]
                    ];
                @endphp

                @foreach($plans as $plan)
                    <div class="relative rounded-3xl p-7 flex flex-col {{ $plan['highlight'] ? 'bg-primary border-2 border-primary shadow-teal text-white' : 'bg-white border border-border hover:shadow-card' }} transition-all duration-300">
                        @if($plan['badge'])
                            <span class="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-accent text-fg text-2xs font-bold uppercase tracking-widest px-3 py-1 rounded-full shadow-accent">
                                {{ $plan['badge'] }}
                            </span>
                        @endif

                        <div class="mb-6 pb-6 border-b {{ $plan['highlight'] ? 'border-white/10' : 'border-border' }}">
                            <h2 class="font-display text-xl font-semibold mb-1">{{ $plan['name'] }}</h2>
                            <p class="text-xs mb-4 opacity-70">{{ $plan['tagline'] }}</p>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-4xl font-bold font-display">{{ $plan['price'] }}</span>
                                <span class="text-sm opacity-60">{{ $plan['period'] }}</span>
                            </div>
                            <p class="text-xs mt-2 leading-relaxed opacity-80">{{ $plan['desc'] }}</p>
                        </div>

                        <ul class="space-y-3 flex-1 mb-7">
                            @foreach($plan['features'] as $f)
                                <li class="flex items-center gap-2.5 {{ !$f['included'] ? 'opacity-40' : '' }}">
                                    @if($f['included'])
                                        <svg width="15" height="15" viewBox="0 0 16 16" fill="none" class="{{ $plan['highlight'] ? 'text-accent' : 'text-primary' }}">
                                            <path d="M3 8l3.5 3.5L13 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    @else
                                        <svg width="15" height="15" viewBox="0 0 16 16" fill="none" class="text-fg-subtle">
                                            <path d="M4 8h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        </svg>
                                    @endif
                                    <span class="text-sm">{{ $f['text'] }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ url('/contact') }}" class="text-center py-3.5 rounded-xl text-sm font-semibold transition-all duration-200 {{ $plan['highlight'] ? 'btn-accent shadow-accent' : 'border border-border hover:border-primary/40 hover:bg-primary/5 text-fg' }}">
                            {{ $plan['cta'] }}
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="text-center p-6 rounded-2xl bg-white border border-border">
                <p class="text-sm text-fg-muted">
                    <span class="font-semibold text-fg">Need help choosing?</span> Contact us for a demo and setup assistance. 
                    <a href="mailto:hello@buildarya.in" class="text-primary font-medium hover:underline">hello@buildarya.in</a>
                </p>
            </div>
        </div>
    </section>

    <!-- Pricing FAQ -->
    <section class="py-16 pt-4 bg-bg">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-3 block">FAQ</span>
                <h2 class="font-display text-3xl text-fg">Common questions</h2>
            </div>
            <div class="space-y-3">
                @php
                    $faqs = [
                        ['q' => 'Is there a free trial?', 'a' => 'We offer a free demo session where we walk you through the system. Contact us to book your demo — no credit card required.'],
                        ['q' => 'Can I change my plan later?', 'a' => 'Yes. You can upgrade or downgrade your plan at any time. Changes take effect from the next billing cycle.'],
                        ['q' => 'Is my data secure?', 'a' => 'Yes. Your data is stored securely and only accessible to users you authorize. We do not share your data with third parties.'],
                        ['q' => 'Do you offer setup assistance?', 'a' => 'Yes. All plans include basic setup assistance. Scale plan includes a dedicated account manager.'],
                    ];
                @endphp

                @foreach($faqs as $i => $faq)
                    <div class="rounded-2xl border border-border bg-white overflow-hidden">
                        <button class="faq-btn w-full flex items-center justify-between px-6 py-5 text-left gap-4" data-index="{{ $i }}">
                            <span class="text-sm font-semibold text-fg">{{ $faq['q'] }}</span>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="faq-icon flex-shrink-0 text-fg-muted transition-transform duration-200">
                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <div class="faq-answer px-6 pb-5 hidden">
                            <p class="text-sm text-fg-muted leading-relaxed">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.faq-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const answer = btn.nextElementSibling;
            const icon = btn.querySelector('.faq-icon');
            answer.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        });
    });
</script>
@endsection
