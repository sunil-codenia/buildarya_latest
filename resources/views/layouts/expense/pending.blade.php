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
                                <!-- Bulk Actions Bar -->
                                <div id="bulkActionsBar" class="p-2 mb-2 border rounded shadow-sm bulk-actions-container" style="display: none; border-left: 5px solid #eda61a !important;">
                                    <div class="row align-items-center">
                                        <div class="col-sm-6">
                                            <span id="bulkSelectionText" class="ml-2 font-weight-bold" style="color: #ffffff; font-size: 14px;">
                                                <span id="selectedCount" style="color: #eda61a; font-weight: 800; font-size: 16px;">0</span> Expenses Selected
                                                <span id="allPagesBadge" class="badge badge-warning ml-2" style="display: none; background: #eda61a; color: #000; font-weight: 700;">All Pages</span>
                                            </span>
                                        </div>
                                        <div class="col-sm-6 text-right">
                                            @if (checkmodulepermission(2, 'can_certify') == 1)
                                                <button type="submit" name="approve_expense" value="approve_expense"
                                                    class="btn btn-success btn-sm btn-round">
                                                    <i class="zmdi zmdi-check"></i> Approve
                                                </button>
                                                <button type="submit" name="reject_expense" value="reject_expense"
                                                    class="btn btn-danger btn-sm btn-round">
                                                    <i class="zmdi zmdi-block"></i> Reject
                                                </button>
                                            @endif
                                            @if (checkmodulepermission(2, 'can_edit') == 1)
                                                <button type="submit" formaction="{{ url('/pending_expense/bulk_edit_expense') }}"
                                                    class="btn btn-warning btn-sm btn-round">
                                                    <i class="zmdi zmdi-edit"></i> Edit
                                                </button>
                                                @if(session()->get('role') == 1 || session()->get('role') == 2)
                                                    <button type="button" onclick="openReturnModal()"
                                                        class="btn btn-info btn-sm btn-round">
                                                        <i class="zmdi zmdi-undo"></i> Return
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <table id="pendingExpenseTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="select_all" onclick="selectAll(this)"></th>
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
    @if (checkmodulepermission(2, 'can_certify') == 1 && (session()->get('role') == 1 || session()->get('role') == 2))
        <div class="modal fade" id="returnexpensemodal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="title">Return Expenses</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row clearfix">
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <label>Return Comment</label>
                                    <textarea id="return_comment" class="form-control" placeholder="Enter reason for returning these expenses..." rows="4"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary btn-simple waves-effect" data-dismiss="modal"><a>CLOSE</a></button>
                        <button type="button" onclick="submitReturn()" class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                    </div>
                </div>
            </div>
        </div>
    @endif

@endsection
@section('scripts')
    <script>
        var selectedPendingExpenseIds = new Set();
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

        function returnexpense(id) {
            Swal.fire({
                title: 'Return Expense',
                text: "Enter reason for returning this expense:",
                input: 'textarea',
                inputPlaceholder: 'Reason...',
                showCancelButton: true,
                confirmButtonText: 'Return',
                showLoaderOnConfirm: true,
                preConfirm: (comment) => {
                    if (!comment) {
                        Swal.showValidationMessage('Please enter a reason');
                    }
                    return $.ajax({
                        url: "{{ url('/return_expense_action') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            check_list: [id],
                            return_comment: comment
                        }
                    }).then(response => {
                        if (response.status !== 'success') {
                            throw new Error(response.message);
                        }
                        return response;
                    }).catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error}`);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire('Success!', 'Expense has been returned.', 'success').then(() => {
                        $('#pendingExpenseTable').DataTable().ajax.reload();
                    });
                }
            });
        }

        var allPendingExpensesSelectedAcrossPages = false;

        function updateBulkBarPending(isAllPages = false) {
            let count = selectedPendingExpenseIds.size;
            if (count > 0) {
                $('#selectedCount').text(count);
                if (isAllPages) {
                    $('#allPagesBadge').show();
                } else {
                    $('#allPagesBadge').hide();
                }
                $('#bulkActionsBar').fadeIn();
            } else {
                $('#bulkActionsBar').fadeOut();
                $('#allPagesBadge').hide();
                $('#select_all').prop('checked', false);
                allPendingExpensesSelectedAcrossPages = false;
            }
        }

        function selectAll(source) {
            var isChecked = source.checked;
            if (isChecked) {
                allPendingExpensesSelectedAcrossPages = true;
                $('.check_item').prop('checked', true);

                let dtParams = {};
                if ($.fn.DataTable.isDataTable('#pendingExpenseTable')) {
                    dtParams = $('#pendingExpenseTable').DataTable().ajax.params() || {};
                }
                dtParams._token = "{{ csrf_token() }}";
                dtParams.all_ids = 1;

                $('#selectedCount').html('<i class="zmdi zmdi-spinner zmdi-hc-spin"></i>');
                $('#bulkActionsBar').fadeIn();

                $.ajax({
                    url: "{{ url('/pending_expense_ajax') }}",
                    type: "POST",
                    data: dtParams,
                    success: function(res) {
                        if (res.status === 'Ok' && Array.isArray(res.ids)) {
                            selectedPendingExpenseIds.clear();
                            res.ids.forEach(function(id) {
                                selectedPendingExpenseIds.add(String(id));
                            });
                            $('.check_item').prop('checked', true);
                            updateBulkBarPending(true);
                        } else {
                            $('.check_item').each(function() {
                                selectedPendingExpenseIds.add(this.value);
                            });
                            updateBulkBarPending(false);
                        }
                    },
                    error: function() {
                        $('.check_item').each(function() {
                            selectedPendingExpenseIds.add(this.value);
                        });
                        updateBulkBarPending(false);
                    }
                });
            } else {
                allPendingExpensesSelectedAcrossPages = false;
                selectedPendingExpenseIds.clear();
                $('.check_item').prop('checked', false);
                updateBulkBarPending(false);
            }
        }

        $(document).on('change click', '.check_item', function() {
            if (this.checked) {
                selectedPendingExpenseIds.add(this.value);
            } else {
                selectedPendingExpenseIds.delete(this.value);
                allPendingExpensesSelectedAcrossPages = false;
            }
            updateSelectAll();
        });

        function updateSelectAll() {
            var source = document.getElementById('select_all');
            var checkboxes = $('.check_item');
            var allChecked = true;
            if (checkboxes.length == 0) allChecked = false;
            for (var i = 0; i < checkboxes.length; i++) {
                if (!checkboxes[i].checked) {
                    allChecked = false;
                    break;
                }
            }
            if (source) source.checked = (allChecked && selectedPendingExpenseIds.size > 0);
            updateBulkBarPending(allPendingExpensesSelectedAcrossPages);
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
                    var url = "{{ url('/edit_expense/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function openReturnModal() {
            if (selectedPendingExpenseIds.size === 0) {
                Swal.fire({
                    title: 'Error!',
                    text: "Please select at least one expense to return!",
                    icon: 'error',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                });
                return;
            }
            $('#returnexpensemodal').modal();
        }

        function submitReturn() {
            var ids = Array.from(selectedPendingExpenseIds);
            var comment = $('#return_comment').val();
            if (comment == "") {
                Swal.fire({
                    title: 'Error!',
                    text: "Please enter a return comment!",
                    icon: 'error',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                });
                return;
            }

            $.ajax({
                url: "{{ url('/return_expense_action') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    check_list: ids,
                    return_comment: comment
                },
                success: function(response) {
                    if (response.status == 'success') {
                        selectedPendingExpenseIds.clear();
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.href = "{{ url('/return_expense') }}";
                        });
                    } else {
                        Swal.fire({
                            title: 'Error!',
                            text: response.message,
                            icon: 'error',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                        });
                    }
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

            var table = $('#pendingExpenseTable').DataTable({
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
                    $('.check_item').each(function() {
                        if (selectedPendingExpenseIds.has(this.value)) {
                            this.checked = true;
                        } else {
                            this.checked = false;
                        }
                    });
                    updateSelectAll();
                    $("img.lazy").each(function () {
                        if ($(this).attr("data-src")) {
                           $(this).attr("src", $(this).attr("data-src"));
                        }
                    });
                }
            });

            // Sync hidden inputs for pending expense bulk form actions (Approve, Reject, Bulk Edit)
            $('form[action*="updateExpenses"]').on('submit', function(e) {
                var form = $(this);
                form.find('.sync-hidden-check').remove();
                if (selectedPendingExpenseIds.size === 0) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Error!',
                        text: "Please select at least one expense!",
                        icon: 'error',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                    });
                    return false;
                }
                selectedPendingExpenseIds.forEach(function(id) {
                    var inDom = form.find('input[name="check_list[]"][value="' + id + '"]:checked').length > 0;
                    if (!inDom) {
                        form.append('<input type="hidden" class="sync-hidden-check" name="check_list[]" value="' + id + '">');
                    }
                });
            });

            // Apply column search
            $('.column-search').on('keyup change', function() {
                var colIndex = $(this).data('column');
                table.column(colIndex).search(this.value).draw();
            });
        });
    </script>
@endsection
