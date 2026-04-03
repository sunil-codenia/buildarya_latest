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
                        <div class="row mb-2 mt-2">
                            <div class="col-md-8 col-xs-12">
                                <a href="{{ url('/verified_expense/export/csv?search=' . request('search')) }}"
                                    class="btn btn-round waves-effect waves-light btn-custom-color">CSV</a>
                                <a href="{{ url('/verified_expense/export/xlsx?search=' . request('search')) }}"
                                    class="btn btn-round waves-effect waves-light btn-custom-color">Excel</a>
                                <a href="{{ url('/verified_expense/export/pdf?search=' . request('search')) }}"
                                    class="btn btn-round waves-effect waves-light btn-custom-color">PDF</a>
                            </div>
                            <div class="col-md-4 col-xs-12 text-right">
                                <form action="{{ url('/verified_expense') }}" method="GET"
                                    class="form-inline float-right">
                                    <div class="form-group mb-0">
                                        <input type="text" name="search" class="form-control"
                                            placeholder="Search all data..." value="{{ request('search') }}"
                                            style="width: 200px; border: 1px solid #ccc; border-radius: 20px; padding: 5px 15px;">
                                    </div>
                                    <button type="submit" class="btn btn-info btn-round ml-2">Search</button>
                                    @if (request('search'))
                                        <a href="{{ url('/verified_expense') }}"
                                            class="btn btn-warning btn-round ml-1">Reset</a>
                                    @endif
                                </form>
                            </div>
                        </div>

                        <div id="bulkActionsVerified" style="display: none; margin-bottom: 10px;">
                            <div class="alert alert-info" style="display: inline-block; padding: 10px 20px; margin-bottom: 0;">
                                <strong>Bulk Actions: </strong>
                                @if (isSuperAdmin() || checkmodulepermission(2, 'can_edit'))
                                    <button class="btn btn-warning btn-icon btn-round" title="Bulk Edit"
                                        type="button" onclick="submitBulkEdit()">
                                        <i class="zmdi zmdi-edit" style="color: white;"></i>
                                    </button>
                                @endif
                                @if (isSuperAdmin() || checkmodulepermission(2, 'can_certify'))
                                    <button class="btn btn-success btn-icon btn-round" title="Bulk Approve"
                                        type="button" onclick="submitBulkStatus('Approve')">
                                        <i class="zmdi zmdi-check" style="color: white;"></i>
                                    </button>
                                    <button class="btn btn-danger btn-icon btn-round" title="Bulk Reject"
                                        type="button" onclick="submitBulkStatus('Reject')">
                                        <i class="zmdi zmdi-block" style="color: white;"></i>
                                    </button>
                                @endif
                            </div>
                        </div>

                        <form action="{{ url('/pending_expense/bulk_edit_expense') }}" method="POST" id="bulkActionForm">
                            @csrf
                            <input type="hidden" name="status" id="bulkStatusField" value="">
                            <div class="table-responsive">
                                <style>
                                    .pagination {
                                        justify-content: center;
                                        margin-top: 20px;
                                    }
                                    .bulk-show {
                                        display: block !important;
                                    }
                                </style>
                                <table id="verifiedExpenseTableCustom" class="table table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width: 20px;">
                                                <div class="checkbox">
                                                    <input id="select_all_verified" type="checkbox" onclick="setTimeout(toggleBulkActions, 50)">
                                                    <label for="select_all_verified">&nbsp;</label>
                                                </div>
                                            </th>
                                            <th style="width: 50px;">#</th>
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
                                            <td style="padding-left: 10px;">
                                                <div class="checkbox">
                                                    <input id="check_{{ $ddid }}" name="check_list[]" class="item_checkbox" type="checkbox" value="{{ $ddid }}" onclick="toggleBulkActions()">
                                                    <label for="check_{{ $ddid }}">&nbsp;</label>
                                                </div>
                                            </td>
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
                    </form>
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

        window.toggleBulkActions = function() {
            var checkedCount = $(".item_checkbox:checked").length;
            if (checkedCount > 0) {
                $("#bulkActionsVerified").addClass('bulk-show');
            } else {
                $("#bulkActionsVerified").removeClass('bulk-show');
            }
        };

        $("#select_all_verified").click(function() {
            $('.item_checkbox').prop('checked', this.checked);
            toggleBulkActions();
        });

        $(document).on('change', '.item_checkbox', function() {
            toggleBulkActions();
            if ($('.item_checkbox:checked').length == $('.item_checkbox').length) {
                $('#select_all_verified').prop('checked', true);
            } else {
                $('#select_all_verified').prop('checked', false);
            }
        });

        function submitBulkEdit() {
            $("#bulkActionForm").attr('action', "{{ url('/pending_expense/bulk_edit_expense') }}");
            $("#bulkActionForm").submit();
        }

        function submitBulkStatus(status) {
            $("#bulkStatusField").val(status);
            var url = status == 'Approve' ? "{{ url('/bulk_approve_verified') }}" : "{{ url('/bulk_reject_verified') }}";
            $("#bulkActionForm").attr('action', url);
            $("#bulkActionForm").submit();
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
