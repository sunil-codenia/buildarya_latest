@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Contact Category'])
    @php
        $edit = false;
        $dataarray = json_decode($data, true);
        if (isset($dataarray['edit_data']) && !empty($dataarray['edit_data'])) {
            $editdata = $dataarray['edit_data'];
            $edit = true;
        }
    @endphp
    <div class="row clearfix">
        @if ($edit)
            @if (checkmodulepermission(10, 'can_edit') == 1)
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="card project_list">
                        <form action="{{ url('/update_contact_category') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                                <div class="modal-header" style="background: linear-gradient(135deg, #eda61a 0%, #f5af19 100%); color: white; border-radius: 12px 12px 0 0;">
                                    <h4 class="title m-0" style="font-weight: bold;"><i class="zmdi zmdi-edit mr-2"></i>Edit Contact Category</h4>
                                </div>
                                <div class="modal-body p-4">
                                    <div class="row clearfix">
                                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                            <label for="Name" class="font-weight-bold" style="color: #555;">Name</label>
                                        </div>
                                        <div class="col-lg-8 col-md-8 col-sm-8">
                                            <div class="form-group">
                                                <input type="hidden" name="id" value="{{ $editdata['id'] }}">
                                                <input type="text" id="Name" required class="form-control"
                                                    value="{{ $editdata['name'] }}" name="name"
                                                    placeholder="Enter the Category Name">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer p-3">
                                    <a href="{{ url('/contact_categories') }}" class="btn btn-secondary btn-round mr-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary btn-round waves-effect" style="font-weight: bold;">Update</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
            <br>
        @endif
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list" style="border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <div class="header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px;">
                    <h2><strong>Contact Category</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Contact categories used across company contact forms will be listed here.</div>
                    </h2>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div id="bulk-action-toolbar" style="display: none; gap: 5px;">
                            @if(checkmodulepermission(10,'can_delete') == 1)
                                <button type="button" class="btn btn-danger btn-icon btn-round hidden-sm-down m-l-10" onclick="submitBulkAction('delete')" title="Delete Selected">
                                    <i class="zmdi zmdi-delete" style="color: white;"></i>
                                </button>
                            @endif
                        </div>
                        <ul class="header-dropdown" style="position: relative; top: auto; right: auto; box-shadow: none;">
                            <li>
                                @if (checkmodulepermission(10, 'can_add') == 1)
                                    <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                        data-toggle="modal" data-target="#newcontactcategorymodal" type="button" title="Add Contact Category">
                                        <i class="zmdi zmdi-plus" style="color: white;"></i>
                                    </button>
                                @endif
                            </li>
                        </ul>
                    </div>
                </div>
                @if (checkmodulepermission(10, 'can_view') == 1)
                    <div class="body p-4">
                        <div class="table-responsive">
                            <form id="bulkActionForm" action="{{ url('/contact_category/bulk_action') }}" method="POST">
                                @csrf
                                <input type="hidden" name="bulk_action" id="bulk_action_input">
                                <table id="contactCategoryTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"><div class="checkbox"><input id="select_all" type="checkbox"><label for="select_all">&nbsp;</label></div></th>
                                            <th style="width: 50px;">#</th>
                                            <th>Category Name</th>
                                            <th style="width: 100px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Populated via DataTables AJAX -->
                                    </tbody>
                                </table>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="alert alert-danger m-3"> You Don't Have Permission to View Contact Categories </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('models')
    @if (checkmodulepermission(10, 'can_add') == 1)
        <div class="modal fade" id="newcontactcategorymodal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/add_contact_category') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none;">
                        <div class="modal-header p-4" style="background: linear-gradient(135deg, #eda61a 0%, #f5af19 100%); color: white;">
                            <h4 class="title m-0" style="font-weight: bold;"><i class="zmdi zmdi-plus-circle mr-2"></i>Add New Contact Category</h4>
                        </div>
                        <div class="modal-body p-4">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-4 form-control-label">
                                    <label for="Name" class="font-weight-bold" style="color: #555;">Category Name</label>
                                </div>
                                <div class="col-lg-9 col-md-9 col-sm-8">
                                    <div class="form-group mb-0">
                                        <input type="text" id="Name" required class="form-control" name="name"
                                            placeholder="Enter Contact Category Name (e.g. Sub Contractor)">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer p-3" style="border-top: 1px solid #f1f2f6;">
                            <button type="button" class="btn btn-secondary btn-round" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary btn-round waves-effect" style="font-weight: bold;">Save Category</button>
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
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/delete_contact_category/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function editdata(id) {
            var url = "{{ url('/edit_contact_category/?id=') }}" + id;
            window.location.href = url;
        }

        function submitBulkAction(action) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to " + action + " all selected categories!",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Yes, proceed',
                cancelButtonText: 'Cancel',
                customClass:{
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#bulk_action_input').val(action);
                    $('#bulkActionForm').submit();
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

            function updateToolbar() {
                if ($('.check_item:checked').length > 0) {
                    $('#bulk-action-toolbar').css('display', 'flex');
                } else {
                    $('#bulk-action-toolbar').hide();
                }
            }

            $("#select_all").click(function() {
                $('.check_item').prop('checked', this.checked);
                updateToolbar();
            });

            $(document).on('change', '.check_item', function() {
                if ($('.check_item:checked').length == $('.check_item').length && $('.check_item').length > 0) {
                    $('#select_all').prop('checked', true);
                } else {
                    $('#select_all').prop('checked', false);
                }
                updateToolbar();
            });

            $('#contactCategoryTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "{{ url('/get_contact_category_ajax') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [0, 1, 3] }
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
                    $('#select_all').prop('checked', false);
                    updateToolbar();
                }
            });
        });
    </script>
<style>
    .btn-custom-color {
        background-color: #eda61a !important;
        color: white !important;
    }
</style>
@endsection
