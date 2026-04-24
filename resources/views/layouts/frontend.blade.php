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
        <a href="{{ url('/contact') }}" class="mt-8 btn-accent text-center py-4 rounded-xl text-base font-semibold">Book Free Demo</a>
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
    @yield('scripts')
</body>
</html>
