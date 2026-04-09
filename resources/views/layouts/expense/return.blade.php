@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Returned Expenses '])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Returned Expenses</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Expenses which are returned by admin for corrections will be listed here.</div>
                    </h2>
                </div>
                <div class="body">
                    @if (checkmodulepermission(2, 'can_view') == 1)
                        <div class="table-responsive">
                            <form action="#" method="POST" id="bulkActionForm">
                                @csrf
                                <div class="align-right">
                                    @if (checkmodulepermission(2, 'can_edit') == 1)
                                        <button type="submit" formaction="{{ url('/pending_expense/bulk_edit_expense') }}"
                                            class="btn btn-warning btn-simple btn-round waves-effect"><a>Edit</a></button>
                                    @endif
                                    <button type="button" onclick="bulkResubmit()"
                                        class="btn btn-success btn-simple btn-round waves-effect"><a>Resubmit</a></button>
                                </div>
                                <table id="returnExpenseTable" class="table table-hover">
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
                                            <th>Remark / Comment</th>
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

@section('scripts')
    <script>
        $('#select_all').on('click', function() {
            $('.check_item').prop('checked', this.checked);
        });

        $(document).on('change', '.check_item', function() {
            if ($('.check_item:checked').length == $('.check_item').length) {
                $('#select_all').prop('checked', true);
            } else {
                $('#select_all').prop('checked', false);
            }
        });

        function bulkResubmit() {
            var ids = [];
            $('.check_item:checked').each(function() {
                ids.push($(this).val());
            });

            if (ids.length == 0) {
                Swal.fire('Error!', 'Please select at least one expense to resubmit!', 'error');
                return;
            }

            Swal.fire({
                title: 'Resubmit ' + ids.length + ' Expenses?',
                text: "These expenses will move back to Pending status.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Resubmit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ url('/bulk_resubmit_returned_expense') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            check_list: ids
                        },
                        success: function(response) {
                            if (response.status == 'success') {
                                Swal.fire({
                                    title: 'Success!',
                                    text: response.message,
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.href = "{{ url('/pending_expense') }}";
                                });
                            } else {
                                Swal.fire('Error!', response.message, 'error');
                            }
                        }
                    });
                }
            });
        }

        function editexpense(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Expense ?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#eda61a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Edit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ url('/edit_expense/?id=') }}" + id;
                }
            });
        }

        function resubmitexpense(id) {
            Swal.fire({
                title: 'Resubmit Expense?',
                text: "This will move the expense back to Pending status.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Resubmit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ url('/resubmit_returned_expense') }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            id: id
                        },
                        success: function(response) {
                            if (response.status == 'success') {
                                Swal.fire({
                                    title: 'Success!',
                                    text: response.message,
                                    icon: 'success',
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.href = "{{ url('/pending_expense') }}";
                                });
                            } else {
                                Swal.fire('Error!', response.message, 'error');
                            }
                        }
                    });
                }
            });
        }

        $(document).ready(function() {
            $('#returnExpenseTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "{{ url('/return_expense_ajax') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [0, 1, 12, 13] }
                ],
                responsive: true,
                dom: 'lBfrtip',
                buttons: [
                    { extend: 'csvHtml5', className: 'btn btn-round btn-custom-color' },
                    { extend: 'excelHtml5', className: 'btn btn-round btn-custom-color' },
                    { extend: 'pdfHtml5', className: 'btn btn-round btn-custom-color' }
                ],
                pagingType: "full_numbers",
                drawCallback: function() {
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
