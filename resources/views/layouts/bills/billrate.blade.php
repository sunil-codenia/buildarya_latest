@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Bill Rates'])
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
                @if (checkmodulepermission(4, 'can_edit') == 1)
                    <div class="card project_list">

                        <form action="{{ url('/updatebillrate') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit Bill Rate</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="row clearfix">
                                        <div class="col-lg-12 col-md-12 col-sm-12">
                                            <div class="form-group">
                                                <label for="name">Work</label>
                                                <input type="hidden" name="id" value="{{ $editdata['id'] }}">
                                                <select name="work_id" id="work_id" required
                                                    class="form-control show-tick" data-live-search="true">
                                                    @php
                                                        $works = getallworkslist();
                                                    @endphp
                                                    @foreach ($works as $work)
                                                        @if ($editdata['work_id'] == $work->id)
                                                            <option selected value="{{ $work->id }}">{{ $work->name }}
                                                            </option>
                                                        @else
                                                            <option value="{{ $work->id }}">{{ $work->name }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-12 col-md-12 col-sm-12">
                                            <div class="form-group">
                                                <label for="name">Rate</label>

                                                <input type="number" id="rate" required
                                                    value="{{ $editdata['rate'] }}" class="form-control" name="rate">
                                            </div>
                                        </div>
                                        <div class="col-lg-12 col-md-12 col-sm-12">
                                            <div class="form-group">
                                                <label for="name">Site</label>

                                                <select id="site_id" name="site_id" required
                                                    class="form-control show-tick" data-live-search="true">
                                                    @php
                                                        $sites = getallsites();
                                                    @endphp
                                                    @foreach ($sites as $site)
                                                        @if ($editdata['site_id'] == $site->id)
                                                            <option selected value="{{ $site->id }}">{{ $site->name }}
                                                            </option>
                                                        @else
                                                            <option value="{{ $site->id }}">{{ $site->name }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
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
                    </div>
                @else
                    <div class="alert alert-danger">You Don't Have Permission to Edit</div>
                @endif

            </div>
            <br>
        @endif
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Bills Rate</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">The rate of different works at different sites will be listed here.</div>
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
                                        <th>Work</th>
                                        <th>Rate</th>
                                        <th>Site</th>

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
                                                <a class="single-user-name"
                                                    href="#">{{ getWorkDetailsById($dd['work_id'])->name }}</a>
                                            </td>

                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['rate'] }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name"
                                                    href="#">{{ getSiteDetailsById($dd['site_id'])->name }}</a>
                                            </td>



                                            <td>
                                                @if (checkmodulepermission(4, 'can_edit') == 1)
                                                    <button title="Edit" onclick="editdata('{{ $ddid }}')"
                                                        style="all:unset"><i class="zmdi zmdi-edit"></i> </button>
                                                    &nbsp;
                                                @endif
                                                @if (checkmodulepermission(4, 'can_delete') == 1)
                                                    <button title="Delete" onclick="deletedata('{{ $ddid }}')"
                                                        style="all:unset"><i class="zmdi zmdi-delete"></i> </button>
                                                @endif
                                            </td>

                                        </tr>
                                    @endforeach

                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-danger">You Don't Have Permissio to View</div>
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
                <form action="{{ url('/addbillrate') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New Bill Rate</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-12 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label for="name">Work</label>
                                        <select name="work_id" id="work_id" required class="form-control show-tick"
                                            data-live-search="true">
                                            @php
                                                $works = getallworkslist();
                                            @endphp
                                            @foreach ($works as $work)
                                                <option value="{{ $work->id }}">{{ $work->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-12 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label for="name">Site Id</label>
                                        <select name="site_id" id="site_id" required class="form-control show-tick"
                                            data-live-search="true">
                                            @php
                                                $sites = getallsites();
                                            @endphp
                                            @foreach ($sites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                                            @endforeach
                                        </select>

                                    </div>

                                </div>
                                <div class="col-lg-12 col-md-12 col-sm-12">
                                    <div class="form-group">
                                        <label for="name">Rate</label>
                                        <input type="number" id="rate" required class="form-control"
                                            name="rate">
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
                    var url = "{{ url('/delete_billrate/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function editdata(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Work Rate ?",
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
                    var url = "{{ url('/edit_billrate/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }
    </script>
@endsection
