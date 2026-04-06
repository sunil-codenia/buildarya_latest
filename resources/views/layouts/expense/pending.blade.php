@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Pending Expenses '])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Pending Expenses</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Expenses which are pending for approval will be listed here.</div>
                    </h2>

                </div>
                <div class="body">
                    @if (checkmodulepermission(2, 'can_view') == 1)
                        <div class="table-responsive">
                            <form action="{{ url('/updateExpenses') }}" method="POST">
                                @csrf
                                <div class="align-right">
                                    @if (checkmodulepermission(2, 'can_certify') == 1)
                                        <button type="submit" name="approve_expense" value="approve_expense"
                                            class="btn btn-success btn-simple btn-round waves-effect"><a>Approve</a></button>
                                    @endif
                                    @if (checkmodulepermission(2, 'can_certify') == 1)
                                        <button type="submit" name="reject_expense" value="reject_expense"
                                            class="btn btn-danger btn-simple btn-round waves-effect"><a>Reject</a></button>
                                    @endif
                                    @if (checkmodulepermission(2, 'can_edit') == 1)
                                        <button type="submit" formaction="{{ url('/pending_expense/bulk_edit_expense') }}"
                                            class="btn btn-warning btn-simple btn-round waves-effect"><a>Edit</a></button>
                                    @endif
                                </div>
                                <table id="pendingExpenseTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="select_all"></th>
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
                                        <!-- Populated via AJAX -->
                                    </tbody>
                                </table>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
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
                                        <select name="machinery_head" class="form-control show-tick"
                                            data-live-search="true" required>
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
@section('scripts')
    <script>
        $('#select_all').on('click', function() {
            if (this.checked) {
                $('.check_item').each(function() {
                    this.checked = true;
                });
            } else {
                $('.check_item').each(function() {
                    this.checked = false;
                });
            }
        });

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
                    var url = "{{ url('/edit_expense/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }



        function openassignmachineryheadmodel(id) {
            $('#machinery_head_expense_id').val(id);
            $('#assignmachineryhead').modal();
        }

        $("#select_all").click(function() {
            $('.check_item').prop('checked', this.checked);
        });

        $(document).on('change', '.check_item', function() {
            if ($('.check_item:checked').length == $('.check_item').length) {
                $('#select_all').prop('checked', true);
            } else {
                $('#select_all').prop('checked', false);
            }
        });

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

            $('#pendingExpenseTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "{{ url('/pending_expense_ajax') }}",
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
                        text: window.csvButtonTrans || 'CSV',
                        action: newExportAction,
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },
                    {
                        extend: 'excelHtml5',
                        text: window.excelButtonTrans || 'Excel',
                        action: newExportAction,
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
                    },
                    {
                        extend: 'pdfHtml5',
                        text: window.pdfButtonTrans || 'PDF',
                        action: newExportAction,
                        className: 'btn btn-round waves-effect waves-light btn-custom-color'
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
                    if ($('.check_item:checked').length > 0 && $('.check_item:checked').length == $('.check_item').length) {
                        $('#select_all').prop('checked', true);
                    } else {
                        $('#select_all').prop('checked', false);
                    }
                    $("img.lazy").each(function () {
                        if ($(this).attr("data-src")) {
                           $(this).attr("src", $(this).attr("data-src"));
                        }
                    });
                }
            });
        });
    </script>
@endsection
