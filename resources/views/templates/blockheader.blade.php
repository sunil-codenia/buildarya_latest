<div class="block-header">
    <div class="row align-items-center">
        <div class="col-lg-7 col-md-6 col-sm-12">
            <div class="d-flex align-items-center">
                <div class="title-icon-box mr-3 d-none d-md-flex" style="width: 45px; height: 45px; background: #fff; border-radius: 12px; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                    <i class="zmdi zmdi-view-dashboard col-blue" style="font-size: 20px;"></i>
                </div>
                <div>
                    @isset($pagename)
                    <h2 class="mb-0">{{$pagename}}</h2>
                    @endisset
                    <small class="text-muted" style="font-weight: 500;">Welcome back to <b class="col-blue">Build Arya</b> System</small>
                </div>
            </div>
        </div>
        <div class="col-lg-5 col-md-6 col-sm-12">
            <ul class="breadcrumb float-md-right mb-0">
                <li class="breadcrumb-item"><a href="{{url('/dashboard')}}" class="col-blue"><i class="zmdi zmdi-home"></i> Home</a></li>
                <li class="breadcrumb-item active">
                    @isset($pagename)
                    {{$pagename}}
                    @endisset
                </li>
            </ul>
        </div>
    </div>
</div>