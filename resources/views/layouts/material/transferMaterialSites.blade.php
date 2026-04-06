@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Transfer Material Through Sites'])
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

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                @if (checkmodulepermission(3, 'can_add') == 1 && $entry_at_site == 'all') 
                    <div class="modal-content">
                        <div class="modal-body">
                            <form method="post" action="{{ url('/newMaterialTransferForm') }}" enctype="multipart/form-data">
                                @csrf
                                <hr>                                
                                        <div class="row clearfix">
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>From Site</label>
                                                    <select name="from_site" id="site_id" onchange="sitechanges()" class="form-control show-tick"
                                                        data-live-search="true" required>
                                                        <option value="" selected disabled>--Select Site--</option>

                                                      
                                                            @foreach ($sites as $site)
                                                              
                                                                    <option  value="{{ $site->id }}">
                                                                        {{ $site->name }}</option>
                                                               
                                                            @endforeach
                                                 

                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>To Site</label>
                                                    <select name="to_site"   class="form-control show-tick"
                                                        data-live-search="true" required>
                                                        <option value="" selected disabled>--Select Site--</option>

                                                      
                                                            @foreach ($sites as $site)
                                                              
                                                                    <option  value="{{ $site->id }}">
                                                                        {{ $site->name }}</option>
                                                               
                                                            @endforeach
                                                    
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Material</label>
                                                    <select name="material_id" id="material_id" onchange="materialchanges()" class="form-control show-tick"
                                                        data-live-search="true" required>
                                                    <option value="" selected disabled>--Select Site First</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Unit</label>
                                                    <select name="unit" id="unit_id" class="form-control show-tick"
                                                        data-live-search="true" required>
                                                       <option value="" selected disabled>--Select Material First</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Quantity</label>
                                                    <input type="number" placeholder="0.00" required
                                                       class="form-control" name="qty"
                                                        min="0" step="0.01" pattern="^\d+(?:\.\d{1,2})?$">

                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Vehicle No.</label>
                                                    <input type="text" class="form-control" required name="vehicle_no"                                                      
                                                        placeholder="Enter The Vehicle No.">
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Remark</label>
                                                    <input type="text" class="form-control" name="remark"
                                                       
                                                        placeholder="Enter The Remark (If Any)">
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Date</label>
                                                    <input type="date" required class="form-control"
                                                        min="{{ $min_date }}" max="{{ $max_date }}"
                                                       name="date">
                                                </div>
                                            </div>
                                            <div class="col-lg-1 col-md-1 col-sm-1">
                                                <div class="form-group">
                                                    <br>
                                                </div>
                                            </div>
                                        </div>
                               
                                <hr>
                                <div class="row clearfix">
                                    <div class="col-lg-9 col-md-9 col-sm-9">
                                    </div>
                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <button type="submit"
                                                class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="alert alert-danger">You Don't Have Permission to Transfer </div>
                @endif
            </div>
        </div>
    </div>

@endsection
@section('scripts')
    <script type="text/javascript">
        let stockData = @json($material_stock_record); // Full stock data
        function sitechanges() {
            var site_id = $('#site_id').val();
            $('#material_id').empty(); 

         var new_material_html = '';
         new_material_html += '<option selected disabled value="">-- Select Material --</option>';

         var new_unit_html = '';
                new_unit_html += '<option selected disabled value="">-- Select Material First --</option>';
                $('#unit_id').append(new_unit_html).val(null).trigger('change');
                    $('#unit_id').selectpicker('refresh');

            if (site_id) {
                let filteredMaterials = [...new Map(
                    stockData
                    .filter(stock => stock.site_id == site_id)
                    .map(stock => [stock.material_id, {
                        id: stock.material_id,
                        name: stock.material_name
                    }])
                ).values()];
                if (filteredMaterials.length) {
                    $('#material_id').prop('disabled', false);
                    $.each(filteredMaterials, function(key, material) {
                        new_material_html += '<option value="' + material.id + '">' + material.name +
                            '</option>';
                    });
                }
                $('#material_id').append(new_material_html).val(null).trigger('change');
                $('#material_id').selectpicker('refresh');
            }
        }

        function materialchanges() {
            let site_id = $('#site_id').val();
                let material_id = $('#material_id').val();
                $('#unit_id').empty(); 

                var new_unit_html = '';
                new_unit_html += '<option selected disabled value="">-- Select Unit --</option>';

                if (site_id && material_id) {
                    let filteredUnits = [...new Map(
                        stockData
                            .filter(stock => stock.site_id == site_id && stock.material_id == material_id)
                            .map(stock => [stock.unit, { unit: stock.unit, unit_name: stock.unit_name }])
                    ).values()];

                    if (filteredUnits.length) {
                        $('#unit').prop('disabled', false);
                        $.each(filteredUnits, function (key, unit) {
                            new_unit_html +=  '<option value="' + unit.unit + '">' +  unit.unit_name + '</option>';
                        });
                    }
                    $('#unit_id').append(new_unit_html).val(null).trigger('change');
                    $('#unit_id').selectpicker('refresh');
                }
        }
        </script>
        @endsection