@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Edit Material Entry'])
@php
$data = json_decode($data, true);
$sites = $data['sites'];
$suppliers = $data['material_supplier'];
$materials = $data['materials'];
$materialentry = $data['materialentry'];
$units = $data['units'];
$site_id = session()->get("site_id");
$role_details = getRoleDetailsById(session()->get('role'));
$entry_at_site = $role_details->entry_at_site;
$add_duration = session()->get('add_duration');
$duration = getdurationdates($add_duration);
$today = substr($duration['today'], 0, 10);
$min_date = substr($duration['min'], 0, 10);
$max_date = substr($duration['max'], 0, 10);

@endphp

<div class="row clearfix">
   <div class="col-md-12 col-sm-12 col-xs-12">
      <div class="card project_list">
      @if(checkmodulepermission(3,'can_edit') == 1)
         <div class="modal-content">
            <div class="modal-body">
               <form method="post" action="{{url('/updatematerialEntry')}}" enctype="multipart/form-data">
                  @csrf
                    <hr>
                  <div class="row clearfix">
                      <div class="col-lg-3 col-md-3 col-sm-3">
                         <div class="form-group" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">   
                             <input type="hidden" name="id" value="{{$materialentry['id']}}"/>                                
                             @for ($i = 2; $i <= 5; $i++)
                                <input type="hidden" name="clear_image{{ $i }}" id="clear_image{{ $i }}" value="0"/>
                             @endfor
                             @php
                                 $imgPaths = !empty($materialentry['image']) ? explode(',', $materialentry['image']) : [];
                                 $displayImages = [];
                                 $displayImages[1] = !empty($imgPaths[0]) ? $imgPaths[0] : '/images/expense.png';
                                 $displayImages[2] = !empty($imgPaths[1]) ? $imgPaths[1] : (!empty($materialentry['image2']) ? $materialentry['image2'] : '/images/expense.png');
                                 $displayImages[3] = !empty($imgPaths[2]) ? $imgPaths[2] : (!empty($materialentry['image3']) ? $materialentry['image3'] : '/images/expense.png');
                                 $displayImages[4] = !empty($imgPaths[3]) ? $imgPaths[3] : (!empty($materialentry['image4']) ? $materialentry['image4'] : '/images/expense.png');
                                 $displayImages[5] = !empty($imgPaths[4]) ? $imgPaths[4] : (!empty($materialentry['image5']) ? $materialentry['image5'] : '/images/expense.png');
                              @endphp
                              <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; align-items: center;">
                                 <div style="text-align: center;">
                                    <img height="80" width="80" id="user_image" src="{{ asset($displayImages[1]) }}" class="rounded-circle img-raised"> 
                                    <label style="font-size: 11px; display: block; margin-top: 5px; margin-bottom: 0;">Image 1</label>
                                    <input type="file" accept="Image/*" name="image" onchange="document.getElementById('user_image').src = window.URL.createObjectURL(this.files[0])" style="width: 110px; font-size: 10px;">            
                                 </div>
                                 
                                 @for ($i = 2; $i <= 5; $i++)
                                    @php
                                       $imgSrc = $displayImages[$i];
                                       $hasImage = !empty($imgSrc) && $imgSrc != '/images/expense.png' && $imgSrc != 'images/expense.png';
                                    @endphp
                                    <div id="image{{ $i }}_container" style="display: {{ $hasImage ? 'block' : 'none' }}; text-align: center; position: relative;">
                                       <img height="80" width="80" id="user_image{{ $i }}" src="{{ asset($imgSrc) }}" class="rounded-circle img-raised">
                                       <button type="button" class="btn btn-danger btn-xs btn-round" onclick="toggleEditImage({{ $i }}, false)" style="position: absolute; top: 0; right: 0; padding: 2px 5px; min-width: auto; margin: 0;"><i class="zmdi zmdi-minus" style="color: white !important;"></i></button>
                                       <label style="font-size: 11px; display: block; margin-top: 5px; margin-bottom: 0;">Image {{ $i }}</label>
                                       <input type="file" accept="Image/*" name="image{{ $i === 2 ? '2' : $i }}" id="image{{ $i }}_input" onchange="document.getElementById('user_image{{ $i }}').src = window.URL.createObjectURL(this.files[0])" style="width: 110px; font-size: 10px;">
                                    </div>
                                 @endfor

                                 <div id="add_img_btn_container" style="display: flex; align-items: center; justify-content: center;">
                                    <button type="button" class="btn btn-primary btn-xs btn-round" onclick="addEditImageInput()" style="padding: 5px 8px; min-width: auto;"><i class="zmdi zmdi-plus" style="color: white !important;"></i></button>
                                 </div>
                              </div>
                         </div>
                      </div>
                     <div class="col-lg-9 col-md-9 col-sm-9">
                        <div class="row clearfix">
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Site</label>
                                 <select name="site_id"   class="form-control show-tick" data-live-search="true" required>
                                   <option value="" selected disabled >--Select Site--</option>

                               @if ($entry_at_site == 'current')
                                    <option selected value="{{ $site_id }}">
                                       {{ getSiteDetailsById($site_id)->name }}
                                    </option>
                                    @else
                                    @foreach ($sites as $site)
                                    @if($materialentry['site_id'] == $site['id'])
                                    <option selected value="{{$site['id']}}">{{$site['name']}}</option>
                                    @else
                                    <option value="{{$site['id']}}">{{$site['name']}}</option>
                                    @endif
                                    @endforeach
                                    @endif

                           </select>
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Supplier</label>
                                  <select name="supplier"  class="form-control show-tick" data-live-search="true" required>
                                    <option value="" selected disabled >--Select Supplier--</option>
                                @foreach($suppliers as $supplier)
                                @php
                                  $isPending = ($supplier['status'] ?? '') == 'Pending';
                                  $isSelected = $materialentry['supplier'] == $supplier['id'];
                                @endphp
                                <option value="{{$supplier['id']}}" {{ $isSelected ? 'selected' : '' }} {{ $isPending && !$isSelected ? 'disabled' : '' }}>
                                    {{$supplier['name']}}{{ $isPending ? ' (Pending Activation)' : '' }}
                                </option>
                                @endforeach
                            </select>
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Material</label>
                                 <select name="material_id"   class="form-control show-tick" data-live-search="true" required>
                                   <option value="" selected disabled >--Select Material--</option>
                               @foreach($materials as $material)
                               @if($materialentry['material_id'] == $material['id'])
                              <option selected value = "{{$material['id']}}">{{$material['name']}}</option>
                               @else
                               <option value = "{{$material['id']}}">{{$material['name']}}</option>

                               @endif
                               @endforeach
                           </select>
                          </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Unit</label>
                                 <select name="unit"   class="form-control show-tick" data-live-search="true" required>
                                   <option value="" selected disabled >--Select Unit--</option>
                               @foreach($units as $unit)
                               @if($materialentry['unit'] == $unit['id'])
                               <option selected value = "{{$unit['id']}}">{{$unit['name']}}</option>
                               @else
                               <option value = "{{$unit['id']}}">{{$unit['name']}}</option>

                               @endif
                               @endforeach
                           </select>
                          </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Quantity</label>
                                 <input type="number" placeholder="0.00" required value={{$materialentry['qty']}} class="form-control" name="qty" min="0"  step="0.01" pattern="^\d+(?:\.\d{1,2})?$">
                               
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Vehicle</label>
                                 <input type="text"  required class="form-control" name="vehical" value={{$materialentry['vehical']}} placeholder="Enter The Vehicle No">
   
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Remark</label>
                                 <input type="text" class="form-control" name="remark" value={{$materialentry['remark']}} placeholder="Enter The Remark (If Any)">
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Date</label>
                                 <input type="date" required class="form-control" min="{{$min_date}}" max="{{$max_date}}" value="{{$materialentry['date']}}" name="date" >
                              </div>
                           </div>
                           <div class="col-lg-1 col-md-1 col-sm-1">
                              <div class="form-group">
                                 <br>
                              </div>
                           </div>
                        </div>
                     </div>
                    </div>
                  <hr>
                  <div class="row clearfix">
                   <div class="col-lg-9 col-md-9 col-sm-9">
                   </div>
                   <div class="col-lg-3 col-md-3 col-sm-3">
                      <div class="form-group">
                         <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>Update</a></button>
                      </div>
                   </div>
                </div>
               </form>
            </div>
         </div>
         @else
         <div class="alert alert-danger">You Don't Have Permission to Edit / Update </div>
         @endif
      </div>
   </div>
</div>

<script>
function addEditImageInput() {
    for (var i = 2; i <= 5; i++) {
        var container = document.getElementById('image' + i + '_container');
        if (container && container.style.display === 'none') {
            container.style.display = 'block';
            document.getElementById('clear_image' + i).value = '0';
            break;
        }
    }
    updateEditAddButtonVisibility();
}

function toggleEditImage(imgIndex, show) {
    if (show) {
        document.getElementById('image' + imgIndex + '_container').style.display = 'block';
        document.getElementById('clear_image' + imgIndex).value = '0';
    } else {
        document.getElementById('image' + imgIndex + '_container').style.display = 'none';
        document.getElementById('clear_image' + imgIndex).value = '1';
        // Clear input file
        var input = document.getElementById('image' + imgIndex + '_input');
        if (input) {
            input.value = '';
        }
        // Reset preview
        document.getElementById('user_image' + imgIndex).src = "{{asset('/images/expense.png')}}";
    }
    updateEditAddButtonVisibility();
}

function updateEditAddButtonVisibility() {
    var hasHidden = false;
    for (var i = 2; i <= 5; i++) {
        var container = document.getElementById('image' + i + '_container');
        if (container && container.style.display === 'none') {
            hasHidden = true;
            break;
        }
    }
    var btn = document.getElementById('add_img_btn_container');
    if (btn) {
        btn.style.display = hasHidden ? 'flex' : 'none';
    }
}

document.addEventListener("DOMContentLoaded", function() {
    updateEditAddButtonVisibility();
});
</script>
@endsection
