@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Machinery Report'])
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
                        <h2><strong>Generate Machinery Report</strong></h2>
                    </div>
                    <div class="body">
                        <form method="post" id="machinery_report_form" action="{{ url('/machineryexport') }}">
                            @csrf
                            <div class="row clearfix">
                                <!-- Report Category Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12">
                                    <div class="form-group">
                                        <label>Report For</label>
                                        <select name="type" id="report_category" class="form-control show-tick" required onchange="updateFields()">
                                            <option value="" selected disabled>--Select Report Category--</option>
                                            <optgroup label="General Reports">
                                                <option value="site_complete">Machinery Complete Report According To Site</option>
                                            </optgroup>
                                            <optgroup label="Purchase Reports">
                                                <option value="1">Purchase Report According To Head</option>
                                                <option value="2">Purchase Report According To Site</option>
                                                <option value="3">Complete Purchase Report</option>
                                            </optgroup>
                                            <optgroup label="Sale Reports">
                                                <option value="4">Sale Report According To Head</option>
                                                <option value="5">Sale Report According To Site</option>
                                                <option value="6">Complete Sale Report</option>
                                            </optgroup>
                                            <optgroup label="Transfer Reports">
                                                <option value="7">Transfer Report According To Head</option>
                                                <option value="8">Complete Transfer Report</option>
                                            </optgroup>
                                            <optgroup label="Document Reports">
                                                <option value="9">Documents Report According To Head</option>
                                                <option value="10">Complete Documents Report</option>
                                            </optgroup>
                                            <optgroup label="Service Reports">
                                                <option value="11">Service Report According To Head</option>
                                                <option value="12">Complete Service Report</option>
                                            </optgroup>
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

                                <!-- Dynamic Machinery Head Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field head-field" style="display:none;">
                                    <div class="form-group">
                                        <label>Machinery Head</label>
                                        <select name="head_id" id="head_id_select" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Head--</option>
                                            @foreach ($machinery_heads as $head)
                                                <option value="{{ $head->id }}">{{ $head->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row clearfix date-range-fields">
                                <!-- From Date -->
                                <div class="col-lg-3 col-md-3 col-sm-6" id="from_date_container">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date">
                                    </div>
                                </div>

                                <!-- To Date -->
                                <div class="col-lg-3 col-md-3 col-sm-6" id="to_date_container">
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
            var form = $('#machinery_report_form');

            // Hide all dynamic fields first
            $('.dynamic-field').hide();
            $('#from_date_container, #to_date_container').show();

            // Reset required attributes
            $('#site_id_select, #head_id_select, #start_date, #end_date').prop('required', false);

            if (category === 'site_complete') {
                form.attr('action', "{{ url('/machinery_of_site_report') }}");
                $('.site-field').show();
                $('#site_id_select').prop('required', true);
                // Site complete report usually doesn't have date range in the original code? 
                // Let's check original code. It didn't have date inputs for the first card.
                $('#from_date_container, #to_date_container').hide();
            } else {
                form.attr('action', "{{ url('/machineryexport') }}");
                $('#start_date, #end_date').prop('required', true);

                // Show fields based on category
                if (['2', '5', '9', '11'].includes(category)) {
                    // Site field for Purchase/Sale/Doc/Service by Site
                    // Wait, original code:
                    // type 2: Purchase by Site -> Site field
                    // type 5: Sale by Site -> Site field
                    // type 11: Service by Head -> Head field
                    // type 9: Doc by Head -> Head field
                    // I need to be careful.
                }

                // Correct mapping based on original cards:
                // 1: Purchase by Head -> Head field
                // 2: Purchase by Site -> Site field
                // 3: Complete Purchase -> No extra field
                // 4: Sale by Head -> Head field
                // 5: Sale by Site -> Site field
                // 6: Complete Sale -> No extra field
                // 7: Transfer by Head -> Head field
                // 8: Complete Transfer -> No extra field
                // 9: Doc by Head -> Head field
                // 10: Complete Doc -> No extra field
                // 11: Service by Head -> Head field
                // 12: Complete Service -> No extra field

                if (['1', '4', '7', '9', '11'].includes(category)) {
                    $('.head-field').show();
                    $('#head_id_select').prop('required', true);
                }
                if (['2', '5'].includes(category)) {
                    $('.site-field').show();
                    $('#site_id_select').prop('required', true);
                }
            }

            // Refresh selectpicker
            $('.show-tick').selectpicker('refresh');
        }

        // Initialize on page load
        $(document).ready(function() {
            updateFields();
        });
    </script>
@endsection
