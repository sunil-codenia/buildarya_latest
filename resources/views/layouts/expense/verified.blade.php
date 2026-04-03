@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Verified Expenses '])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Verified Expenses</strong> List &nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Expenses which are approved or rejected will be listed here.</div>
                    </h2>

                </div>
                <div class="body">
                    @if (checkmodulepermission(2, 'can_view') == 1)
                        <div class="table-responsive">
                            <style>
    .pagination {
        justify-content: center;
        margin-top: 20px;
    }
    /* Hide some potential DataTable remnants just in case */
    .dataTables_wrapper .row:first-child, .dataTables_wrapper .row:last-child {
        display: none !important;
    }
</style>
<table id="verifiedExpenseTableCustom" class="table table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Party</th>
                                        <th>Head</th>
                                        <th>Particular</th>
                                        <th>Amount</th>

                                        <th>Site</th>
                                        <th>User</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Remark</th>
                                        <th>Date</th>
                                        <th>Image</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>


                                    @php

                                        $i = ($data->currentPage() - 1) * $data->perPage() + 1;
                                    @endphp
                                    @foreach ($data as $dd)
                                        @php
                                            $dd = (array)$dd;
                                            $ddid = $dd['id'];
                                        @endphp
                                        <tr>
                                            <td>{{ $i++ }}</td>
                                            <td>
                                                {{ $dd['party_name'] }}
                                            </td>
                                            <td>
                                                {{ $dd['head'] }}
                                            </td>
                                            <td>
                                                {{ $dd['particular'] }}
                                            </td>
                                            <td>
                                                {{ $dd['amount'] }}
                                            </td>

                                            <td>
                                                {{ $dd['site'] }}
                                            </td>
                                            <td>
                                                {{ $dd['user'] }}
                                            </td>
                                            <td>
                                                {{ $dd['location'] }}
                                            </td>
                                            <td>
                                                {{ $dd['status'] }}
                                            </td>
                                            <td>
                                                {{ $dd['remark'] }}
                                            </td>
                                            <td>
                                                {{ $dd['date'] }}
                                            </td>
                                            <td>
                                                @php
                                                    $image = $dd['image'];

                                                @endphp
                                                <img class="lazy" data-src="{{ $dd['image'] }}"
                                                    onclick="enlargeImage('{{ $image }}')" height="50px"
                                                    width="50px" />
                                            </td>
                                            <td>


                                                @if ($dd['status'] == 'Approved')
                                                    @if (checkmodulepermission(2, 'can_certify') == 1)
                                                        <button title="Reject"
                                                            onclick="rejectexpense('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-block"></i> </button>
                                                    @endif
                                                @else
                                                    @if (in_array($dd['head_id'], $asset_expense_heads))
                                                        @if (!empty($dd['asset_head']))
                                                            Asset Category -
                                                            {{ $asset_heads[$dd['asset_head']] ?? 'N/A' }}<br>
                                                        @endif
                                                        @if (checkmodulepermission(2, 'can_certify') == 1)
                                                            <button type="button"
                                                                onclick="openassignassetheadmodel('{{ $ddid }}')"
                                                                style="all:unset"><i class="zmdi  zmdi-wrench"></i>
                                                            </button>
                                                        @endif
                                                    @elseif(in_array($dd['head_id'], $machinery_expense_heads))
                                                        @if (!empty($dd['machinery_head']))
                                                            Machinery Category -
                                                            {{ $machinery_heads[$dd['machinery_head']] ?? 'N/A' }}<br>
                                                        @endif
                                                        @if (checkmodulepermission(2, 'can_certify') == 1)
                                                            <button type="button"
                                                                onclick="openassignmachineryheadmodel('{{ $ddid }}')"
                                                                style="all:unset"><img
                                                                    src="{{ asset('/images/gears.png') }}"
                                                                    style="width:20px" /> </button>
                                                        @endif
                                                    @endif



                                                    @if (checkmodulepermission(2, 'can_certify') == 1)
                                                        <button title="Aprovel"
                                                            onclick="approveexpense('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-check-circle"></i>
                                                        </button>
                                                    @endif
                                                    &nbsp;
                                                    @if (checkmodulepermission(2, 'can_edit') == 1)
                                                        <button title="Edit" onclick="editexpense('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-edit"></i> </button>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach

                                </tbody>

                    </table>
                </div>
                <div class="card-footer text-center">
                    {{ $data->links('pagination::bootstrap-4') }}
                </div>
            @else
                        <div class="alert alert-danger">You Don't Have Permission To View</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script>
        function rejectexpense(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Reject This Expense?",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Reject',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/reject_expense_by_id?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function approveexpense(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Approve This Expense ?",
                icon: 'success',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#17ce0a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Approve',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/approve_expense_by_id?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function editexpense(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Expense ?",
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
                    var url = "{{ url('/edit_expense?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function openassignassetheadmodel(id) {
            $('#asset_head_expense_id').val(id);
            $('#assignassethead').modal();
        }

        function openassignmachineryheadmodel(id) {
            $('#machinery_head_expense_id').val(id);
            $('#assignmachineryhead').modal();
        }
    </script>
@endsection

@section('models')

    @if (checkmodulepermission(2, 'can_certify') == 1)
        <div class="modal fade" id="assignassethead" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/updateexpenseAssetHead') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Assign Asset Head To Expense</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="email_address_2">Asset Head</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="hidden" name="asset_head_expense_id" id="asset_head_expense_id"
                                            required>
                                        <select name="asset_head" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Head--</option>
                                            @php
                                                $heads = getAssetHeads();
                                            @endphp
                                            @foreach ($heads as $head)
                                                <option value="{{ $head->id }}">{{ $head->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>


                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit"
                                class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @if (checkmodulepermission(2, 'can_certify') == 1)
        <div class="modal fade" id="assignmachineryhead" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/updateexpenseMachineryHead') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Assign Machinery Head To Expense</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="email_address_2">Machinery Head</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="hidden" name="machinery_head_expense_id"
                                            id="machinery_head_expense_id" required>
                                        <select name="machinery_head" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Head--</option>
                                            @php
                                                $heads = getMachineryHeads();
                                            @endphp
                                            @foreach ($heads as $head)
                                                <option value="{{ $head->id }}">{{ $head->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>


                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit"
                                class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

@endsection
