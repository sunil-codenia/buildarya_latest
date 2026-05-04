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
                <div class="header">
                    <h2><strong>Generate Expense Report</strong></h2>
                </div>
                <div class="body">
                    <form method="post" action="{{ url('/expensereports') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row clearfix">
                            <!-- Report Type Selection -->
                            <div class="col-lg-4 col-md-4 col-sm-12">
                                <div class="form-group">
                                    <label>Report For</label>
                                    <select name="type" id="report_category" class="form-control show-tick" required onchange="updateFields()">
                                        <option value="" selected disabled>--Select Report Category--</option>
                                        <option value="1">Expenses According To Date</option>
                                        <option value="2">Expenses According To Site</option>
                                        <option value="3">Expenses According To Party</option>
                                        <option value="4">Expenses According To Party At Particular Site</option>
                                        <option value="5">Expenses According To Head</option>
                                        <option value="6">Expenses According To Head At Particular Site</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Dynamic Site Selection -->
                            <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field site-field" style="display:none;">
                                <div class="form-group">
                                    <label>Site Name</label>
                                    <select name="site_id" id="site_id_select" class="form-control show-tick" data-live-search="true">
                                        <option value="" selected disabled>--Select Site--</option>
                                        @php $sites = getallsites(); @endphp
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Dynamic Party Selection -->
                            <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field party-field" style="display:none;">
                                <div class="form-group">
                                    <label>Party Name</label>
                                    <select name="party_id" id="party_id_select" class="form-control show-tick" data-live-search="true">
                                        <option value="" selected disabled>--Select Party--</option>
                                        <optgroup label="Expense Parties">
                                            @foreach ($expense_party as $party)
                                                <option value="{{ $party->id }}||expense">{{ $party->name }}</option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Bill Parties">
                                            @foreach ($bill_party as $party)
                                                <option value="{{ $party->id }}||bill">{{ $party->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </div>
                            </div>

                            <!-- Dynamic Head Selection -->
                            <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field head-field" style="display:none;">
                                <div class="form-group">
                                    <label>Head Name</label>
                                    <select name="head_id" id="head_id_select" class="form-control show-tick" data-live-search="true">
                                        <option value="" selected disabled>--Select Head--</option>
                                        @php $heads = getallCostCategories(); @endphp
                                        @foreach ($heads as $head)
                                            <option value="{{ $head->id }}">{{ $head->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row clearfix">
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
        
        // Reset required attributes
        $('#site_id_select, #party_id_select, #head_id_select').prop('required', false);

        // Show fields based on selection
        if (category == '2' || category == '4' || category == '6') {
            $('.site-field').show();
            $('#site_id_select').prop('required', true);
        }
        if (category == '3' || category == '4') {
            $('.party-field').show();
            $('#party_id_select').prop('required', true);
        }
        if (category == '5' || category == '6') {
            $('.head-field').show();
            $('#head_id_select').prop('required', true);
        }
        
        // Refresh selectpicker if used
        $('.show-tick').selectpicker('refresh');
    }

    // Initialize on page load
    $(document).ready(function() {
        updateFields();
    });
</script>
@endsection
