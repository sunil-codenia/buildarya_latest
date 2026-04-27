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
            @if (checkmodulepermission(9, 'can_edit') == 1)
            <li>
                <a href="javascript:void(0);" class="js-right-sidebar" data-close="true">
                    <i class="zmdi zmdi-settings zmdi-hc-spin"></i>
                </a>
            </li>
            @endif
        </ul>
    </div>
</nav>

<div class="page-loader-wrapper">
    <div class="loader">
        <div class="m-t-30"><img class="zmdi-hc-spin" src="{{asset('/images/buildarya.png')}}" width="160" alt="Buildarya"></div>
        <p>Please wait...</p>
    </div>
</div>
