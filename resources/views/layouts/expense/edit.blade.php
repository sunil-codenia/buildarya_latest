@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Edit Expense Entry'])
@php
$data = json_decode($data, true);
$expense = $data['expense'];
$sites = $data['sites'];
$parties = $data['expense_party'];
$bill_parties = $data['bill_party'];
$heads = $data['expense_head'];
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
            <div class="modal-body">
               @if(checkmodulepermission(2,'can_edit') == 1)
               <form method="post" action="{{url('/updateEditExpenses')}}" enctype="multipart/form-data">
                  @csrf
                  <hr>
                  <div class="row clearfix">
                     <div class="col-lg-3 col-md-3 col-sm-3">
                        <div class="form-group">
                           <img height="150" width="150" id="user_image" src="{{asset($expense['image'])}}" class="rounded-circle img-raised">
                           <input type="file" accept="Image/*" name="image" onchange="document.getElementById('user_image').src = window.URL.createObjectURL(this.files[0])">
                        </div>
                     </div>
                     <div class="col-lg-9 col-md-9 col-sm-9">
                        <div class="row clearfix">
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Site</label>
                                 <input type="hidden" name="id" value="{{$expense['id']}}" />
                                 <select name="site_id" class="form-control show-tick" data-live-search="true" required>
                                    <option value="" selected disabled>--Select Site--</option>

                                    @if ($entry_at_site == 'current')
                                    <option selected value="{{ $site_id }}">
                                       {{ getSiteDetailsById($site_id)->name }}
                                    </option>
                                    @else
                                    @foreach ($sites as $site)
                                    @if($expense['site_id'] == $site['id'])
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
                                 <label>Expense Party</label>
                                 <select name="party_id" class="form-control show-tick" data-live-search="true" required>
                                    <option disabled>--Expense Parties--</option>
                                    @php
                                    $party_id = $expense['party_id']."||".$expense['party_type'];
                                    @endphp
                                    @foreach($parties as $party)
                                    @if($party_id == $party['id']."||expense")
                                    <option selected value="{{$party['id']}}||expense">{{$party['name']}}</option>
                                    @else
                                    <option value="{{$party['id']}}||expense">{{$party['name']}}</option>
                                    @endif
                                    @endforeach
                                    <option disabled>--Bill Parties--</option>
                                    @foreach($bill_parties as $party)
                                    @if($party_id == $party['id']."||bill")
                                    <option selected value="{{$party['id']}}||bill">{{$party['name']}}</option>
                                    @else
                                    <option value="{{$party['id']}}||bill">{{$party['name']}}</option>
                                    @endif
                                    @endforeach
                                 </select>
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Expense Head</label>
                                 <select name="head_id" class="form-control show-tick" data-live-search="true" required>
                                    <option value="" selected disabled>--Select Head--</option>
                                    @foreach($heads as $head)
                                    @if($head['id'] == $expense['head_id'])
                                    <option selected value="{{$head['id']}}">{{$head['name']}}</option>
                                    @else
                                    <option value="{{$head['id']}}">{{$head['name']}}</option>
                                    @endif
                                    @endforeach
                                 </select>
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Particular</label>
                                 <input type="text" required class="form-control" name="particular" value="{{$expense['particular']}}" placeholder="Enter The Particular Item">
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Amount</label>
                                 <input type="number" placeholder="0.00" required class="form-control" name="amount" min="0" value="{{$expense['amount']}}" step="0.01" pattern="^\d+(?:\.\d{1,2})?$">
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Remark</label>
                                 <input type="text" class="form-control" name="remark" value="{{$expense['remark']}}" placeholder="Enter The Remark (If Any)">
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
                              <div class="form-group">
                                 <label>Date</label>
                                 <input type="date" required class="form-control" min="{{$min_date}}" max="{{$max_date}}" value="{{$expense['date']}}" name="date">
                              </div>
                           </div>
                           <div class="col-lg-3 col-md-3 col-sm-3">
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
                           <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>Update</a></button>
                        </div>
                     </div>
                  </div>
               </form>
               @endif
            </div>
         </div>
      </div>
   </div>
</div>
@endsection