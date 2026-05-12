@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Site Bill Report'])
    @php
        $role_details = getRoleDetailsById(session()->get('role'));
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
                    <div class="header">
                        <h2><strong>Generate Bill Report</strong></h2>
                    </div>
                    <div class="body">
                        <form method="post" action="{{ url('/sitebillreport') }}">
                            @csrf
                            <div class="row clearfix">
                                <!-- Report Category Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12">
                                    <div class="form-group">
                                        <label>Report For</label>
                                        <select name="type" id="report_category" class="form-control show-tick" required onchange="updateFields()">
                                            <option value="" selected disabled>--Select Report Category--</option>
                                            <option value="1">Date Report</option>
                                            <option value="2">Detailed Date Report</option>
                                            <option value="3">Item Report</option>
                                            <option value="4">Item Report At Particular Site</option>
                                            <option value="5">Party Report</option>
                                            <option value="6">Party Detailed Report</option>
                                            <option value="7">Party Report At Particular Site</option>
                                            <option value="8">Party Detailed Report At Particular Site</option>
                                            <option value="9">Site Report</option>
                                            <option value="10">Site Detailed Report</option>
                                            <option value="12">Site Detailed With Work Report</option>
                                            <option value="11">Party Statement</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dynamic Site Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field site-field" style="display:none;">
                                    <div class="form-group">
                                        <label>Site Name</label>
                                        <select name="site_id" id="site_id_select" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Site--</option>
                                            @foreach ($sites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Dynamic Bill Party Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field party-field" style="display:none;">
                                    <div class="form-group">
                                        <label>Bill Party Name</label>
                                        <select name="party_id" id="party_id_select" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Party--</option>
                                            @foreach ($parties as $party)
                                                <option value="{{ $party->id }}">{{ $party->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Dynamic Work Item Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field work-field" style="display:none;">
                                    <div class="form-group">
                                        <label>Work Item</label>
                                        <select name="work_id" id="work_id_select" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Work--</option>
                                            @foreach ($works as $work)
                                                <option value="{{ $work->id }}">{{ $work->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row clearfix date-range-fields">
                                <!-- From Date -->
                                <div class="col-lg-3 col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date">
                                    </div>
                                </div>

                                <!-- To Date -->
                                <div class="col-lg-3 col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date" 
                                            name="end_date">
                                    </div>
                                </div>

                                <!-- Export Format -->
                                <div class="col-lg-3 col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>Report Format</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Format--</option>
                                            <option value="2">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Download Button -->
                                <div class="col-lg-3 col-md-3 col-sm-6">
                                    <div class="form-group">
                                        <label>&nbsp;</label><br>
                                        <button type="submit" class="btn btn-primary btn-round waves-effect btn-block">
                                            <i class="zmdi zmdi-download"></i> Download Report
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- For Statements -->
                            <div class="row clearfix statement-fields" style="display:none;">
                                <div class="col-lg-6 col-md-6 col-sm-6">
                                    <div class="form-group">
                                        <label>Report Format</label>
                                        <select name="Report_Type_Statement" id="report_type_statement" class="form-control show-tick">
                                            <option value="" selected disabled>--Select Format--</option>
                                            <option value="2">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-md-6 col-sm-6">
                                    <div class="form-group">
                                        <label>&nbsp;</label><br>
                                        <button type="button" onclick="submitStatement()" class="btn btn-primary btn-round waves-effect btn-block">
                                            <i class="zmdi zmdi-download"></i> Download Statement
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
    <script>
        function updateFields() {
            var category = $('#report_category').val();

            // Hide all dynamic fields first
            $('.dynamic-field').hide();
            $('.date-range-fields').show();
            $('.statement-fields').hide();

            // Reset required attributes
            $('#site_id_select, #party_id_select, #work_id_select, #start_date, #end_date').prop('required', false);

            if (category == '11') {
                // Party Statement
                $('.date-range-fields').hide();
                $('.statement-fields').show();
                $('.party-field').show();
                $('#party_id_select').prop('required', true);
            } else {
                $('#start_date, #end_date').prop('required', true);
                
                // Show fields based on category
                if (['4', '7', '8', '9', '10', '12'].includes(category)) {
                    $('.site-field').show();
                    $('#site_id_select').prop('required', true);
                }
                if (['5', '6', '7', '8'].includes(category)) {
                    $('.party-field').show();
                    $('#party_id_select').prop('required', true);
                }
                if (['3', '4'].includes(category)) {
                    $('.work-field').show();
                    $('#work_id_select').prop('required', true);
                }
            }

            // Refresh selectpicker
            $('.show-tick').selectpicker('refresh');
        }

        function submitStatement() {
            var party = $('#party_id_select').val();
            var format = $('#report_type_statement').val();

            if (!party) {
                alert('Please select a party');
                return;
            }
            if (format === "" || format === null) {
                alert('Please select a format');
                return;
            }

            // Create a temporary form to submit
            var form = $('<form></form>');
            form.attr("method", "post");
            form.attr("action", "{{ url('/sitebillreport') }}");
            form.append('<input type="hidden" name="_token" value="{{ csrf_token() }}">');
            form.append('<input type="hidden" name="type" value="11">');
            form.append('<input type="hidden" name="party_id" value="' + party + '">');
            form.append('<input type="hidden" name="Report_Type" value="' + format + '">');
            
            $(document.body).append(form);
            form.submit();
        }

        // Initialize on page load
        $(document).ready(function() {
            updateFields();
        });
    </script>
@endsection
