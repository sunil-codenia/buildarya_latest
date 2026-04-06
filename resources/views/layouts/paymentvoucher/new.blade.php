@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'New Payment Voucher'])
    @php
        $data = json_decode($data, true);
        $sites = $data['sites'];
        $official_sites = $data['official_sites'];
        $companies = $data['companies'];
        $material_suppliers = $data['material_suppliers'];
        $bill_parties = $data['bill_parties'];
        $other_parties = $data['other_parties'];

        $site_id = session()->get('site_id');
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
                @if (checkmodulepermission(8, 'can_add') == 1)
                    <div class="modal-content">
                        <div class="modal-body">
                            <form method="post" action="{{ url('/addnewpaymentvouchers') }}" enctype="multipart/form-data">
                                @csrf
                                <hr>
                                <div class="row clearfix">
                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <img height= "150" width="150" id="user_image"
                                                src="{{ asset('/images/expense.png') }}" class="rounded-circle img-raised">
                                            <input type="file" accept="Image/*" name="image[]"
                                                onchange="document.getElementById('user_image').src = window.URL.createObjectURL(this.files[0])">
                                        </div>
                                    </div>
                                    <div class="col-lg-9 col-md-9 col-sm-9">
                                        <div class="row clearfix">
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Company</label>
                                                    <select name="company_id[]" class="form-control show-tick"
                                                        data-live-search="true" required>
                                                        <option value="" selected disabled>--Select Company--</option>

                                                        @foreach ($companies as $company)
                                                            <option value = "{{ $company['id'] }}">{{ $company['name'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Voucher Party</label>
                                                    <select name="party_id[]" id="party_id_1" required onchange="update_sitesoption(1)"
                                                        class="form-control show-tick " data-live-search="true">

                                                        <optgroup label="Material Supplier">
                                                            @foreach ($material_suppliers as $party)
                                                                <option value = "{{ $party['id'] }}||material">
                                                                    {{ $party['name'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                        <optgroup label="Bill Parties">
                                                            @foreach ($bill_parties as $party)
                                                                <option value = "{{ $party['id'] }}||bill">
                                                                    {{ $party['name'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                        <optgroup label="Other Parties">
                                                            @foreach ($other_parties as $party)
                                                                <option value = "{{ $party['id'] }}||other">
                                                                    {{ $party['name'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                        <optgroup label="Sites">
                                                            @foreach ($sites as $site)
                                                                <option value = "{{ $site['id'] }}||site||{{$site['sites_type'][0]}}">
                                                                    {{ $site['name'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    </select>

                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Site</label>
                                                    <select name="site_id[]" id="site_id_1" data-live-search="true" class="form-control form-select"
                                                       required>
                                                   <option value="" selected disabled>--Select Voucher Party First--</option>
{{--                                                        @if ($entry_at_site == 'current')
                                                            <option selected value="{{ $site_id }}">
                                                                {{ getSiteDetailsById($site_id)->name }}
                                                            </option>
                                                        @else
                                                            @foreach ($sites as $site)
                                                                <option value="{{ $site['id'] }}">{{ $site['name'] }}
                                                                </option>
                                                            @endforeach
                                                        @endif --}}

                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Voucher No.</label>
                                                    <input type="text" required class="form-control"
                                                        value = "{{ getLatestPaymentVoucherNo() }}" name="voucher_no[]"
                                                        placeholder="Enter The Voucher No.">

                                                </div>
                                            </div>

                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Amount</label>
                                                    <input type="number" placeholder="0.00" required class="form-control"
                                                        name="amount[]" min="0" step="0.01"
                                                        pattern="^\d+(?:\.\d{1,2})?$">
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
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Payment Details</label>
                                                    <input type="text" class="form-control" name="payment_details[]"
                                                        placeholder="Enter The Payment Details">
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Remark</label>
                                                    <input type="text" class="form-control" name="remark[]"
                                                        placeholder="Enter The Remark (If Any)">
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
                                            <button type="button" id="addrow"
                                                class="btn btn-primary btn-simple btn-round waves-effect"><i
                                                    class='zmdi zmdi-plus' style='color: white;'></i></button>
                                            <button type="submit"
                                                class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="alert alert-danger"> You Don't Have Permission to Add Permission </div>
                @endif
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script type="text/javascript">
        var count = 1;
        $('#addrow').click(function() {
            count++;
            var site_html = '<select name="site_id[]" id="site_id_' + count +
                '" class="form-control show-tick"  data-live-search="true"   required><option selected disabled value="">Select Voucher Party First</option></select>';
            var party_html = '<select name="party_id[]" onchange="update_sitesoption('+count+')" id="party_id_' + count +
                '"  class="form-control show-tick"    data-live-search="true" required><option value="" selected disabled >--Select Party--</option><optgroup label="Material Supplier">@foreach ($material_suppliers as $party)<option value = "{{ $party['id'] }}||material">{{ $party['name'] }}</option>@endforeach</optgroup><optgroup label="Bill Parties">@foreach ($bill_parties as $party)<option value = "{{ $party['id'] }}||bill">{{ $party['name'] }}</option>@endforeach</optgroup><optgroup label="Other Parties">@foreach ($other_parties as $party)<option value = "{{ $party['id'] }}||other">{{ $party['name'] }}</option>@endforeach</optgroup><optgroup label="Sites">@foreach ($sites as $site)<option value = "{{ $site['id'] }}||site||{{$site['sites_type'][0]}}">{{ $site['name'] }}</option>@endforeach</optgroup></select>';
            var company_html = '<select name="company_id[]" id="company_id_' + count +
                '"  class="form-control show-tick"    data-live-search="true" required><option value="" selected disabled >--Select Company--</option>@foreach ($companies as $company)<option value = "{{ $company['id'] }}">{{ $company['name'] }}</option>@endforeach</select>';

            var result = '<div  id="row_' + count +
                '"><hr><div class="row clearfix"><div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><img height= "150" width="150" id="' +
                count + '" src=' + "{{ asset('/images/expense.png') }}" +
                '  class="rounded-circle img-raised"><input type="file" accept="Image/*" name="image[]" onchange="document.getElementById(' +
                count + ').src = window.URL.createObjectURL(this.files[0])"></div></div>';
            result +=
                '<div class="col-lg-9 col-md-9 col-sm-9"><div class="row clearfix"><div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Company</label>' +
                company_html + '</div></div>';

            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Voucher Party</label>' +
                party_html + '</div></div>';
                result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Site</label>' +
                  site_html + '</div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Voucher No.</label><input type="text"  required class="form-control" name="voucher_no[]" placeholder="Enter The Voucher No."></div></div>';
            result +=
                '<div class="col-lg-2 col-md-2 col-sm-2"><div class="form-group"><label>Amount</label><input type="number" placeholder="0.00" required class="form-control" name="amount[]" min="0"  step="0.01" pattern="^\d+(?:\.\d{1,2})?$"></div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Date</label><input type="date" min="{{ $min_date }}" max="{{ $max_date }}" value="{{ $today }}" required class="form-control" name="date[]"></div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Payment Details</label><input type="text"   class="form-control" name="payment_details[]" placeholder="Enter The Payment Details"></div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Remark</label><input type="text" class="form-control" name="remark[]" placeholder="Enter The Remark (If Any)"></div></div>';
            result +=
                '<div class="col-lg-1 col-md-1 col-sm-1"><div class="form-group"><br><button type="button" onclick="deleterow(' +
                count +
                ')" class="btn btn-primary btn-simple btn-round waves-effect"><i class="zmdi zmdi-minus"  style="color: white;"></i></button></div></div></div></div></div></div>';
            $('#rowData').append(result);
            $("#site_id_" + count).selectpicker({
                liveSearch: true
            });
            $("#party_id_" + count).selectpicker({
                liveSearch: true,
            });
            $("#company_id_" + count).selectpicker({
                liveSearch: true
            });
        });

        function deleterow(id) {
            $('#row_' + id).remove();
        }
        function update_sitesoption(item_count){
         var type = $('#party_id_'+item_count).val().split('||');
         if(type[1] == 'site' && type[2] == 'W'){
            var c_site_id = type[0];
            var c_site_name = $('#party_id_'+item_count).find(":selected").text();
            console.log(c_site_id,c_site_name);
            var site_html = '<option value="" selected disabled >--Select Site--</option>@if ($entry_at_site == 'current')<option selected value="{{ $site_id }}">{{ getSiteDetailsById($site_id)->name }}</option>@else <option selected value="'+c_site_id+'">'+c_site_name+'</option>  @foreach ($official_sites as $site)<option value = "{{ $site['id'] }}">{{ $site['name'] }}</option>@endforeach <option  value="{{ $site_id }}">{{ getSiteDetailsById($site_id)->name }}</option> @endif';
         }else{
            var site_html = '<option value="" selected disabled >--Select Site--</option>@if ($entry_at_site == 'current')<option selected value="{{ $site_id }}">{{ getSiteDetailsById($site_id)->name }}</option>@else @foreach ($sites as $site)<option value = "{{ $site['id'] }}">{{ $site['name'] }}</option>@endforeach @endif';
         }
         $('#site_id_'+item_count).empty(); 
         $('#site_id_'+item_count).append(site_html).val(null).trigger('change');
         $("#site_id_" + item_count).selectpicker('refresh');
        }
    </script>
@endsection
