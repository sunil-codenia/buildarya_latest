@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => "Machinery's Expense head"])

<div class="row clearfix">

    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
      
            <div class="header">

                <h2>
                    <a href="{{ url('/machinery_head') }}" class="btn btn-primary btn-round waves-effect" style="color: white !important; margin-right: 10px; padding: 6px 12px; vertical-align: middle;">
                        <i class="zmdi zmdi-arrow-left"></i> Back
                    </a>
                    <strong>Machinery's Expense Head</strong> List
                </h2>
                <ul class="header-dropdown" style="display: flex; align-items: center; gap: 10px;">
                    <li>
                    @if(checkmodulepermission(6,'can_add') == 1)
                        <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right" data-toggle="modal" data-target="#newmachineryExpensehead1" type="button">
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
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $i=1;
                            $data = json_decode($data, true);
                            $dataarray = $data['data'];
                            $heads = $data['heads'];
                            @endphp
                            @foreach($dataarray as $dd)
                            @php
                            $ddid = $dd['id'];
                            @endphp

                            <tr>
                                <td>{{$i++}}</td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['head']}}</a>
                                </td>
                                <td>
                                @if(checkmodulepermission(6,'can_delete') == 1)
                                    <button title="Delete" onclick="deletedata('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-delete"></i> </button>
                                @endif
                                </td>

                            </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-danger">You Don't Have Permission to View</div>
                @endif
            </div>
           
        </div>
    </div>
</div>

@endsection

@section('models')
@if(checkmodulepermission(6,'can_add') == 1)
<div class="modal fade" id="newmachineryExpensehead1" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <form action="{{url('/addmachineryExpensehead')}}" method="post" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add New Machinery's Expense Head</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                            <label for="Name">Head</label>
                        </div>
                        <div class="col-lg-8 col-md-8 col-sm-8">
                            <div class="form-group">
                                <select name="head_id"  class="form-control show-tick" data-live-search="true" required>
                                    <option value="" selected disabled >--Select Head--</option>
                                @foreach($heads as $head)
                                <option value = "{{$head['id']}}">{{$head['name']}}</option>
                                @endforeach
                            </select> 
                            
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
                var url = "{{url('/delete_machineryExpense_head/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }

      
</script>

@endsection