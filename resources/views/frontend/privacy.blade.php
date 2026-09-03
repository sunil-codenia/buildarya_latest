@extends('layouts.frontend')

@section('title', 'Privacy Policy — Buildarya Construction Management')
@section('description', 'Learn how Buildarya protects your construction company data, personal information, and site privacy.')

@section('content')
    <section class="pt-32 pb-20 bg-slate-900 text-white relative overflow-hidden animate-fade-in">
        <div class="ambient-mesh bg-teal-500/20 top-0 right-0 w-[500px] h-[500px]"></div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <span class="text-xs uppercase tracking-widest font-extrabold text-teal-400 bg-teal-500/10 border border-teal-500/30 px-4 py-1.5 rounded-full inline-block mb-4">
                LEGAL & SECURITY
            </span>
            <h1 class="font-display text-4xl sm:text-5xl font-extrabold text-white mb-4">Privacy Policy</h1>
            <p class="text-xs text-slate-400 font-medium">Last updated: April 2026</p>
        </div>
    </section>

    <section class="py-20 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-3xl p-8 sm:p-12 border border-slate-200 shadow-soft space-y-8 text-slate-700 text-sm leading-relaxed font-medium">
                <p class="text-base text-slate-900 font-semibold border-l-4 border-teal-500 pl-4">
                    At Buildarya, we value your privacy and are committed to protecting your construction business and personal data. This policy explains how we collect, use, and safeguard your information across web and mobile applications.
                </p>

                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <h2 class="font-display text-xl font-extrabold text-slate-900">1. Information Collection</h2>
                    <p>We collect information you provide directly to us when creating a tenant company account, onboarding team members, or logging site operations. This includes your name, business email, phone number, GST registration details, and company address.</p>
                </div>

                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <h2 class="font-display text-xl font-extrabold text-slate-900">2. Data Isolation & Usage</h2>
                    <p>We use your data strictly to provide and improve our services, authenticate account access, and ensure multi-tenant database connection security. Each company database is isolated to guarantee that no unauthorized third party can view or access your site financial records.</p>
                </div>

                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <h2 class="font-display text-xl font-extrabold text-slate-900">3. Information Sharing</h2>
                    <p>We do not sell or trade your data with third parties for marketing purposes. Data is shared exclusively with verified infrastructure service providers (such as cloud database hosts and SMS notification services) operating under strict confidentiality obligations.</p>
                </div>

                <div class="pt-6 border-t border-slate-100 bg-teal-50/60 p-6 rounded-2xl border border-teal-200/60">
                    <h3 class="font-display text-lg font-bold text-slate-900 mb-1">Questions About Privacy?</h3>
                    <p class="text-xs text-slate-600">If you have any questions regarding this Privacy Policy, please contact our data privacy officer at <a href="mailto:hello@buildarya.in" class="text-teal-700 font-bold hover:underline">hello@buildarya.in</a></p>
                </div>
            </div>
        </div>
    </section>
@endsection
