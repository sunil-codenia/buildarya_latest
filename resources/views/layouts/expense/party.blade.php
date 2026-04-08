@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Expense Party'])
@php
$edit=false;
$dataarray = json_decode($data, true);
                            if(isset($dataarray['edit_data']) && count($dataarray['edit_data']) > 0){
                            $editdata = $dataarray['edit_data'][0];
                            $edit=true;
                            $dataarray = $dataarray['data'];
                            }
@endphp
<div class="row clearfix">
@if($edit)
@if(checkmodulepermission(2,'can_edit') == 1)

    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">

        <form action="{{url('/updateexpenseparty')}}" method="post" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Edit Expense Party</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">

                        <div class="col-lg-3 col-md-3 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Name</label>
                                <input type="hidden" name="id" value="{{$editdata['id']}}">
                                <input type="text" id="Name" required class="form-control" value="{{$editdata['name']}}" name="name" >
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Address</label>
                                <input type="text" id="Name" required class="form-control" value="{{$editdata['address']}}" name="address" >
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Pan No.</label>
                                <input type="text" id="Name" required class="form-control" value="{{$editdata['pan_no']}}" name="pan_no" >
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-3 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Cost Category</label>
                                <select name="cost_category_id" class="form-control show-tick" required>
                                    <option value="">Select Cost Category</option>
                                    @foreach($dataarray['cost_categories'] as $cat)
                                        <option value="{{ $cat['id'] }}" {{ $editdata['cost_category_id'] == $cat['id'] ? 'selected' : '' }}>{{ $cat['name'] }}</option>
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
                <h2><strong>Expense Party</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                    <div class="info-content" >Expenses parties will be listed here.</h2>
                <ul class="header-dropdown">
                   <li id="bulkActions" style="display: none;">
                            @if (checkmodulepermission(2, 'can_edit') == 1)
                                <button class="btn btn-warning btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    title="Bulk Edit" type="button" onclick="submitBulkEdit()">
                                    <i class="zmdi zmdi-edit" style="color: white;"></i>
                                </button>
                                <button class="btn btn-success btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    title="Bulk Activate" type="button" onclick="submitBulkStatus('Active')">
                                    <i class="zmdi zmdi-check" style="color: white;"></i>
                                </button>
                                <button class="btn btn-danger btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    title="Bulk Deactivate" type="button" onclick="submitBulkStatus('Deactive')">
                                    <i class="zmdi zmdi-close" style="color: white;"></i>
                                </button>
                            @endif
                   </li>
                   <li>
                            @if(checkmodulepermission(2,'can_add') == 1)
                                <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10" data-toggle="modal" data-target="#newexpensepartymodal"  type="button">
                                    <i class="zmdi zmdi-plus" style="color: white;" ></i>
                                </button>
                            @endif
                   </li>
                </ul>
            </div>

            <div class="body">
            @if(checkmodulepermission(2,'can_view') == 1)
                <form action="{{ url('/bulk_edit_party') }}" method="POST" id="bulkEditForm">
                    @csrf
                    <input type="hidden" name="type" value="party">
                    <input type="hidden" name="status" id="bulkStatusField" value="">
                    <div class="table-responsive">
                        <table id="expensePartyTable" class="table table-hover">
                            <thead>
                                <tr>      
                                    <th style="width: 20px;">
                                        <div class="checkbox">
                                            <input id="select_all" type="checkbox">
                                            <label for="select_all">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th style="width: 50px;">#</th>                                 
                                    <th >Name</th>
                                    <th><strong>Address</strong></th>                                        
                                    <th >Pan No</th>
                                    <th><strong>Cost Category</strong></th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTables will populate this dynamically -->
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

@section('models')


@if(checkmodulepermission(2,'can_add') == 1)
<div class="modal fade" id="newexpensepartymodal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
       <form action="{{url('/addexpenseparty')}}" method="post" class="form">
        @csrf
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="title" >Add New Expense Parties</h4>
            </div>
            <div class="modal-body"> 
            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="email_address_2">Name</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="text" name="name" required class="form-control" placeholder="Enter the name of expense party">
                                    </div>
                                </div>
                            </div>        
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="email_address_2">Address</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="text" name="address" class="form-control" required placeholder="Enter the address of expense party">
                                    </div>
                                </div>
                            </div>
                            <div class="row clearfix" >
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="email_address_2">Pan</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="text" name="pan_no" required class="form-control" placeholder="Enter the Pan No. of expense party">
                                    </div>
                                </div>
                            </div>
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="email_address_2">Cost Category</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <select name="cost_category_id" class="form-control show-tick" required>
                                            <option value="">Select Cost Category</option>
                                            @foreach($dataarray['cost_categories'] as $cat)
                                                <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

            </div>
            <div class="modal-footer">       
                <button type="button" class="btn btn-primary btn-simple waves-effect" data-dismiss="modal"><a >CLOSE</a></button>
                <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a >Submit</a></button>
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
                var url = "{{url('/delete_expense_party?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        function updatepartystatus(id,status) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To "+status+" This Party?",
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
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/update_expense_party_status?id=')}}" + id + "&status="+status;
                window.location.href = url;
            }
        });
        }
        function editparty(id) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This Party ?",
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
                var url = "{{url('/edit_expense_party?id=')}}" + id;
                window.location.href = url;
            }
        });
        }

        $("#select_all").click(function() {
            $('.item_checkbox').prop('checked', this.checked);
            toggleBulkActions();
        });

        $(document).on('change', '.item_checkbox', function() {
            toggleBulkActions();
            if ($('.item_checkbox:checked').length == $('.item_checkbox').length) {
                $('#select_all').prop('checked', true);
            } else {
                $('#select_all').prop('checked', false);
            }
        });

        function toggleBulkActions() {
            var checkedCount = $(".item_checkbox:checked").length;
            if (checkedCount > 0) {
                $("#bulkActions").show();
            } else {
                $("#bulkActions").hide();
            }
        }

        function submitBulkEdit() {
            $("#bulkEditForm").attr('action', "{{ url('/bulk_edit_party') }}");
            $("#bulkEditForm").submit();
        }

        function submitBulkStatus(status) {
            $("#bulkStatusField").val(status);
            $("#bulkEditForm").attr('action', "{{ url('/update_bulk_party_status') }}");
            $("#bulkEditForm").submit();
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

            $('#expensePartyTable').DataTable({
                serverSide: true,
                processing: true,
                ajax: {
                    url: "{{ url('/expense_party_ajax') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}"
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [0, 1, 7] }
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
                    if ($('.item_checkbox:checked').length > 0 && $('.item_checkbox:checked').length == $('.item_checkbox').length) {
                        $('#select_all').prop('checked', true);
                    } else {
                        $('#select_all').prop('checked', false);
                    }
                    toggleBulkActions();
                }
            });
        });
</script>


@endsection