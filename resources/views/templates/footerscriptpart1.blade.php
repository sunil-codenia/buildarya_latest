<!-- Jquery Core Js --> 

<script >
    var r = document.querySelector(':root');
    r.style.setProperty('--custom-primary', "{{ is_array(Session::get('primary_color')) ? Session::get('primary_color')[0] : (Session::get('primary_color') ?? '#6f42c1') }}");
    r.style.setProperty('--custom-secondary', "{{ is_array(Session::get('secondry_color')) ? Session::get('secondry_color')[0] : (Session::get('secondry_color') ?? '#6f42c1') }}");
    r.style.setProperty('--custom-gradient-start', "{{ is_array(Session::get('gradient_start')) ? Session::get('gradient_start')[0] : (Session::get('gradient_start') ?? '#6f42c1') }}");
    r.style.setProperty('--custom-gradient-end', "{{ is_array(Session::get('gradient_end')) ? Session::get('gradient_end')[0] : (Session::get('gradient_end') ?? '#6f42c1') }}");
    document.getElementById('body').className.replace("theme-blue","");

    // document.querySelector('body').classList.remove('theme-blue');
    document.getElementById('body').classList.add("theme-custom");

    function enlargeImage(image) {
        console.log(image);
            var modalImg = document.getElementById("enlargeimage");
            $('#enlargeimagemodal').modal('show');
            modalImg.src = image;
        }
//lazy load 
document.addEventListener("DOMContentLoaded", function() {
  var lazyloadImages = document.querySelectorAll("img.lazy");    
  var lazyloadThrottleTimeout;
  
  function lazyload () {
    if(lazyloadThrottleTimeout) {
      clearTimeout(lazyloadThrottleTimeout);
    }    
    
    lazyloadThrottleTimeout = setTimeout(function() {
        var scrollTop = window.pageYOffset;
        lazyloadImages.forEach(function(img) {
            if(img.offsetTop < (window.innerHeight + scrollTop)) {
              img.src = img.dataset.src;
              img.classList.remove('lazy');
            }
        });
        if(lazyloadImages.length == 0) { 
          document.removeEventListener("scroll", lazyload);
          window.removeEventListener("resize", lazyload);
          window.removeEventListener("orientationChange", lazyload);
        }
    }, 20);
  }
  
  document.addEventListener("scroll", lazyload);
  window.addEventListener("resize", lazyload);
  window.addEventListener("orientationChange", lazyload);
});
</script>
<script src="{{asset('/bundles/libscripts.bundle.js')}}"></script> <!-- Lib Scripts Plugin Js ( jquery.v3.2.1, Bootstrap4 js) --> 

<script src="{{asset('/bundles/vendorscripts.bundle.js')}}"></script> <!-- slimscroll, waves Scripts Plugin Js -->
<script src="{{asset('/bundles/morrisscripts.bundle.js')}}"></script><!-- Morris Plugin Js -->
<script src="{{asset('/bundles/jvectormap.bundle.js')}}"></script> <!-- JVectorMap Plugin Js -->
<script src="{{asset('/bundles/knob.bundle.js')}}"></script> <!-- Jquery Knob Plugin Js -->
<script src="{{asset('/bundles/countTo.bundle.js')}}"></script> <!-- Jquery CountTo Plugin Js -->
<script src="{{asset('/bundles/sparkline.bundle.js')}}"></script> <!-- Sparkline Plugin Js -->
<script src="{{asset('/bundles/mainscripts.bundle.js')}}"></script>

