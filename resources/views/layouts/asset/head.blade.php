@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Assets List'])
    @php
        $edit = false;
        $dataarray = json_decode($data, true);
        if (isset(json_decode($data, true)['edit_data'])) {
            $editdata = $dataarray['edit_data'][0];
            $edit = true;
        }
    @endphp

    @php
        $heads = getallAssetHeads();
        $sites = getallActivesites();

    @endphp

    <div class="row clearfix">
        @if ($edit)
            @if (checkmodulepermission(5, 'can_edit') == 1)
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="card project_list">

                        <form action="{{ url('/updateassethead') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit Asset List</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="row clearfix">
                                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                            <label for="Name">Name</label>
                                        </div>
                                        <div class="col-lg-8 col-md-8 col-sm-8">
                                            <div class="form-group">
                                                <input type="hidden" name="id" value="{{ $editdata['id'] }}">
                                                <input type="text" id="Name" required class="form-control"
                                                    value="{{ $editdata['name'] }}" name="name"
                                                    placeholder="Enter the Asset Head Name">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Update</a></button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            @endif
            <br>
        @endif
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Asset</strong> List</h2>

                    <ul class="header-dropdown" style="    display: flex;align-items: center;">

                        <li>
                            @if (checkmodulepermission(5, 'can_view') == 1)
                                <div class="card" style="width: auto;">
                                    <div class="body" style="    text-align-last: center;">
                                        @if ($showing_data == 'all')
                                            Showing Assets Of :- <u>Whole Company</u>
                                        @else
                                            @php $sitedetail = getSiteDetailsById($showing_data)->name;@endphp
                                            Showing Assets Of :- <u>{{ $sitedetail }}</u>
                                        @endif
                                        <hr>
                                        <form method="post" action="{{ url('/search_asset_head_sites') }}">
                                            @php
                                                $sites = getallsites();
                                            @endphp
                                            @csrf
                                            <select class="form-control show-tick" data-live-search="true" required
                                                name="display_site">
                                                <option selected value=""> Change Display Site</option>
                                                @foreach ($sites as $dd)
                                                    <option value="{{ $dd->id }}">{{ $dd->name }}</option>
                                                @endforeach
                                            </select>

                                            <button type="submit" class="btn btn-primary  btn-round waves-effect"
                                                style="color:white !important;">Search</button>

                                        </form>

                                    </div>

                                </div>
                            @endif
                        </li>
                        <li>

                            @if ($showing_data != 'all')
                                @if (checkmodulepermission(5, 'can_view') == 1)
                                    <a href="{{ url('/asset_head') }}" type="button"
                                        class="btn btn-primary  btn-round waves-effect" style="color:white !important;">Show
                                        All Assets</a>
                                @endif
                            @endif
                            @if (checkmodulepermission(5, 'can_add') == 1)
                                <button class="btn btn-primary" data-toggle="modal" data-target="#newassets" type="button">
                                    <span class=" text-white"> Add New Asset </span>
                                </button>
                            @endif



                            @if (checkmodulepermission(5, 'can_add') == 1)
                                <button class="btn btn-primary m-l-10" data-toggle="modal" data-target="#newAssethead1"
                                    type="button">
                                    <span class=" text-white"> Add Asset Head </span>
                                </button>
                            @endif
                        </li>
                    </ul>
                </div>
                <br><br> <br><br>

                <div class="body">
                    @if (checkmodulepermission(5, 'can_view') == 1)
                        @if ($showing_data == 'all')
                            <div class="table-responsive">
                                <table id="dataTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>

                                            <th>Name</th>
                                            <th>Count</th>

                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 1;
                                        @endphp
                                        @foreach ($dataarray as $dd)
                                            @php
                                                $ddid = $dd['id'];
                                            @endphp

                                            <tr>
                                                <td>{{ $i++ }}</td>
                                                <td>
                                                    <a class="single-user-name" href="#">{{ $dd['name'] }}</a>
                                                </td>
                                                <td>
                                                    <a class="single-user-name"
                                                        href="#">{{ getAssetHeadUsageCount($dd['id']) }}</a>
                                                </td>

                                                <td>

                                                    <a title="View" href="{{ url('/asset/?asset_id=') . $ddid }}"><i
                                                            class="zmdi zmdi-eye"></i>
                                                    </a>&nbsp;
                                                    @if (checkmodulepermission(5, 'can_edit') == 1)
                                                        <button title="Edit" onclick="editdata('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-edit"></i> </button>
                                                        &nbsp;
                                                    @endif
                                                    @if (checkmodulepermission(5, 'can_delete') == 1)
                                                        <button title="Delete" onclick="deletedata('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-delete"></i> </button>
                                                    @endif
                                                </td>

                                            </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table id="dataTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Head</th>
                                            <th>Cost Price</th>
                                            <th>Site Name</th>
                                            <th>Status</th>
                                            <th>At Current Site Since</th>
                                            <th>Purchase Date</th>
                                            <th>Action</th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $i = 1;
                                            $data = json_decode($data, true);
                                        @endphp

                                        @foreach ($data as $dd)
                                            @if (!empty($dd))
                                                @php
                                                    $ddid = $dd['id'];
                                                @endphp

                                                <tr>
                                                    <td>{{ $i++ }}</td>
                                                    <td>
                                                        {{ $dd['name'] }}
                                                    </td>
                                                    <td>
                                                        {{ $dd['head'] }}
                                                    </td>
                                                    <td>
                                                        @if ($dd['status'] == 'Sold')
                                                            Cost Price - {{ $dd['cost_price'] }}<br>
                                                            Sale Price - {{ $dd['sale_price'] }}
                                                        @else
                                                            {{ $dd['cost_price'] }}
                                                        @endif


                                                    </td>
                                                    <td>
                                                        {{ $dd['site'] }}
                                                    </td>
                                                    <td>
                                                        {{ $dd['status'] }}
                                                    </td>


                                                    <td>
                                                        @if ($dd['status'] != 'Sold')
                                                            <a class="single-user-name"
                                                                href="#">{{ getAssetLastTransfer($dd['id'])->create_datetime }}</a>
                                                        @else
                                                            Already sold
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($dd['status'] == 'Sold')
                                                            Purchase - {{ $dd['create_datetime'] }}<br>
                                                            Sold - {{ getAssetLastTransfer($dd['id'])->create_datetime }}
                                                        @else
                                                            {{ $dd['create_datetime'] }}
                                                        @endif
                                                    </td>

                                                    <td>
                                                        @if (checkmodulepermission(5, 'can_edit') == 1)
                                                            <a
                                                                href="{{ url('/assetTransferHistory/?asset_id=') . $ddid }}"><img
                                                                    src="{{ asset('/images/transfer_history.png') }}"
                                                                    style="width:20px" /></a>&nbsp;
                                                        @endif

                                                        @if ($dd['status'] != 'Sold')
                                                            @if (checkmodulepermission(5, 'can_edit') == 1)
                                                                <button
                                                                    onclick="transferassetmodel({{ $ddid }}, {{ $dd['site_id'] }},{{ $dd['head_id'] }})"
                                                                    title="Transfer Asset" style="all:unset"><img
                                                                        src="{{ asset('/images/transfer.png') }}"
                                                                        style="width:20px" />
                                                                </button> &nbsp;
                                                                <button type="button" title="Sell Asset"
                                                                    onclick="soldassetmodel('{{ $ddid }}', {{ $dd['site_id'] }},{{ $dd['head_id'] }})"
                                                                    style="all:unset"><img
                                                                        src="{{ asset('/images/sold.png') }}"
                                                                        style="width:30px" />
                                                                </button>
                                                            @endif
                                                        @endif

                                                    </td>

                                                </tr>
                                            @endif
                                        @endforeach

                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @else
                        <div class="alert alert-danger"> You Don't Have Permission to View </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@section('models')




    @if (checkmodulepermission(5, 'can_add') == 1)
        <div class="modal fade" id="newassets" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <form action="{{ url('/add_newassets') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New Asset </h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <label for="Site Name"> Site Name </label>
                                    <div class="form-group">
                                        <select name="site_id" id="site_id" class="form-control show-tick"
                                            data-live-search="true" required>
                                            <option value="" selected disabled>--Select Site--</option>
                                            @foreach ($sites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                                            @endforeach


                                        </select>
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <label for="Site Name"> Head Name </label>
                                    <div class="form-group">
                                        <select name="head_id" id="head_id" class="form-control show-tick"
                                            data-live-search="true" required>
                                            <option value="" selected disabled>--Select Head --</option>
                                            @foreach ($heads as $head)
                                                <option value="{{ $head->id }}">{{ $head->name }}</option>
                                            @endforeach

                                        </select>
                                    </div>
                                </div>


                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Name</label>
                                        <input type="text" name="assetsname" id="assetsname" required
                                            class="form-control" placeholder="Enter Asset Name ">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Cost Price </label>
                                        <input type="number" id="costprice" required class="form-control"
                                            name="costprice" value="0" min="0" step="0.001"
                                            pattern="^\d+(?:\.\d{1,4})?$" placeholder="Enter Cost Price ">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit"
                                class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE</a></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif




    @if (checkmodulepermission(5, 'can_add') == 1)
        <div class="modal fade" id="newAssethead1" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/addassethead') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New Asset Head</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="Name">Name</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="text" id="Name" required class="form-control"
                                            name="name" placeholder="Enter the Asset Head Name">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE
                                    CHANGES</a></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
@section('scripts')
    <script type="text/javascript">
        function deletedata(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/delete_asset_head/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function editdata(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Head ?",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#eda61a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Edit',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/edit_asset_head/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }
    </script>
@endsection
