@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Assets'])

    @php
    $edit = false;
    $dataarray = json_decode($data, true);
    if (isset(json_decode($data, true)['edit_data'])) {
        $editdata = $dataarray['edit_data'][0];
        $edit = true;
    }
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
                    <h2><strong>Assets</strong> List</h2>
                </div>
                @if(checkmodulepermission(5,'can_view') == 1)
                <div class="body">
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
                                                @if( $dd['status'] == "Sold")
                                                Cost Price - {{ $dd['cost_price']}}<br>
                                                Sale Price - {{ $dd['sale_price']}}
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
                                                @if( $dd['status'] != "Sold")
                                                <a class="single-user-name"
                                                    href="#">{{ getAssetLastTransfer($dd['id'])->create_datetime }}</a>
                                                    @else
                                                    Already sold
                                                    @endif
                                            </td>
                                            <td>
                                                @if( $dd['status'] == "Sold")
                                                Purchase - {{ $dd['create_datetime'] }}<br>
                                                Sold - {{ getAssetLastTransfer($dd['id'])->create_datetime }}
                                                @else
                                                {{ $dd['create_datetime'] }}
                                                @endif
                                            </td>

                                            <td>
                                            @if(checkmodulepermission(5,'can_edit') == 1)
                                                <a href="{{ url('/assetTransferHistory/?asset_id=') . $ddid }}"><img
                                                    src="{{ asset('/images/transfer_history.png') }}"
                                                    style="width:20px" /></a>&nbsp;
                                                    @endif

                                                @if( $dd['status'] != "Sold")
                                                @if(checkmodulepermission(5,'can_edit') == 1)
                                                <button
                                                    onclick="transferassetmodel({{ $ddid }}, {{ $dd['site_id'] }},{{ $dd['head_id'] }})"
                                                    title="Transfer Asset" style="all:unset"><img
                                                        src="{{ asset('/images/transfer.png') }}" style="width:20px" />
                                                </button> &nbsp;
                                                <button type="button" title="Sell Asset"
                                                    onclick="soldassetmodel('{{ $ddid }}', {{ $dd['site_id'] }},{{ $dd['head_id'] }})"
                                                    style="all:unset"><img src="{{ asset('/images/sold.png') }}"
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
                </div>
                @else
                <div class="alert alert-danger">You Don't Have Permission to view</div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('models')
@if(checkmodulepermission(5,'can_edit') == 1)
    <div class="modal fade" id="transferassetmodel" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-md" role="document">
            <form action="{{ url('/transferasset') }}" method="post" class="form">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="title">Transfer Asset</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row clearfix">

                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="Name">Choose Site</label>
                                    @php
                                        $sites = getallsites();
                                    @endphp
                                    <input type="hidden" name="id" id="asset_transfer_id" />
                                    <input type="hidden" name="head_id" id="asset_transfer_head_id" />

                                    <input type="hidden" name="from_site" id="asset_transfer_from_site_id" />
                                    <select name="to_site" class="form-control show-tick" data-live-search="true" required>
                                        <option disabled selected>--Choose Site--</option>
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="Name">Remark</label>
                                    <input type="text" name="remark" id="remark" class="form-control"
                                        placeholder="Remark" />
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
    @if(checkmodulepermission(5,'can_edit') == 1)
    <div class="modal fade" id="soldassetmodel" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-md" role="document">
            <form action="{{ url('/soldasset') }}" method="post" class="form">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="title">Sell Asset</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row clearfix">

                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="Name">Sell Price</label>

                                    <input type="hidden" name="id" id="asset_sold_id" />
                                    <input type="hidden" name="head_id" id="asset_sold_head_id" />

                                    <input type="hidden" name="from_site" id="asset_sold_from_site_id" />
                                    <input type="number" name="sold_value" id="sold_value" value="0" min="0"  step="0.001" pattern="^\d+(?:\.\d{1,4})?$" class="form-control" />

                                </div>
                            </div>
                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="Name">Remark</label>
                                    <input type="text" name="remark" id="remark" class="form-control"
                                        placeholder="Remark" />
                                </div>
                            </div>
                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="Name">Date</label>
                                    <input type="date" name="date" id="date" class="form-control"
                                        placeholder="Date" />
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
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                customClass: {
                    cancelButton: 'order-1 margin-10p',
                    confirmButton: 'order-2 margin-10p',
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/delete_asset_head/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function transferassetmodel(asset_id, site_id, head_id) {
            $('#transferassetmodel').modal('show');
            $('#asset_transfer_id').val(asset_id);
            $('#asset_transfer_from_site_id').val(site_id);
            $('#asset_transfer_head_id').val(head_id);
        }

        function soldassetmodel(asset_id, site_id, head_id) {
            $('#soldassetmodel').modal('show');
            $('#asset_sold_id').val(asset_id);
            $('#asset_sold_from_site_id').val(site_id);
            $('#asset_sold_head_id').val(head_id);
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
                    var url = "{{ url('/edit_asset_head_data/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }
    </script>
@endsection
