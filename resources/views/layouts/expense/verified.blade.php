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
                                <a href="{{ url('/verified_expense/export/csv') }}"
                                    class="btn btn-round waves-effect waves-light btn-custom-color">CSV</a>
                                <a href="{{ url('/verified_expense/export/xlsx') }}"
                                    class="btn btn-round waves-effect waves-light btn-custom-color">Excel</a>
                                <a href="{{ url('/verified_expense/export/pdf') }}"
                                    class="btn btn-round waves-effect waves-light btn-custom-color">PDF</a>
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
                                <table id="verifiedExpenseTable" class="table table-hover table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="width: 20px;">
                                                <div class="checkbox">
                                                    <input id="select_all_verified" type="checkbox" onclick="selectAllVerified(this)">
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
                                        <tr class="search-row">
                                            <th></th>
                                            <th></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Party" data-column="2"></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Head" data-column="3"></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Particular" data-column="4"></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Amount" data-column="5"></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Site" data-column="6"></th>
                                            <th><input type="text" class="form-control column-search" placeholder="User" data-column="7"></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Loc" data-column="8"></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Status" data-column="9"></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Remark" data-column="10"></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Date" data-column="11"></th>
                                            <th></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                        </div>
                    </form>
            @else
                        <div class="alert alert-danger">You Don't Have Permission To View</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            var newExportAction = function (e, dt, button, config) {
                var self = this;
                var oldStart = dt.settings()[0]._iDisplayStart;
                dt.one('preXhr', function (e, s, data) {
                    data.start = 0;
                    data.length = -1;
                    dt.one('preDraw', function (e, settings) {
                        if (button[0].className.indexOf('buttons-copy') >= 0) {
                            $.fn.dataTable.ext.buttons.copyHtml5.action.call(self, e, dt, button, config);
                        } else if (button[0].className.indexOf('buttons-excel') >= 0) {
                            $.fn.dataTable.ext.buttons.excelHtml5.available(dt, config) ?
                                $.fn.dataTable.ext.buttons.excelHtml5.action.call(self, e, dt, button, config) :
                                $.fn.dataTable.ext.buttons.excelFlash.action.call(self, e, dt, button, config);
                        } else if (button[0].className.indexOf('buttons-csv') >= 0) {
                            $.fn.dataTable.ext.buttons.csvHtml5.available(dt, config) ?
                                $.fn.dataTable.ext.buttons.csvHtml5.action.call(self, e, dt, button, config) :
                                $.fn.dataTable.ext.buttons.csvFlash.action.call(self, e, dt, button, config);
                        } else if (button[0].className.indexOf('buttons-pdf') >= 0) {
                            $.fn.dataTable.ext.buttons.pdfHtml5.available(dt, config) ?
                                $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, button, config) :
                                $.fn.dataTable.ext.buttons.pdfFlash.action.call(self, e, dt, button, config);
                        } else if (button[0].className.indexOf('buttons-print') >= 0) {
                            $.fn.dataTable.ext.buttons.print.action(e, dt, button, config);
                        }
                        dt.one('preXhr', function (e, s, data) {
                            settings._iDisplayStart = oldStart;
                            data.start = oldStart;
                        });
                        setTimeout(dt.ajax.reload, 0);
                        return false;
                    });
                });
                dt.ajax.reload();
            };

            var table = $('#verifiedExpenseTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "{{ url('/verified_expense_ajax') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [0, 1, 12, 13] }
                ],
                responsive: true,
                dom: 'lBfrtip<"actions">',
                buttons: [
                    {
                        extend: 'csvHtml5',
                        action: newExportAction,
                        className: 'btn btn-round btn-custom-color'
                    },
                    {
                        extend: 'excelHtml5',
                        action: newExportAction,
                        className: 'btn btn-round btn-custom-color'
                    },
                    {
                        extend: 'pdfHtml5',
                        action: newExportAction,
                        className: 'btn btn-round btn-custom-color'
                    }
                ],
                "oLanguage": {
                    "oPaginate": {
                        "sFirst": '<i class="zmdi zmdi-fast-rewind"></i>',
                        "sLast": '<i class="zmdi zmdi-fast-forward"></i>',
                        "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>',
                        "sNext": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>'
                    },
                    "sInfo": "Showing ( <b>_START_ - _END_ </b>) Of <b> _TOTAL_ </b> Entries <br> Page<b> _PAGE_ </b>of <b>_PAGES_</b> Pages",
                    "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                    "sSearchPlaceholder": "Search...",
                    "sLengthMenu": "Results :  _MENU_",
                    "sPadding": '2rem'
                },
                pagingType: "full_numbers",
                drawCallback: function(settings) {
                    updateSelectAllVerified();
                    $("img.lazy").each(function () {
                        if ($(this).attr("data-src")) {
                           $(this).attr("src", $(this).attr("data-src"));
                        }
                    });
                }
            });

            // Apply column search
            $('.column-search').on('keyup change', function() {
                var colIndex = $(this).data('column');
                table.column(colIndex).search(this.value).draw();
            });
        });


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

        function selectAllVerified(source) {
            var checkboxes = document.getElementsByClassName('item_checkbox');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
            toggleBulkActions();
        }

        function updateSelectAllVerified() {
            var source = document.getElementById('select_all_verified');
            var checkboxes = document.getElementsByClassName('item_checkbox');
            var allChecked = true;
            if (checkboxes.length == 0) allChecked = false;
            for (var i = 0; i < checkboxes.length; i++) {
                if (!checkboxes[i].checked) {
                    allChecked = false;
                    break;
                }
            }
            if(source) source.checked = allChecked;
            toggleBulkActions();
        }

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
