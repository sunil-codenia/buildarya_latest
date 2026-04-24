@extends('layouts.frontend')

@section('title', 'Privacy Policy — Buildarya')

@section('content')
    <section class="pt-32 pb-20 bg-bg">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-12">
                <span class="text-2xs uppercase tracking-widest font-bold text-primary mb-4 block">Legal</span>
                <h1 class="font-display text-4xl sm:text-5xl text-fg leading-tight mb-6">Privacy Policy</h1>
                <p class="text-sm text-fg-muted">Last updated: April 2026</p>
            </div>

            <div class="prose prose-sm max-w-none text-fg-muted leading-relaxed space-y-8">
                <p class="text-lg">At Buildarya, we value your privacy and are committed to protecting your business and personal data. This policy explains how we collect, use, and safeguard your information.</p>

                <div class="space-y-6">
                    <h2 class="font-display text-2xl text-fg">1. Information Collection</h2>
                    <p>We collect information you provide directly to us, such as when you create an account, fill out a form, or communicate with our support team. This includes name, email, phone number, and company details.</p>
                </div>

                <div class="space-y-6">
                    <h2 class="font-display text-2xl text-fg">2. Data Usage</h2>
                    <p>We use your data strictly to provide and improve our services, communicate with you about your account, and ensure the security of our platform.</p>
                </div>

                <div class="space-y-6">
                    <h2 class="font-display text-2xl text-fg">3. Information Sharing</h2>
                    <p>We do not sell or share your data with third parties for marketing purposes. Data is only shared with service providers who help us run our platform, under strict confidentiality agreements.</p>
                </div>

                <div class="space-y-6 md:p-8 md:bg-white md:rounded-3xl md:border md:border-border">
                    <h2 class="font-display text-2xl text-fg lowercase italic">Contact us for legal queries</h2>
                    <p>If you have any questions about this Privacy Policy, please contact us at <a href="mailto:hello@buildarya.in" class="text-primary font-bold">hello@buildarya.in</a></p>
                </div>
            </div>
        </div>
    </section>
@endsection
