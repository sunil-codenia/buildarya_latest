@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Other Party'])
    @php
        $edit = false;
        $dataarray = json_decode($data, true);
        if (isset(json_decode($data, true)['edit_data'])) {
            $editdata = $dataarray['edit_data'][0];
            $edit = true;
            $dataarray = $dataarray['data'];
        }
    @endphp
    <div class="row clearfix">

        @if ($edit)
            @if (checkmodulepermission(8, 'can_edit') == 1)
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="card project_list">

                        <form action="{{ url('/updateotherparty') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit Party</h4>
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
                                                <label for="Name">Pan No.</label>

                                                <input type="text" id="panno" required class="form-control"
                                                    value="{{ $editdata['panno'] }}" name="panno">
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Address</label>

                                                <input type="text" id="address" required class="form-control"
                                                    value="{{ $editdata['address'] }}" name="address">
                                            </div>
                                        </div>

                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Bank A/C</label>
                                                <input type="text" id="bank_ac" required class="form-control"
                                                    value="{{ $editdata['bank_ac'] }}" name="bank_ac">
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Bank Ifsc</label>
                                                <input type="text" id="bank_ifsc" required class="form-control"
                                                    value="{{ $editdata['bank_ifsc'] }}" name="bank_ifsc">
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Bank Name</label>
                                                <input type="text" id="bank_name" required class="form-control"
                                                    value="{{ $editdata['bank_name'] }}" name="bank_name">
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Bank A/C Holder</label>
                                                <input type="text" id="bank_ac_holder" required class="form-control"
                                                    value="{{ $editdata['bank_ac_holder'] }}" name="bank_ac_holder">
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="cost_category_id">Cost Category</label>
                                                <select name="cost_category_id" id="cost_category_id" class="form-control show-tick" required>
                                                    <option value="">-- Select Cost Category --</option>
                                                    @foreach($cost_categories as $category)
                                                        <option value="{{$category->id}}" {{$editdata['cost_category_id'] == $category->id ? 'selected' : ''}}>{{$category->name}}</option>
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

                </div>
            @endif
            <br>
        @endif
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Other Parties</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Parties which are not suitable for bill parties, material
                            suppliers,expense parties can be created as other parties and will be listed here.</div>
                    </h2>
                    <ul class="header-dropdown">
                        <li>
                            @if (checkmodulepermission(8, 'can_add') == 1)
                                <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    data-toggle="modal" data-target="#newexpensehead1" type="button">
                                    <i class="zmdi zmdi-plus" style="color: white;"></i>
                                </button>
                            @endif
                        </li>
                    </ul>
                </div>
                <div class="body">
                    @if (checkmodulepermission(8, 'can_view') == 1)
                        <div class="table-responsive">
                            <table id="dataTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Pan No.</th>
                                        <th>Address</th>
                                        <th>Bank Details</th>
                                        <th>Cost Category</th>
                                        <th>Status</th>
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
                                                <a class="single-user-name" href="#">{{ $dd['panno'] }}</a>
                                            </td>

                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['address'] }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">Bank -
                                                    {{ $dd['bank_name'] }}<br>Bank A/C - {{ $dd['bank_ac'] }}<br>Bank IFSC
                                                    - {{ $dd['bank_ifsc'] }}<br>A/C Holder -
                                                    {{ $dd['bank_ac_holder'] }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['category_name'] }}</a>
                                            </td>
                                            <td>
                                                @if ($dd['status'] == 'Active')
                                                    @if (checkmodulepermission(8, 'can_certify') == 1)
                                                        <span onclick="updatepartystatus('{{ $ddid }}','Deactive')"
                                                            class="badge badge-success">{{ $dd['status'] }}</span>
                                                    @else
                                                        <span onclick="updatepartystatus('{{ $ddid }}','Active')"
                                                            class="badge badge-danger">{{ $dd['status'] }}</span>
                                                    @endif
                                                @endif
                                            </td>
                                            <td>
                                                @if (checkmodulepermission(8, 'can_edit') == 1)
                                                    <button title="Edit" onclick="editparty('{{ $ddid }}')"
                                                        style="all:unset"><i class="zmdi zmdi-edit"></i> </button>
                                                    &nbsp;
                                                @endif
                                                @if (checkmodulepermission(8, 'can_delete') == 1)
                                                    @if (isOtherPartyDeletable($ddid))
                                                        <button title="Delete"
                                                            onclick="deletedata('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-delete"></i> </button>
                                                        &nbsp;
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
    @if (checkmodulepermission(8, 'can_add') == 1)
        <div class="modal fade" id="newexpensehead1" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <form action="{{ url('/addotherparty') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New Other Party</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Name</label>

                                        <input type="text" id="Name" required class="form-control"
                                            name="name">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Pan No.</label>

                                        <input type="text" id="panno" required class="form-control"
                                            name="panno">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Address</label>

                                        <input type="text" id="address" required class="form-control"
                                            name="address">
                                    </div>
                                </div>

                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Bank A/C</label>

                                        <input type="text" id="bank_ac" required class="form-control"
                                            name="bank_ac">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Bank Ifsc</label>

                                        <input type="text" id="bank_ifsc" required class="form-control"
                                            name="bank_ifsc">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Bank Name</label>

                                        <input type="text" id="bank_name" required class="form-control"
                                            name="bank_name">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Bank A/C Holder</label>

                                        <input type="text" id="bank_ac_holder" required class="form-control"
                                            name="bank_ac_holder">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="cost_category_id">Cost Category</label>
                                        <select name="cost_category_id" id="cost_category_id" class="form-control show-tick" required>
                                            <option value="">-- Select Cost Category --</option>
                                            @foreach($cost_categories as $category)
                                                <option value="{{$category->id}}">{{$category->name}}</option>
                                            @endforeach
                                        </select>
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
                    var url = "{{ url('/delete_otherparty/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function updatepartystatus(id, status) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To " + status + " This Party?",
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
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/update_other_party_status/?id=') }}" + id + "&status=" + status;
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
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/edit_otherparty/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }
    </script>
@endsection
