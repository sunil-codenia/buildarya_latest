<nav class="navbar p-0">
    <div class="col-12 d-flex align-items-center justify-content-between h-100">        
        <!-- Branding Area -->
        <div class="navbar-header p-0 d-flex align-items-center">
            @php   
             $logo = "/images/buildarya.png";
            @endphp
            <a href="javascript:void(0);" class="ls-toggle-btn bars d-xl-none" data-close="true" style="padding: 0 15px; z-index: 102;">
                <i class="zmdi zmdi-menu" style="color: #ffffff; font-size: 28px;"></i>
            </a>
            <a class="navbar-brand dashboard-logo-link m-0" href="{{url('/dashboard')}}">
                <img src="{{asset($logo)}}" class="dashboard-logo" alt="Buildarya" style="width: auto; max-height: none !important;">
            </a>
        </div>

        <!-- Centered Company Name -->
        <div class="company-name-center">
            <h5>{{ Session::get('comp_name') }}</h5>
        </div>

        <!-- Right Icons Area -->
        <ul class="nav navbar-nav navbar-right d-flex align-items-center">
            <li>
                <a title="Sign-out" data-toggle="modal" data-target="#logoutmodal" class="mega-menu" data-close="true">
                    <i class="zmdi zmdi-power"></i>
                </a>
            </li>
            <li>
                <a href="javascript:void(0);" class="js-right-sidebar" data-close="true">
                    <i class="zmdi zmdi-settings zmdi-hc-spin"></i>
                </a>
            </li>
        </ul>
    </div>
</nav>

<div class="page-loader-wrapper">
    <div class="loader">
        <div class="m-t-30"><img class="zmdi-hc-spin" src="{{asset('/images/buildarya.png')}}" width="160" alt="Buildarya"></div>
        <p>Please wait...</p>
    </div>
</div>

<!-- Invisible but rendered Google Translate Element (Non-zero height/width forces Google script to render it) -->
<div id="google_translate_element" style="position: absolute !important; top: 0px !important; left: 0px !important; width: 10px !important; height: 10px !important; opacity: 0.01 !important; overflow: hidden !important; z-index: -1000 !important;"></div>

<!-- Google Translate CSS Hides Banners & Fixes Page Shifts -->
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
    .dropdown-menu .lang-item:hover {
        background-color: #f5f5f5 !important;
        color: #764ba2 !important;
    }
</style>

<script type="text/javascript">
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en',
            layout: google.translate.TranslateElement.InlineLayout.SIMPLE
        }, 'google_translate_element');
    }

    // Function to update the custom language selector label
    function updateLanguageLabel(langCode) {
        var label = document.querySelector('.selected-lang-label');
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

    // Function to handle language changes
    function changeLanguage(langCode) {
        setGoogTransCookie(langCode);

        // Save selection in localStorage
        localStorage.setItem('selected_language', langCode);
        updateLanguageLabel(langCode);

        // Sync with Laravel session via AJAX POST
        $.ajax({
            url: '/change-language',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                locale: langCode
            },
            success: function() {
                // Dispatch select combo change event if select element is loaded
                var select = document.querySelector('select.goog-te-combo');
                if (select) {
                    select.value = langCode;
                    select.dispatchEvent(new Event('change'));
                }
                // Reload page to apply the native PHP translation instantly
                location.reload();
            }
        });
    }

    // Load Translate Element Script
    (function() {
        var gtScript = document.createElement('script');
        gtScript.type = 'text/javascript';
        gtScript.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
        document.body.appendChild(gtScript);
    })();

    // Auto-apply saved language on page load
    document.addEventListener('DOMContentLoaded', function() {
        var savedLang = localStorage.getItem('selected_language') || 'en';
        var activeSessionLang = "{{ session()->get('locale', 'en') }}";

        if (savedLang !== activeSessionLang) {
            changeLanguage(savedLang);
        } else {
            updateLanguageLabel(savedLang);
            if (savedLang !== 'en') {
                setGoogTransCookie(savedLang);
                
                var checkInterval = setInterval(function() {
                    var select = document.querySelector('select.goog-te-combo');
                    if (select) {
                        clearInterval(checkInterval);
                        select.value = savedLang;
                        select.dispatchEvent(new Event('change'));
                    }
                }, 100);
            }
        }
    });
</script>
