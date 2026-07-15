@include('templates.header')
  
   <!-- Page Loader -->
<!-- Top Bar -->
@include('templates.topbar')
<!-- Overlay For Sidebars -->
@include('templates.sidebar')
@include('sweetalert::alert')
<!-- Chat-launcher -->


   <!-- Main Content -->
   <section class="content home">
      @if ($errors->any())

      <div class="alert alert-danger">

         <ul>
              @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
              @endforeach
          </ul>
      </div>
   @endif
      @yield('content')
   </section>

  

@if(!Session::has('key'))
@php
header("Location: " . URL::to('/logout'), true, 302);
exit();
@endphp
@endif
@yield('models')
<div class="modal fade" id="logoutmodal" tabindex="-1" role="dialog" style="padding: 150px; magin:100px;">
   <div class="modal-dialog modal-sm" role="document">
       <div class="modal-content text-center">
           <div class="modal-header">
               <h4 class="title" id="smallModalLabel">Are You Sure!</h4>
           </div>
           <div class="modal-body">Do You Really Want To Logout!</div>
           <div class="modal-footer">
               <a href="{{url('/logout')}}" class="btn btn-danger btn-round waves-effect">Logout</a>
               <button type="button" class="btn btn-neutral btn-round  waves-effect" data-dismiss="modal">Cancel</button>
           </div>
       </div>
   </div>
</div>

@include('templates.modal')
@include('templates.upgrade_modal')
@include('templates.footerscriptpart1')

@yield('chart_scripts');
@include('templates.footer')
<section >

    @yield('scripts');
    </section>
</body>
</html>