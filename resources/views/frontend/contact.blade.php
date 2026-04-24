@extends('layouts.frontend')

@section('title', 'Contact — Book a Free Demo with Buildarya')

@section('content')
    <!-- Contact Hero -->
    <section class="pt-28 pb-16 bg-bg border-b border-border relative overflow-hidden animate-fade-in">
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="max-w-2xl">
                <span class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-4 block">Contact</span>
                <h1 class="font-display text-4xl sm:text-5xl text-fg leading-tight mb-5">
                    Modernize your<br />
                    <span class="italic text-teal-gradient">construction site</span>
                </h1>
                <p class="text-base text-fg-muted leading-relaxed max-w-lg mb-8 border-l-2 border-primary/30 pl-4">
                    Book a free demo or get in touch with our team. We're here to help you get started with Buildarya.
                </p>
            </div>
        </div>
    </section>

    <!-- Contact Content -->
    <section class="py-16 bg-bg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-12 items-start">
                
                <!-- Contact Form -->
                <div class="lg:col-span-3">
                    <div class="rounded-3xl border border-border bg-white p-7 sm:p-10 shadow-soft">
                        <div class="mb-8">
                            <h2 class="font-display text-2xl font-semibold text-fg mb-2 text-teal-gradient">Send us a message</h2>
                            <p class="text-xs text-fg-muted">Fill out the form below and we'll get back to you within 24 hours.</p>
                        </div>

                        <form action="#" method="POST" class="space-y-6">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-fg mb-1.5 uppercase tracking-wide">Full Name <span class="text-primary">*</span></label>
                                    <input type="text" name="name" required placeholder="Rajesh Sharma" class="w-full px-4 py-3 rounded-xl border border-border bg-bg text-sm text-fg focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/10 transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-fg mb-1.5 uppercase tracking-wide">Company Name</label>
                                    <input type="text" name="company" placeholder="Sharma Constructions" class="w-full px-4 py-3 rounded-xl border border-border bg-bg text-sm text-fg focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/10 transition-all">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-fg mb-1.5 uppercase tracking-wide">Email Address <span class="text-primary">*</span></label>
                                    <input type="email" name="email" required placeholder="rajesh@example.com" class="w-full px-4 py-3 rounded-xl border border-border bg-bg text-sm text-fg focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/10 transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-fg mb-1.5 uppercase tracking-wide">Phone Number</label>
                                    <input type="tel" name="phone" placeholder="+91 98765 43210" class="w-full px-4 py-3 rounded-xl border border-border bg-bg text-sm text-fg focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/10 transition-all">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-fg mb-1.5 uppercase tracking-wide">Message</label>
                                <textarea name="message" rows="4" placeholder="Tell us about your business needs..." class="w-full px-4 py-3 rounded-xl border border-border bg-bg text-sm text-fg focus:outline-none focus:border-primary/50 focus:ring-2 focus:ring-primary/10 transition-all resize-none"></textarea>
                            </div>

                            <button type="submit" class="btn-accent w-full py-3.5 rounded-xl text-sm font-semibold shadow-accent hover:shadow-lg flex items-center justify-center gap-2">
                                Send Message
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="lg:col-span-2 space-y-6 text-fg">
                    <div class="rounded-3xl border border-border bg-white p-7 shadow-soft">
                        <h2 class="font-display text-xl font-semibold mb-6 italic">Contact Info</h2>
                        <div class="space-y-5">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div>
                                    <p class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-0.5">Email</p>
                                    <a href="mailto:hello@buildarya.in" class="text-sm font-medium hover:text-primary transition-colors">hello@buildarya.in</a>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div>
                                    <p class="text-2xs uppercase tracking-widest font-bold text-fg-subtle mb-0.5">Phone</p>
                                    <a href="tel:+919876543210" class="text-sm font-medium hover:text-primary transition-colors">+91 98765 43210</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-border bg-white p-7 shadow-soft">
                        <h2 class="font-display text-xl font-semibold mb-5 italic">Office Hours</h2>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between py-2 border-b border-border">
                                <span class="text-sm text-fg-muted">Mon – Fri</span>
                                <span class="text-sm font-medium">9:00 AM – 6:00 PM</span>
                            </div>
                            <div class="flex items-center justify-between py-2 border-b border-border">
                                <span class="text-sm text-fg-muted">Saturday</span>
                                <span class="text-sm font-medium">10:00 AM – 2:00 PM</span>
                            </div>
                            <div class="flex items-center justify-between py-2 last:border-0 opacity-40 italic">
                                <span class="text-sm text-fg-muted">Sunday</span>
                                <span class="text-sm font-medium">Closed</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl bg-primary p-7 relative overflow-hidden text-white shadow-teal">
                        <div class="absolute inset-0 noise-overlay opacity-[0.04] pointer-events-none"></div>
                        <div class="relative z-10">
                            <h3 class="font-display text-lg font-semibold mb-2 italic">Free Demo Session</h3>
                            <p class="text-sm opacity-70 leading-relaxed mb-4">
                                Book a free 30-minute demo to see why Indian contractors trust Buildarya.
                            </p>
                            <a href="tel:+919876543210" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-accent">
                                Call for instant demo
                                <svg width="12" height="12" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </section>
@endsection
