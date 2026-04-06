@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Pending Material'])

<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
                <h2><strong>Pending Material</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                    <div class="info-content">Material entry which are pending will be listed here.</div>
                </h2>
                <ul class="header-dropdown">
                    <li id="bulkActions" style="display: none;">
                        @if (checkmodulepermission(3, 'can_certify') == 1)
                            <button class="btn btn-warning btn-icon btn-round hidden-sm-down float-right m-l-10"
                                title="Bulk Edit" type="button" onclick="bulkEdit()">
                                <i class="zmdi zmdi-edit" style="color: white;"></i>
                            </button>
                            <button class="btn btn-success btn-icon btn-round hidden-sm-down float-right m-l-10"
                                title="Approve Selected" type="button" onclick="bulkAction('approve')">
                                <i class="zmdi zmdi-check" style="color: white;"></i>
                            </button>
                            <button class="btn btn-danger btn-icon btn-round hidden-sm-down float-right m-l-10"
                                title="Reject Selected" type="button" onclick="bulkAction('reject')">
                                <i class="zmdi zmdi-close" style="color: white;"></i>
                            </button>
                        @endif
                    </li>
                </ul>
            </div>
            <div class="body">
                @if (checkmodulepermission(3, 'can_view') == 1)
                    <div class="table-responsive">
                        <form id="bulkActionForm" action="{{ url('/update_material') }}" method="POST">
                            @csrf
                            <input type="hidden" name="approve_material" id="approve_material_val" value="">
                            <input type="hidden" name="reject_material" id="reject_material_val" value="">
                            
                            <table id="pendingMaterialTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 20px;">
                                            <div class="checkbox">
                                                <input id="select_all" type="checkbox">
                                                <label for="select_all">&nbsp;</label>
                                            </div>
                                        </th>
                                        <th style="width: 20px;">#</th>
                                        <th>Supplier</th>
                                        <th>Material</th>
                                        <th>Unit</th>
                                        <th>Quantity</th>
                                        <th>Vehicle</th>
                                        <th>Status</th>
                                        <th>Remark</th>
                                        <th>Site</th>
                                        <th>User</th>
                                        <th>Location</th>
                                        <th>Date</th>
                                        <th>Image</th>
                                        <th style="width: 40px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </form>
                    </div>
                @else
                    <div class="alert alert-danger"> You Don't Have Permission to View !!</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script type="text/javascript">
    function editmaterial(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This Material Entry ?",
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
                var url = "{{ url('/edit_material_entry/?id=') }}" + id;
                window.location.href = url;
            }
        });
    }

    $("#select_all").click(function() {
        $('.check_item').prop('checked', this.checked);
        toggleBulkActions();
    });

    $(document).on('change', '.check_item', function() {
        toggleBulkActions();
        if ($('.check_item:checked').length == $('.check_item').length) {
            $('#select_all').prop('checked', true);
        } else {
            $('#select_all').prop('checked', false);
        }
    });

    function toggleBulkActions() {
        var checkedCount = $(".check_item:checked").length;
        if (checkedCount > 0) {
            $("#bulkActions").show();
        } else {
            $("#bulkActions").hide();
        }
    }

    function bulkEdit() {
        Swal.fire({
            title: 'Are you sure?',
            text: "You want to edit the selected entries?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#eda61a',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Yes, edit them!'
        }).then((result) => {
            if (result.isConfirmed) {
                var form = document.getElementById('bulkActionForm');
                form.action = "{{ url('/bulk_edit_pending_material') }}";
                form.submit();
            }
        });
    }

    function bulkAction(type) {
        if (type === 'approve') {
            $('#approve_material_val').val('approve_material');
            $('#reject_material_val').val('');
        } else {
            $('#approve_material_val').val('');
            $('#reject_material_val').val('reject_material');
        }
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You want to " + type + " the selected entries?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: type === 'approve' ? '#28a745' : '#dc3545',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Yes, ' + type + ' them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $("#bulkActionForm").submit();
            }
        });
    }

    $(document).ready(function() {
        var newExportAction = function (e, dt, button, config) {
            var self = this;
            var oldStart = dt.settings()[0]._iDisplayStart;
            dt.one('preXhr', function (e, s, data) {
                data.start = 0;
                data.length = -1;
                dt.one('preDraw', function (e, settings) {
                    if (button[0].className.indexOf('buttons-csv') >= 0) {
                        $.fn.dataTable.ext.buttons.csvHtml5.available(dt, config) ?
                            $.fn.dataTable.ext.buttons.csvHtml5.action.call(self, e, dt, button, config) :
                            $.fn.dataTable.ext.buttons.csvFlash.action.call(self, e, dt, button, config);
                    } else if (button[0].className.indexOf('buttons-excel') >= 0) {
                        $.fn.dataTable.ext.buttons.excelHtml5.available(dt, config) ?
                            $.fn.dataTable.ext.buttons.excelHtml5.action.call(self, e, dt, button, config) :
                            $.fn.dataTable.ext.buttons.excelFlash.action.call(self, e, dt, button, config);
                    } else if (button[0].className.indexOf('buttons-pdf') >= 0) {
                        $.fn.dataTable.ext.buttons.pdfHtml5.available(dt, config) ?
                            $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, button, config) :
                            $.fn.dataTable.ext.buttons.pdfFlash.action.call(self, e, dt, button, config);
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

        $('#pendingMaterialTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: "{{ url('/pending_material_ajax') }}",
                type: "POST",
                data: function(d) {
                    d._token = "{{ csrf_token() }}";
                }
            },
            columns: [
                { data: 0, orderable: false },
                { data: 1 },
                { data: 2 },
                { data: 3 },
                { data: 4 },
                { data: 5 },
                { data: 6, orderable: false },
                { data: 7, orderable: false },
                { data: 8 },
                { data: 9 },
                { data: 10, orderable: false },
                { data: 11 },
                { data: 12 },
                { data: 13, orderable: false },
                { data: 14, orderable: false }
            ],
            responsive: true,
            dom: 'lBfrtip',
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
                toggleBulkActions();
            }
        });
    });
</script>
<style>
    .btn-custom-color {
        background-color: #eda61a !important;
        color: white !important;
    }
    .btn-icon.btn-round {
        width: 35px;
        height: 35px;
        padding: 0;
        line-height: 35px;
        text-align: center;
    }
</style>
@endsection
