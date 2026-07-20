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

        <ul class="nav navbar-nav navbar-right d-flex align-items-center">
            <li class="dropdown">
                <a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown" role="button" style="display: flex; align-items: center;" onclick="markAllNotificationsRead()">
                    <div style="position: relative; display: inline-block;">
                        <i class="zmdi zmdi-notifications"></i>
                        @php 
                            $unreadCount = 0; 
                            $webNotifications = collect();
                            $conn = session()->get('comp_db_conn_name');
                            $uid = session()->get('uid');
                            if ($conn && $uid) {
                                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasTable('web_notifications')) {
                                    $webNotifications = DB::connection($conn)->table('web_notifications')
                                        ->where('user_id', $uid)
                                        ->orderBy('created_at', 'desc')
                                        ->limit(10)
                                        ->get();
                                    $unreadCount = $webNotifications->where('is_read', 0)->count();
                                }
                            }
                        @endphp
                        @if($unreadCount > 0)
                            <span class="badge badge-danger notif-badge" style="position: absolute; top: -10px; right: -12px; border-radius: 50%; padding: 4px 6px; font-size: 10px; line-height: 1; background: #e43a45; color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.3);">{{ $unreadCount }}</span>
                        @endif
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-right slideDown" style="width: 320px; padding: 0; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-radius: 8px; border: none; overflow: hidden; margin-top: 5px;">
                    <li class="header" style="background: #f8f9fa; padding: 15px 20px; font-weight: 700; color: #333; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; font-size: 13px; letter-spacing: 0.5px;">
                        NOTIFICATIONS
                        @if($unreadCount > 0)
                            <span class="badge notif-badge" style="background-color: var(--custom-primary, #764ba2); color: white; border-radius: 12px; padding: 4px 8px; font-size: 11px;">{{ $unreadCount }} New</span>
                        @endif
                    </li>
                    <li class="body" style="max-height: 350px; overflow-y: auto; padding: 0;">
                        <ul class="list-unstyled" style="margin: 0; padding: 0;">
                            @if(count($webNotifications) > 0)
                                @foreach($webNotifications as $notif)
                                    <li style="margin: 0 !important; padding: 0 !important; height: auto !important; min-height: 0 !important; border: none !important;">
                                        <a href="{{ route('notifications.read', ['id' => $notif->id]) }}" style="display: flex !important; align-items: flex-start !important; padding: 15px 20px !important; border-bottom: 1px solid #f1f1f1 !important; background-color: {{ $notif->is_read ? '#ffffff' : '#fcfaff' }} !important; height: auto !important; min-height: 0 !important; white-space: normal !important; line-height: normal !important; transition: all 0.2s ease; width: 100% !important; box-sizing: border-box !important;">
                                            <div style="flex-shrink: 0 !important; width: 38px !important; height: 38px !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; margin-right: 15px !important; background: {{ $notif->is_read ? '#f0f0f0' : 'linear-gradient(135deg, #764ba2, #a88be8)' }} !important; color: {{ $notif->is_read ? '#999' : '#fff' }} !important; box-shadow: {{ $notif->is_read ? 'none' : '0 2px 5px rgba(118,75,162,0.3)' }} !important;">
                                                <i class="zmdi zmdi-notifications-active" style="font-size: 18px !important;"></i>
                                            </div>
                                            <div class="menu-info" style="flex: 1 !important; min-width: 0 !important; text-align: left !important; overflow: hidden !important; padding: 0 !important; margin: 0 !important;">
                                                <h4 style="margin: 0 0 5px 0 !important; padding: 0 !important; font-size: 13px !important; color: #222 !important; {{ $notif->is_read ? 'font-weight: 500;' : 'font-weight: 700;' }} line-height: 1.3 !important; display: block !important; word-break: break-word !important; white-space: normal !important; overflow-wrap: break-word !important;">{{ $notif->title }}</h4>
                                                <p style="margin: 0 0 5px 0 !important; padding: 0 !important; font-size: 12px !important; color: #555 !important; white-space: normal !important; line-height: 1.4 !important; display: block !important; text-align: left !important; word-break: break-word !important; overflow-wrap: break-word !important;">{{ $notif->message }}</p>
                                                <p style="margin: 0 !important; padding: 0 !important; font-size: 11px !important; color: #999 !important; display: block !important; text-align: left !important;"><i class="zmdi zmdi-time" style="margin-right: 3px;"></i> {{ \Carbon\Carbon::parse($notif->created_at)->diffForHumans() }}</p>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            @else
                                <li style="margin: 0; padding: 0;">
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; text-align: center;">
                                        <i class="zmdi zmdi-notifications-none" style="font-size: 40px; color: #e0e0e0; margin-bottom: 15px;"></i>
                                        <h4 style="margin: 0 0 5px 0; font-size: 14px; color: #666; font-weight: 600;">All Caught Up!</h4>
                                        <p style="margin: 0; font-size: 12px; color: #999;">You have no new notifications.</p>
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </li>
                    <li class="footer" style="padding: 0; text-align: center; border-top: 1px solid #eee; background: #fff;"> 
                        <a href="javascript:void(0);" style="display: block !important; padding: 12px !important; color: var(--custom-primary, #764ba2) !important; font-weight: 600 !important; font-size: 12px !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; transition: background 0.2s !important;">View All Notifications</a> 
                    </li>
                </ul>
            </li>
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
<script>
function markAllNotificationsRead() {
    var badges = document.querySelectorAll('.notif-badge');
    badges.forEach(b => b.style.display = 'none');
    
    fetch('{{ route("notifications.markAllRead") }}', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    }).then(r => r.json()).then(data => {
        if(data.status === 'success'){
            console.log('All notifications marked as read.');
        }
    }).catch(e => console.error(e));
}
</script>
