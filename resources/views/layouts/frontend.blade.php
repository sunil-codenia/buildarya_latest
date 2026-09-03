<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Buildarya — Premier Construction MIS & Site Management Platform')</title>
    <meta name="description" content="@yield('description', 'Buildarya helps Indian contractors manage sites, expenses, materials, labour attendance, and billing in one unified, simple system.')">
    
    <link rel="icon" href="{{ asset('frontend/favicon.ico') }}" type="image/x-icon">
    
    <!-- Modern Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CDN with Custom Extensions -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        teal: {
                            50: '#F0FDFA',
                            100: '#CCFBF1',
                            200: '#99F6E4',
                            400: '#2DD4BF',
                            500: '#14B8A6',
                            600: '#0D9488',
                            700: '#0F766E',
                            800: '#115E59',
                            900: '#134E4A',
                        },
                        amber: {
                            50: '#FFFBEB',
                            100: '#FEF3C7',
                            400: '#FBBF24',
                            500: '#F59E0B',
                            600: '#D97706',
                            700: '#B45309',
                        },
                    },
                    fontFamily: {
                        display: ['Outfit', 'sans-serif'],
                        body: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(15, 23, 42, 0.05)',
                        'card': '0 12px 32px -4px rgba(15, 23, 42, 0.08)',
                        'glass': '0 8px 32px 0 rgba(15, 23, 42, 0.06)',
                        'teal-glow': '0 12px 30px -4px rgba(13, 148, 136, 0.35)',
                        'amber-glow': '0 12px 30px -4px rgba(245, 158, 11, 0.35)',
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F8FAFC;
            color: #0F172A;
            -webkit-font-smoothing: antialiased;
        }

        .font-display { font-family: 'Outfit', sans-serif; }

        /* Custom Gradients */
        .bg-gradient-teal-amber {
            background: linear-gradient(135deg, #0D9488 0%, #0F766E 50%, #D97706 100%);
        }

        .text-gradient-teal {
            background: linear-gradient(135deg, #0D9488 0%, #0F766E 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .text-gradient-amber {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        /* Ambient Glow Mesh */
        .ambient-glow {
            position: absolute;
            border-radius: 9999px;
            filter: blur(80px);
            pointer-events: none;
            opacity: 0.45;
        }

        /* Micro Floating Keyframe */
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }
        .animate-float-slow {
            animation: floatSlow 5s ease-in-out infinite;
        }

        @keyframes marquee {
            0% { transform: translateX(0%); }
            100% { transform: translateX(-50%); }
        }
        .animate-marquee {
            display: flex;
            width: 200%;
            animation: marquee 25s linear infinite;
        }
        .animate-marquee:hover {
            animation-play-state: paused;
        }

        /* Modern Shimmer Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #0D9488 0%, #0F766E 100%);
            color: #FFFFFF;
            font-weight: 800;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 25px -4px rgba(13, 148, 136, 0.35);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px -4px rgba(13, 148, 136, 0.45);
        }

        .btn-amber {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: #FFFFFF;
            font-weight: 800;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 25px -4px rgba(245, 158, 11, 0.35);
        }
        .btn-amber:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px -4px rgba(245, 158, 11, 0.45);
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    </style>
    @yield('styles')
</head>
<body class="bg-slate-50 text-slate-900 antialiased selection:bg-teal-500 selection:text-white">

    <!-- Top Announcement Bar -->
    <div class="bg-gradient-to-r from-teal-800 via-teal-700 to-slate-900 text-white text-[11px] font-extrabold py-2 px-4 text-center tracking-wide flex items-center justify-center gap-2 border-b border-teal-600/40">
        <span class="bg-amber-400 text-slate-950 px-2 py-0.5 rounded-full text-[9px] uppercase font-black">NEW</span>
        <span>Buildarya Mobile APK v2.4 Released with Offline Receipt Capture</span>
        <a href="{{ url('/contact') }}" class="underline hover:text-amber-300 ml-1">Book Free Demo →</a>
    </div>

    <!-- Glassmorphic Header -->
    <header id="main-header" class="sticky top-0 z-50 bg-white/90 backdrop-blur-xl border-b border-slate-200/80 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Brand Logo -->
                <a href="{{ url('/') }}" class="flex items-center flex-shrink-0 group overflow-hidden" style="width: 205px; height: 60px; position: relative;">
                    <img src="{{ asset('images/buildarya.png') }}" style="position: absolute; width: 310px; max-width: none; left: -58px; top: -60px;" alt="Buildarya Logo" class="transition-transform duration-300 group-hover:scale-105">
                </a>

                <!-- Nav Links with Active Indicator -->
                <nav class="hidden lg:flex items-center gap-1.5 bg-slate-100/80 p-1.5 rounded-2xl border border-slate-200/70 shadow-inner">
                    <a href="{{ url('/') }}" class="px-4 py-2 text-xs font-extrabold text-slate-700 hover:text-teal-700 hover:bg-white rounded-xl transition-all shadow-sm">Home</a>
                    <a href="{{ url('/features') }}" class="px-4 py-2 text-xs font-extrabold text-slate-700 hover:text-teal-700 hover:bg-white rounded-xl transition-all shadow-sm">Features</a>
                    <a href="{{ url('/modules') }}" class="px-4 py-2 text-xs font-extrabold text-slate-700 hover:text-teal-700 hover:bg-white rounded-xl transition-all shadow-sm">Modules</a>
                    <a href="{{ url('/pricing') }}" class="px-4 py-2 text-xs font-extrabold text-slate-700 hover:text-teal-700 hover:bg-white rounded-xl transition-all shadow-sm">Pricing</a>
                    <a href="{{ url('/contact') }}" class="px-4 py-2 text-xs font-extrabold text-slate-700 hover:text-teal-700 hover:bg-white rounded-xl transition-all shadow-sm">Contact</a>
                </nav>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <!-- Language Selection Dropdown -->
                    <div class="relative inline-block text-left" id="langDropdownContainer">
                        <button type="button" class="inline-flex items-center justify-center gap-1.5 h-10 px-3 rounded-xl text-xs font-extrabold border border-slate-200 bg-white text-slate-700 hover:border-teal-500 hover:text-teal-700 transition-all shadow-sm" id="langDropdownButton">
                            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-.778.099-1.533.284-2.253"/></svg>
                            <span class="selected-lang-label uppercase font-black text-slate-900">EN</span>
                            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div class="origin-top-right absolute right-0 mt-2 w-44 rounded-2xl shadow-xl border border-slate-200 bg-white divide-y divide-slate-100 hidden z-50 max-h-72 overflow-y-auto" id="langDropdownMenu">
                            <div class="py-1">
                                <a href="javascript:void(0);" onclick="changeLanguage('en')" class="flex items-center justify-between px-4 py-2 text-xs font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700">English <span class="text-[9px] text-white bg-teal-600 px-1.5 py-0.5 rounded font-black">EN</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('hi')" class="flex items-center justify-between px-4 py-2 text-xs font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700">हिन्दी <span class="text-[9px] text-white bg-teal-600 px-1.5 py-0.5 rounded font-black">HI</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('te')" class="flex items-center justify-between px-4 py-2 text-xs font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700">తెలుగు <span class="text-[9px] text-white bg-teal-600 px-1.5 py-0.5 rounded font-black">TE</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('ta')" class="flex items-center justify-between px-4 py-2 text-xs font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700">தமிழ் <span class="text-[9px] text-white bg-teal-600 px-1.5 py-0.5 rounded font-black">TA</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('mr')" class="flex items-center justify-between px-4 py-2 text-xs font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700">मराठी <span class="text-[9px] text-white bg-teal-600 px-1.5 py-0.5 rounded font-black">MR</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('bn')" class="flex items-center justify-between px-4 py-2 text-xs font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700">বাংলা <span class="text-[9px] text-white bg-teal-600 px-1.5 py-0.5 rounded font-black">BN</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('gu')" class="flex items-center justify-between px-4 py-2 text-xs font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700">ગુજરાતી <span class="text-[9px] text-white bg-teal-600 px-1.5 py-0.5 rounded font-black">GU</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('pa')" class="flex items-center justify-between px-4 py-2 text-xs font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700">ਪੰਜਾਬੀ <span class="text-[9px] text-white bg-teal-600 px-1.5 py-0.5 rounded font-black">PA</span></a>
                            </div>
                        </div>
                    </div>

                    <!-- Login Button -->
                    <a href="{{ url('/login') }}" class="hidden sm:inline-flex items-center justify-center h-10 px-5 rounded-xl text-xs font-extrabold border border-slate-200 bg-white text-slate-700 hover:border-teal-500 hover:text-teal-700 transition-all shadow-sm flex-shrink-0 whitespace-nowrap">
                        Login
                    </a>

                    <!-- Book Demo Button -->
                    <a href="{{ url('/contact') }}" class="hidden lg:inline-flex items-center justify-center btn-amber h-10 px-5 rounded-xl text-xs font-black uppercase tracking-wider">
                        Book Free Demo
                    </a>

                    <!-- Mobile Menu Trigger -->
                    <button id="mobile-menu-btn" class="lg:hidden flex flex-col gap-1.5 w-10 h-10 items-center justify-center rounded-xl bg-white border border-slate-200 shadow-sm">
                        <span class="w-5 h-0.5 bg-slate-800 rounded-full"></span>
                        <span class="w-5 h-0.5 bg-slate-800 rounded-full"></span>
                        <span class="w-5 h-0.5 bg-slate-800 rounded-full"></span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Drawer -->
    <div id="mobile-menu" class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-md flex flex-col pt-24 px-6 hidden">
        <div class="bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 flex flex-col gap-3">
            <nav class="flex flex-col gap-1">
                <a href="{{ url('/') }}" class="py-2.5 px-4 text-sm font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700 rounded-xl">Home</a>
                <a href="{{ url('/features') }}" class="py-2.5 px-4 text-sm font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700 rounded-xl">Features</a>
                <a href="{{ url('/modules') }}" class="py-2.5 px-4 text-sm font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700 rounded-xl">Modules</a>
                <a href="{{ url('/pricing') }}" class="py-2.5 px-4 text-sm font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700 rounded-xl">Pricing</a>
                <a href="{{ url('/contact') }}" class="py-2.5 px-4 text-sm font-bold text-slate-800 hover:bg-teal-50 hover:text-teal-700 rounded-xl">Contact</a>
            </nav>
            <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                <a href="{{ url('/login') }}" class="text-center py-3 rounded-xl text-xs font-bold border border-slate-200 text-slate-800">Login</a>
                <button type="button" id="apk-modal-btn-mobile" class="py-3 rounded-xl text-xs font-bold border border-teal-200 bg-teal-50 text-teal-700">Download APK</button>
                <a href="{{ url('/contact') }}" class="btn-amber text-center py-3 rounded-xl text-xs font-extrabold uppercase tracking-wider">Book Free Demo</a>
            </div>
        </div>
    </div>

    <!-- APK Download Modal -->
    <div id="apk-modal" class="fixed inset-0 z-[200] flex items-center justify-center p-4 hidden">
        <div id="apk-modal-backdrop" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>

        <div class="relative z-10 w-full max-w-md bg-white rounded-3xl shadow-2xl border border-slate-100 p-7 animate-fade-in">
            <button type="button" id="apk-modal-close" class="absolute top-5 right-5 w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200">
                ✕
            </button>

            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center font-bold">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 16l-6-6h4V4h4v6h4l-6 6z" fill="currentColor"/><path d="M20 18H4v2h16v-2z" fill="currentColor"/></svg>
                </div>
                <div>
                    <h3 class="font-display text-lg font-bold text-slate-900">Download Buildarya APK</h3>
                    <p class="text-[11px] text-slate-500">Android App for Site Supervisors</p>
                </div>
            </div>

            <form id="apk-download-form" action="{{ route('apk.download') }}" method="POST" class="space-y-3.5">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Full Name *</label>
                    <input type="text" name="name" required placeholder="Rajesh Sharma" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Email Address *</label>
                    <input type="email" name="email" required placeholder="rajesh@company.com" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 mb-1 uppercase">Phone Number</label>
                    <input type="tel" name="phone" placeholder="+91 98765 43210" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs font-medium focus:bg-white focus:border-teal-500">
                </div>

                <button type="submit" id="apk-submit-btn" class="btn-primary w-full py-3 rounded-xl text-xs font-bold flex items-center justify-center gap-2">
                    Download APK Now
                </button>
            </form>

            <div id="apk-success-panel" class="hidden text-center py-4">
                <h4 class="font-display text-lg font-bold text-emerald-700 mb-1">Download Started!</h4>
                <p class="text-xs text-slate-500 mb-4">Check your downloads folder.</p>
                <button type="button" id="apk-success-close" class="px-5 py-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700">Close</button>
            </div>
            <div id="apk-fetch-error" class="hidden mt-3 p-3 rounded-xl bg-red-50 text-red-700 text-xs font-semibold"></div>
        </div>
    </div>

    <!-- Main Content Yield -->
    <main>
        @yield('content')
    </main>

    <!-- Clean Footer -->
    <footer class="bg-white border-t border-slate-200 pt-16 pb-12 text-slate-600">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-10 pb-12 border-b border-slate-100">
                <div class="md:col-span-5 space-y-4">
                    <a href="{{ url('/') }}" class="inline-block overflow-hidden" style="width: 190px; height: 55px; position: relative;">
                        <img src="{{ asset('images/buildarya.png') }}" style="position: absolute; width: 290px; max-width: none; left: -54px; top: -55px;" alt="Buildarya Logo">
                    </a>
                    <p class="text-xs text-slate-500 leading-relaxed max-w-sm font-medium">
                        Buildarya is India's premier site management MIS designed for civil contractors and builders to track expenses, materials, and workforce operations seamlessly.
                    </p>
                </div>
                <div class="md:col-span-7 grid grid-cols-2 sm:grid-cols-3 gap-6 text-xs">
                    <div class="space-y-3">
                        <h4 class="font-bold text-slate-900 uppercase tracking-wider text-[11px]">Platform</h4>
                        <ul class="space-y-2 font-medium">
                            <li><a href="{{ url('/features') }}" class="hover:text-teal-700">Features</a></li>
                            <li><a href="{{ url('/modules') }}" class="hover:text-teal-700">Modules</a></li>
                            <li><a href="{{ url('/pricing') }}" class="hover:text-teal-700">Pricing</a></li>
                        </ul>
                    </div>
                    <div class="space-y-3">
                        <h4 class="font-bold text-slate-900 uppercase tracking-wider text-[11px]">Company</h4>
                        <ul class="space-y-2 font-medium">
                            <li><a href="{{ url('/contact') }}" class="hover:text-teal-700">Contact Us</a></li>
                            <li><a href="{{ url('/login') }}" class="hover:text-teal-700">Login</a></li>
                        </ul>
                    </div>
                    <div class="space-y-3 col-span-2 sm:col-span-1">
                        <h4 class="font-bold text-slate-900 uppercase tracking-wider text-[11px]">Contact</h4>
                        <ul class="space-y-2 font-medium text-slate-500">
                            <li>hello@buildarya.in</li>
                            <li>+91 98765 43210</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-[11px] font-medium text-slate-400">
                <p>© 2026 Buildarya by Shaarvik Technology. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="{{ url('/privacy-policy') }}" class="hover:text-teal-700">Privacy Policy</a>
                    <span>•</span>
                    <a href="{{ url('/terms-and-conditions') }}" class="hover:text-teal-700">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Header & Interactive Scripts -->
    <script>
        const menuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        if (menuBtn && mobileMenu) {
            menuBtn.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
        }

        const apkModal      = document.getElementById('apk-modal');
        const apkModalClose = document.getElementById('apk-modal-close');
        const apkModalBdrop = document.getElementById('apk-modal-backdrop');
        const apkModalBtnD  = document.getElementById('apk-modal-btn');
        const apkModalBtnM  = document.getElementById('apk-modal-btn-mobile');

        function openApkModal() { if (apkModal) apkModal.classList.remove('hidden'); }
        function closeApkModal() { if (apkModal) apkModal.classList.add('hidden'); }

        if (apkModalBtnD)  apkModalBtnD.addEventListener('click', openApkModal);
        if (apkModalBtnM)  apkModalBtnM.addEventListener('click', () => { if (mobileMenu) mobileMenu.classList.add('hidden'); openApkModal(); });
        if (apkModalClose) apkModalClose.addEventListener('click', closeApkModal);
        if (apkModalBdrop) apkModalBdrop.addEventListener('click', closeApkModal);

        // Async APK Download Form Handling
        const apkForm      = document.getElementById('apk-download-form');
        const apkSubmitBtn = document.getElementById('apk-submit-btn');
        const apkSuccess   = document.getElementById('apk-success-panel');
        const apkFetchErr  = document.getElementById('apk-fetch-error');
        const apkSuccClose = document.getElementById('apk-success-close');

        if (apkSuccClose) apkSuccClose.addEventListener('click', closeApkModal);

        if (apkForm) {
            apkForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (apkSubmitBtn) apkSubmitBtn.disabled = true;

                try {
                    const formData = new FormData(apkForm);
                    const response = await fetch(apkForm.action, {
                        method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!response.ok) throw new Error('Download failed. Please check inputs.');

                    const contentType = response.headers.get('Content-Type') || '';
                    if (contentType.includes('application/json')) {
                        const json = await response.json();
                        throw new Error(json.message || 'APK unavailable.');
                    }

                    const blob = await response.blob();
                    const url  = window.URL.createObjectURL(blob);
                    const a    = document.createElement('a');
                    a.href     = url; a.download = 'buildarya_latest.apk';
                    document.body.appendChild(a); a.click(); a.remove();
                    window.URL.revokeObjectURL(url);

                    if (apkForm) apkForm.classList.add('hidden');
                    if (apkSuccess) apkSuccess.classList.remove('hidden');

                } catch (err) {
                    if (apkFetchErr) { apkFetchErr.textContent = err.message; apkFetchErr.classList.remove('hidden'); }
                    if (apkSubmitBtn) apkSubmitBtn.disabled = false;
                }
            });
        }
    </script>
    
    <!-- Google Translate Script Container -->
    <div id="google_translate_element" style="position: absolute !important; top: 0px !important; left: 0px !important; width: 10px !important; height: 10px !important; opacity: 0.01 !important; overflow: hidden !important; z-index: -1000 !important;"></div>

    <style>
        .goog-te-banner-frame, .goog-te-banner, .skiptranslate, iframe[id*="translate"], .goog-logo-link { display: none !important; }
        body { top: 0px !important; }
        .goog-tooltip, .goog-tooltip:hover { display: none !important; }
        .goog-text-highlight { background-color: transparent !important; border: none !important; box-shadow: none !important; }
    </style>

    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({ pageLanguage: 'en', layout: google.translate.TranslateElement.InlineLayout.SIMPLE }, 'google_translate_element');
        }
        function updateLanguageLabel(langCode) {
            const label = document.querySelector('.selected-lang-label');
            if (label) label.innerText = langCode.toUpperCase();
        }
        function setGoogTransCookie(langCode) {
            var cookieValue = "/en/" + langCode;
            if (langCode === 'en') {
                document.cookie = "googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                document.cookie = "googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=" + window.location.hostname;
            } else {
                document.cookie = "googtrans=" + cookieValue + "; path=/;";
                document.cookie = "googtrans=" + cookieValue + "; path=/; domain=" + window.location.hostname;
            }
        }
        function changeLanguage(langCode) {
            setGoogTransCookie(langCode);
            localStorage.setItem('selected_language', langCode);
            updateLanguageLabel(langCode);
            fetch('/change-language', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ locale: langCode })
            }).finally(() => location.reload());
        }

        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('langDropdownButton');
            const menu = document.getElementById('langDropdownMenu');
            if (btn && menu) {
                btn.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('hidden'); });
                document.addEventListener('click', () => menu.classList.add('hidden'));
            }
            const savedLang = localStorage.getItem('selected_language') || 'en';
            updateLanguageLabel(savedLang);
        });

        (function() {
            var gtScript = document.createElement('script');
            gtScript.type = 'text/javascript';
            gtScript.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
            document.body.appendChild(gtScript);
        })();
    </script>
    @yield('scripts')
</body>
</html>
