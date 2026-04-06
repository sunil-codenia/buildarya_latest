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
                        <div class="form-group">   
                            <input type="hidden" name="id" value="{{$materialentry['id']}}"/>                                
                           <img height= "150" width="150" id="user_image" src="{{asset($materialentry['image'])}}"  class="rounded-circle img-raised"> 
                           <input type="file" accept="Image/*" name="image" onchange="document.getElementById('user_image').src = window.URL.createObjectURL(this.files[0])">            
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
                               @if($materialentry['supplier'] == $supplier['id'])
                               <option selected value = "{{$supplier['id']}}">{{$supplier['name']}}</option>
                               @else
                               <option value = "{{$supplier['id']}}">{{$supplier['name']}}</option>
                               @endif
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

@endsection
