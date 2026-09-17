@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Material'])
    @php
        $edit = false;
        $dataarray = json_decode($data, true);
        if (isset(json_decode($data, true)['edit_data'])) {
            $editdata = $dataarray['edit_data'][0];
            $edit = true;
        }
    @endphp
    <div class="row clearfix">
        @if ($edit)
            @if (checkmodulepermission(3, 'can_edit') == 1)
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="card project_list">

                        <form action="{{ url('/updatematerial') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit Materials</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="row clearfix">
                                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                            <label for="Name">Name</label>
                                        </div>
                                        <div class="col-lg-8 col-md-8 col-sm-8">
                                            <div class="form-group">
                                                <input type="hidden" name="id" value="{{ $editdata['id'] }}">
                                                <input type="text" id="Name" required class="form-control"
                                                    value="{{ $editdata['name'] }}" name="name"
                                                    placeholder="Enter the Material Name">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row clearfix">
                                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                            <label for="is_royalty">Is Royalty Material</label>
                                        </div>
                                        <div class="col-lg-8 col-md-8 col-sm-8">
                                            <div class="form-group">
                                                <div class="checkbox">
                                                    <input type="checkbox" id="is_royalty" name="is_royalty" value="1" 
                                                        {{ (isset($editdata['is_royalty']) && $editdata['is_royalty']) ? 'checked' : '' }}>
                                                    <label for="is_royalty">Check if this material requires cubic meter conversion for royalty purposes</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Update</a></button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            @endif
            <br>
        @endif

        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><strong>Materials</strong> List</h2>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div id="bulk-action-toolbar" style="display: none; gap: 5px;">
                            @if(checkmodulepermission(3,'can_edit') == 1)
                                <button type="button" class="btn btn-primary btn-icon btn-round hidden-sm-down m-l-10" onclick="bulkEdit()" title="Edit Selected">
                                    <i class="zmdi zmdi-edit" style="color: white;"></i>
                                </button>
                            @endif
                        </div>
                        <ul class="header-dropdown" style="position: relative; top: auto; right: auto; box-shadow: none;">
                            <li>
                                @if (checkmodulepermission(3, 'can_add') == 1)
                                    <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                        data-toggle="modal" data-target="#newexpensehead1" type="button">
                                        <i class="zmdi zmdi-plus" style="color: white;"></i>
                                    </button>
                                @endif
                            </li>
                        </ul>
                    </div>
                </div>


                @if (checkmodulepermission(3, 'can_view') == 1)
                    <div class="body">
                        <!-- Bulk Actions Bar -->
                        <div id="bulkActionsBar" class="p-2 mb-2 border rounded shadow-sm bulk-actions-container" style="display: none; border-left: 5px solid #eda61a !important;">
                            <div class="row align-items-center">
                                <div class="col-sm-6">
                                    <span id="bulkSelectionText" class="ml-2 font-weight-bold" style="color: #ffffff; font-size: 14px;">
                                        <span id="selectedCount" style="color: #eda61a; font-weight: 800; font-size: 16px;">0</span> Materials Selected
                                        <span id="allPagesBadge" class="badge badge-warning ml-2" style="display: none; background: #eda61a; color: #000; font-weight: 700;">All Pages</span>
                                    </span>
                                </div>
                                <div class="col-sm-6 text-right">
                                    @if(checkmodulepermission(3,'can_edit') == 1)
                                        <button type="button" class="btn btn-warning btn-sm btn-round" onclick="bulkEdit()" title="Edit Selected">
                                            <i class="zmdi zmdi-edit"></i> Edit
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <form id="bulkActionForm" action="{{ url('/material/bulk_action') }}" method="POST">
                                @csrf
                                <input type="hidden" name="bulk_action" id="bulk_action_input">
                                <table id="materialTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"><div class="checkbox"><input id="select_all" type="checkbox"><label for="select_all">&nbsp;</label></div></th>
                                            <th style="width: 50px;">#</th>
                                            <th>Name</th>
                                            <th style="width: 150px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Populated via AJAX -->
                                    </tbody>
                                </table>
                            </form>
                        </div>
                    </div>
                @else
                    <div class="alert alert-danger mx-5">You Don't Have Permission to View !! </div>
                @endif

            </div>
        </div>

    </div>

@endsection

@section('models')
    @if (checkmodulepermission(3, 'can_add') == 1)
        <div class="modal fade" id="newexpensehead1" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/addmaterial') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New Material</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="Name">Name</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="text" id="Name" required class="form-control" name="name"
                                            placeholder="Enter the Material Name">
                                    </div>
                                </div>
                            </div>
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="is_royalty_add">Is Royalty Material</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <input type="checkbox" id="is_royalty_add" name="is_royalty" value="1">
                                            <label for="is_royalty_add">Check if this material requires cubic meter conversion for royalty purposes</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE
                                </a></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
@section('scripts')
    <script type="text/javascript">
        var selectedMaterialIds = new Set();
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
                    var url = "{{ url('/delete_material/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function editdata(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Material ?",
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
                    var url = "{{ url('/edit_material/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function syncMaterialFormInputs() {
            var form = $("#bulkActionForm");
            form.find('.sync-hidden-check').remove();
            selectedMaterialIds.forEach(function(id) {
                var inDom = form.find('input[name="check_list[]"][value="' + id + '"]:checked').length > 0;
                if (!inDom) {
                    form.append('<input type="hidden" class="sync-hidden-check" name="check_list[]" value="' + id + '">');
                }
            });
        }

        function bulkEdit() {
            if (selectedMaterialIds.size > 0) {
                syncMaterialFormInputs();
                $('#bulkActionForm').attr('action', "{{ url('/bulk_edit_material') }}");
                $('#bulkActionForm').submit();
            } else {
                Swal.fire({
                    title: 'No Items Selected',
                    text: 'Please select at least one Material to edit.',
                    icon: 'info',
                    confirmButtonColor: '#343a40'
                });
            }
        }

        function submitBulkAction(action) {
            if (selectedMaterialIds.size === 0) {
                Swal.fire({
                    title: 'No Items Selected',
                    text: 'Please select at least one Material.',
                    icon: 'info',
                    confirmButtonColor: '#343a40'
                });
                return;
            }
            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to " + action + " all selected materials!",
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
                    syncMaterialFormInputs();
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

            var allMaterialsSelectedAcrossPages = false;

            function updateToolbar(isAllPages = false) {
                let count = selectedMaterialIds.size;
                if (count > 0) {
                    $('#selectedCount').text(count);
                    if (isAllPages) {
                        $('#allPagesBadge').show();
                    } else {
                        $('#allPagesBadge').hide();
                    }
                    $('#bulkActionsBar').fadeIn();
                    $('#bulk-action-toolbar').show();
                } else {
                    $('#bulkActionsBar').fadeOut();
                    $('#bulk-action-toolbar').hide();
                    $('#allPagesBadge').hide();
                    $('#select_all').prop('checked', false);
                    allMaterialsSelectedAcrossPages = false;
                }
            }

            function updateSelectAllMaterialState() {
                var total = $('.check_item').length;
                var checked = $('.check_item:checked').length;
                if (total > 0 && checked === total && selectedMaterialIds.size > 0) {
                    $('#select_all').prop('checked', true);
                } else {
                    $('#select_all').prop('checked', false);
                }
            }

            $("#select_all").click(function() {
                var isChecked = this.checked;
                if (isChecked) {
                    allMaterialsSelectedAcrossPages = true;
                    $('.check_item').prop('checked', true);

                    let dtParams = {};
                    if ($.fn.DataTable.isDataTable('#materialTable')) {
                        dtParams = $('#materialTable').DataTable().ajax.params() || {};
                    }
                    dtParams._token = "{{ csrf_token() }}";
                    dtParams.all_ids = 1;

                    $('#selectedCount').html('<i class="zmdi zmdi-spinner zmdi-hc-spin"></i>');
                    updateToolbar(true);

                    $.ajax({
                        url: "{{ url('/material_ajax') }}",
                        type: "POST",
                        data: dtParams,
                        success: function(res) {
                            if (res.status === 'Ok' && Array.isArray(res.ids)) {
                                selectedMaterialIds.clear();
                                res.ids.forEach(function(id) {
                                    selectedMaterialIds.add(String(id));
                                });
                                $('.check_item').prop('checked', true);
                                updateToolbar(true);
                            } else {
                                $('.check_item').each(function() {
                                    selectedMaterialIds.add(this.value);
                                });
                                updateToolbar(false);
                            }
                        },
                        error: function() {
                            $('.check_item').each(function() {
                                selectedMaterialIds.add(this.value);
                            });
                            updateToolbar(false);
                        }
                    });
                } else {
                    allMaterialsSelectedAcrossPages = false;
                    selectedMaterialIds.clear();
                    $('.check_item').prop('checked', false);
                    updateToolbar(false);
                }
            });

            $(document).on('change click', '.check_item', function() {
                if (this.checked) {
                    selectedMaterialIds.add(this.value);
                } else {
                    selectedMaterialIds.delete(this.value);
                    allMaterialsSelectedAcrossPages = false;
                }
                updateSelectAllMaterialState();
                updateToolbar(allMaterialsSelectedAcrossPages);
            });

            $('#materialTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "{{ url('/material_ajax') }}",
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
                    $('.check_item').each(function() {
                        if (selectedMaterialIds.has(this.value)) {
                            this.checked = true;
                        } else {
                            this.checked = false;
                        }
                    });
                    updateSelectAllMaterialState();
                    updateToolbar(allMaterialsSelectedAcrossPages);
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
