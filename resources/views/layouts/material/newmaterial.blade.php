@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'New Material Entry'])
@php
$data = json_decode($data, true);
$sites = $data['sites'];
$suppliers = $data['material_supplier'];
$materials = $data['materials'];
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

         <div class="modal-content">
         @if(checkmodulepermission(3,'can_add') == 1)
         
            <div class="modal-body">
               <form method="post" action="{{url('/addnewmaterial')}}" enctype="multipart/form-data">
                  @csrf
                    <hr>
                  <div class="row clearfix">
                     <div class="col-lg-3 col-md-3 col-sm-3">
                        <div class="form-group">                                   
                           <img height= "150" width="150" id="user_image" src="{{asset('/images/expense.png')}}"  class="rounded-circle img-raised"> 
                           <input type="file" accept="Image/*" name="image[]" onchange="document.getElementById('user_image').src = window.URL.createObjectURL(this.files[0])">            
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
                               <option value = "{{$supplier['id']}}">{{$supplier['name']}}</option>
                               @endforeach
                           </select>
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Material</label>
                                 <select name="material_id[]"   class="form-control show-tick" data-live-search="true" required>
                                   <option value="" selected disabled >--Select Material--</option>
                               @foreach($materials as $material)
                               <option value = "{{$material['id']}}">{{$material['name']}}</option>
                               @endforeach
                           </select>
                          </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Unit</label>
                                 <select name="unit[]"   class="form-control show-tick" data-live-search="true" required>
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
                                 <input type="number" placeholder="0.00" required class="form-control" name="qty[]" step="0.01" pattern="^\d+(?:\.\d{1,2})?$">
                               
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

var count = 1;
$('#addrow').click(function() {
  
       count++;
       var site_html = '<select name="site_id[]" id="site_id_'+count+'" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Site--</option> @if($entry_at_site == "current")<option selected value="{{ $site_id }}">{{ getSiteDetailsById($site_id)->name }}</option>@else @foreach ($sites as $site)<option value = "{{ $site['id'] }}">{{ $site['name'] }}</option>@endforeach @endif</select>';
var supplier_html = '<select name="supplier[]" id="supplier_id_'+count+'" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Supplier--</option>@foreach($suppliers as $supplier)<option value = "{{$supplier['id']}}">{{$supplier['name']}}</option>@endforeach</select>';
var material_html = '<select name="material_id[]" id="material_id_'+count+'"  class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Material--</option>@foreach($materials as $material)<option value = "{{$material['id']}}">{{$material['name']}}</option>@endforeach</select>';
var unit_html = '<select name="unit[]" id="unit_id_'+count+'" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Unit--</option>@foreach($units as $unit)<option value = "{{$unit['id']}}">{{$unit['name']}}</option>@endforeach</select>';
       var result = '<div id="row_'+count+'"><hr><div class="row clearfix"><div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><img height= "150" width="150" id="'+count+'" src='+"{{asset('/images/expense.png')}}"+'  class="rounded-circle img-raised"> <input type="file" accept="Image/*" name="image[]" onchange="document.getElementById('+count+').src = window.URL.createObjectURL(this.files[0])"></div></div>';
       result += '<div class="col-lg-9 col-md-9 col-sm-9"><div class="row clearfix"><div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Site</label>'+site_html+'</div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Supplier</label>'+supplier_html+'</div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Material</label>'+material_html+'</div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Unit</label>'+unit_html+'</div></div>';
         result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Quantity</label><input type="number" placeholder="0.00" required class="form-control" name="qty[]" min="0"  step="0.01" pattern="^\d+(?:\.\d{1,2})?$"></div></div>';
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
   function deleterow(id) {
       $('#row_'+id).remove();
       }
</script>
@endsection