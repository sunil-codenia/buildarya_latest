<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Buildarya — Construction Management for Indian Contractors')</title>
    <meta name="description" content="@yield('description', 'Buildarya helps Indian contractors manage sites, expenses, materials, and documents in one simple, reliable system built for daily construction operations.')">
    
    <link rel="icon" href="{{ asset('frontend/favicon.ico') }}" type="image/x-icon">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,400;1,9..144,600&display=swap" rel="stylesheet">

    <!-- Tailwind CDN with Custom Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#0B6E6E',
                            dark: '#085858',
                            light: '#0E8A8A',
                            50: '#F0F9F9',
                            100: '#CCEBEB',
                            200: '#99D6D6',
                            300: '#66C2C2',
                            400: '#33ADAD',
                            500: '#0B6E6E',
                            600: '#085858',
                            700: '#064242',
                            800: '#042C2C',
                            900: '#021616',
                        },
                        accent: {
                            DEFAULT: '#F5A623',
                            dark: '#D4911A',
                            light: '#F7B84E',
                        },
                        bg: {
                            DEFAULT: '#F8F7F4',
                            alt: '#FFFFFF',
                            surface: '#F0EFEC',
                        },
                        fg: {
                            DEFAULT: '#1C1917',
                            muted: '#78716C',
                            subtle: '#A8A29E',
                        },
                        border: {
                            DEFAULT: '#E7E5E4',
                            strong: '#D6D3D1',
                        },
                    },
                    fontFamily: {
                        display: ['Fraunces', 'serif'],
                        body: ['DM Sans', 'sans-serif'],
                        sans: ['DM Sans', 'sans-serif'],
                    },
                    borderRadius: {
                        '4xl': '2rem',
                        '5xl': '3rem',
                        '6xl': '6rem',
                        '7xl': '12rem',
                    },
                    boxShadow: {
                        'soft': '0 2px 12px rgba(0,0,0,0.06)',
                        'card': '0 4px 24px rgba(0,0,0,0.08)',
                        'card-hover': '0 8px 40px rgba(0,0,0,0.12)',
                        'teal': '0 4px 20px rgba(11,110,110,0.25)',
                        'accent': '0 4px 20px rgba(245,166,35,0.35)',
                    },
                }
            }
        }
    </script>

    <style>
        :root {
            --color-primary: #0B6E6E;
            --color-primary-dark: #085858;
            --color-primary-light: #0E8A8A;
            --color-accent: #F5A623;
            --color-bg: #F8F7F4;
            --color-bg-alt: #FFFFFF;
            --color-fg: #1C1917;
            --color-muted: #78716C;
            --color-border: #E7E5E4;
            --font-display: 'Fraunces', serif;
            --font-body: 'DM Sans', sans-serif;
        }
        body {
            font-family: var(--font-body);
            background-color: var(--color-bg);
            color: var(--color-fg);
            -webkit-font-smoothing: antialiased;
        }
        .font-display { font-family: var(--font-display); }
        .grid-overlay {
            position: fixed; inset: 0; pointer-events: none; z-index: 0; display: flex; justify-content: center; opacity: 0.35;
        }
        .grid-inner {
            width: 100%; height: 100%; max-width: 90rem; display: flex; justify-content: space-between; padding: 0 1.5rem;
        }
        .grid-line-v {
            width: 1px; height: 100%; background: linear-gradient(to bottom, transparent, #D6D3D1 30%, #D6D3D1 70%, transparent);
        }
        .btn-accent {
            background: var(--color-accent); color: #1C1917; font-weight: 600; position: relative; overflow: hidden;
            transition: all 0.2s;
        }
        .btn-accent:hover { transform: translateY(-1px); box-shadow: 0 4px 20px rgba(245,166,35,0.35); }
        
        /* Custom styles from your React index.css */
        .text-teal-gradient {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .animate-fade-in { animation: fadeIn 0.6s ease-out forwards; }
    </style>
    @yield('styles')
</head>
<body class="selection:bg-accent selection:text-fg">
    <!-- Grid Overlay -->
    <div class="grid-overlay">
        <div class="grid-inner">
            <div class="grid-line-v"></div>
            <div class="grid-line-v hidden sm:block"></div>
            <div class="grid-line-v hidden md:block"></div>
            <div class="grid-line-v hidden lg:block"></div>
            <div class="grid-line-v"></div>
        </div>
    </div>

    <!-- Header -->
    <header id="main-header" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300 bg-transparent">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-24 md:h-28">
                <a href="{{ url('/') }}" class="flex items-center flex-shrink-0">
                    <img src="{{ asset('images/buildarya.png') }}" style="width: 220px;" alt="Buildarya Logo">
                </a>

                <nav class="hidden md:flex items-center gap-1">
                    <a href="{{ url('/') }}" class="px-4 py-2 text-sm font-medium text-fg-muted hover:text-fg hover:bg-bg-surface rounded-lg transition-all duration-200">Home</a>
                    <a href="{{ url('/features') }}" class="px-4 py-2 text-sm font-medium text-fg-muted hover:text-fg hover:bg-bg-surface rounded-lg transition-all duration-200">Features</a>
                    <a href="{{ url('/modules') }}" class="px-4 py-2 text-sm font-medium text-fg-muted hover:text-fg hover:bg-bg-surface rounded-lg transition-all duration-200">Modules</a>
                    <a href="{{ url('/pricing') }}" class="px-4 py-2 text-sm font-medium text-fg-muted hover:text-fg hover:bg-bg-surface rounded-lg transition-all duration-200">Pricing</a>
                    <a href="{{ url('/contact') }}" class="px-4 py-2 text-sm font-medium text-fg-muted hover:text-fg hover:bg-bg-surface rounded-lg transition-all duration-200">Contact</a>
                </nav>

                <div class="flex items-center gap-3">
                    <!-- Language Selection Dropdown -->
                    <div class="relative inline-block text-left" id="langDropdownContainer">
                        <button type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-border text-fg hover:bg-bg-surface transition-all duration-200" id="langDropdownButton" aria-expanded="false" aria-haspopup="true">
                            <svg class="w-5 h-5 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-.778.099-1.533.284-2.253"></path>
                            </svg>
                            <span class="selected-lang-label uppercase font-bold text-fg">EN</span>
                            <svg class="w-4 h-4 text-fg-muted" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"></path>
                            </svg>
                        </button>
                        <div class="origin-top-right absolute right-0 mt-2 w-48 rounded-2xl shadow-card border border-border bg-white divide-y divide-border focus:outline-none hidden z-50 max-h-72 overflow-y-auto" id="langDropdownMenu" role="menu" aria-orientation="vertical" aria-labelledby="langDropdownButton" tabindex="-1">
                            <div class="py-1" role="none">
                                <a href="javascript:void(0);" onclick="changeLanguage('en')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">English <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">EN</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('hi')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">हिन्दी <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">HI</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('te')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">తెలుగు <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">TE</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('ta')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">தமிழ் <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">TA</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('mr')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">मराठी <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">MR</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('bn')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">বাংলা <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">BN</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('gu')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">ગુજરાતી <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">GU</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('pa')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">ਪੰਜਾਬੀ <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">PA</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('es')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">Español <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">ES</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('fr')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">Français <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">FR</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('ar')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">العربية <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">AR</span></a>
                                <a href="javascript:void(0);" onclick="changeLanguage('de')" class="flex items-center justify-between px-4 py-2 text-sm font-semibold text-fg hover:bg-bg-surface" role="menuitem">Deutsch <span class="text-xs font-bold text-white bg-primary px-2 py-0.5 rounded-md">DE</span></a>
                            </div>
                        </div>
                    </div>
                    <a href="{{ url('/login') }}" class="hidden md:inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold border border-border text-fg hover:bg-bg-surface transition-all duration-200">
                        Login
                    </a>
                    <a href="{{ url('/contact') }}" class="hidden md:inline-flex items-center gap-2 btn-accent px-5 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200">
                        Book Free Demo
                    </a>
                    <!-- Hamburger -->
                    <button id="mobile-menu-btn" class="md:hidden flex flex-col gap-1.5 w-8 h-8 items-center justify-center">
                        <span class="w-5 h-0.5 bg-fg rounded-full transition-all duration-300"></span>
                        <span class="w-5 h-0.5 bg-fg rounded-full transition-all duration-300"></span>
                        <span class="w-5 h-0.5 bg-fg rounded-full transition-all duration-300"></span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="fixed inset-0 z-40 bg-white/98 backdrop-blur-md flex flex-col pt-24 px-6 hidden overflow-y-auto">
        <nav class="flex flex-col gap-2">
            <a href="{{ url('/') }}" class="py-4 px-4 text-lg font-medium text-fg border-b border-border">Home</a>
            <a href="{{ url('/features') }}" class="py-4 px-4 text-lg font-medium text-fg border-b border-border">Features</a>
            <a href="{{ url('/modules') }}" class="py-4 px-4 text-lg font-medium text-fg border-b border-border">Modules</a>
            <a href="{{ url('/pricing') }}" class="py-4 px-4 text-lg font-medium text-fg border-b border-border">Pricing</a>
            <a href="{{ url('/contact') }}" class="py-4 px-4 text-lg font-medium text-fg border-b border-border">Contact</a>
            <a href="{{ url('/privacy-policy') }}" class="py-4 px-4 text-lg font-medium text-fg border-b border-border">Privacy Policy</a>
            <a href="{{ url('/terms-and-conditions') }}" class="py-4 px-4 text-lg font-medium text-fg border-b border-border">Terms & Conditions</a>
        </nav>
        <div class="mt-8 flex flex-col gap-3">
            <a href="{{ url('/login') }}" class="inline-flex items-center justify-center gap-2 px-5 py-4 rounded-xl text-base font-semibold border border-border text-fg hover:bg-bg-surface transition-all duration-200">
                Login
            </a>
            <a href="{{ url('/contact') }}" class="btn-accent text-center py-4 rounded-xl text-base font-semibold">Book Free Demo</a>
        </div>
    </div>

    <!-- Content -->
    <main class="relative z-10">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="border-t border-border bg-white relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-10">
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2.5">
                        <img src="{{ asset('images/buildarya.png') }}" style="width: 150px;" alt="Buildarya Logo">
                    </div>
                    <p class="text-sm text-fg-muted max-w-xs leading-relaxed">
                        Construction management software built for Indian contractors.
                    </p>
                </div>

                <div class="flex flex-wrap gap-x-12 gap-y-6">
                    <div class="flex flex-col gap-3">
                        <a href="{{ url('/features') }}" class="text-sm font-medium text-fg-muted hover:text-fg transition-colors">Features</a>
                        <a href="{{ url('/modules') }}" class="text-sm font-medium text-fg-muted hover:text-fg transition-colors">Modules</a>
                        <a href="{{ url('/pricing') }}" class="text-sm font-medium text-fg-muted hover:text-fg transition-colors">Pricing</a>
                    </div>
                    <div class="flex flex-col gap-3">
                        <a href="{{ url('/') }}" class="text-sm font-medium text-fg-muted hover:text-fg transition-colors">About</a>
                        <a href="{{ url('/contact') }}" class="text-sm font-medium text-fg-muted hover:text-fg transition-colors">Contact</a>
                        <a href="{{ url('/privacy-policy') }}" class="text-sm font-medium text-fg-muted hover:text-fg transition-colors">Privacy Policy</a>
                        <a href="{{ url('/terms-and-conditions') }}" class="text-sm font-medium text-fg-muted hover:text-fg transition-colors">Terms & Conditions</a>
                    </div>
                </div>
            </div>

            <div class="mt-12 pt-6 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-xs text-fg-subtle">© 2026 Buildarya. All rights reserved.</p>
                <div class="flex items-center gap-3">
                    <a href="{{ url('/privacy-policy') }}" class="text-[10px] uppercase tracking-widest font-bold text-fg-muted px-4 py-2 rounded-lg border border-border hover:border-primary/30 hover:bg-primary/5 hover:text-primary transition-all duration-200">Privacy Policy</a>
                    <a href="{{ url('/terms-and-conditions') }}" class="text-[10px] uppercase tracking-widest font-bold text-fg-muted px-4 py-2 rounded-lg border border-border hover:border-primary/30 hover:bg-primary/5 hover:text-primary transition-all duration-200">Terms of Use</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.getElementById('main-header');
            if (window.scrollY > 20) {
                header.classList.remove('bg-transparent');
                header.classList.add('bg-white/95', 'backdrop-blur-md', 'shadow-soft', 'border-b', 'border-border');
            } else {
                header.classList.add('bg-transparent');
                header.classList.remove('bg-white/95', 'backdrop-blur-md', 'shadow-soft', 'border-b', 'border-border');
            }
        });

        // Mobile menu toggle
        const menuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        menuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            if (!mobileMenu.classList.contains('hidden')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        });
    </script>
    
    <!-- Invisible but rendered Google Translate Element (Non-zero height/width forces Google script to render it) -->
    <div id="google_translate_element" style="position: absolute !important; top: 0px !important; left: 0px !important; width: 10px !important; height: 10px !important; opacity: 0.01 !important; overflow: hidden !important; z-index: -1000 !important;"></div>

    <style>
        .goog-te-banner-frame, .goog-te-banner, .skiptranslate, iframe[id*="translate"], .goog-logo-link {
            display: none !important;
        }
        body {
            top: 0px !important;
        }
        .goog-tooltip, .goog-tooltip:hover {
            display: none !important;
        }
        .goog-text-highlight {
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
    </style>

    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                layout: google.translate.TranslateElement.InlineLayout.SIMPLE
            }, 'google_translate_element');
        }

        function updateLanguageLabel(langCode) {
            const label = document.querySelector('.selected-lang-label');
            if (label) {
                label.innerText = langCode.toUpperCase();
            }
        }

        // Set translation cookies across multiple paths and subdomains
        function setGoogTransCookie(langCode) {
            var cookieValue = "/en/" + langCode;
            if (langCode === 'en') {
                document.cookie = "googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
                document.cookie = "googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=" + window.location.hostname;
            } else {
                document.cookie = "googtrans=" + cookieValue + "; path=/;";
                document.cookie = "googtrans=" + cookieValue + "; path=/; domain=" + window.location.hostname;
                if (!/^[0-9.]+$/.test(window.location.hostname)) {
                    document.cookie = "googtrans=" + cookieValue + "; path=/; domain=." + window.location.hostname;
                }
            }
        }

        function changeLanguage(langCode) {
            setGoogTransCookie(langCode);

            localStorage.setItem('selected_language', langCode);
            updateLanguageLabel(langCode);

            // POST to change language
            fetch('/change-language', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ locale: langCode })
            }).finally(() => {
                location.reload();
            });
        }

        // Toggle dropdown display
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('langDropdownButton');
            const menu = document.getElementById('langDropdownMenu');
            if (btn && menu) {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    menu.classList.toggle('hidden');
                });
                document.addEventListener('click', () => {
                    menu.classList.add('hidden');
                });
            }

            // Sync with session / localStorage
            const savedLang = localStorage.getItem('selected_language') || 'en';
            const activeSessionLang = "{{ session()->get('locale', 'en') }}";

            if (savedLang !== activeSessionLang) {
                changeLanguage(savedLang);
            } else {
                updateLanguageLabel(savedLang);
                if (savedLang !== 'en') {
                    setGoogTransCookie(savedLang);
                    
                    const checkInterval = setInterval(() => {
                        const select = document.querySelector('select.goog-te-combo');
                        if (select) {
                            clearInterval(checkInterval);
                            select.value = savedLang;
                            select.dispatchEvent(new Event('change'));
                        }
                    }, 100);
                }
            }
        });

        // Load Translate Script
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
