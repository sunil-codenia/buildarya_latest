@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'New Expense Entry'])
    @php
        $data = json_decode($data, true);
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
                    @if (checkmodulepermission(2, 'can_add') == 1)
                        <div class="modal-body">

                            <form method="post" action="{{ url('/addnewExpenses') }}" enctype="multipart/form-data">
                                @csrf
                                <hr>
                                <div class="row clearfix">
                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <img height="150" width="150" id="user_image"
                                                src="{{ asset('/images/expense.png') }}" class="rounded-circle img-raised">
                                            <input type="file" accept="Image/*" name="image[]"
                                                onchange="document.getElementById('user_image').src = window.URL.createObjectURL(this.files[0])">
                                        </div>
                                    </div>
                                    <div class="col-lg-9 col-md-9 col-sm-9">
                                        <div class="row clearfix">
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Site</label>
                                                    <select name="site_id[]" class="form-control show-tick"
                                                        data-live-search="true" required>
                                                        <option value="" selected disabled>--Select Site--</option>
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
                                                    <label>Expense Party</label>
                                                    <select name="party_id[]" class="form-control show-tick"
                                                        data-live-search="true" required>
                                                        <option disabled>--Expense Parties--</option>
                                                        @foreach ($parties as $party)
                                                            <option value="{{ $party['id'] }}||expense">
                                                                {{ $party['name'] }}</option>
                                                        @endforeach
                                                        <option disabled>--Bill Parties--</option>
                                                        @foreach ($bill_parties as $party)
                                                            <option value="{{ $party['id'] }}||bill">{{ $party['name'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Cost Category</label>
                                                    <select name="head_id[]" class="form-control show-tick"
                                                        data-live-search="true" required>
                                                        <option value="" selected disabled>--Select Head--</option>
                                                        @foreach ($heads as $head)
                                                            <option value="{{ $head['id'] }}">{{ $head['name'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Particular</label>
                                                    <input type="text" required class="form-control" name="particular[]"
                                                        placeholder="Enter The Particular Item">
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
                                                    <label>Remark</label>
                                                    <input type="text" class="form-control" name="remark[]"
                                                        placeholder="Enter The Remark (If Any)">
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
                    @else
                        <div class="alert alert-danger"> You Don't Have Permission </div>
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
            var site_html = '<select name="site_id[]"  id="site_id_' + count +
                '" class="form-control show-tick" data-live-search="true"  required><option value="" selected disabled >--Select Site--</option> @if($entry_at_site == "current")<option selected value="{{ $site_id }}">{{ getSiteDetailsById($site_id)->name }}</option>@else @foreach ($sites as $site)<option value = "{{ $site['id'] }}">{{ $site['name'] }}</option>@endforeach @endif</select>';
            var party_html = '<select name="party_id[]"  id="party_id_' + count +
                '"  class="form-control show-tick" data-live-search="true" required><option disabled>--Expense Parties--</option>@foreach ($parties as $party)<option value = "{{ $party['id'] }}||expense">{{ $party['name'] }}</option>@endforeach<option disabled>--Bill Parties--</option>@foreach ($bill_parties as $party)<option value = "{{ $party['id'] }}||bill">{{ $party['name'] }}</option>@endforeach</select>';
            var head_html = '<select name="head_id[]"  id="head_id_' + count +
                '" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Head--</option>@foreach ($heads as $head)<option value = "{{ $head['id'] }}">{{ $head['name'] }}</option>@endforeach</select>';

            var result = '<div  id="row_' + count +
                '"><hr><div class="row clearfix"><div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><img height= "150" width="150" id="' +
                count + '" src=' + "{{ asset('/images/expense.png') }}" +
                '  class="rounded-circle img-raised"><input type="file" accept="Image/*" name="image[]" onchange="document.getElementById(' +
                count + ').src = window.URL.createObjectURL(this.files[0])"></div></div>';
            result +=
                '<div class="col-lg-9 col-md-9 col-sm-9"><div class="row clearfix"><div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Site</label>' +
                site_html + '</div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Expense Party</label>' +
                party_html + '</div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Cost Category</label>' +
                head_html + '</div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Particular</label><input type="text"  required class="form-control" name="particular[]" placeholder="Enter The Particular Item"></div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Amount</label><input type="number" placeholder="0.00" required class="form-control" name="amount[]" min="0"  step="0.01" pattern="^\d+(?:\.\d{1,2})?$"></div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Remark</label><input type="text" class="form-control" name="remark[]"></div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Date</label><input type="date" min="{{ $min_date }}" max="{{ $max_date }}" value="{{ $today }}" required class="form-control" name="date[]"></div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><br><button type="button" onclick="deleterow(' +
                count +
                ')" class="btn btn-primary btn-simple btn-round waves-effect"><i class="zmdi zmdi-minus"  style="color: white;"></i></button></div></div></div></div></div></div>';
            $('#rowData').append(result);
            $("#site_id_" + count).selectpicker({
                liveSearch: true
            });
            $("#party_id_" + count).selectpicker({
                liveSearch: true
            });
            $("#head_id_" + count).selectpicker({
                liveSearch: true
            });
        });

        function deleterow(id) {
            $('#row_' + id).remove();
        }
    </script>
@endsection