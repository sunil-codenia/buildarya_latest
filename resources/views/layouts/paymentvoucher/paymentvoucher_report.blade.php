@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Payment Voucher Report'])
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
    @if (checkmodulepermission(8, 'can_report') == 1)
        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list" style="padding: 20px;">
                    <p class="header"><strong>Generate Consolidated Payment Voucher Report</strong></p>
                    <form method="post" action="{{ url('/paymentvoucherreport') }}" enctype="multipart/form-data" onsubmit="return prepareReportSubmit(event)">
                        @csrf
                        <input type="hidden" id="report_type_input" name="type" value="1">
                        
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <!-- Party Name Filter (Optional) -->
                                <div class="col-lg-3 col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Party Name (Optional)</label>
                                        <select id="report_party_id" name="party_id" class="form-control show-tick" data-live-search="true">
                                            <option value="">-- All Parties --</option>
                                            @foreach($parties as $party)
                                                <option value="{{$party['id']}}||{{$party['type']}}">{{$party['name']}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Site Name Filter (Optional) -->
                                <div class="col-lg-3 col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Site Name (Optional)</label>
                                        <select id="report_site_id" name="site_id" class="form-control show-tick" data-live-search="true">
                                            <option value="">-- All Sites --</option>
                                            @php
                                                $sites = getallsites();
                                            @endphp
                                            @foreach ($sites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- From Date -->
                                <div class="col-lg-2 col-md-2 col-sm-6">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" onchange="updateMaxDate()">
                                    </div>
                                </div>

                                <!-- To Date -->
                                <div class="col-lg-2 col-md-2 col-sm-6">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" onchange="updateMinDate()">
                                    </div>
                                </div>

                                <!-- Report Format Type -->
                                <div class="col-lg-2 col-md-2 col-sm-6">
                                    <div class="form-group">
                                        <label>Report Format</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Centered Download Button Row -->
                            <div class="row clearfix" style="margin-top: 15px;">
                                <div class="col-lg-12 text-right">
                                    <button type="submit" class="btn btn-primary btn-round waves-effect" style="min-width: 180px;">
                                        <i class="zmdi zmdi-download" style="margin-right: 5px;"></i> Download Report
                                    </button>
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
        function prepareReportSubmit(event) {
            var partySelect = document.getElementById("report_party_id");
            var siteSelect = document.getElementById("report_site_id");
            var typeInput = document.getElementById("report_type_input");

            var partySelected = partySelect && partySelect.value !== "";
            var siteSelected = siteSelect && siteSelect.value !== "";

            if (partySelected && siteSelected) {
                typeInput.value = "4"; // Party at particular site
            } else if (partySelected) {
                typeInput.value = "2"; // Party only
            } else if (siteSelected) {
                typeInput.value = "3"; // Site only
            } else {
                typeInput.value = "1"; // Date only
            }
            return true;
        }

        function updateMaxDate() {
            var startDateVal = document.getElementById("start_date").value;
            if (!startDateVal) return;
            var startDate = new Date(startDateVal);
            var maxDate = new Date(startDate);
            maxDate.setMonth(maxDate.getMonth() + 6);
            document.getElementById("end_date").max = maxDate.toISOString().split('T')[0];
        }

        function updateMinDate() {
            var endDateVal = document.getElementById("end_date").value;
            if (!endDateVal) return;
            var endDate = new Date(endDateVal);
            var maxDate = new Date(endDate);
            maxDate.setDate(maxDate.getDate() - 1);
            document.getElementById("start_date").max = maxDate.toISOString().split('T')[0];
        }
    </script>
@endsection
