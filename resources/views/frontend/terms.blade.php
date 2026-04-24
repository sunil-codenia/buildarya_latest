@extends('layouts.frontend')

@section('title', 'Terms and Conditions — Buildarya')

@section('content')
    <section class="pt-32 pb-20 bg-bg">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-12">
                <span class="text-2xs uppercase tracking-widest font-bold text-primary mb-4 block">Legal</span>
                <h1 class="font-display text-4xl sm:text-5xl text-fg leading-tight mb-6">Terms of Service</h1>
                <p class="text-sm text-fg-muted">Last updated: April 2026</p>
            </div>

            <div class="space-y-16">
                <!-- 01 -->
                <div class="group flex items-start gap-6">
                    <span class="font-display text-4xl text-primary/20 shrink-0 pt-1">01</span>
                    <div>
                        <h2 class="font-display text-2xl text-fg mb-4">Acceptance of Terms</h2>
                        <p class="text-fg-muted leading-relaxed text-lg">By accessing or using Buildarya, you agree to be bound by these Terms. If you do not agree to these terms, please do not use the Platform.</p>
                    </div>
                </div>

                <!-- 07 -->
                <div class="group flex items-start gap-6">
                    <span class="font-display text-4xl text-primary/20 shrink-0 pt-1">02</span>
                    <div>
                        <h2 class="font-display text-2xl text-fg mb-4">Subscription & Payments</h2>
                        <ul class="list-disc pl-5 space-y-2 text-fg-muted text-lg">
                            <li>Services are provided on a subscription basis.</li>
                            <li>Fees are payable as per agreed plans.</li>
                            <li>Payments are generally non-refundable.</li>
                        </ul>
                    </div>
                </div>

                <!-- 08 -->
                <div class="group flex items-start gap-6">
                    <span class="font-display text-4xl text-primary/20 shrink-0 pt-1">03</span>
                    <div>
                        <h2 class="font-display text-2xl text-fg mb-4">Acceptable Use</h2>
                        <div class="bg-white p-8 rounded-3xl border border-border">
                            <p class="font-bold text-fg mb-4 uppercase tracking-widest text-xs">Users shall NOT:</p>
                            <ul class="list-disc pl-5 text-fg-muted space-y-2 text-sm italic">
                                <li>Use the Platform for unlawful or fraudulent purposes</li>
                                <li>Attempt unauthorized access to systems or data</li>
                                <li>Upload malware or harmful code</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Governing Law -->
                <div class="group flex items-start gap-6 border-t border-border pt-12">
                    <span class="font-display text-4xl text-primary/20 shrink-0 pt-1">04</span>
                    <div>
                        <h2 class="font-display text-2xl text-fg mb-4">Governing Law</h2>
                        <p class="text-fg-muted leading-relaxed text-lg">These Terms shall be governed by the laws of India. Any disputes shall be subject to the jurisdiction of competent courts in India.</p>
                    </div>
                </div>

                <!-- Disclaimer -->
                <div class="bg-fg rounded-[2.5rem] p-12 text-center text-white relative overflow-hidden shadow-card">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-primary/20 rounded-full blur-3xl -mr-32 -mt-32"></div>
                    <h2 class="font-display text-3xl mb-6 relative z-10">Disclaimer</h2>
                    <p class="text-white/70 max-w-2xl mx-auto leading-relaxed relative z-10">
                        Buildarya is a technology platform only and does not provide legal, financial, or engineering consultancy. Users are solely responsible for decisions made using the Platform.
                    </p>
                </div>

            </div>
        </div>
    </section>
@endsection
