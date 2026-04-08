@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Expense Reports'])
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
        @if(checkmodulepermission(2,'can_report') == 1)

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <p class="header"><strong>Expenses According To Date</strong></p>
                <form method="post" action="{{ url('/expensereports') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">

                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                        name="start_date" onchange="updateMaxDate()">
                                </div>
                                <input type="hidden" name="type" value="1">
                            </div>
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date" name="end_date"
                                        onchange="updateMinDate()">
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
                            <div class="col-lg-3 col-md-3 col-sm-3">
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
                <p class="header"><strong>Expenses According To Site</strong></p>
                <form method="post" action="{{ url('/expensereports') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <input type="hidden" name="type" value="2">
                                    <label>Site Name</label>
                                    <select name="site_id" class="form-control show-tick" data-live-search="true" required>
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
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date1"
                                        name="start_date" onchange="updateMaxDate1()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date1"
                                        name="end_date" onchange="updateMinDate1()">
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
                            <div class="col-lg-2 col-md-2 col-sm-2">
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
                <p class="header"><strong>Expenses According To Party</strong></p>
                <form method="post" action="{{ url('/expensereports') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <input type="hidden" name="type" value="3">
                                    <label>Party Name</label>
                                  
                                    <select name="party_id" class="form-control show-tick"
                                    data-live-search="true" required>
                                    <option disabled>--Expense Parties--</option>
                                    @foreach ($expense_party as $party)
                                        <option value="{{ $party->id }}||expense">
                                            {{ $party->name }}</option>
                                    @endforeach
                                    <option disabled>--Bill Parties--</option>
                                    @foreach ($bill_party as $party)
                                        <option value="{{ $party->id }}||bill">{{ $party->name }}
                                        </option>
                                    @endforeach
                                </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" name="start_date"
                                        id="start_date2" onchange="updateMaxDate2()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" name="end_date"
                                        id="end_date2" onchange="updateMinDate2()">
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
                            <div class="col-lg-2 col-md-2 col-sm-2">
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
                <p class="header"><strong>Expenses According To Party At Particular Site</strong></p>
                <form method="post" action="{{ url('/expensereports') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <input type="hidden" name="type" value="4">
                                    <label>Party Name</label>
                                    <select name="party_id" class="form-control show-tick"
                                    data-live-search="true" required>
                                    <option disabled>--Expense Parties--</option>
                                    @foreach ($expense_party as $party)
                                        <option value="{{ $party->id }}||expense">
                                            {{ $party->name }}</option>
                                    @endforeach
                                    <option disabled>--Bill Parties--</option>
                                    @foreach ($bill_party as $party)
                                        <option value="{{ $party->id }}||bill">{{ $party->name }}
                                        </option>
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
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date3" name="start_date"
                                        onchange="updateMaxDate3()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date3" name="end_date"
                                        onchange="updateMinDate3()">
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
                            <div class="col-lg-2 col-md-2 col-sm-2">
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
                <p class="header"><strong>Expenses According To Head</strong></p>
                <form method="post" action="{{ url('/expensereports') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <input type="hidden" name="type" value="5">
                                    <label>Head Name</label>
                                    <select name="head_id" class="form-control show-tick" data-live-search="true"
                                        required>
                                        <option value="" selected disabled>--Select Head--</option>
                                        @php
                                            $heads = getallCostCategories();
                                        @endphp
                                        @foreach ($heads as $head)
                                            <option value="{{ $head->id }}">{{ $head->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>From Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date4" name="start_date"
                                        onchange="updateMaxDate4()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date4" name="end_date"
                                        onchange="updateMinDate4()">
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
                            <div class="col-lg-2 col-md-2 col-sm-2">
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
                <p class="header"><strong>Expenses According To Head At Particular Site</strong></p>
                <form method="post" action="{{ url('/expensereports') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <input type="hidden" name="type" value="6">
                                    <label>Head Name</label>
                                    <select name="head_id" class="form-control show-tick" data-live-search="true"
                                        required>
                                        <option value="" selected disabled>--Select Head--</option>
                                        @php
                                            $heads = getallCostCategories();
                                        @endphp
                                        @foreach ($heads as $head)
                                            <option value="{{ $head->id }}">{{ $head->name }}</option>
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
                                        max="{{ $max_date }}" value="{{ $today }}" id="start_date5" name="start_date"
                                        onchange="updateMaxDate5()">
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label>To Date</label>
                                    <input type="date" required class="form-control" min="{{ $min_date }}"
                                        max="{{ $max_date }}" value="{{ $today }}" id="end_date5" name="end_date"
                                        onchange="updateMinDate5()">
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
                            <div class="col-lg-2 col-md-2 col-sm-2">
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
            var startDate = new Date(document.getElementById("start_date").value);
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 12);
            document.getElementById("end_date").max = maxDate.toISOString().split('T')[0];
        }
        function updateMinDate() {
            var endDate = new Date(document.getElementById("end_date").value);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            document.getElementById("start_date").max = maxDate.toISOString().split('T')[0];
        }
        function updateMaxDate1() {
            var startDate = new Date(document.getElementById("start_date1").value);
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 12);
            document.getElementById("end_date1").max = maxDate.toISOString().split('T')[0];
        }
        function updateMinDate1() {
            var endDate = new Date(document.getElementById("end_date1").value);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            document.getElementById("start_date1").max = maxDate.toISOString().split('T')[0];
        }
        function updateMaxDate2() {
            var startDate = new Date(document.getElementById("start_date2").value);
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 12);
            document.getElementById("end_date2").max = maxDate.toISOString().split('T')[0];
        }
        function updateMinDate2() {
            var endDate = new Date(document.getElementById("end_date2").value);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            document.getElementById("start_date2").max = maxDate.toISOString().split('T')[0];
        }
        function updateMaxDate3() {
            var startDate = new Date(document.getElementById("start_date3").value);
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 12);
            document.getElementById("end_date3").max = maxDate.toISOString().split('T')[0];
        }
        function updateMinDate3() {
            var endDate = new Date(document.getElementById("end_date3").value);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            document.getElementById("start_date3").max = maxDate.toISOString().split('T')[0];
        }
        function updateMaxDate4() {
            var startDate = new Date(document.getElementById("start_date4").value);
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 12);
            document.getElementById("end_date4").max = maxDate.toISOString().split('T')[0];
        }
        function updateMinDate4() {
            var endDate = new Date(document.getElementById("end_date4").value);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            document.getElementById("start_date4").max = maxDate.toISOString().split('T')[0];
        }
        function updateMaxDate5() {
            var startDate = new Date(document.getElementById("start_date5").value);
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 12);
            document.getElementById("end_date5").max = maxDate.toISOString().split('T')[0];
        }
        function updateMinDate5() {
            var endDate = new Date(document.getElementById("end_date5").value);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            document.getElementById("start_date5").max = maxDate.toISOString().split('T')[0];
        }
    </script>
@endsection
