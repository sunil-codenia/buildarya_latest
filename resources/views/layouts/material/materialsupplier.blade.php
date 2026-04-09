@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Material Supplier'])
@php
$edit=false;
$dataarray = json_decode($data, true);
                            if(isset(json_decode($data, true)['edit_data'])){
                            $editdata = $dataarray['edit_data'][0];
                            $edit=true;
                            $dataarray = $dataarray['data'];
                            }
@endphp
<div class="row clearfix">

@if($edit)
@if(checkmodulepermission(3,'can_edit') == 1)
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">

        <form action="{{url('/updatematerialsupplier')}}" method="post" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Edit Material Supplier</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Name</label>
                                <input type="hidden" name="id" value="{{$editdata['id']}}">
                                <input type="text" id="Name" required class="form-control" value="{{$editdata['name']}}" name="name" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Address</label>

                                <input type="text" id="adress" required class="form-control" value="{{$editdata['address']}}" name="address" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Gstin</label>
                                <input type="text" id="gstin" required class="form-control" value="{{$editdata['gstin']}}" name="gstin"  >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Bank A/C</label>
                                <input type="text" id="bank_ac" required class="form-control" value="{{$editdata['bank_ac']}}" name="bank_ac"  >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Bank Ifsc</label>
                                <input type="text" id="bank_ifsc" required class="form-control" value="{{$editdata['bank_ifsc']}}" name="bank_ifsc"  >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Bank Name</label>
                                <input type="text" id="bank_name" required class="form-control" value="{{$editdata['bank_name']}}" name="bank_name"  >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Bank_ac_holder">Bank A/C Holder</label>
                                <input type="text" id="bank_ac_holder" required class="form-control" value="{{$editdata['bank_ac_holder']}}" name="bank_ac_holder"  >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="cost_category_id">Cost Category</label>
                                <select name="cost_category_id" id="cost_category_id" class="form-control show-tick" required>
                                    <option value="">-- Select Cost Category --</option>
                                    @php
                                        $categories = json_decode($data, true)['cost_categories'];
                                    @endphp
                                    @foreach($categories as $category)
                                        <option value="{{$category['id']}}" {{$editdata['cost_category_id'] == $category['id'] ? 'selected' : ''}}>{{$category['name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>Update</a></button>
                </div>
            </div>
        </form>
        </div>

    </div>
    <br>
    @endif
    @endif

  
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
                <h2><strong>Material Supplier</strong> List</h2>
                <ul class="header-dropdown" style="display: flex; align-items: center;">
                    <div id="bulk-action-toolbar" style="display: none; margin-right: 15px;">
                        @if(checkmodulepermission(3,'can_certify') == 1)
                            <button type="button" class="btn btn-success btn-icon btn-round hidden-sm-down waves-effect waves-light" onclick="submitBulkAction('active')" title="Activate Selected"><i class="zmdi zmdi-check" style="color: white;"></i></button>
                            <button type="button" class="btn btn-warning btn-icon btn-round hidden-sm-down waves-effect waves-light" onclick="submitBulkAction('deactive')" title="Deactivate Selected"><i class="zmdi zmdi-close" style="color: white;"></i></button>
                        @endif
                        @if(checkmodulepermission(3,'can_edit') == 1)
                            <button type="button" class="btn btn-primary btn-icon btn-round hidden-sm-down waves-effect waves-light" onclick="bulkEdit()" title="Edit Selected"><i class="zmdi zmdi-edit" style="color: white;"></i></button>
                        @endif
                    </div>
                    <li>
                  
                    @if(checkmodulepermission(3,'can_add') == 1)
                        <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10" data-toggle="modal" data-target="#newexpensehead1" type="button">
                            <i class="zmdi zmdi-plus" style="color: white;"></i>
                        </button>
                        @endif
                    </li>
                </ul>
            </div> 
             @if(checkmodulepermission(3,'can_view') == 1)

            <div class="body">
                <form id="bulkActionForm" action="{{ url('/materialsupplier/bulk_action') }}" method="POST">
                    @csrf
                    <input type="hidden" name="bulk_action" id="bulk_action_input">
                <div class="table-responsive">
                    <table id="materialSupplierTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 40px;"><div class="checkbox"><input id="select_all" type="checkbox"><label for="select_all">&nbsp;</label></div></th>
                                <th style="width: 50px;">#</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Gstin</th>
                                <th>Bank A/C</th>
                                <th>Bank Ifsc</th>
                                <th>Bank Name</th>
                                <th>Bank A/C Holder</th>
                                <th>Cost Category</th>
                                <th>Status</th>
                                <th style="width: 100px;">Action</th>
                                
                            </tr>
                        </thead>
                        <tbody> 
                            <!-- Populated via AJAX -->
                        </tbody>
                    </table>
                </div>
                </form>
            </div>
            @else
            <div class="alert alert-danger m-5"> You Don't Have Permission To View..!!! </div>
            @endif
        </div>
    </div>
   
</div>
@endsection


@section('models')

@if(checkmodulepermission(3,'can_add') == 1)
<div class="modal fade" id="newexpensehead1" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <form action="{{url('/addmaterialsupplier')}}" method="post" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add New Material Supplier</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Name</label>

                                <input type="text" id="Name" required class="form-control" name="name" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Address</label>

                                <input type="text" id="address" required class="form-control" name="address" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Gstin</label>

                                <input type="text" id="gstin" required class="form-control" name="gstin" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Bank  A/C</label>

                                <input type="text" id="Bank_ac" required class="form-control" name="bank_ac" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Bank Ifsc</label>

                                <input type="text" id="Bank_ifsc" required class="form-control" name="bank_ifsc" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Bank Name</label>

                                <input type="text" id="Bank_name" required class="form-control" name="bank_name" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Bank Account</label>

                                <input type="text" id="bank_ac_holder" required class="form-control" name="bank_ac_holder" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="cost_category_id">Cost Category</label>
                                <select name="cost_category_id" id="cost_category_id" class="form-control show-tick" required>
                                    <option value="">-- Select Cost Category --</option>
                                    @php
                                        $categories = json_decode($data, true)['cost_categories'];
                                    @endphp
                                    @foreach($categories as $category)
                                        <option value="{{$category['id']}}">{{$category['name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-simple waves-effect" data-dismiss="modal"><a>CLOSE</a></button>
                    <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE</a></button>
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
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/delete_materialsupplier/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        function editdata(id) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This Supplier ?",
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
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/edit_materialsupplier/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        function updateuserstatus(id,status){
        Swal.fire({
            title: 'Are you sure?',
            text: "You Want To "+status+" This Supplier?",
            icon: 'warning',
            showCancelButton: true,
            toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
            confirmButtonColor: '#ff0000',
            cancelButtonColor: '#000000',
            confirmButtonText: status,
            cancelButtonText: 'Cancel',
            focusConfirm:true,
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/update_material_supplier_status/?id=')}}" + id + "&status="+status;
                window.location.href = url;
            }
        });
      }

        function bulkEdit() {
            var selectedRows = $('.check_item:checked');
            if (selectedRows.length > 0) {
                $('#bulkActionForm').attr('action', "{{ url('/bulk_edit_supplier') }}");
                $('#bulkActionForm').submit();
            } else {
                Swal.fire({
                    title: 'No Items Selected',
                    text: 'Please select at least one Material Supplier to edit.',
                    icon: 'info',
                    confirmButtonColor: '#343a40'
                });
            }
        }

        function submitBulkAction(action) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You are about to " + action + " all selected suppliers!",
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
                    $('#bulk-action-toolbar').show();
                } else {
                    $('#bulk-action-toolbar').show();
                    // Or keep it hidden until selection? The instruction was "when we click check box": 
                    // Let's implement correct logic.
                    // Actually, I'll hide it.
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

            $('#materialSupplierTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "{{ url('/materialsupplier_ajax') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [0, 1, 11] }
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