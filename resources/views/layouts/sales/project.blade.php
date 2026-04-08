@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Sales Project'])
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
@if(checkmodulepermission(7,'can_edit') == 1)

    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">

        <form action="{{url('/updatesalesproject')}}" method="post"  enctype="multipart/form-data" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Edit Sales Project</h4>
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
                            <label for="Name">Details</label>
                                <input type="text" id="details" required class="form-control" value="{{$editdata['details']}}" name="details" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Attachment</label>
                                <input type="file" id="attachment"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="attachment" >
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
    @endif
    <br>
    @endif
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
                <h2><strong>Sales Project</strong> List</h2>
                <ul class="header-dropdown">
                    <li>
                    @if(checkmodulepermission(7,'can_add') == 1)
                        <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10" data-toggle="modal" data-target="#newexpensehead1" type="button">
                            <i class="zmdi zmdi-plus" style="color: white;"></i>
                        </button>
                        @endif
                    </li>
                </ul>
            </div>

            <div class="body">
            @if(checkmodulepermission(7,'can_view') == 1)
                <div class="table-responsive">
                    <table id="dataTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th>Attachment</th>
                                <th>Invoices</th>
                                <th>Action</th>
                              
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
                                <td>{{$i++}}</td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['name']}}</a>
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['details']}}</a>
                                </td>
                               
                                @if($dd['status'] == 'Active')
                                @if(checkmodulepermission(7,'can_certify') == 1)
                                        <td><span onclick="updateprojectstatus('{{$ddid}}','Deactive')" class="badge badge-success">{{$dd['status']}}</span></td>
                                        @endif
                                        @else
                                        @if(checkmodulepermission(7,'can_certify') == 1)
                                        <td><span onclick="updateprojectstatus('{{$ddid}}','Active')" class="badge badge-danger">{{$dd['status']}}</span></td>
                                        @endif
                                        @endif

                                        <td>
                                            @if($dd['attachment'] != null)
                                            <a title="Attachment" href="{{url('/').'/'.$dd['attachment']}}" target="_blank" ><i class="zmdi zmdi-attachment-alt"></i> </a>
                                            @endif
                                      </td>
                                      <td style="vertical-align:center;">
                                        
                                        <a title="Receipt" href="{{url('/sales_invoice/?project_id=').$dd['id']}}" ><i class="zmdi zmdi-receipt"></i> &nbsp; <span class="  badge rounded-pill bg-primary" style="color:white;"> {{$dd['invoices']}}</span></a>
                                  </td>
                                <td>
                                @if(checkmodulepermission(7,'can_edit') == 1)
                                    <button title="Edit" onclick="editproject('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button>
                                    @endif
                                    &nbsp;
                                    @if(checkmodulepermission(7,'can_delete') == 1)
                                    @if(isSalesProjectDeletable($ddid))
                                    <button title="Delete" onclick="deletedata('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-delete"></i> </button>
                                    @endif
                                    @endif
                                </td>

                            </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-danger">You Don't Have Permission to View </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection


@section('models')
@if(checkmodulepermission(7,'can_add') == 1)

<div class="modal fade" id="newexpensehead1" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <form action="{{url('/addsalesproject')}}" method="post" enctype="multipart/form-data" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add New project</h4>
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
                            <label for="Name">Details</label>

                                <input type="text" id="details" required class="form-control" name="details" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Attachment</label>
                                <input type="file" id="attachment"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="attachment" >
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
var uploadField = document.getElementById("attachment");

uploadField.onchange = function() {
    if(this.files[0].size > 10485760){
       alert("File is too big. Max Size Allowed is 10 MB!");
       this.value = "";
    };
};
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
                var url = "{{url('/delete_sales_project/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        function updateprojectstatus(id,status) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To "+status+" This Project?",
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
                var url = "{{url('/update_sales_project_status/?id=')}}" + id + "&status="+status;
                window.location.href = url;
            }
        });
        }

        function editproject(id) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This Project Details ?",
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
                var url = "{{url('/edit_sales_project/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        </script>

@if(session('ask_create_site'))
<script>
    $(document).ready(function() {
        Swal.fire({
            title: 'Project Created Successfully!',
            text: "Do you want to create a site also?",
            icon: 'success',
            showCancelButton: true,
            confirmButtonColor: '#eda61a',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Yes',
            cancelButtonText: 'No',
            timer: 60000,
            timerProgressBar: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{url('/sites?action=add_new&project_id=')}}{{session('ask_create_site')}}";
            }
        });
    });
</script>
@endif

@endsection