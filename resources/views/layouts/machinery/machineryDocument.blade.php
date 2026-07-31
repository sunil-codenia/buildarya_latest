@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Machinery Document'])
@php
$edit=false;
$dataarray = json_decode($data, true);

                            if(isset(json_decode($data, true)['edit_data'])){
                            $editdata = $dataarray['edit_data'][0];
                            $edit=true;
                            $machinery = $dataarray['machinery'][0];
                            $dataarray = $dataarray['machinery_documents'];
                            }else{
                                $machinery = $dataarray['machinery'][0];
                            $dataarray = $dataarray['machinery_documents'];
                            }
@endphp
<div class="row clearfix">
@if($edit)
@if(checkmodulepermission(6,'can_edit') == 1)
    <div class="col-md-12 col-sm-12 col-xs-12">
        
        <div class="card project_list">

        <form action="{{url('/updatemachinerydocument')}}" method="post" enctype="multipart/form-data" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Edit Machinery Document</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                       
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                               
                                <label for="Name">Name</label>
                                <input type="hidden" name="id" value="{{$editdata['id']}}">
                                <input type="hidden" name="machinery_id" value="{{$editdata['machinery_id']}}" id="new_doc_machinery_id"/>
                                <input type="text" id="Name" required class="form-control" name="name" value="{{$editdata['name']}}" placeholder="Enter the Document Name">
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                                <label for="Name">Issue Date</label>
                                <input type="date"  required class="form-control" value="{{$editdata['issue_date']}}" name="issue_date" >
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                                <label for="Name">End Date</label>
                                <input type="date"   class="form-control" value="{{$editdata['end_date']}}" name="end_date" >
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-sm-6">
                            <div class="form-group">
                                <label for="Name">Attachment &nbsp;<sub>(Don't Attach, If Previously Attached Is Correct!)</sub></label>
                                <input type="file"  accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="attachment" >
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
                    <strong>Machinery Document</strong> List
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
                                <th>Name</th>
                               <th>Status</th>
                                <th>Issue Date</th>
                                <th>End Date</th>
                                <th>Attachment</th>
                                
                              <th>Upload Date</th>
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
                                @if($dd['end_date']!= null && (date('Y-m-d') > $dd['end_date'] ))
                                <button class="btn btn-danger" href="#">Expired</a>
                                @else
                                <a class=" btn btn-success" href="#">Valid</a>
                                @endif
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['issue_date']}}</a>
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['end_date']}}</a>
                                </td>
                                <td>
                                    @if($dd['attachment'] != null)
                                    <a href="{{url('/').'/'.$dd['attachment']}}" target="_blank" ><i class="zmdi zmdi-attachment-alt"></i> </a>
                                    @endif
                              </td>
                              <td>
                                <a class="single-user-name" href="#">{{$dd['create_date']}}</a>
                            </td>
                                <td>
                                @if(checkmodulepermission(6,'can_edit') == 1)
                                    <button onclick="editdata({{$ddid}},{{$machinery['id']}})" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button> &nbsp;
                                @endif
                                @if(checkmodulepermission(6,'can_delete') == 1)
                                    <button onclick="deletedata({{$ddid}},{{$machinery['id']}})" style="all:unset" ><i class="zmdi zmdi-delete"></i> </button>
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
    <div class="modal-dialog modal-md" role="document">
        <form action="{{url('/addmachinerydocument')}}" method="post" enctype="multipart/form-data" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add New Machinery Document</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">

                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="form-group">
                                <input type="hidden" name="machinery_id" id="new_doc_machinery_id"/>
                                <label for="Name">Name</label>
                                <input type="text" id="Name" required class="form-control" name="name" placeholder="Enter the Document Name">
                            </div>
                        </div>
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="form-group">
                                <label for="Name">Issue Date</label>
                                <input type="date"  required class="form-control" name="issue_date" >
                            </div>
                        </div>
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="form-group">
                                <label for="Name">End Date</label>
                                <input type="date"   class="form-control" name="end_date" >
                            </div>
                        </div>
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="form-group">
                                <label for="Name">Attachment</label>
                                <input type="file" required accept="image/jpeg,image/gif,image/png,application/pdf,image/x-eps" class="form-control" name="attachment" >
                            </div>
                        </div>
                        <div class="col-lg-12 col-md-12 col-sm-12">
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
                var url = "{{url('/delete_machinery_document/?id=')}}" + id+"&machinery_id="+machinery_id;
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
                var url = "{{url('/edit_machinery_document/?id=')}}" + id+"&machinery_id="+machinery_id;
                window.location.href = url;
            }
        });
        }
        function opennewdocmodel(id){
$('#newmachinerydocmodel').modal();
$('#new_doc_machinery_id').val(id);
        }
</script>

@endsection