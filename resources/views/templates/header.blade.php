
<!doctype html>
<html class="no-js " lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=Edge">
<meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
<meta name="description" content="BuildArya: Best MIS Software For All Your Constructions Needs.">
<title>:: Build Arya ::</title>
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="stylesheet" href="/plugins/bootstrap/css/bootstrap.min.css"/>
<link rel="stylesheet" href="/plugins/jvectormap/jquery-jvectormap-2.0.3.min.css"/>
<link rel="stylesheet" href="/plugins/morrisjs/morris.min.css" />
<link rel="stylesheet" href="/css/main.css"/>
<link rel="stylesheet" href="/css/color_skins.css"/>
<link rel="stylesheet" href="/css/select2.css"/>
<link rel="stylesheet" href="/css/owl.carousel.min.css"/>
<link rel="stylesheet" href="/css/owl.theme.green.min.css"/>

<link rel="stylesheet" href="/plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.css" />
<link rel="stylesheet" href="/plugins/multi-select/css/multi-select.css">
<link rel="stylesheet" href="/plugins/jquery-spinner/css/bootstrap-spinner.css">
<link rel="stylesheet" href="/plugins/bootstrap-tagsinput/bootstrap-tagsinput.css">
<link rel="stylesheet" href="/plugins/bootstrap-select/css/bootstrap-select.css" />
<link rel="stylesheet" href="/plugins/nouislider/nouislider.min.css" />
<link rel="stylesheet" href="/plugins/DataTables/datatables.min.css" />

<link rel="stylesheet" href="/css/custom.css"/>

<style>
    /* Global fix for btn-primary text color overriding from color_skins.css */
    body .btn-primary:not(.btn-simple), 
    body .btn-primary:not(.btn-simple):hover, 
    body .btn-primary:not(.btn-simple):focus, 
    body .btn-primary:not(.btn-simple):active,
    body a.btn-primary:not(.btn-simple), 
    body a.btn-primary:not(.btn-simple):hover, 
    body a.btn-primary:not(.btn-simple):focus, 
    body a.btn-primary:not(.btn-simple):active,
    body .btn-primary:not(.btn-simple) i,
    .theme-blue .btn-primary:not(.btn-simple),
    .theme-custom .btn-primary:not(.btn-simple) {
        color: #ffffff !important;
    }
    
    /* Ensure btn-simple buttons with primary class are fully visible */
    body .btn-primary.btn-simple {
        background-color: transparent !important;
        color: var(--custom-primary, #764ba2) !important;
        border: 1px solid var(--custom-primary, #764ba2) !important;
    }
    
    body .btn-primary.btn-simple a,
    body .btn-primary.btn-simple i,
    body .btn-primary.btn-simple span {
        color: var(--custom-primary, #764ba2) !important;
    }
    
    body .btn-primary.btn-simple:hover {
        background-color: var(--custom-primary, #764ba2) !important;
        color: #ffffff !important;
    }

    body .btn-primary.btn-simple:hover a,
    body .btn-primary.btn-simple:hover i,
    body .btn-primary.btn-simple:hover span {
        color: #ffffff !important;
    }
    
    /* Cross-browser perfect vertical centering for text inputs */
    body input.form-control {
        height: 42px !important;
        padding: 8px 15px !important;
        line-height: 1.5 !important;
        font-size: 14px !important;
        border-radius: 8px !important;
    }
    
    /* Perfect vertical centering for select dropdowns */
    body select.form-control {
        height: 42px !important;
        padding: 0 15px !important;
        line-height: 40px !important;
        font-size: 14px !important;
        border-radius: 8px !important;
    }
    
    body textarea.form-control {
        height: auto !important;
        min-height: 80px;
    }

    /* Fix for Bootstrap Select Dropdowns */
    body .bootstrap-select > .btn {
        height: 42px !important;
        padding: 8px 15px !important;
        line-height: 1.5 !important;
        font-size: 14px !important;
        border-radius: 8px !important;
    }

    body .bootstrap-select .filter-option,
    body .bootstrap-select .filter-option-inner-inner {
        height: 40px !important;
        line-height: 40px !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Force all buttons in modals to be round (pill-shaped) */
    body .modal .btn {
        border-radius: 30px !important;
    }
</style>

</head>   <!-- include header -->

{{-- @if(Session::has('theme'))
<body class="theme-{{Session::get('theme')[0]}}">
@else
<body class="theme-blue">
@endif --}}
<body id="body" class="theme-blue">




@if(Session::has('menutheme'))
@if(Session::get('menutheme')[0] == "menu_dark")
<script>
var body = document.body;
body.classList.add("menu_dark");
 </script> 
@else
<script>
var body = document.body;
body.classList.remove("menu_dark");
</script>
 @endif
@endif 

 

