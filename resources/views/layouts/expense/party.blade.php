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
                                <input type="text" id="Name" required class="form-control" value="{{$editdata['address']}}" name="address" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Pan No.</label>
                                <input type="text" id="Name" required class="form-control" value="{{$editdata['pan_no']}}" name="pan_no" >
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
                        <table id="dataTable" class="table table-hover">
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
                                   
                                    <th><strong>Address</th>                                        
                                    <th >Pan No</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 100px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php
                                $i=1;
                                @endphp
                                @foreach($dataarray as $dd)
                                @php
                                $ddid = $dd['id'];
                                @endphp
                              <tr>
                                <td style="padding-left: 10px;">
                                    <div class="checkbox">
                                        <input id="check_{{ $ddid }}" name="check_list[]" class="item_checkbox" type="checkbox" value="{{ $ddid }}">
                                        <label for="check_{{ $ddid }}">&nbsp;</label>
                                    </div>
                                </td>
                                <td>
                                    {{$i++}}
                                </td>
                                        <td>
                                            <a class="single-user-name" href="#">{{$dd['name']}}</a>
                                        </td>
                                        <td>
                                            {{$dd['address']}}
                                        </td>                                        
                                        
                                        <td>
                                            <strong>{{$dd['pan_no']}}</strong>
                                        </td>   
                                        @if($dd['status'] == 'Active')
                                        @if(checkmodulepermission(2,'can_certify') == 1)
                            
                                            <td><span onclick="updatepartystatus('{{$ddid}}','Deactive')" class="badge badge-success">{{$dd['status']}}</span></td>
                                        @endif
                                            @else
                                            @if(checkmodulepermission(2,'can_certify') == 1)
                                        <td><span onclick="updatepartystatus('{{$ddid}}','Active')" class="badge badge-danger">{{$dd['status']}}</span></td>
                                        @endif
                                        @endif
                                        <td>
                                        @if(checkmodulepermission(2,'can_edit') == 1)
                                        <button title="Edit" onclick="editparty('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button>
                                        &nbsp;
                                        @endif
                                        @if(checkmodulepermission(2,'can_delete') == 1)
                                        @if(isExpensepartyDeletable($ddid))
                                    <button title="Delete" onclick="deletedata('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-delete"></i> </button>
                                    &nbsp;
                                    @endif
                                    @endif
                                    @if($dd['status'] == 'Pending')
                                    @if(checkmodulepermission(2,'can_certify') == 1)
                                    <button onclick="updatepartystatus('{{$ddid}}','Active')" style="all:unset" ><i class="zmdi zmdi-check-circle"></i> </button>
                                    @endif
                                    @endif

                                        </td>
                                    </tr>    
                       @endforeach
                            
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
</script>


@endsection