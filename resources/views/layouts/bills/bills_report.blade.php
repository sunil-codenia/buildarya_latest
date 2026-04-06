@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Bills Report'])
    @php

        $site_id = session()->get('site_id');
        $role_details = getRoleDetailsById(session()->get('role'));
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $role_details->add_duration;
        $duration = getdurationdates($add_duration);
        $today = substr($duration['today'], 0, 10);
        $min_date = substr($duration['min'], 0, 10);
        $max_date = substr($duration['max'], 0, 10);

    @endphp
@if (checkmodulepermission(4, 'can_report') == 1)

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <p class="header"><strong>Bill Report According To Date</strong></p>
                <form method="post" action="{{ url('/sitebillreport') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date" name="start_date" onchange="updateMaxDate()">
                                </div>
                                
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date" name="end_date" onchange="updateMinDate()">
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>Format</label>
                                    <select name="type" class="form-control show-tick" required>
                                       
                                        <option selected value="1">Simple</option>
                                        <option value="2">Detailed With Bills</option>
                                      
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>Report Type</label>
                                    <select name="Report_Type" class="form-control show-tick" required>
                                        <option value="" selected disabled>--Select Type--</option>
                                        <option value="0">PDF Format</option>
                                        <option value="1">Excel Format</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2 text-center">
                                <div class="form-group">
                                    <label> </label>
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
  
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <p class="header"><strong>Bill Report According To Item</strong></p>
                <form method="post" action="{{ url('/sitebillreport') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <input type="hidden" name="type" value="3">
                                    <label>Item Name</label>
                                    <select name="work_id" class="form-control show-tick" data-live-search="true" required>
                                        <option value="" selected disabled>--Select Item--</option>
                                        @php
                                            $works = getallworkslist();
                                        @endphp
                                        @foreach ($works as $work)
                                            <option value="{{ $work->id }}">{{ $work->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date1" name="start_date" onchange="updatestartDate()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date1" name="end_date" onchange="updateendDate()">
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>Report Type</label>
                                    <select name="Report_Type" class="form-control show-tick" required>
                                        <option value="" selected disabled>--Select Type--</option>
                                        <option value="0">PDF Format</option>
                                        <option value="1">Excel Format</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2 text-center">
                                <div class="form-group">
                                    <label> </label>
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <p class="header"><strong>Bill Report According To Item At Particular Site</strong></p>
                <form method="post" action="{{ url('/sitebillreport') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <input type="hidden" name="type" value="4">
                                    <label>Item Name</label>
                                    <select name="work_id" class="form-control show-tick" data-live-search="true" required>
                                        <option value="" selected disabled>--Select Item--</option>
                                        @php
                                            $works = getallworkslist();
                                        @endphp
                                        @foreach ($works as $work)
                                            <option value="{{ $work->id }}">{{ $work->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                  
                                    <label>Site Name</label>
                                    <select name="site_id" class="form-control show-tick" data-live-search="true"
                                    required>
                                    <option value="" selected disabled>--Select Site--</option>
                                    @php
                                        $sites = getallsites();
                                    @endphp
                                    @foreach ($sites as $site)
                                        <option value="{{ $site->id }}">{{ $site->name }}</option>
                                    @endforeach
                                </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date1" name="start_date" onchange="updatestartDate()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date1" name="end_date" onchange="updateendDate()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>Report Type</label>
                                    <select name="Report_Type" class="form-control show-tick" required>
                                        <option value="" selected disabled>--Select Type--</option>
                                        <option value="0">PDF Format</option>
                                        <option value="1">Excel Format</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2 text-center">
                                <div class="form-group">
                                    <label> </label>
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <p class="header"><strong>Bill Report According To Party</strong></p>
                <form method="post" action="{{ url('/sitebillreport') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">

                                    <label>Party Name</label>
                                    <select name="party_id" class="form-control show-tick" data-live-search="true" required>
                                        <option value="" selected disabled>--Select Party--</option>
                                        @php
                                            $parties = getallbillparties();
                                        @endphp
                                        @foreach ($parties as $party)
                                            <option value="{{ $party->id }}">{{ $party->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date1" name="start_date" onchange="updatestartDate()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date1" name="end_date" onchange="updateendDate()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>Format</label>
                                    <select name="type" class="form-control show-tick" required>
                                       
                                        <option selected value="5">Simple</option>
                                        <option value="6">Detailed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>Report Type</label>
                                    <select name="Report_Type" class="form-control show-tick" required>
                                        <option value="" selected disabled>--Select Type--</option>
                                        <option value="0">PDF Format</option>
                                        <option value="1">Excel Format</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2 text-center">
                                <div class="form-group">
                                    <label> </label>
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
 
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <p class="header"><strong>Bill According To Bill Party At Particular Site</strong></p>
                <form method="post" action="{{ url('/sitebillreport') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    
                                    <label>Party Name</label>
                                    <select name="party_id" class="form-control show-tick" data-live-search="true"
                                        required>
                                        <option value="" selected disabled>--Select Party--</option>
                                        @php
                                            $parties = getallbillparties();
                                        @endphp
                                        @foreach ($parties as $party)
                                            <option value="{{ $party->id }}">{{ $party->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>Site Name</label>
                                    <select name="site_id" class="form-control show-tick" data-live-search="true"
                                        required>
                                        <option value="" selected disabled>--Select Site--</option>
                                        @php
                                            $sites = getallsites();
                                        @endphp
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" name="start_date" id="start_date2" onchange="updatestartDate2()">
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" name="end_date" id="end_date2" onchange="updateendDate2()">
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-4">
                                <div class="form-group">
                                    <label>Format</label>
                                    <select name="type" class="form-control show-tick" required>
                                       
                                        <option selected value="7">Simple</option>
                                        <option value="8">Detailed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-4">
                                <div class="form-group">
                                    <label>Report Type</label>
                                    <select name="Report_Type" class="form-control show-tick" required>
                                        <option value="" selected disabled>--Select Type--</option>
                                        <option value="0">PDF Format</option>
                                        <option value="1">Excel Format</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-4 text-center">
                                <div class="form-group">
                                   
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
 
 
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <p class="header"><strong>Bill Report According To Site</strong></p>
                <form method="post" action="{{ url('/sitebillreport') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                   <label>Site Name</label>
                                    <select name="site_id" class="form-control show-tick" data-live-search="true"
                                        required>
                                        <option value="" selected disabled>--Select Site--</option>
                                        @php
                                            $sites = getallsites();
                                        @endphp
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date1" name="start_date" onchange="updatestartDate()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date1" name="end_date" onchange="updateendDate()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>Format</label>
                                    <select name="type" class="form-control show-tick" required>
                                       
                                        <option selected value="9">Simple</option>
                                        <option value="10">Detailed</option>
                                        <option value="12">Detailed With Work</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>Report Type</label>
                                    <select name="Report_Type" class="form-control show-tick" required>
                                        <option value="" selected disabled>--Select Type--</option>
                                        <option value="0">PDF Format</option>
                                        <option value="1">Excel Format</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2 text-center">
                                <div class="form-group">
                                    <label> </label>
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
  
 
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <p class="header"><strong>Bill Party Statement</strong></p>
                <form method="post" action="{{ url('/sitebillreport') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <input type="hidden" name="type" value="11">
                                    <label>Party Name</label>
                                    <select name="party_id" class="form-control show-tick" data-live-search="true"
                                        required>
                                        <option value="" selected disabled>--Select Party--</option>
                                        @php
                                            $parties = getallbillparties();
                                        @endphp
                                        @foreach ($parties as $party)
                                            <option value="{{ $party->id }}">{{ $party->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                      
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>Report Type</label>
                                    <select name="Report_Type" class="form-control show-tick" required>
                                        <option value="" selected disabled>--Select Type--</option>
                                        <option value="0">PDF Format</option>
                                        <option value="1">Excel Format</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2 text-center">
                                <div class="form-group">
                                    <label> </label>
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
 
@endif
 
@endsection
@section('scripts')
    <script>
        function updateMaxDate() {
            // Get the selected start date
            var startDate = new Date(document.getElementById("start_date").value);

            // Calculate the maximum date allowed (6 months from the start date)
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 12);

            // Set the max attribute for the end date input
            document.getElementById("end_date").max = maxDate.toISOString().split('T')[0];
        }
        function updateMinDate() {
            // Get the selected start date
            var endDate = new Date(document.getElementById("end_date").value);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            // var maxDate = new Date(endDate);
            // maxDate.setMonth(maxDate.getMonth() - 6);

            document.getElementById("start_date").max = maxDate.toISOString().split('T')[0];
        }
        function updatestartDate() {
            // Get the selected start date
            var startDate = new Date(document.getElementById("start_date1").value);
            // Calculate the maximum date allowed (6 months from the start date)
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 12);
            // Set the max attribute for the end date input
            document.getElementById("end_date1").max = maxDate.toISOString().split('T')[0];
        }


        function updateendDate() {
            // Get the selected start date
            var endDate = new Date(document.getElementById("end_date1").value);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            // var maxDate = new Date(endDate);
            // maxDate.setMonth(maxDate.getMonth() - 6);
            document.getElementById("start_date1").max = maxDate.toISOString().split('T')[0];
        }
        function updatestartDate2() {
            // Get the selected start date
            var startDate = new Date(document.getElementById("start_date2").value);
            // Calculate the maximum date allowed (6 months from the start date)
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 12);
            // Set the max attribute for the end date input
            document.getElementById("end_date2").max = maxDate.toISOString().split('T')[0];
        }


        function updateendDate2() {
            // Get the selected start date
            var endDate = new Date(document.getElementById("end_date2").value);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            // var maxDate = new Date(endDate);
            // maxDate.setMonth(maxDate.getMonth() - 6);
            document.getElementById("start_date2").max = maxDate.toISOString().split('T')[0];
        }
    </script>
@endsection
