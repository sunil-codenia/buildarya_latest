@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'New Material Entry'])
@php
$data = json_decode($data, true);
$sites = $data['sites'];
$suppliers = $data['material_supplier'];
$materials = $data['materials'];
$units = $data['units'];
$conversion_format = $data['conversion_format'] ?? [];
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

         <div class="modal-content">
         @if(checkmodulepermission(3,'can_add') == 1)
         
            <div class="modal-body">
               <form method="post" action="{{url('/addnewmaterial')}}" enctype="multipart/form-data">
                  @csrf
                    <hr>
                  <div class="row clearfix">
                      <div class="col-lg-3 col-md-3 col-sm-3">
                         <div class="form-group" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">                                   
                            <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; align-items: center;">
                               <div style="text-align: center;">
                                  <img height="80" width="80" id="user_image_1" src="{{asset('/images/expense.png')}}" class="rounded-circle img-raised"> 
                                  <label style="font-size: 11px; display: block; margin-top: 5px; margin-bottom: 0;">Image 1</label>
                                  <input type="file" accept="Image/*" name="image[]" onchange="document.getElementById('user_image_1').src = window.URL.createObjectURL(this.files[0])" style="width: 110px; font-size: 10px;">            
                               </div>
                               
                               @for ($i = 2; $i <= 5; $i++)
                               <div id="image{{ $i }}_container_1" style="display: none; text-align: center; position: relative;">
                                  <img height="80" width="80" id="user_image{{ $i }}_1" src="{{asset('/images/expense.png')}}" class="rounded-circle img-raised">
                                  <button type="button" class="btn btn-danger btn-xs btn-round" onclick="removeImageInput(1, {{ $i }})" style="position: absolute; top: 0; right: 0; padding: 2px 5px; min-width: auto; margin: 0;"><i class="zmdi zmdi-minus" style="color: white !important;"></i></button>
                                  <label style="font-size: 11px; display: block; margin-top: 5px; margin-bottom: 0;">Image {{ $i }}</label>
                                  <input type="file" accept="Image/*" name="image{{ $i }}[]" id="image{{ $i }}_input_1" onchange="document.getElementById('user_image{{ $i }}_1').src = window.URL.createObjectURL(this.files[0])" style="width: 110px; font-size: 10px;">
                               </div>
                               @endfor

                               <div id="add_img_btn_container_1" style="display: flex; align-items: center; justify-content: center;">
                                  <button type="button" class="btn btn-primary btn-xs btn-round" onclick="addImageInput(1)" style="padding: 5px 8px; min-width: auto;"><i class="zmdi zmdi-plus" style="color: white !important;"></i></button>
                               </div>
                            </div>
                         </div>
                      </div>
                     <div class="col-lg-9 col-md-9 col-sm-9">
                        <div class="row clearfix">
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Site</label>
                                 <select name="site_id[]"   class="form-control show-tick" data-live-search="true" required>
                                   <option value="" selected disabled >--Select Site--</option>
                                   @if ($entry_at_site == 'current')
                                                            <option selected value="{{ $site_id }}">
                                                                {{ getSiteDetailsById($site_id)->name }}
                                                            </option>
                                                        @else
                                                            @foreach ($sites as $site)
                                                                <option value="{{ $site['id'] }}">{{ $site['name'] }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                 
                           </select>
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Supplier</label>
                                  <select name="supplier[]"   class="form-control show-tick" data-live-search="true" required>
                                    <option value="" selected disabled >--Select Supplier--</option>
                                @foreach($suppliers as $supplier)
                                <option value = "{{$supplier['id']}}" {{ ($supplier['status'] ?? '') == 'Pending' ? 'disabled' : '' }}>
                                    {{$supplier['name']}}{{ ($supplier['status'] ?? '') == 'Pending' ? ' (Pending Activation)' : '' }}
                                </option>
                                @endforeach
                            </select>
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Material</label>
                                 <select name="material_id[]" id="material_id_1" onchange="convertQuantity(1)"  class="form-control show-tick" data-live-search="true" required>
                                   <option value="" selected disabled >--Select Material--</option>
                               @foreach($materials as $material)
                               <option value = "{{$material['id']}}" data-is-royalty="{{$material['is_royalty'] ?? 0}}">{{$material['name']}}</option>
                               @endforeach
                           </select>
                          </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Unit</label>
                                 <select name="unit[]" id="unit_id_1" onchange="convertQuantity(1)"  class="form-control show-tick" data-live-search="true" required>
                                   <option value="" selected disabled >--Select Unit--</option>
                               @foreach($units as $unit)
                               <option value = "{{$unit['id']}}">{{$unit['name']}}</option>
                               @endforeach
                           </select>
                          </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Quantity</label>
                                 <input type="number" placeholder="0.00" required class="form-control" name="qty[]" step="0.01" pattern="^\d+(?:\.\d{1,2})?$" onchange="convertQuantity(1)">
                               
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Converted Qty (Cubic M)</label>
                                 <input type="number" placeholder="0.00" readonly class="form-control" name="converted_qty[]" step="0.01" pattern="^\d+(?:\.\d{1,2})?$">
                               
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Vehicle</label>
                                 <input type="text"  required class="form-control" name="vehical[]" placeholder="Enter The Vehicle No">
   
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Remark</label>
                                 <input type="text" class="form-control" name="remark[]" placeholder="Enter The Remark (If Any)">
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Date</label>
                                 <input type="date" required class="form-control"
                                                        min="{{ $min_date }}" max="{{ $max_date }}"
                                                        value="{{ $today }}" name="date[]">
                                              
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
                  <div id="rowData">
                  </div>
                  <hr>
                  <div class="row clearfix">
                   <div class="col-lg-9 col-md-9 col-sm-9">
                   </div>
                   <div class="col-lg-3 col-md-3 col-sm-3">
                      <div class="form-group">
                         <button type="button" id="addrow" class="btn btn-primary btn-simple btn-round waves-effect"><i class='zmdi zmdi-plus'  style='color: white;'></i></button>
                         <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                      </div>
                   </div>
                </div>
               </form>
            </div>
            @else
            <div class="alert alert-danger"> You Don't Have Permission to Add </div>
            @endif
         </div>
      </div>
   </div>
</div>

@endsection
@section('scripts')
<script type="text/javascript">
// Get conversion rules from backend data
let conversionRules = @json($conversion_format ?? []);
let materialsData = @json($materials ?? []);

function convertQuantity(rowId) {
    let materialSelect = document.getElementById('material_id_' + rowId);
    let unitSelect = document.getElementById('unit_id_' + rowId);
    let qtyInput = document.querySelector('input[name="qty[]"][onchange*="' + rowId + '"]') || 
                   document.querySelectorAll('input[name="qty[]"]')[rowId - 1];
    let convertedQtyInput = document.querySelector('input[name="converted_qty[]"][readonly]');
    
    // Get all converted qty inputs and find the right one
    let allConvertedQtyInputs = document.querySelectorAll('input[name="converted_qty[]"]');
    let rowIndex = Array.from(document.querySelectorAll('input[name="qty[]"]')).indexOf(qtyInput);
    if (rowIndex >= 0 && allConvertedQtyInputs[rowIndex]) {
        convertedQtyInput = allConvertedQtyInputs[rowIndex];
    }
    
    if (!materialSelect || !unitSelect || !qtyInput || !convertedQtyInput) {
        return;
    }
    
    let materialId = materialSelect.value;
    let unitId = unitSelect.value;
    let qty = parseFloat(qtyInput.value) || 0;
    
    // Find material data to check is_royalty
    let selectedMaterialOption = materialSelect.querySelector('option[value="' + materialId + '"]');
    let isRoyalty = selectedMaterialOption ? selectedMaterialOption.getAttribute('data-is-royalty') : 0;
    
    convertedQtyInput.value = '';
    
    if (materialId && unitId && qty && isRoyalty == 1 && Array.isArray(conversionRules) && conversionRules.length > 0) {
        // Find conversion rule: from_unit (input unit) to cubic meter (id = 1 or find by name 'Cubic Meter')
        let rule = conversionRules.find(r => 
            String(r.material_id) === String(materialId) && 
            String(r.from_unit) === String(unitId) &&
            r.to_unit_name && r.to_unit_name.toLowerCase().includes('cubic')
        );
        
        if (rule && rule.conversion_factor) {
            let convertedQty = qty * parseFloat(rule.conversion_factor);
            convertedQtyInput.value = convertedQty.toFixed(2);
        }
    }
}

// Initialize data for materials with data-is-royalty attributes
document.addEventListener('DOMContentLoaded', function() {
    // Add data-is-royalty to initial material select if not already set
    if (Array.isArray(materialsData) && materialsData.length > 0) {
        let materialSelects = document.querySelectorAll('select[name="material_id[]"]');
        materialSelects.forEach((select, index) => {
            let options = select.querySelectorAll('option');
            options.forEach(opt => {
                if (opt.value) {
                    let material = materialsData.find(m => String(m.id) === opt.value);
                    if (material && !opt.getAttribute('data-is-royalty')) {
                        opt.setAttribute('data-is-royalty', material.is_royalty || 0);
                    }
                }
            });
        });
    }
});

var count = 1;
$('#addrow').click(function() {
  
       count++;
       var site_html = '<select name="site_id[]" id="site_id_'+count+'" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Site--</option> @if($entry_at_site == "current")<option selected value="{{ $site_id }}">{{ getSiteDetailsById($site_id)->name }}</option>@else @foreach ($sites as $site)<option value = "{{ $site['id'] }}">{{ $site['name'] }}</option>@endforeach @endif</select>';
var supplier_html = '<select name="supplier[]" id="supplier_id_'+count+'" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Supplier--</option>@foreach($suppliers as $supplier)<option value = "{{$supplier['id']}}" {{ ($supplier['status'] ?? "") == "Pending" ? "disabled" : "" }}>{{$supplier['name']}}{{ ($supplier['status'] ?? "") == "Pending" ? " (Pending Activation)" : "" }}</option>@endforeach</select>';
var material_html = '<select name="material_id[]" id="material_id_'+count+'" onchange="convertQuantity('+count+')" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Material--</option>@foreach($materials as $material)<option value = "{{$material['id']}}" data-is-royalty="{{$material['is_royalty'] ?? 0}}">{{$material['name']}}</option>@endforeach</select>';
var unit_html = '<select name="unit[]" id="unit_id_'+count+'" onchange="convertQuantity('+count+')" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Unit--</option>@foreach($units as $unit)<option value = "{{$unit['id']}}">{{$unit['name']}}</option>@endforeach</select>';
       var image_containers_html = '';
       for (var i = 2; i <= 5; i++) {
           image_containers_html += '<div id="image' + i + '_container_' + count + '" style="display: none; text-align: center; position: relative;">' +
               '<img height="80" width="80" id="img' + i + '_' + count + '" src="{{asset('/images/expense.png')}}" class="rounded-circle img-raised">' +
               '<button type="button" class="btn btn-danger btn-xs btn-round" onclick="removeImageInput(' + count + ', ' + i + ')" style="position: absolute; top: 0; right: 0; padding: 2px 5px; min-width: auto; margin: 0;"><i class="zmdi zmdi-minus" style="color: white !important;"></i></button>' +
               '<label style="font-size: 11px; display: block; margin-top: 5px; margin-bottom: 0;">Image ' + i + '</label>' +
               '<input type="file" accept="Image/*" name="image' + i + '[]" id="image' + i + '_input_' + count + '" onchange="document.getElementById(\'img' + i + '_' + count + '\').src = window.URL.createObjectURL(this.files[0])" style="width: 110px; font-size: 10px;">' +
           '</div>';
       }

       var result = '<div id="row_'+count+'"><hr><div class="row clearfix">' +
           '<div class="col-lg-3 col-md-3 col-sm-3">' +
               '<div class="form-group" style="display: flex; flex-direction: column; align-items: center; gap: 10px;">' +
                   '<div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; align-items: center;">' +
                       '<div style="text-align: center;">' +
                           '<img height="80" width="80" id="img1_'+count+'" src="{{asset('/images/expense.png')}}" class="rounded-circle img-raised">' +
                           '<label style="font-size: 11px; display: block; margin-top: 5px; margin-bottom: 0;">Image 1</label>' +
                           '<input type="file" accept="Image/*" name="image[]" onchange="document.getElementById(\'img1_'+count+'\').src = window.URL.createObjectURL(this.files[0])" style="width: 110px; font-size: 10px;">' +
                       '</div>' +
                       image_containers_html +
                       '<div id="add_img_btn_container_'+count+'" style="display: flex; align-items: center; justify-content: center;">' +
                           '<button type="button" class="btn btn-primary btn-xs btn-round" onclick="addImageInput('+count+')" style="padding: 5px 8px; min-width: auto;"><i class="zmdi zmdi-plus" style="color: white !important;"></i></button>' +
                       '</div>' +
                   '</div>' +
               '</div>' +
           '</div>';
       result += '<div class="col-lg-9 col-md-9 col-sm-9"><div class="row clearfix"><div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Site</label>'+site_html+'</div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Supplier</label>'+supplier_html+'</div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Material</label>'+material_html+'</div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Unit</label>'+unit_html+'</div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Quantity</label><input type="number" placeholder="0.00" required class="form-control" name="qty[]" min="0"  step="0.01" pattern="^\d+(?:\.\d{1,2})?$" onchange="convertQuantity('+count+')"></div></div>';
       result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Converted Qty (Cubic M)</label><input type="number" placeholder="0.00" readonly class="form-control" name="converted_qty[]" step="0.01" pattern="^\d+(?:\.\d{1,2})?$"></div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Vehicle</label><input type="text"  required class="form-control" name="vehical[]" placeholder="Enter The Vehicle No"></div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Remark</label><input type="text" class="form-control" name="remark[]" placeholder="Enter The Remark (If Any)"></div></div>';
         result += '<div class="col-lg-2 col-md-2 col-sm-2"><div class="form-group"><label>Date</label><input type="date" required class="form-control" min="{{$min_date}}" max="{{$max_date}}" value="{{$today}}" name="date[]" ></div></div>';
         result += '<div class="col-lg-1 col-md-1 col-sm-1"><div class="form-group"><br><button type="button" onclick="deleterow('+count+')" class="btn btn-primary btn-simple btn-round waves-effect"><i class="zmdi zmdi-minus"  style="color: white;"></i></button></div></div></div></div></div></div>';
         console.log(result);
       $('#rowData').append(result);
       $("#site_id_"+count).selectpicker({
         liveSearch: true
      });
      $("#supplier_id_"+count).selectpicker({
         liveSearch: true
      });
      $("#material_id_"+count).selectpicker({
         liveSearch: true
      });
      $("#unit_id_"+count).selectpicker({
         liveSearch: true
      });
     });      
    function addImageInput(rowId) {
        for (var i = 2; i <= 5; i++) {
            var container = document.getElementById('image' + i + '_container_' + rowId);
            if (container && container.style.display === 'none') {
                container.style.display = 'block';
                break;
            }
        }
        updateAddButtonVisibility(rowId);
    }

    function removeImageInput(rowId, imgIndex) {
        var container = document.getElementById('image' + imgIndex + '_container_' + rowId);
        if (container) {
            container.style.display = 'none';
            var input = document.getElementById('image' + imgIndex + '_input_' + rowId);
            if (input) {
                input.value = '';
            }
            var preview = document.getElementById('user_image' + imgIndex + '_' + rowId) || document.getElementById('img' + imgIndex + '_' + rowId);
            if (preview) {
                preview.src = "{{asset('/images/expense.png')}}";
            }
        }
        updateAddButtonVisibility(rowId);
    }

    function updateAddButtonVisibility(rowId) {
        var hasHidden = false;
        for (var i = 2; i <= 5; i++) {
            var container = document.getElementById('image' + i + '_container_' + rowId);
            if (container && container.style.display === 'none') {
                hasHidden = true;
                break;
            }
        }
        var btn = document.getElementById('add_img_btn_container_' + rowId);
        if (btn) {
            btn.style.display = hasHidden ? 'flex' : 'none';
        }
    }

    function deleterow(id) {
        $('#row_'+id).remove();
        }
</script>
@endsection