@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Invoice Heads'])
@php
$edit=false;
$dataarray = json_decode($data, true);
                            if(isset(json_decode($data, true)['edit_data'])){
                            $editdata = $dataarray['edit_data'][0];
                            $edit=true;
                            }
@endphp
<div class="row clearfix">

@if($edit)
@if(checkmodulepermission(7,'can_edit') == 1)
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">

        <form action="{{url('/updatesalesinv_head')}}" method="post"  enctype="multipart/form-data" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Edit Sales Invoice Head</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                            <label for="Name">Name</label>
                                <input type="hidden" name="id" value="{{$editdata['id']}}">
                                <input type="text" id="Name" required class="form-control" value="{{$editdata['name']}}" name="name" >
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                            <label for="Name">Type</label>
                            <select id="Type" class="form-control show-tick" data-live-search="true" name="type" required>
                                @php 
                                $types = getInvoiceHeads();
                                @endphp
                                @foreach($types as $key => $value)
                                @if($key == $editdata['type'])
                                <option selected value="{{$key}}">{{$value}}</option>
                                @else
                                <option value="{{$key}}">{{$value}}</option>
                                @endif
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
    @endif
    <br>
    @endif
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
                <h2><strong>Sales Invoice Heads</strong> List</h2>
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
                                <th>Type</th>
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
                                    <a class="single-user-name" href="#">{{getInvoiceHeads($dd['type'])}}</a>
                                </td> 
                                <td>
                                @if(checkmodulepermission(7,'can_edit') == 1)
                                    <button title="Edit" onclick="edithead('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button>
                                    @endif
                                    &nbsp;
                                    @if(checkmodulepermission(7,'can_delete') == 1)
                                    @if(isInvoiceHeadDeletable($ddid))
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
                <div class="alert alert-danger"> You Don't Have Permission to View </div>
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
        <form action="{{url('/addsalesinv_head')}}" method="post" enctype="multipart/form-data" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add New Invoice Head</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                            <label for="Name">Name</label>

                                <input type="text" id="Name" required class="form-control" name="name" >
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                            <label for="Name">Type</label>
                            <select id="Type"  class="form-control show-tick" data-live-search="true" name="type" required>
@php 
$types = getInvoiceHeads();
@endphp
@foreach($types as $key => $value)
<option value="{{$key}}">{{$value}}</option>
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
                var url = "{{url('/delete_sales_inv_head/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
    
        function edithead(id) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This Head Details ?",
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
                var url = "{{url('/edit_sales_inv_head/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        </script>

@endsection