@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Material Unit Conversion'])
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
                @if (checkmodulepermission(3, 'can_add') == 1)
                    <div class="modal-content">
                        <div class="modal-body">
                            <form method="post" action="{{ url('/newStockUnitConversionForm') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" id="site_id" name="site_id" value="{{ $site_id }}">
                                <hr>
                                <div class="row clearfix">
                                    {{-- Site selection removed: defaulting to session site_id on submit --}}

                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <label>Material</label>
                                            <select name="material_id" id="material_id" onchange="materialchanges()"
                                                class="form-control show-tick" data-live-search="true" required>
                                                <option value="" selected disabled>--Select Material--</option>
                                                @foreach($materials as $mat)
                                                    <option value="{{ $mat->id }}">{{ $mat->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <label>From Unit</label>
                                            <select name="from_unit" id="from_unit" onchange="fromunitchanges();"
                                                class="form-control show-tick" data-live-search="true" required>
                                                <option value="" selected disabled>--Select Material First</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <label>To Unit</label>
                                            <select name="to_unit" id="to_unit" onchange="tounitchanges();"
                                                class="form-control show-tick" data-live-search="true" required>
                                                <option value="" selected disabled>--Select Material First</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <label>Quantity</label>
                                            <input type="number" placeholder="0.00" id="qty" onchange="qtychanges();"
                                                required class="form-control" name="qty" min="0" step="0.01"
                                                pattern="^\d+(?:\.\d{1,2})?$">

                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-3 col-sm-3">
                                        <div class="form-group">
                                            <label>Updated Quantity</label>
                                            <input type="number" placeholder="0.00" id="updated_qty" required
                                                class="form-control" name="updated_qty" min="0" step="0.01"
                                                pattern="^\d+(?:\.\d{1,2})?$">

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
                                            <input type="date" required class="form-control" min="{{ $min_date }}"
                                                max="{{ $max_date }}" name="date">
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
        let conversion_rules = @json($conversion_format);
        let allUnits = @json($units ?? []);
        var conversion_factor = 0;

        // Debug: show loaded data in console for troubleshooting
        console.log('Loaded stockData count:', Array.isArray(stockData) ? stockData.length : 0);
        console.log('Loaded allUnits count:', Array.isArray(allUnits) ? allUnits.length : 0);
        console.log('Loaded conversion_rules count:', Array.isArray(conversion_rules) ? conversion_rules.length : 0);
        console.log('stockData sample (first 10):', Array.isArray(stockData) ? stockData.slice(0, 10) : stockData);

        // Populate both unit selects using unique units from stockData (fallback to allUnits)
        function populateAllUnits() {
            let unitMap = {};
            // prefer units present in stockData (so users see only relevant units)
            if (Array.isArray(stockData) && stockData.length > 0) {
                stockData.forEach(s => {
                    if (s.unit && !(s.unit in unitMap)) {
                        unitMap[s.unit] = s.unit_name || s.unit;
                    }
                });
            }

            // fallback to master units list when no stockData units found
            if (Object.keys(unitMap).length === 0 && Array.isArray(allUnits)) {
                allUnits.forEach(u => {
                    unitMap[u.id] = u.name;
                });
            }

            let new_unit_html = '<option selected disabled value="">-- Select Unit --</option>';
            Object.keys(unitMap).forEach(function(unitId) {
                new_unit_html += '<option value="' + unitId + '">' + unitMap[unitId] + '</option>';
            });

            $('#from_unit').empty().append(new_unit_html).val(null).trigger('change');
            $('#to_unit').empty().append(new_unit_html).val(null).trigger('change');
            if (typeof $ !== 'undefined' && $.fn.selectpicker) {
                    $('#from_unit').selectpicker('refresh');
                    $('#to_unit').selectpicker('refresh');
                    setTimeout(function() { $('#from_unit').selectpicker('refresh'); $('#to_unit').selectpicker('refresh'); }, 50);
            }
        }

        // Call once on load so From/To units show relevant units without material selection
        $(document).ready(function() {
            populateAllUnits();
        });

        function materialchanges() {
            let material_id = $('#material_id').val();
            let selectedText = $('#material_id option:selected').text().trim();

            var new_unit_html = '<option selected disabled value="">-- Select Unit --</option>';
            $('#to_unit').empty().append(new_unit_html).val(null).trigger('change');
            if (typeof $ !== 'undefined' && $.fn.selectpicker) {
                $('#to_unit').selectpicker('refresh');
                setTimeout(function() { $('#to_unit').selectpicker('refresh'); }, 50);
            }

            let unitMap = {};
            if (material_id && Array.isArray(stockData)) {
                stockData.forEach(s => {
                    if (!s) return;
                    let matchesId = s.material_id !== undefined && String(s.material_id) === String(material_id);
                    let matchesName = s.material_name !== undefined && String(s.material_name).trim() === selectedText;
                    if (matchesId || matchesName) {
                        unitMap[s.unit] = s.unit_name || s.unit;
                    }
                });
            }

            if (Array.isArray(allUnits)) {
                allUnits.forEach(u => {
                    if (!(u.id in unitMap)) {
                        unitMap[u.id] = u.name;
                    }
                });
            }

            Object.keys(unitMap).forEach(function(u) {
                new_unit_html += '<option value="' + u + '">' + unitMap[u] + '</option>';
            });

            $('#from_unit').empty().append(new_unit_html).val(null).trigger('change').prop('disabled', false);
            if (typeof $ !== 'undefined' && $.fn.selectpicker) {
                $('#from_unit').selectpicker('refresh');
                setTimeout(function() { $('#from_unit').selectpicker('refresh'); }, 50);
            }
        }

        function fromunitchanges() {
            let site_id = $('#site_id').val();
            let material_id = $('#material_id').val();
            let from_unit_id = $('#from_unit').val();
            $('#to_unit').empty();

            var new_unit_html = '';
            new_unit_html += '<option selected disabled value="">-- Select Unit --</option>';


            if (material_id && from_unit_id) {
                console.log('fromunitchanges called, material_id:', material_id, 'from_unit_id:', from_unit_id);
                console.log('conversion_rules sample count:', Array.isArray(conversion_rules) ? conversion_rules.length : 0, conversion_rules.slice ? conversion_rules.slice(0,5) : conversion_rules);

                let filteredUnits = [...new Map(
                    conversion_rules
                    .filter(rule => String(rule.material_id) === String(material_id) && String(rule.from_unit) === String(from_unit_id))
                    .map(rule => [rule.to_unit, {
                        unit: rule.to_unit,
                        unit_name: rule.to_unit_name
                    }])
                ).values()];

                console.log('filteredUnits (by rule):', filteredUnits.length, filteredUnits);

                if (filteredUnits.length) {
                    $('#to_unit').prop('disabled', false);
                    $.each(filteredUnits, function(key, unit) {
                        new_unit_html += '<option value="' + unit.unit + '">' + unit.unit_name + '</option>';
                    });
                } else {
                    // Fallback: show all units (except from_unit) so user can still pick a target unit
                    console.log('No conversion rules found for this from_unit; falling back to allUnits');
                    if (Array.isArray(allUnits)) {
                        allUnits.forEach(function(u) {
                            if (String(u.id) !== String(from_unit_id)) {
                                new_unit_html += '<option value="' + u.id + '">' + u.name + '</option>';
                            }
                        });
                        $('#to_unit').prop('disabled', false);
                    }
                }

                $('#to_unit').append(new_unit_html).val(null).trigger('change');
                if (typeof $ !== 'undefined' && $.fn.selectpicker) {
                    $('#to_unit').selectpicker('refresh');
                    setTimeout(function() { $('#to_unit').selectpicker('refresh'); }, 50);
                }
            }
        }

        function recalculate(isUnitChange = false) {
            let qty = parseFloat($('#qty').val());
            if (!isNaN(qty) && conversion_factor !== null) {
                var updated_qty = (qty * conversion_factor);
                $('#updated_qty').val(updated_qty.toFixed(2));
            } else {
                if (isUnitChange || isNaN(qty)) {
                    $('#updated_qty').val('');
                }
            }
        }

        function tounitchanges() {
            let material_id = $('#material_id').val();
            let from_unit_id = $('#from_unit').val();
            let to_unit_id = $('#to_unit').val();

            const result = conversion_rules.find(item =>
                Number(item.material_id) === Number(material_id) &&
                Number(item.from_unit) === Number(from_unit_id) &&
                Number(item.to_unit) === Number(to_unit_id)
            );
            conversion_factor = result ? parseFloat(result.conversion_factor) : null;
            recalculate(true);
        }

        $('#qty').on('input change', function() {
            recalculate(false);
        });
    </script>
@endsection
