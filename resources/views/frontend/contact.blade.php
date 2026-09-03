@extends('layouts.frontend')

@section('title', 'Contact — Book a Free Demo with Buildarya')
@section('description', 'Get in touch with Buildarya team or schedule a free live demo.')

@section('content')
    <section class="py-16 bg-gradient-to-b from-teal-50/50 via-slate-50 to-white text-center border-b border-slate-200/80">
        <div class="max-w-4xl mx-auto px-4 space-y-3">
            <span class="text-xs uppercase tracking-widest font-extrabold text-teal-700 bg-teal-50 border border-teal-200 px-3.5 py-1 rounded-full inline-block">
                GET IN TOUCH
            </span>
            <h1 class="font-display text-3xl sm:text-5xl font-extrabold text-slate-900">Book a Free Live Demo</h1>
            <p class="text-xs sm:text-sm text-slate-600 font-medium max-w-xl mx-auto">Speak with our site specialists to get custom access.</p>
        </div>
    </section>

    <section class="py-16 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-soft">
                @if(session('success'))
                    <div class="mb-6 p-4 rounded-2xl bg-emerald-50 text-emerald-800 text-xs font-bold">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 p-4 rounded-2xl bg-red-50 text-red-800 text-xs font-semibold">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('contact.submit') }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Full Name *</label>
                            <input type="text" name="name" required placeholder="Rajesh Sharma" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Company Name</label>
                            <input type="text" name="company" placeholder="Sharma Constructions" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Email Address *</label>
                            <input type="email" name="email" required placeholder="rajesh@company.com" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Phone Number</label>
                            <input type="tel" name="phone" placeholder="+91 98765 43210" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">State *</label>
                            <select name="state" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                                <option value="" disabled selected>Select State</option>
                                @foreach([
                                    "Andhra Pradesh", "Arunachal Pradesh", "Assam", "Bihar", "Chhattisgarh", "Goa", "Gujarat", "Haryana",
                                    "Himachal Pradesh", "Jharkhand", "Karnataka", "Kerala", "Madhya Pradesh", "Maharashtra", "Manipur",
                                    "Meghalaya", "Mizoram", "Nagaland", "Odisha", "Punjab", "Rajasthan", "Sikkim", "Tamil Nadu",
                                    "Telangana", "Tripura", "Uttar Pradesh", "Uttarakhand", "West Bengal", "Andaman and Nicobar Islands",
                                    "Chandigarh", "Dadra and Nagar Haveli and Daman and Diu", "Delhi", "Jammu and Kashmir", "Ladakh",
                                    "Lakshadweep", "Puducherry"
                                ] as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">GST Number</label>
                            <input type="text" name="gst_number" placeholder="GST number (optional)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Address</label>
                        <textarea name="address" rows="2" placeholder="Company Address" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500 resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Message</label>
                        <textarea name="message" rows="3" placeholder="Tell us about your sites..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500 resize-none"></textarea>
                    </div>

                    <div class="cf-turnstile mb-3" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>

                    <button type="submit" class="btn-amber w-full py-3.5 rounded-xl text-xs font-extrabold uppercase tracking-wider">
                        Send Message Now
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endsection
