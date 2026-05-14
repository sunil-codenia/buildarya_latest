@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Asset Report'])
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

    @if (checkmodulepermission(6, 'can_report') == 1)
        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <div class="header">
                        <h2><strong>Generate Asset Report</strong></h2>
                    </div>
                    <div class="body">
                        <form method="post" action="{{ url('/assetreport') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row clearfix">
                                <!-- Report Category Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12">
                                    <div class="form-group">
                                        <label>Report Category</label>
                                        <select name="type" id="report_category" class="form-control show-tick" required onchange="updateFields()">
                                            <option value="" selected disabled>--Select Report Category--</option>
                                            <option value="9">Asset Complete Report According To Site</option>
                                            <option value="1">Asset Purchase Report According To Head</option>
                                            <option value="2">Asset Purchase Report According To Site</option>
                                            <option value="3">Complete Asset Purchase Report</option>
                                            <option value="4">Asset Sale Report According To Head</option>
                                            <option value="5">Asset Sale Report According To Site</option>
                                            <option value="6">Asset Complete Sale Report</option>
                                            <option value="7">Asset Transfer Report According To Head</option>
                                            <option value="8">Asset Complete Transfer Report</option>
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

                                <!-- Dynamic Asset Head Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field head-field" style="display:none;">
                                    <div class="form-group">
                                        <label>Asset Head</label>
                                        <select name="head_id" id="head_id_select" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Head--</option>
                                            @foreach ($asset_heads as $asset_head)
                                                <option value="{{ $asset_head->id }}">{{ $asset_head->name }}</option>
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
                                            <option value="0">PDF Format</option>
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

            // Reset required attributes
            $('#site_id_select, #head_id_select, #start_date, #end_date').prop('required', false);

            if (category == '9') {
                // Site Complete Report - No dates needed
                $('.site-field').show();
                $('#site_id_select').prop('required', true);
                $('.date-range-fields .form-group:not(:has(select[name="Report_Type"])):not(:has(button))').hide();
                $('#start_date, #end_date').prop('required', false);
            } else {
                $('.date-range-fields .form-group').show();
                $('#start_date, #end_date').prop('required', true);
                
                if (category == '1' || category == '4' || category == '7') {
                    // Head related
                    if (category != '4' && category != '7') {
                         $('.head-field').show();
                         $('#head_id_select').prop('required', true);
                    }
                }
                
                if (category == '2' || category == '5') {
                    // Site related
                    $('.site-field').show();
                    $('#site_id_select').prop('required', true);
                }

                // If type is 1, 4, or 7 we show head field EXCEPT for Complete ones
                if (category == '1' || category == '4' || category == '7') {
                     if (category == '1' || category == '4' || category == '7') {
                         // wait, type 1 is Purchase By Head, type 4 is Sale By Head, type 7 is Transfer By Head
                         $('.head-field').show();
                         $('#head_id_select').prop('required', true);
                     }
                }
                
                // Let's refine the logic based on the actual categories
                $('.dynamic-field').hide();
                if (category == '1' || category == '4' || category == '7') {
                    $('.head-field').show();
                    $('#head_id_select').prop('required', true);
                } else if (category == '2' || category == '5' || category == '9') {
                    $('.site-field').show();
                    $('#site_id_select').prop('required', true);
                }
                
                if (category == '9') {
                    $('.date-range-fields').find('input[type="date"]').closest('.col-lg-3').hide();
                    $('#start_date, #end_date').prop('required', false);
                } else {
                    $('.date-range-fields').find('input[type="date"]').closest('.col-lg-3').show();
                    $('#start_date, #end_date').prop('required', true);
                }
            }

            // Refresh selectpicker if used
            if ($('.show-tick').length > 0) {
                $('.show-tick').selectpicker('refresh');
            }
        }

        // Initialize on page load
        $(document).ready(function() {
            updateFields();
        });
    </script>
@endsection
