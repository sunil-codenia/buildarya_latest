@extends('app')
@section('content')
    <style>
        .column-search {
            width: 100% !important;
            padding: 5px !important;
            height: auto !important;
            font-size: 12px !important;
        }
        .search-row th {
            padding: 5px !important;
        }
    </style>

    @include('templates.blockheader', ['pagename' => 'Returned Materials '])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Returned Materials</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Material entries which are returned for corrections will be listed here.</div>
                    </h2>
                </div>
                <div class="body">
                    @if (checkmodulepermission(3, 'can_view') == 1)
                        <div class="table-responsive">
                            <form action="#" method="POST" id="bulkActionForm">
                                @csrf
                                <div class="align-right">
                                    @if (checkmodulepermission(3, 'can_edit') == 1)
                                        <button type="button" onclick="bulkResubmit()"
                                            class="btn btn-success btn-simple btn-round waves-effect"><a>Resubmit Selected</a></button>
                                    @endif
                                </div>
                                <table id="returnMaterialTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="select_all"></th>
                                            <th>#</th>
                                            <th>Supplier</th>
                                            <th>Material</th>
                                            <th>Unit</th>
                                            <th>Quantity</th>
                                            <th>Converted Qty (Cubic M)</th>
                                            <th>Vehicle</th>
                                            <th>Status</th>
                                            <th>Remark / Return Comment</th>
                                            <th>Site</th>
                                            <th>User</th>
                                            <th>Location</th>
                                            <th>Date</th>
                                            <th>Image</th>
                                            <th>Action</th>
                                        </tr>
                                        <tr class="search-row">
                                            <th></th>
                                            <th></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Supplier" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Material" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Unit" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Qty" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Converted Qty" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Vehicle" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Status" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Remark" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Site" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search User" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Location" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Date" /></th>
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

@section('scripts')
    <script>
        $(document).on('change', '#select_all', function() {
            var status = this.checked;
            $('.check_item').each(function() {
                $(this).prop('checked', status);
            });
        });

        $(document).on('change', '.check_item', function() {
            if ($('.check_item:checked').length == $('.check_item').length && $('.check_item').length > 0) {
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
                Swal.fire('Error!', 'Please select at least one material entry to resubmit!', 'error');
                return;
            }

            Swal.fire({
                title: 'Resubmit ' + ids.length + ' Material Entries?',
                text: "These entries will move back to Pending status.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Resubmit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ url('/bulk_resubmit_returned_material') }}",
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
                                    window.location.href = "{{ url('/pending_material') }}";
                                });
                            } else {
                                Swal.fire('Error!', response.message, 'error');
                            }
                        }
                    });
                }
            });
        }

        function editmaterial(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Material Entry ?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#eda61a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Edit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ url('/edit_material_entry/?id=') }}" + id;
                }
            });
        }

        function resubmitmaterial(id) {
            Swal.fire({
                title: 'Resubmit Material Entry?',
                text: "This will move the material entry back to Pending status.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Resubmit',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ url('/resubmit_returned_material') }}",
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
                                    window.location.href = "{{ url('/pending_material') }}";
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
            var table = $('#returnMaterialTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "{{ url('/return_material_ajax') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [0, 1, 14, 15] }
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

            // Column Search Logic
            table.columns().every(function() {
                var that = this;
                $('input', $('.search-row th').get(this.index())).on('keyup change clear', function() {
                    if (that.search() !== this.value) {
                        that.search(this.value).draw();
                    }
                });
            });
        });
    </script>
@endsection
