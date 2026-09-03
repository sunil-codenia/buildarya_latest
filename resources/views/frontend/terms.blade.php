@extends('layouts.frontend')

@section('title', 'Terms and Conditions — Buildarya')
@section('description', 'Read the Terms of Service for using Buildarya construction management platform.')

@section('content')
    <section class="pt-32 pb-20 bg-slate-900 text-white relative overflow-hidden animate-fade-in">
        <div class="ambient-mesh bg-amber-500/20 top-0 left-0 w-[500px] h-[500px]"></div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <span class="text-xs uppercase tracking-widest font-extrabold text-amber-400 bg-amber-500/10 border border-amber-500/30 px-4 py-1.5 rounded-full inline-block mb-4">
                TERMS OF SERVICE
            </span>
            <h1 class="font-display text-4xl sm:text-5xl font-extrabold text-white mb-4">Terms & Conditions</h1>
            <p class="text-xs text-slate-400 font-medium">Last updated: April 2026</p>
        </div>
    </section>

    <section class="py-20 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="bg-white rounded-3xl p-8 sm:p-12 border border-slate-200 shadow-soft space-y-12">
                <!-- 01 -->
                <div class="flex items-start gap-6">
                    <span class="font-display text-4xl font-extrabold text-teal-600/30 shrink-0">01</span>
                    <div>
                        <h2 class="font-display text-2xl font-extrabold text-slate-900 mb-3">Acceptance of Terms</h2>
                        <p class="text-slate-600 text-sm leading-relaxed font-medium">By accessing or using Buildarya web and mobile applications, you agree to be bound by these Terms of Service. If you do not agree to these terms, please refrain from using the Platform.</p>
                    </div>
                </div>

                <!-- 02 -->
                <div class="flex items-start gap-6 border-t border-slate-100 pt-8">
                    <span class="font-display text-4xl font-extrabold text-teal-600/30 shrink-0">02</span>
                    <div>
                        <h2 class="font-display text-2xl font-extrabold text-slate-900 mb-3">Subscription & Payments</h2>
                        <ul class="list-disc pl-5 space-y-2 text-slate-600 text-sm font-medium">
                            <li>Services are provided on a recurring subscription basis according to selected plan limits.</li>
                            <li>Subscription fees are payable in advance as specified during checkout.</li>
                            <li>Payments processed through authorized payment gateways are generally non-refundable once activated.</li>
                        </ul>
                    </div>
                </div>

                <!-- 03 -->
                <div class="flex items-start gap-6 border-t border-slate-100 pt-8">
                    <span class="font-display text-4xl font-extrabold text-teal-600/30 shrink-0">03</span>
                    <div>
                        <h2 class="font-display text-2xl font-extrabold text-slate-900 mb-3">Acceptable Use Policy</h2>
                        <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200 space-y-3">
                            <p class="font-extrabold text-slate-900 uppercase tracking-wider text-xs">Users shall NOT:</p>
                            <ul class="list-disc pl-5 text-slate-600 space-y-1.5 text-xs font-medium italic">
                                <li>Use the Platform for unlawful, fraudulent, or deceptive civil engineering claims.</li>
                                <li>Attempt unauthorized access to other tenant databases or system infrastructure.</li>
                                <li>Upload malicious code, viruses, or disruptive scripts.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- 04 -->
                <div class="flex items-start gap-6 border-t border-slate-100 pt-8">
                    <span class="font-display text-4xl font-extrabold text-teal-600/30 shrink-0">04</span>
                    <div>
                        <h2 class="font-display text-2xl font-extrabold text-slate-900 mb-3">Governing Law</h2>
                        <p class="text-slate-600 text-sm leading-relaxed font-medium">These Terms shall be governed by and construed in accordance with the laws of India. Any disputes arising hereunder shall be subject to the exclusive jurisdiction of competent courts in India.</p>
                    </div>
                </div>

                <!-- Disclaimer Banner -->
                <div class="bg-slate-900 rounded-3xl p-8 sm:p-10 text-center text-white relative overflow-hidden shadow-2xl border border-slate-800">
                    <div class="ambient-mesh bg-teal-500/20 top-0 right-0 w-64 h-64"></div>
                    <h2 class="font-display text-2xl font-extrabold mb-4 relative z-10">Platform Disclaimer</h2>
                    <p class="text-slate-300 text-xs sm:text-sm max-w-2xl mx-auto leading-relaxed relative z-10 font-medium">
                        Buildarya is a technology platform for project record management and does not provide legal, structural engineering, or certified accounting advice. Users are solely responsible for verifying data entered into the Platform.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
