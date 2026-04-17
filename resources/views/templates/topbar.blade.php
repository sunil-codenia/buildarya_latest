<nav class="navbar">
    <div class="col-12">        
        <div class="navbar-header" style="text-align-last: center;">
            @php   
             $menu_theme_arr = session()->get('menutheme');
             $menu_theme = is_array($menu_theme_arr) ? $menu_theme_arr[0] : ($menu_theme_arr ?? 'menu_dark');
            if($menu_theme == "menu_light"){
                $logo="/images/logoblackhori.png";
            }else{
                $logo="/images/build6.png";
            }

            @endphp
            <a class="navbar-brand dashboard-logo-link" href="{{url('/dashboard')}}"><img src="{{asset($logo)}}" class="dashboard-logo" alt="Construction Munshi"></a>
        </div>
        <ul class="nav navbar-nav navbar-left">
            <li><a href="javascript:void(0);" class="ls-toggle-btn" data-close="true"><i class="zmdi zmdi-swap"></i></a></li>            
            <li class="hidden-sm-down">
                {{-- <div class="input-group">
                    <input type="text" class="form-control search-input" placeholder="Search...">
                    <span class="input-group-addon">
                        <i class="zmdi zmdi-search"></i>
                    </span>
                </div> --}}
            </li>
        </ul>
        <ul class="nav navbar-nav navbar-right">
            {{-- <li class="dropdown"> <a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown" role="button"><i class="zmdi zmdi-notifications"></i>
                <div class="notify"><span class="heartbit"></span><span class="point"></span></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-right slideDown">
                    <li class="header">NOTIFICATIONS</li>
                    <li class="body">
                        <ul class="menu list-unstyled">
                            <li> <a href="javascript:void(0);">
                                <div class="icon-circle bg-blue"><i class="zmdi zmdi-account"></i></div>
                                <div class="menu-info">
                                    <h4>8 New Members joined</h4>
                                    <p><i class="zmdi zmdi-time"></i> 14 mins ago </p>
                                </div>
                                </a> </li>
                            <li> <a href="javascript:void(0);">
                                <div class="icon-circle bg-amber"><i class="zmdi zmdi-shopping-cart"></i></div>
                                <div class="menu-info">
                                    <h4>4 Sales made</h4>
                                    <p> <i class="zmdi zmdi-time"></i> 22 mins ago </p>
                                </div>
                                </a> </li>
                            <li> <a href="javascript:void(0);">
                                <div class="icon-circle bg-red"><i class="zmdi zmdi-delete"></i></div>
                                <div class="menu-info">
                                    <h4><b>Nancy Doe</b> Deleted account</h4>
                                    <p> <i class="zmdi zmdi-time"></i> 3 hours ago </p>
                                </div>
                                </a> </li>
                            <li> <a href="javascript:void(0);">
                                <div class="icon-circle bg-green"><i class="zmdi zmdi-edit"></i></div>
                                <div class="menu-info">
                                    <h4><b>Nancy</b> Changed name</h4>
                                    <p> <i class="zmdi zmdi-time"></i> 2 hours ago </p>
                                </div>
                                </a> </li>
                            <li> <a href="javascript:void(0);">
                                <div class="icon-circle bg-grey"><i class="zmdi zmdi-comment-text"></i></div>
                                <div class="menu-info">
                                    <h4><b>John</b> Commented your post</h4>
                                    <p> <i class="zmdi zmdi-time"></i> 4 hours ago </p>
                                </div>
                                </a> </li>
                            <li> <a href="javascript:void(0);">
                                <div class="icon-circle bg-purple"><i class="zmdi zmdi-refresh"></i></div>
                                <div class="menu-info">
                                    <h4><b>John</b> Updated status</h4>
                                    <p> <i class="zmdi zmdi-time"></i> 3 hours ago </p>
                                </div>
                                </a> </li>
                            <li> <a href="javascript:void(0);">
                                <div class="icon-circle bg-light-blue"><i class="zmdi zmdi-settings"></i></div>
                                <div class="menu-info">
                                    <h4>Settings Updated</h4>
                                    <p> <i class="zmdi zmdi-time"></i> Yesterday </p>
                                </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="footer"> <a href="javascript:void(0);">View All Notifications</a> </li>
                </ul>
            </li>
            <li class="dropdown"> <a href="javascript:void(0);" class="dropdown-toggle" data-toggle="dropdown" role="button"><i class="zmdi zmdi-flag"></i>
                <div class="notify"><span class="heartbit"></span><span class="point"></span></div>
                </a>
                <ul class="dropdown-menu dropdown-menu-right slideDown">
                    <li class="header">TASKS</li>
                    <li class="body">
                        <ul class="menu tasks list-unstyled">
                            <li> <a href="javascript:void(0);">
                                <div class="progress-container progress-primary">
                                    <span class="progress-badge">Footer display issue</span>
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow="86" aria-valuemin="0" aria-valuemax="100" style="width: 86%;">
                                            <span class="progress-value">86%</span>
                                        </div>
                                    </div>
                                </div>
                                </a>
                            </li>
                            <li> <a href="javascript:void(0);">
                                <div class="progress-container progress-info">
                                    <span class="progress-badge">Answer GitHub questions</span>
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow="35" aria-valuemin="0" aria-valuemax="100" style="width: 35%;">
                                            <span class="progress-value">35%</span>
                                        </div>
                                    </div>
                                </div>
                                </a>
                            </li>
                            <li> <a href="javascript:void(0);">
                                <div class="progress-container progress-success">
                                    <span class="progress-badge">Solve transition issue</span>
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow="72" aria-valuemin="0" aria-valuemax="100" style="width: 72%;">
                                            <span class="progress-value">72%</span>
                                        </div>
                                    </div>
                                </div>
                                </a>
                            </li>
                            <li><a href="javascript:void(0);">
                                <div class="progress-container">
                                    <span class="progress-badge"> Create new dashboard</span>
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100" style="width: 45%;">
                                            <span class="progress-value">45%</span>
                                        </div>
                                    </div>
                                </div>
                                </a>
                            </li>
                            <li> <a href="javascript:void(0);">
                                <div class="progress-container progress-warning">
                                    <span class="progress-badge">Panding Project</span>
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow="29" aria-valuemin="0" aria-valuemax="100" style="width: 29%;">
                                            <span class="progress-value">29%</span>
                                        </div>
                                    </div>
                                </div>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="footer"><a href="javascript:void(0);">View All</a></li>
                </ul>
            </li> --}}
           
            <li><a title="Sign-out" data-toggle="modal" data-target="#logoutmodal" class="mega-menu" data-close="true"><i class="zmdi zmdi-power"></i></a></li>
           
            @if (checkmodulepermission(9, 'can_edit') == 1)
            <li class=""><a href="javascript:void(0);" class="js-right-sidebar" data-close="true"><i class="zmdi zmdi-settings zmdi-hc-spin"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
<div class="page-loader-wrapper">
    <div class="loader">
        <div class="m-t-30"><img class="zmdi-hc-spin" src="{{asset('/images/logo.svg')}}" width="48" height="48" alt="Compass"></div>
        <p>Please wait...</p>
    </div>
</div>
