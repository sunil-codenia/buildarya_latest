@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Bill Works'])
    @php
        $edit = false;
        $dataarray = json_decode($data, true);
        if (isset($dataarray['edit_data'])) {
            $editdata = $dataarray['edit_data'][0];
            $edit = true;
            $dataarray = $dataarray['data'];
        }
    @endphp
    <div class="row clearfix">

        @if ($edit)
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    @if (checkmodulepermission(4, 'can_edit') == 1)
                        <form action="{{ url('/updatebillwork') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit Bill Works</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="row clearfix">
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Name</label>
                                                <input type="hidden" name="id" value="{{ $editdata['id'] }}">
                                                <input type="text" id="Name" required class="form-control"
                                                    value="{{ $editdata['name'] }}" name="name">
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Unit</label>

                                                <input type="text" id="unit" required class="form-control"
                                                    value="{{ $editdata['unit'] }}" name="unit">
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
                    @endif
                </div>

            </div>
            <br>
        @endif
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Site</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Works for which a bill can be created will be listed here.</div>
                    </h2>
                    <ul class="header-dropdown">
                        <li>

                            @if (checkmodulepermission(4, 'can_add') == 1)
                                <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    data-toggle="modal" data-target="#newexpensehead1" type="button">
                                    <i class="zmdi zmdi-plus" style="color: white;"></i>
                                </button>
                            @endif
                        </li>
                    </ul>
                </div>

                <div class="body">
                    @if (checkmodulepermission(4, 'can_view') == 1)
                        <div class="table-responsive">
                            <table id="dataTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Unit</th>

                                        <th>Action</th>

                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 1;
                                    @endphp
                                    @foreach ($dataarray as $dd)
                                        @php
                                            $ddid = $dd['id'];
                                        @endphp

                                        <tr>
                                            <td>{{ $i++ }}</td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['name'] }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['unit'] }}</a>
                                            </td>


                                            <td>
                                                @if (checkmodulepermission(4, 'can_edit') == 1)
                                                    <button title="Edit" onclick="editdata('{{ $ddid }}')"
                                                        style="all:unset"><i class="zmdi zmdi-edit"></i> </button>
                                                @endif &nbsp;
                                                @if (checkmodulepermission(4, 'can_delete') == 1)
                                                    @if (isBillWorkDeletable($ddid))
                                                        <button title="Delete" onclick="deletedata('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-delete"></i> </button>
                                                    @endif
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
    @if (checkmodulepermission(4, 'can_add') == 1)
        <div class="modal fade" id="newexpensehead1" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/addbillwork') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New Bill Works</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-12 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label for="Name">Name</label>

                                        <input type="text" id="Name" required class="form-control" name="name">
                                    </div>
                                </div>
                                <div class="col-lg-12 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label for="Name">Unit</label>

                                        <input type="text" id="unit" required class="form-control" name="unit">
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit"
                                class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE</a></button>
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
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/delete_billwork/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function editdata(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Work ?",
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
                    var url = "{{ url('/edit_billwork/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }
    </script>
@endsection
