@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Machineries'])
                                    
    @php
        $sites = getallActivesites();
        $add_duration = session()->get('add_duration');
        $duration     = getdurationdates($add_duration);
        $today        = substr($duration['today'], 0, 10);
        $min_date     = substr($duration['min'],   0, 10);
        $max_date     = substr($duration['max'],   0, 10);
    @endphp

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Machineries</strong> List</h2>
                </div>


                @if(checkmodulepermission(6,'can_view') == 1)
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
                                    <th>Next Service On</th>
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
                                                        href="#"><?= getMachineryNextService($dd['id']) ?></a>
                                                @else
                                                    Already sold
                                                @endif
                                            </td>
                                            <td>
                                                @if ($dd['status'] != 'Sold')
                                                    <a class="single-user-name"
                                                        href="#">{{ getmachineryLastTransfer($dd['id'])->create_datetime }}</a>
                                                @else
                                                    Already sold
                                                @endif
                                            </td>
                                            <td>
                                                @if ($dd['status'] == 'Sold')
                                                    Purchase - {{ $dd['create_datetime'] }}<br>
                                                    Sold - {{ getmachineryLastTransfer($dd['id'])->create_datetime }}
                                                @else
                                                    {{ $dd['create_datetime'] }}
                                                @endif
                                            </td>

                                            <td>
                                            @if(checkmodulepermission(6,'can_edit') == 1)
                                                <a href="{{ url('/machineryTransferHistory/?machinery_id=') . $ddid }}"><img
                                                        src="{{ asset('/images/transfer_history.png') }}"
                                                        style="width:20px" /></a>&nbsp;
                                                        <a href="{{ url('/machineryDocuments/?machinery_id=') . $ddid }}"><img
                                                            src="{{ asset('/images/document.png') }}"
                                                            style="width:20px" /></a>&nbsp;
                                                    @endif

                                                @if ($dd['status'] != 'Sold')
                                                @if(checkmodulepermission(6,'can_edit') == 1)
                                                    <button
                                                        onclick="transfermachinerymodel({{ $ddid }}, {{ $dd['site_id'] }},{{ $dd['head_id'] }})"
                                                        title="Transfer machinery" style="all:unset"><img
                                                            src="{{ asset('/images/transfer.png') }}" style="width:20px" />
                                                    </button> &nbsp;
                                                    <a href="{{ url('/machineryService/?machinery_id=') . $ddid }}"><img
                                                        src="{{ asset('/images/service.png') }}"
                                                        style="width:20px" /></a>&nbsp;
                                                    <button type="button" title="Sell machinery"
                                                        onclick="soldmachinerymodel('{{ $ddid }}', {{ $dd['site_id'] }},{{ $dd['head_id'] }})"
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
                <div class="alert alert-danger">You Don't Have Permission To View</div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('models')
    @if(checkmodulepermission(6,'can_edit') == 1)
    <div class="modal fade" id="transfermachinerymodel" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-md" role="document">
            <form action="{{ url('/transfermachinery') }}" method="post" class="form">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="title">Transfer machinery</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row clearfix">

                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="Name">Choose Site</label>

                                    <input type="hidden" name="id" id="machinery_transfer_id" />
                                    <input type="hidden" name="head_id" id="machinery_transfer_head_id" />

                                    <input type="hidden" name="from_site" id="machinery_transfer_from_site_id" />
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

 

    @if(checkmodulepermission(6,'can_edit') == 1)
    <div class="modal fade" id="soldmachinerymodel" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-md" role="document">
            <form action="{{ url('/soldmachinery') }}" method="post" class="form">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="title">Sell machinery</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row clearfix">

                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="Name">Sell Price</label>

                                    <input type="hidden" name="id" id="machinery_sold_id" />
                                    <input type="hidden" name="head_id" id="machinery_sold_head_id" />

                                    <input type="hidden" name="from_site" id="machinery_sold_from_site_id" />
                                    <input type="number" name="sold_value" id="sold_value" class="form-control" />

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
                                        min="{{ $min_date }}" max="{{ $max_date }}"
                                        value="{{ $today }}"
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
                    var url = "{{ url('/delete_machinery_head/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function transfermachinerymodel(machinery_id, site_id, head_id) {
            $('#transfermachinerymodel').modal('show');
            $('#machinery_transfer_id').val(machinery_id);
            $('#machinery_transfer_from_site_id').val(site_id);
            $('#machinery_transfer_head_id').val(head_id);
        }

        function soldmachinerymodel(machinery_id, site_id, head_id) {
            $('#soldmachinerymodel').modal('show');
            $('#machinery_sold_id').val(machinery_id);
            $('#machinery_sold_from_site_id').val(site_id);
            $('#machinery_sold_head_id').val(head_id);
        }
    </script>
@endsection
