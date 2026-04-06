@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Edit Payment Voucher'])
@php
$data = json_decode($data, true);
$paymentvoucher = $data['paymentvoucher'];
$sites = $data['sites'];
$companies = $data['companies'];
$material_suppliers = $data['material_suppliers'];
$bill_parties = $data['bill_parties'];
$other_parties = $data['other_parties'];
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
         @if(checkmodulepermission(8,'can_edit') == 1)
            <div class="modal-body">
            <form method="post" action="{{url('/updateEditpaymentvouchers')}}" enctype="multipart/form-data">
               @csrf
                 <hr>
               <div class="row clearfix">
                  <div class="col-lg-3 col-md-3 col-sm-3">
                     <div class="form-group">                                   
                        <img height= "150" width="150" id="user_image" src="{{asset($paymentvoucher['image'])}}"  class="rounded-circle img-raised"> 
                        <input type="file" accept="Image/*"  name="image" onchange="document.getElementById('user_image').src = window.URL.createObjectURL(this.files[0])">            
                     </div>
                  </div>
                  <div class="col-lg-9 col-md-9 col-sm-9">
                     <div class="row clearfix">
                        <div class="col-lg-3 col-md-3 col-sm-3">
                           <div class="form-group">
                             <input type="hidden" name="id" value="{{$paymentvoucher['id']}}"/>
                              <label>Company</label>
                              <select name="company_id"  class="form-control show-tick" data-live-search="true" required>
                                <option value="" selected disabled >--Select Company--</option>
                          
                            @foreach($companies as $company)
                            @if($paymentvoucher['company_id'] == $company['id'])
                            <option selected value = "{{$company['id']}}">{{$company['name']}}</option>
                            @else
                            <option value = "{{$company['id']}}">{{$company['name']}}</option>
                            @endif
                            @endforeach
                            </select>
                           </div>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-3">
                           <div class="form-group">
                              <label>Site</label>
                              <select name="site_id"  class="form-control show-tick" data-live-search="true" required>
                                <option value="" selected disabled >--Select Site--</option>

                            @if ($entry_at_site == 'current')
                                    <option selected value="{{ $site_id }}">
                                       {{ getSiteDetailsById($site_id)->name }}
                                    </option>
                                    @else
                                    @foreach ($sites as $site)
                                    @if($paymentvoucher['site_id'] == $site['id'])
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
                              <label>Voucher Party</label>
                              <select name="party_id"  required  class="form-control show-tick" data-live-search="true">
                         
                           <optgroup label="Material Supplier">
                              @foreach($material_suppliers as $party)
                              @if($paymentvoucher['party_type'] == 'material' && $paymentvoucher['party_id'] == $party['id'])
                              <option selected value = "{{$party['id']}}||material">{{$party['name']}}</option>
                              @else
                              <option value = "{{$party['id']}}||material">{{$party['name']}}</option>
                              @endif
                              @endforeach
                           </optgroup>
                           <optgroup label="Bill Parties">
                              @foreach($bill_parties as $party)
                              @if($paymentvoucher['party_type'] == 'bill' && $paymentvoucher['party_id'] == $party['id'])
                              <option selected value = "{{$party['id']}}||bill">{{$party['name']}}</option>
                              @else
                              <option value = "{{$party['id']}}||bill">{{$party['name']}}</option>
                              @endif
                              @endforeach
                           </optgroup>
                           <optgroup label="Other Parties">
                              @foreach($other_parties as $party)
                              @if($paymentvoucher['party_type'] == 'other' && $paymentvoucher['party_id'] == $party['id'])
                              <option selected value = "{{$party['id']}}||other">{{$party['name']}}</option>
                              @else
                              <option value = "{{$party['id']}}||other">{{$party['name']}}</option>
                              @endif
                              @endforeach
                           </optgroup>
                           <optgroup label="Sites">
                              @foreach($sites as $party)
                              @if($paymentvoucher['party_type'] == 'site' && $paymentvoucher['party_id'] == $party['id'])
                              <option selected value = "{{$party['id']}}||site">{{$party['name']}}</option>
                              @else
                              <option value = "{{$party['id']}}||site">{{$party['name']}}</option>
                              @endif
                              @endforeach
                           </optgroup>
                       </select>

                           </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-3 col-sm-3">
                           <div class="form-group">
                              <label>Voucher No.</label>
                              <input type="text"  required class="form-control" value="{{$paymentvoucher['voucher_no']}}" name="voucher_no" placeholder="Enter The Voucher No.">
                            
                       </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-3 col-sm-3">
                           <div class="form-group">
                              <label>Amount</label>
                              <input type="number" placeholder="0.00" required class="form-control" value="{{$paymentvoucher['amount']}}" name="amount" min="0"  step="0.01" pattern="^\d+(?:\.\d{1,2})?$">
                           </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-3 col-sm-3">
                           <div class="form-group">
                              <label>Date</label>
                              <input type="date" required class="form-control" min="{{$min_date}}" max="{{$max_date}}" value="{{$paymentvoucher['date']}}" name="date" >
                           </div>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-3">
                           <div class="form-group">
                              <label>Payment Details</label>
                              <input type="text"   class="form-control" name="payment_details" value="{{$paymentvoucher['payment_details']}}" placeholder="Enter The Payment Details">
                           </div>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-3">
                           <div class="form-group">
                              <label>Remark</label>
                              <input type="text" class="form-control" name="remark"  value="{{$paymentvoucher['remark']}}" placeholder="Enter The Remark (If Any)">
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
            @else
            <div class="alert alert-danger"> You Don't Have Permission to Edit </div>
            @endif
         </div>
      </div>
   </div>
</div>
@endsection
