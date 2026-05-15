@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Sales Report'])
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
    @if (checkmodulepermission(3, 'can_report') == 1)
        <div class="alert" style="color:black;background:white;">
            <i class="zmdi zmdi-info"></i> &nbsp; Sales Report Only Available In Excel Format Due To Unpredictable Column Count
        </div>

        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Generate Sales Report</strong></p>
                    <form method="post" action="{{ url('/salesreport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="body">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select class="form-control show-tick" name="type" id="report_type" required onchange="toggleFields()">
                                            <option value="" selected disabled>--Select Report Type--</option>
                                            <option value="1">According To Party</option>
                                            <option value="2">According To Project</option>
                                            <option value="3">According To Financial Year</option>
                                            <option value="4">According To Company</option>
                                            <option value="5">According To Invoice Heads</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-3 col-sm-6" id="party_div" style="display:none;">
                                    <div class="form-group">
                                        <label>Sales Party</label>
                                        <select class="form-control show-tick" data-live-search="true" name="party_id">
                                            <option disabled value="" selected>--Select Party--</option>
                                            @foreach ($parties as $party)
                                                <option value="{{ $party->id }}">{{ $party->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-3 col-sm-6" id="project_div" style="display:none;">
                                    <div class="form-group">
                                        <label>Project Name</label>
                                        <select name="project_id" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Project--</option>
                                            @foreach ($projects as $project)
                                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-3 col-sm-6" id="company_div" style="display:none;">
                                    <div class="form-group">
                                        <label>Sales Company</label>
                                        <select class="form-control show-tick" data-live-search="true" name="company_id">
                                            <option disabled value="" selected>--Select Company--</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company->id }}">{{ $company->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-3 col-sm-6" id="head_div" style="display:none;">
                                    <div class="form-group">
                                        <label>Invoice Head</label>
                                        <select name="head_id" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Head--</option>
                                            @foreach ($heads as $head)
                                                <option value="{{ $head->id }}">{{ $head->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-3 col-sm-6" id="year_div" style="display:none;">
                                    <div class="form-group">
                                        <label>Financial Year</label>
                                        <select name="financial_year" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Year--</option>
                                            @php
                                                $years = getFinancialYear();
                                            @endphp
                                            @foreach ($years as $year)
                                                <option value="{{ $year }}">{{ $year }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-3 col-md-3 col-sm-12">
                                    <div class="form-group">
                                        <label>&nbsp;</label><br>
                                        <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect">
                                            Download Report
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function toggleFields() {
                var type = document.getElementById('report_type').value;
                document.getElementById('party_div').style.display = (type == '1') ? 'block' : 'none';
                document.getElementById('project_div').style.display = (type == '2') ? 'block' : 'none';
                document.getElementById('year_div').style.display = (type == '3' || type == '4' || type == '5') ? 'block' : 'none';
                document.getElementById('company_div').style.display = (type == '4') ? 'block' : 'none';
                document.getElementById('head_div').style.display = (type == '5') ? 'block' : 'none';
                
                // Refresh bootstrap-select if used
                $('.show-tick').selectpicker('refresh');
            }
        </script>
    @endif
@endsection
