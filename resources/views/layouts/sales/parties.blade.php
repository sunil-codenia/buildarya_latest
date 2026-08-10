@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Sales Parties'])
@php
$edit=false;
$dataarray = json_decode($data, true);
$parties = $dataarray;
if(isset($dataarray['edit_data'])){
    $editdata = $dataarray['edit_data'][0] ?? null;
    $parties = $dataarray['data'] ?? [];
    $edit=true;
}
@endphp
<div class="row clearfix">

@if($edit)
@if(checkmodulepermission(7,'can_edit') == 1)

    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">

        <form action="{{url('/updatesalesparty')}}" method="post" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Edit Sales Party</h4>
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
                            <label for="Name">Phone No</label>
                                <input type="number" id="phone" required class="form-control" value="{{$editdata['phone']}}" name="phone"  >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Gstin</label>
                                <input type="text" id="gst" required class="form-control" value="{{$editdata['gst']}}" name="gst"  >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">State</label>
                                <input type="text" id="state" required class="form-control" value="{{$editdata['state']}}" name="state"  >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">State Code</label>
                                <input type="number" id="state_code" required class="form-control" value="{{$editdata['state_code']}}" name="state_code"  >
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
                <h2><strong>Sales Parties</strong> List</h2>
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
                                <th>Address</th>
                                <th>Gstin</th>
                                <th>Phone No</th>
                                <th>State</th>
                                <th>Status</th>
                                <th>Action</th>
                              
                            </tr>
                        </thead>
                        <tbody> 
                            @php
                            $i=1;
                            @endphp
                            @foreach($parties as $dd)
                            @php
                            $ddid = $dd['id'];
                            @endphp

                            <tr>
                                <td>{{$i++}}</td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['name']}}</a>
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['address']}}</a>
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['gst']}}</a>
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['phone']}}</a>
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['state']}} ( {{$dd['state_code']}} )</a>
                                </td>
                                @if($dd['status'] == 'Active')
                                @if(checkmodulepermission(7,'can_certify') == 1)
                                        <td><span onclick="updatepartystatus('{{$ddid}}','Deactive')" class="badge badge-success">{{$dd['status']}}</span></td>
                                        @endif
                                        @else
                                        @if(checkmodulepermission(7,'can_certify') == 1)
                                        <td><span onclick="updatepartystatus('{{$ddid}}','Active')" class="badge badge-danger">{{$dd['status']}}</span></td>
                                        @endif
                                        @endif
                                <td>
                                @if(checkmodulepermission(7,'can_edit') == 1)
                                    <button title="Edit" onclick="editparty('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button>
                                    @endif
                                    &nbsp;
                                    @if(checkmodulepermission(7,'can_delete') == 1)
                                    @if(isSalesPartyDeletable($ddid))
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
        <form action="{{url('/addsalesparty')}}" method="post" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add New party</h4>
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

                                <input type="text" id="gst" required class="form-control" name="gst" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Phone No.</label>

                                <input type="number" id="phone" required class="form-control" name="phone" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">State</label>

                                <input type="text" id="state" required class="form-control" name="state" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">State Code</label>

                                <input type="number" id="state_code" required class="form-control" name="state_code" >
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
                var url = "{{url('/delete_sales_party/?id=')}}" + id;
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
                var url = "{{url('/update_sales_party_status/?id=')}}" + id + "&status="+status;
                window.location.href = url;
            }
        });
        }

        function editparty(id) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This Party Details ?",
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
                var url = "{{url('/edit_sales_party/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        </script>

@endsection