@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Material Stock Dashboard'])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Material Stock Dashboard</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Material stock will be listed here.</div>
                    </h2>
                    <div class="align-center">
                        <div class="card">
                            <div class="row-clearfix"
                                style="display:flex;  box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
  transition: 0.3s; padding:20px 10px 20px 10px;    align-items: anchor-center;">
                                <div class="col-3">
                                    <h6>Showing Data Of : <br><span id="show_data_title">{{ session()->get('site_id') ? getSiteDetailsById(session()->get('site_id'))->name : 'Whole Company' }}</span></h6>
                                </div>
                                <div class="col-3">
                                    <label>Change Display Site</label><br><sub>Show Data According To Specific Site</sub>
                                    <select name="from_site" id="site_id" onchange="sitechanges()"
                                        class="form-control show-tick" data-live-search="true" required
                                        {{ session()->get('site_id') ? 'disabled' : '' }}>
                                        <option value="" {{ !session()->get('site_id') ? 'selected' : '' }} disabled>--Select Site--</option>
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}" {{ session()->get('site_id') == $site->id ? 'selected' : '' }}>
                                                {{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-3">
                                    <label>Change Display Material</label><br>
                                    <sub>Show Data According To Specific Material</sub>
                                    <select name="from_site" id="material_id" onchange="materialchanges()"
                                        class="form-control show-tick" data-live-search="true" required>
                                        <option value="" selected disabled>--Select Material--</option>
                                        @foreach ($materials as $mat)
                                            <option value="{{ $mat->id }}">
                                                {{ $mat->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-3">
                                    <button type="button" style="display: none;" onclick="showWholeCompany();"
                                        id="showWholeCompany_btn"
                                        class="btn btn-success btn-fill btn-round waves-effect"><a>Show Data Of Whole
                                            Company</a></button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                @if (checkmodulepermission(3, 'can_view') == 1)
                    <div class="body">
                        <div id="consumption_view" class="table-responsive">

                            <table id="dataTable" class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Site</th>
                                        <th>Material</th>
                                        <th>Quantity</th>
                                        <th>Unit</th>
                                        <th>Show Transactions</th>
                                    </tr>
                                </thead>

                                <tbody id="matTableBody">
                                    @php

                                        $i = 1;
                                    @endphp
                                    @foreach ($whole_comp_data as $dd)
                                        <tr>
                                            <td>{{ $i++ }}

                                            </td>

                                            <td>
                                                {{ $dd->site_name }}
                                            </td>
                                            <td>
                                                {{ $dd->material_name }}
                                            </td>
                                            <td>
                                                {{ $dd->qty }}
                                            </td>
                                            <td>
                                                {{ $dd->unit_name }}
                                            </td>
                                            <td>
                                                <button title="View" type="button" onclick="viewTransactions({{$dd->material_id}},{{$dd->site_id}},{{$dd->unit}})" style="all:unset;"><i class="zmdi zmdi-view-list-alt"></i> </button> &nbsp;

                                            </td>
                                        </tr>
                                    @endforeach

                                </tbody>


                            </table>

                        </div>

                    </div>
                @else
                    <div class="alert alert-danger"> You Don't Have Permission to View !!</div>
                @endif
            </div>
        </div>
    </div>

@endsection
@section('scripts')
<script>
    let site_data = @json($site_wise_data);
    let mat_data = @json($material_wise_data);
    let whole_data = @json($whole_comp_data);

    function sitechanges() {
        var site_id = $('#site_id').val();
        document.getElementById('matTableBody').innerHTML = '';

        if (site_id) {
            var newData = [];
            var table = $('#dataTable').DataTable();
            table.clear();
            let filtered_data = site_data.find(item =>
                Number(item.site_id) === Number(site_id));
            count = 1;
            let site_name = filtered_data['site_name'];
            filtered_data['stock'].forEach((element) => {

                var newDat = [count, site_name, element['material_name'], element[
                    'qty'], element['unit_name'],
                   '<button title="View" type="button" onclick="viewTransactions('+element['material_id']+','+element['site_id']+','+element['unit']+')" style="all:unset;"><i class="zmdi zmdi-view-list-alt"></i> </button>'
                ];

                newData.push(newDat);
                count++;
            });
            table.rows.add(newData);
            table.draw();
            @if(!session()->get('site_id'))
                $('#showWholeCompany_btn').css('display', 'block');
            @endif
            $('#show_data_title').text("Site - "+site_name);
        }

    }
    function materialchanges() {
        var material_id = $('#material_id').val();
        document.getElementById('matTableBody').innerHTML = '';

        if (material_id) {
            var newData = [];
            var table = $('#dataTable').DataTable();
            table.clear();
            let filtered_data = mat_data.find(item =>
                Number(item.material_id) === Number(material_id));
            count = 1;
            let material_name = filtered_data['material_name'];
            filtered_data['stock'].forEach((element) => {

                var newDat = [count, element['site_name'], material_name, element[
                    'qty'], element['unit_name']
                ,
                   '<button title="View" type="button" onclick="viewTransactions('+element['material_id']+','+element['site_id']+','+element['unit']+')" style="all:unset;"><i class="zmdi zmdi-view-list-alt"></i> </button>'
                ];

                newData.push(newDat);
                count++;
            });
            table.rows.add(newData);
            table.draw();
            @if(!session()->get('site_id'))
                $('#showWholeCompany_btn').css('display', 'block');
            @endif
            $('#show_data_title').text("Material - "+material_name);     
        }

    }
    function showWholeCompany(){

        document.getElementById('matTableBody').innerHTML = '';

       
            var newData = [];
            var table = $('#dataTable').DataTable();
            table.clear();
    
            count = 1;

            whole_data.forEach((element) => {

                var newDat = [count, element['site_name'], element['material_name'], element[
                    'qty'], element['unit_name'],
                    '<button title="View" type="button" onclick="viewTransactions('+element['material_id']+','+element['site_id']+','+element['unit']+')" style="all:unset;"><i class="zmdi zmdi-view-list-alt"></i> </button>'];

                newData.push(newDat);
                count++;
            });
            table.rows.add(newData);
            table.draw();
            $('#showWholeCompany_btn').css('display', 'none');       
            $('#show_data_title').text("Whole Company");     
    }
    function viewTransactions(mat_id,site_id,unit){
        var url = "{{ url('/view_mat_transaction/?mat_id=') }}" + mat_id + "&site_id="+site_id+"&unit="+unit;
        window.location.href = url;
    }
</script>
@endsection