<!doctype html>
<html class="no-js " lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=Edge">
<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
<meta name="description" content="Responsive Bootstrap 4 and web Application ui kit.">
<title>:: Build Arya ::</title>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="{{ asset('/plugins/bootstrap/css/bootstrap.min.css') }}"/>
<link rel="stylesheet" href="{{ asset('/plugins/jvectormap/jquery-jvectormap-2.0.3.min.css') }}"/>
<link rel="stylesheet" href="{{ asset('/plugins/morrisjs/morris.min.css') }}" />

<link rel="stylesheet" href="{{ asset('/css/main.css') }}"/>
<link rel="stylesheet" href="{{ asset('/css/color_skins.css') }}"/>
</head>   <!-- include header -->
@if(Session::has('key'))
@php
header("Location: " . URL::to('/dashboard'), true, 302);
exit();
@endphp
@endif
<body class="theme-purple authentication sidebar-collapse">
<div class="page-header">
    <div class="page-header-image" style="background-image:url({{asset('/images/login.jpg')}})"></div>
    <div class="container">
        <div class="col-md-12 content-center">
            @isset($errorcode)
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Sorry!</strong> {{$errorcode}}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
              </div>

           @endisset
           @isset($successcode)
           <div class="alert alert-warning alert-dismissible fade show" role="alert">
                {{$successcode}}
               <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                 <span aria-hidden="true">&times;</span>
               </button>
             </div>

          @endisset
            <div class="card-plain">
                
                <form class="form" method="POST" action="loginf">
                    @csrf

                    <div class="header">
                        <div class="logo-container" style="width: 320px; margin: 0 auto 30px;">
                            <img src="{{asset('images/buildarya.png')}}" alt="Buildarya Logo" style="width: 100%;">
                        </div>
                        <h5>Log in</h5>
                    </div>
                    <div class="content">                                                
                        <div class="input-group input-lg">
                            <input type="text" name="companyid" class="form-control" required placeholder="Enter Company Id">
                            <span class="input-group-addon">
                                <i class="zmdi zmdi-star-circle"></i>
                            </span>
                        </div>
                        <div class="input-group input-lg">
                            <input type="text" class="form-control" name="username" required placeholder="Enter User Name">
                            <span class="input-group-addon">
                                <i class="zmdi zmdi-account-circle"></i>
                            </span>
                        </div>
                        <div class="input-group input-lg">
                            <input type="password" placeholder="Password" name="password" required class="form-control" />
                            <span class="input-group-addon">
                                <i class="zmdi zmdi-lock"></i>
                            </span>
                        </div>
                    </div>
                    <div class="footer text-center">
                        <button type="submit" class="btn l-cyan btn-round btn-lg btn-block waves-effect waves-light">SIGN IN</button>
                        <h6 class="m-t-20"><a href="{{url('/register_user')}}" class="link">Not A User! Register Yourself</a></h6>
                    </div>
                </form>
            </div>
        </div>
    </div>
 
</div>

<!-- Jquery Core Js --> 
<script src="{{asset('/bundles/libscripts.bundle.js')}}"></script> <!-- Lib Scripts Plugin Js ( jquery.v3.2.1, Bootstrap4 js) --> 
<script src="{{asset('/bundles/vendorscripts.bundle.js')}}"></script> <!-- slimscroll, waves Scripts Plugin Js -->

<script src="{{asset('/bundles/morrisscripts.bundle.js')}}"></script><!-- Morris Plugin Js -->
<script src="{{asset('/bundles/jvectormap.bundle.js')}}"></script> <!-- JVectorMap Plugin Js -->
<script src="{{asset('/bundles/knob.bundle.js')}}"></script> <!-- Jquery Knob Plugin Js -->
<script src="{{asset('/bundles/countTo.bundle.js')}}"></script> <!-- Jquery CountTo Plugin Js -->
<script src="{{asset('/bundles/sparkline.bundle.js')}}"></script> <!-- Sparkline Plugin Js -->

<script src="{{asset('/bundles/mainscripts.bundle.js')}}"></script>
<script src="{{asset('/js/pages/index.js')}}"></script>

<script>
 
//=============================================================================
$('.form-control').on("focus", function() {
    $(this).parent('.input-group').addClass("input-group-focus");
}).on("blur", function() {
    $(this).parent(".input-group").removeClass("input-group-focus");
});
</script>
</body>
</html>   
