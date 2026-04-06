@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'New Material Consumption / Wastage'])
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

                <div class="modal-content">
                    @if (checkmodulepermission(3, 'can_add') == 1)

                        <div class="modal-body">
                            <form method="post" action="{{ url('/add_new_consumption') }}" enctype="multipart/form-data">
                                @csrf
                                <hr>
                                <div class="row clearfix">
                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <img height= "150" width="150" id="user_image"
                                                src="{{ asset('/images/expense.png') }}" class="rounded-circle img-raised">
                                            <input type="file" accept="Image/*" name="image[]"
                                                onchange="document.getElementById('user_image').src = window.URL.createObjectURL(this.files[0])">
                                        </div>
                                    </div>
                                    <div class="col-lg-9 col-md-9 col-sm-9">
                                        <div class="row clearfix">
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Choose Type (Consumption / Wastage)</label>
                                                    <select name="consumption_wastage[]" onchange="consumption_wastage_changes(1);" id="consumption_wastage_1"
                                                        class="form-control show-tick" data-live-search="true" required>
                                                        <option value="Consumption" selected >Consumption
                                                        </option>
                                                        <option value="Wastage"  >Wastage
                                                        </option>

                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Site</label>
                                                    <select name="site_id[]" id="site_id_1" onchange="sitechanges(1);"
                                                        class="form-control show-tick" data-live-search="true" required>
                                                        <option value="" selected disabled>--Select Site--</option>
                                                        @if ($entry_at_site == 'current')
                                                            <option  value="{{ $site_id }}">
                                                                {{ getSiteDetailsById($site_id)->name }}
                                                            </option>
                                                        @else
                                                            @foreach ($sites as $site)
                                                                <option value="{{ $site->id }}">{{ $site->name }}
                                                                </option>
                                                            @endforeach
                                                        @endif

                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Material</label>
                                                    <select name="material_id[]" onchange="materialchanges(1);" id="material_id_1"
                                                        class="form-control show-tick" data-live-search="true" required>
                                                        <option value="" selected disabled>--Select Site First--
                                                        </option>

                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Unit</label>
                                                    <select name="unit[]" id="unit_id_1" class="form-control show-tick"
                                                        data-live-search="true" required>
                                                        <option value="" selected disabled>--Select Material First--
                                                        </option>

                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Quantity</label>
                                                    <input type="number" placeholder="0.00" required class="form-control"
                                                        name="qty[]" step="0.01" pattern="^\d+(?:\.\d{1,2})?$">

                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3" style="display:none;" id="reason_container_1">
                                                <div class="form-group">
                                                    <label>Reason</label>
                                                    <input type="text" class="form-control" id="reason_input_1" name="reason[]"
                                                        placeholder="Enter The Reason Of Wastage">
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Remark</label>
                                                    <input type="text" class="form-control" name="remark[]"
                                                        placeholder="Enter The Remark (If Any)">
                                                </div>
                                            </div>
                                      
                                            <div class="col-lg-3 col-md-3 col-sm-3">
                                                <div class="form-group">
                                                    <label>Date</label>
                                                    <input type="date" required class="form-control"
                                                        min="{{ $min_date }}" max="{{ $max_date }}"
                                                        value="{{ $today }}" name="date[]">

                                                </div>
                                            </div>
                                            <div class="col-lg-1 col-md-1 col-sm-1">
                                                <div class="form-group">
                                                    <br>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="rowData">
                                </div>
                                <hr>
                                <div class="row clearfix">
                                    <div class="col-lg-9 col-md-9 col-sm-9">
                                    </div>
                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <button type="button" id="addrow"
                                                class="btn btn-primary btn-simple btn-round waves-effect"><i
                                                    class='zmdi zmdi-plus' style='color: white;'></i></button>
                                            <button type="submit"
                                                class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="alert alert-danger"> You Don't Have Permission to Add </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
@section('scripts')
    <script type="text/javascript">
        let stockData = @json($material_stock_record); // Full stock data
        function sitechanges(id) {
            var site_id = $('#site_id_' + id).val();
            $('#material_id_' + id).empty(); 

         var new_material_html = '';
         new_material_html += '<option selected disabled value="">-- Select Material --</option>';

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
                    $('#material_id_' + id).prop('disabled', false);
                    $.each(filteredMaterials, function(key, material) {
                        new_material_html += '<option value="' + material.id + '">' + material.name +
                            '</option>';
                    });
                }
                $('#material_id_' + id).append(new_material_html).val(null).trigger('change');
                $('#material_id_' + id).selectpicker('refresh');
            }
        }

        function materialchanges(id) {
            let site_id = $('#site_id_' + id).val();
                let material_id = $('#material_id_' + id).val();
                $('#unit_id_' + id).empty(); 

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
                    $('#unit_id_' + id).append(new_unit_html).val(null).trigger('change');
                    $('#unit_id_' + id).selectpicker('refresh');
                }
        }
function consumption_wastage_changes(id){
    let consumption_wastage_val = $('#consumption_wastage_' + id).val();
    if(consumption_wastage_val == 'Wastage'){

        $('#reason_container_' + id).css('display', 'block');
        $('#reason_input_' + id).attr('required', true);
    
    }else{
        $('#reason_container_' + id).css('display', 'none');

        $('#reason_input_' + id).removeAttr('required');
    }

}

        var count = 1;
        $('#addrow').click(function() {

            count++;
            var consum_was_html = '<select name="consumption_wastage[]" onchange="consumption_wastage_changes('+count+');" id="consumption_wastage_'+count+'" class="form-control show-tick" data-live-search="true" required><option value="Consumption" selected >Consumption</option><option value="Wastage"  >Wastage</option></select>';
            var site_html = '<select name="site_id[]" onchange="sitechanges('+count+')" id="site_id_' + count +
                '" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Site--</option> @if ($entry_at_site == 'current')<option value="{{ $site_id }}">{{ getSiteDetailsById($site_id)->name }}</option>@else @foreach ($sites as $site)<option value = "{{ $site->id }}">{{ $site->name }}</option>@endforeach @endif</select>';
            var material_html = '<select name="material_id[]" onchange="materialchanges('+count+')" id="material_id_' + count +
                '"  class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Site First--</option></select>';
            var unit_html = '<select name="unit[]" id="unit_id_' + count +
                '" class="form-control show-tick" data-live-search="true" required><option value="" selected disabled >--Select Material First--</option></select>';
            var result = '<div id="row_' + count +
                '"><hr><div class="row clearfix"><div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><img height= "150" width="150" id="' +
                count + '" src=' + "{{ asset('/images/expense.png') }}" +
                '  class="rounded-circle img-raised"> <input type="file" accept="Image/*" name="image[]" onchange="document.getElementById(' +
                count + ').src = window.URL.createObjectURL(this.files[0])"></div></div>';
                result +=
                '<div class="col-lg-9 col-md-9 col-sm-9"><div class="row clearfix"><div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Choose Type (Consumption / Wastage)</label>' +
                    consum_was_html + '</div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Site</label>' +
                site_html + '</div></div>';

            result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Material</label>' +
                material_html + '</div></div>';
            result += '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Unit</label>' +
                unit_html + '</div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Quantity</label><input type="number" placeholder="0.00" required class="form-control" name="qty[]" min="0"  step="0.01" pattern="^\d+(?:\.\d{1,2})?$"></div></div>';
                result += '<div class="col-lg-3 col-md-3 col-sm-3" style="display:none;" id="reason_container_'+count+'"><div class="form-group"><label>Reason</label><input type="text" class="form-control" id="reason_input_'+count+'" name="reason[]" placeholder="Enter The Reason Of Wastage"></div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Remark</label><input type="text" class="form-control" name="remark[]" placeholder="Enter The Remark (If Any)"></div></div>';
            result +=
                '<div class="col-lg-3 col-md-3 col-sm-3"><div class="form-group"><label>Date</label><input type="date" required class="form-control" min="{{ $min_date }}" max="{{ $max_date }}" value="{{ $today }}" name="date[]" ></div></div>';
            result +=
                '<div class="col-lg-1 col-md-1 col-sm-1"><div class="form-group"><br><button type="button" onclick="deleterow(' +
                count +
                ')" class="btn btn-primary btn-simple btn-round waves-effect"><i class="zmdi zmdi-minus"  style="color: white;"></i></button></div></div></div></div></div></div>';
            console.log(result);
            $('#rowData').append(result);
            $("#site_id_" + count).selectpicker({
                liveSearch: true
            });

            $("#material_id_" + count).selectpicker({
                liveSearch: true
            });
            $("#unit_id_" + count).selectpicker({
                liveSearch: true
            });
        });

        function deleterow(id) {
            $('#row_' + id).remove();
        }
    </script>
@endsection
