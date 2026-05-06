@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Material Report'])
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
        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <div class="header">
                        <h2><strong>Generate Material Report</strong></h2>
                    </div>
                    <div class="body">
                        <form method="post" action="{{ url('/materialreports') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row clearfix">
                                <!-- Report Type Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12">
                                    <div class="form-group">
                                        <label>Report For</label>
                                        <select name="type" id="report_category" class="form-control show-tick" required onchange="updateFields()">
                                            <option value="" selected disabled>--Select Report Category--</option>
                                            <option value="1">Material According To Date</option>
                                            <option value="2">Material According To Site</option>
                                            <option value="3">Material According To Supplier</option>
                                            <option value="4">Material According To Supplier At Particular Site</option>
                                            <option value="5">Material According To Specific Material</option>
                                            <option value="6">Material According To Specific Material At Particular Site</option>
                                            <option value="7">Material Supplier Statement</option>
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

                                <!-- Dynamic Supplier Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field supplier-field" style="display:none;">
                                    <div class="form-group">
                                        <label>Supplier Name</label>
                                        <select name="supplier_id" id="supplier_id_select" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Supplier--</option>
                                            @php $suppliers = getallmaterialsupplier(); @endphp
                                            @foreach ($suppliers as $party)
                                                <option value="{{ $party->id }}">{{ $party->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Dynamic Material Selection -->
                                <div class="col-lg-4 col-md-4 col-sm-12 dynamic-field material-field" style="display:none;">
                                    <div class="form-group">
                                        <label>Material Name</label>
                                        <select name="material_id" id="material_id_select" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Material--</option>
                                            @php $materials = getallmaterial(); @endphp
                                            @foreach ($materials as $head)
                                                <option value="{{ $head->id }}">{{ $head->name }}</option>
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

                            <!-- For Supplier Statement (which might not need date range in original code) -->
                            <div class="row clearfix statement-fields" style="display:none;">
                                <div class="col-lg-6 col-md-6 col-sm-6">
                                    <div class="form-group">
                                        <label>Report Format</label>
                                        <select name="Report_Type_Statement" id="report_type_statement" class="form-control show-tick">
                                            <option value="" selected disabled>--Select Format--</option>
                                            <option value="0">PDF Format</option>
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
            $('#site_id_select, #supplier_id_select, #material_id_select, #start_date, #end_date').prop('required', false);

            if (category == '7') {
                // Supplier Statement
                $('.supplier-field').show();
                $('#supplier_id_select').prop('required', true);
                $('.date-range-fields').hide();
                $('.statement-fields').show();
                $('#report_type_statement').prop('required', true);
            } else {
                $('#start_date, #end_date').prop('required', true);
                
                if (category == '2' || category == '4' || category == '6') {
                    $('.site-field').show();
                    $('#site_id_select').prop('required', true);
                }
                if (category == '3' || category == '4') {
                    $('.supplier-field').show();
                    $('#supplier_id_select').prop('required', true);
                }
                if (category == '5' || category == '6') {
                    $('.material-field').show();
                    $('#material_id_select').prop('required', true);
                }
            }

            // Refresh selectpicker if used
            if ($('.show-tick').length > 0) {
                $('.show-tick').selectpicker('refresh');
            }
        }

        function submitStatement() {
            var category = $('#report_category').val();
            var supplier = $('#supplier_id_select').val();
            var format = $('#report_type_statement').val();

            if (!supplier) {
                alert('Please select a supplier');
                return;
            }
            if (format === "" || format === null) {
                alert('Please select a format');
                return;
            }

            // Create a temporary form to submit
            var form = $('<form></form>');
            form.attr("method", "post");
            form.attr("action", "{{ url('/materialreports') }}");
            form.append('<input type="hidden" name="_token" value="{{ csrf_token() }}">');
            form.append('<input type="hidden" name="type" value="7">');
            form.append('<input type="hidden" name="supplier_id" value="' + supplier + '">');
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
