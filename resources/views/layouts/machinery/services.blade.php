@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Machinery Services'])
@php
$edit=false;
$dataarray = json_decode($data, true);

                            if(isset(json_decode($data, true)['edit_data'])){
                            $editdata = $dataarray['edit_data'][0];
                            $edit=true;
                            $machinery = $dataarray['machinery'][0];
                            $dataarray = $dataarray['machinery_services'];
                            }else{
                                $machinery = $dataarray['machinery'][0];
                            $dataarray = $dataarray['machinery_services'];
                            }
$add_duration = session()->get('add_duration');
$duration     = getdurationdates($add_duration);
$today        = substr($duration['today'], 0, 10);
$min_date     = substr($duration['min'],   0, 10);
$max_date     = substr($duration['max'],   0, 10);
@endphp
<div class="row clearfix">
@if($edit)
@if(checkmodulepermission(6,'can_edit') == 1)
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">

        <form action="{{url('/updatemachineryservice')}}" method="post" enctype="multipart/form-data" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Edit Machinery Service</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                       
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                               
                                <label for="Name">Service Date</label>
                                <input type="hidden" name="id" value="{{$editdata['id']}}">
                                <input type="hidden" name="machinery_id" value="{{$editdata['machinery_id']}}" id="new_doc_machinery_id"/>
                                <input type="date" id="create_date" required class="form-control" name="create_date"
                                    min="{{ $min_date }}" max="{{ $max_date }}"
                                    value="{{$editdata['create_date']}}" >
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                                <label for="Name">Maintainence Items</label>
                                <input type="text"  required class="form-control" value="{{$editdata['maintainence_item']}}" name="maintainence_item" >
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                                <label for="Name">Next Service On</label>
                                <input type="date"  required class="form-control" value="{{$editdata['next_service_on']}}" name="next_service_on" >
                            </div>
                        </div>


                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 1 &nbsp;<sub>(Don't Attach, If Previously Attached Is Correct!)</sub></label>
                                <input type="file"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image1" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 2 &nbsp; <sub>(Don't Attach, If Previously Attached Is Correct!)</sub></label>
                                <input type="file"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image2" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 3 &nbsp; <sub>(Don't Attach, If Previously Attached Is Correct!)</sub></label>
                                <input type="file"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image3" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 4 &nbsp; <sub>(Don't Attach, If Previously Attached Is Correct!)</sub></label>
                                <input type="file"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image4" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 5 &nbsp; <sub>(Don't Attach, If Previously Attached Is Correct!)</sub></label>
                                <input type="file"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image5" >
                            </div>
                        </div>

                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="form-group">
                                <label for="Name">Remark</label>
                                <input type="text"   class="form-control" name="remark" value="{{$editdata['remark']}}" placeholder="Enter Some Remark" >
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
                <h2>
                    <a href="{{ url('/machinery?machinery_id=' . $machinery['head_id']) }}" class="btn btn-primary btn-round waves-effect" style="color: white !important; margin-right: 10px; padding: 6px 12px; vertical-align: middle;">
                        <i class="zmdi zmdi-arrow-left"></i> Back
                    </a>
                    <strong>Machinery Services</strong> List
                </h2>
                <br>
                <h2><strong>Machinery Name - </strong>{{$machinery['name']}}<br>
                    <strong> Head -</strong> {{$machinery['head']}}<br> 
                    <strong>Currently At Site - </strong>{{($machinery['status'] != "Sold") ? getSiteDetailsById($machinery['site_id'])->name : "Already Sold From Site (".getSiteDetailsById($machinery['site_id'])->name.")"}} 
                </h2>
         
                <ul class="header-dropdown" style="display: flex; align-items: center; gap: 10px;">
                    <li>
                        @if(checkmodulepermission(6,'can_add') == 1)
                        <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right" onclick="opennewdocmodel({{$machinery['id']}})" type="button">
                            <i class="zmdi zmdi-plus" style="color: white;"></i>
                        </button>
                        @endif
                    </li>
                </ul>
            </div>

            <div class="body">
            @if(checkmodulepermission(6,'can_view') == 1)
                <div class="table-responsive">
                    <table id="dataTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                               <th>Maintainence</th>
                               <th>Next Service</th>
                                <th>User</th>
                                <th>Attachments</th>
                              <th>Remark</th>
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
                                    <a class="single-user-name" href="#">{{$dd['create_date']}}</a>
                                </td>
                            
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['maintainence_item']}}</a>
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['next_service_on']}}</a>
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{getUserDetailsById($dd['user_id'])->name}}</a>
                                </td>
                                <td>
                                    @if($dd['image1'] != null)
                                    <a href="{{url('/').'/'.$dd['image1']}}" target="_blank" ><i class="zmdi zmdi-attachment-alt"></i> </a>
                                    @endif
                                    @if($dd['image2'] != null)
                                    <a href="{{url('/').'/'.$dd['image2']}}" target="_blank" ><i class="zmdi zmdi-attachment-alt"></i> </a>
                                    @endif
                                    @if($dd['image3'] != null)
                                    <a href="{{url('/').'/'.$dd['image3']}}" target="_blank" ><i class="zmdi zmdi-attachment-alt"></i> </a>
                                    @endif
                                    @if($dd['image4'] != null)
                                    <a href="{{url('/').'/'.$dd['image4']}}" target="_blank" ><i class="zmdi zmdi-attachment-alt"></i> </a>
                                    @endif
                                    @if($dd['image5'] != null)
                                    <a href="{{url('/').'/'.$dd['image5']}}" target="_blank" ><i class="zmdi zmdi-attachment-alt"></i> </a>
                                    @endif
                              </td>
                              <td>
                                <a class="single-user-name" href="#">{{$dd['remark']}}</a>
                            </td>
                                <td>
                                @if(checkmodulepermission(6,'can_edit') == 1)
                                    <button title="Edit" onclick="editdata({{$ddid}},{{$machinery['id']}})" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button> &nbsp;
                                @endif
                                @if(checkmodulepermission(6,'can_delete') == 1)
                                    <button title="Delete" onclick="deletedata({{$ddid}},{{$machinery['id']}})" style="all:unset" ><i class="zmdi zmdi-delete"></i> </button>
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
@if(checkmodulepermission(6,'can_add') == 1)

<div class="modal fade" id="newmachinerydocmodel" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{url('/addmachineryservice')}}" method="post" enctype="multipart/form-data" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add New Machinery Service</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">

                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <input type="hidden" name="machinery_id" id="new_service_machinery_id"/>
                                <label for="create_date">Service Date &nbsp;<span style="color:red;">*</span></label>
                                <input type="date" id="create_date" required class="form-control" name="create_date"
                                    min="{{ $min_date }}" max="{{ $max_date }}"
                                    value="{{ $today }}" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Maintainence Items &nbsp;<span style="color:red;">*</span></label>
                                <input type="text"  required class="form-control" name="maintainence_item" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="next_service_on">Next Service &nbsp;<span style="color:red;">*</span></label>
                                <input type="date" id="next_service_on" required class="form-control" name="next_service_on" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 1 &nbsp;<span style="color:red;">*</span></label>
                                <input type="file" required accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image1" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 2 &nbsp;<span style="color:red;">*</span></label>
                                <input type="file" required accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image2" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 3</label>
                                <input type="file"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image3" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 4</label>
                                <input type="file"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image4" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Attachment 5</label>
                                <input type="file"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="image5" >
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                                <label for="Name">Remark</label>
                                <input type="text"   class="form-control" name="remark" placeholder="Enter Some Remark" >
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-simple waves-effect" data-dismiss="modal"><a>CLOSE</a></button>
                    <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE CHANGES</a></button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
@section('scripts')

<script type="text/javascript">
    function deletedata(id,machinery_id) {
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
                var url = "{{url('/delete_machinery_service/?id=')}}" + id+"&machinery_id="+machinery_id;
                window.location.href = url;
            }
        });
        }

        function editdata(id,machinery_id) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This Head ?",
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
                var url = "{{url('/edit_machinery_service/?id=')}}" + id+"&machinery_id="+machinery_id;
                window.location.href = url;
            }
        });
        }
        function opennewdocmodel(id){
$('#newmachinerydocmodel').modal();
$('#new_service_machinery_id').val(id);
        }
     
</script>

@endsection