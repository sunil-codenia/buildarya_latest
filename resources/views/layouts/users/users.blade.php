@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Users Section'])
    @php
        $edit = false;
        if (isset($data)) {
            $dataarray = json_decode($data, true);
            if (isset($dataarray['edit_data'])) {
                $editdata = $dataarray['edit_data'][0];
                $edit = true;
            }
        }
    @endphp
    <div class="row clearfix">
        @if ($edit)
            @if (checkmodulepermission(1, 'can_edit') == 1)
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="card project_list">
                        <form action="{{ url('/updateusers') }}" method="post" class="form" enctype="multipart/form-data">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit User</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="row clearfix">
                                        <div class="col-lg-3 col-md-3 col-sm-3">
                                            <div class="form-group">
                                                <img height="200" width="200" id="update_user_image"
                                                    src="{{ asset('/' . $editdata['image']) }}"
                                                    class="rounded-circle img-raised">
                                                <input type="file" accept="Image/*" name="image"
                                                    onchange="document.getElementById('update_user_image').src = window.URL.createObjectURL(this.files[0])">
                                            </div>
                                        </div>
                                        <div class="col-lg-9 col-md-9 col-sm-9">
                                            <div class="row clearfix">
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <div class="form-group">
                                                        <label for="Name">Name</label>
                                                        <input type="hidden" name="id" value="{{ $editdata['id'] }}">
                                                        <input type="text" id="Name" required class="form-control"
                                                            value="{{ $editdata['name'] }}" name="name">
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <div class="form-group">
                                                        <label for="username">Username</label>
                                                        <input type="text" id="username" required class="form-control"
                                                            value="{{ $editdata['username'] }}" name="username">
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <div class="form-group">
                                                        <label for="pass">Password</label>
                                                        <input type="password" id="pass" required class="form-control"
                                                            value="{{ $editdata['pass'] }}" name="pass">
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <div class="form-group"><b>Site</b>
                                                        @php
                                                            $assigned_sites = explode(',', $editdata['site_id']);
                                                        @endphp
                                                        <select name="site_id[]" class="form-control show-tick"
                                                            data-live-search="true" required multiple>
                                                            @php
                                                                $sites = getallsites();
                                                            @endphp
                                                            @foreach ($sites as $site)
                                                                <option {{ in_array($site->id, $assigned_sites) ? 'selected' : '' }} value="{{ $site->id }}">{{ $site->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <div class="form-group"><b>Role</b>
                                                        <select name="role_id" class="form-control show-tick"
                                                            data-live-search="true" required>
                                                            <option value="" selected disabled>--Select Role--</option>
                                                            @php
                                                                $roles = getallRoles();
                                                            @endphp
                                                            @foreach ($roles as $role)
                                                                <option {{ $role->id == $editdata['role_id'] ? 'selected' : '' }} value="{{ $role->id }}">{{ $role->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <div class="form-group">
                                                        <label for="pan_no">Pan No.</label>
                                                        <input type="text" id="pan_no" required class="form-control"
                                                            value="{{ $editdata['pan_no'] }}" name="pan_no">
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <div class="form-group">
                                                        <label for="contact_no">Contact No.</label>
                                                        <input type="text" id="contact_no" required class="form-control"
                                                            value="{{ $editdata['contact_no'] }}" name="contact_no">
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <div class="form-group">
                                                        <label>Login Platform</label>
                                                        <select name="mobile_only" class="form-control show-tick" required>       
                                                            <option value="no" {{ $editdata['mobile_only'] == 'no' ? 'selected' : '' }}>Web & Mobile Both</option>
                                                            <option value="yes" {{ $editdata['mobile_only'] == 'yes' ? 'selected' : '' }}>Only Mobile App</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <div class="form-group"><b>Company</b>
                                                        <select name="company_id" class="form-control show-tick" data-live-search="true" required>
                                                            @foreach (getallCompanies() as $comp)
                                                                <option {{ $comp->id == ($editdata['company_id'] ?? '') ? 'selected' : '' }} value="{{ $comp->id }}">{{ $comp->name }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-6 col-md-6 col-sm-12">
                                                    <div class="form-group">
                                                        <label>Data View Duration</label>
                                                        @php
                                                            $view_dur = $editdata['view_duration'] ?? '';
                                                            $is_range = strpos($view_dur, ',') !== false;
                                                            $parts = $is_range ? explode(',', $view_dur) : [$view_dur, ''];
                                                            $from = $parts[0];
                                                            $to = $parts[1] ?? '';
                                                        @endphp
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <small>From</small>
                                                                <input type="date" class="form-control date-range-from" data-target="#view_duration_edit" value="{{ preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $from) ? $from : '' }}">
                                                            </div>
                                                            <div class="col-6">
                                                                <small>To (Optional)</small>
                                                                <input type="date" class="form-control date-range-to" data-target="#view_duration_edit" value="{{ preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $to) ? $to : '' }}">
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="view_duration" id="view_duration_edit" value="{{ $view_dur }}">
                                                        @if(!empty($view_dur) && !strpos($view_dur, '-') && !strpos($view_dur, ','))
                                                            <small class="text-muted">Current: {{ getviewdurations($view_dur) }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-lg-6 col-md-6 col-sm-12">
                                                    <div class="form-group">
                                                        <label>Data Creation Duration</label>
                                                        @php
                                                            $add_dur = $editdata['add_duration'] ?? '';
                                                            $is_add_range = strpos($add_dur, ',') !== false;
                                                            $add_parts = $is_add_range ? explode(',', $add_dur) : [$add_dur, ''];
                                                            $add_from = $add_parts[0];
                                                            $add_to = $add_parts[1] ?? '';
                                                        @endphp
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <small>From</small>
                                                                <input type="date" class="form-control date-range-from" data-target="#add_duration_edit" value="{{ preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $add_from) ? $add_from : '' }}">
                                                            </div>
                                                            <div class="col-6">
                                                                <small>To (Optional)</small>
                                                                <input type="date" class="form-control date-range-to" data-target="#add_duration_edit" value="{{ preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $add_to) ? $add_to : '' }}">
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="add_duration" id="add_duration_edit" value="{{ $add_dur }}">
                                                        @if(!empty($add_dur) && !strpos($add_dur, '-') && !strpos($add_dur, ','))
                                                            <small class="text-muted">Current: {{ getadddurations($add_dur) }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-3 col-sm-3">
                                                    <br>
                                                    <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect">Update</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                    <h2><strong>Users</strong> List</h2>
                    <ul class="header-dropdown">

                        <li>
                            @if (checkmodulepermission(1, 'can_add') == 1)
                                <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    data-toggle="modal" data-target="#addnewuser" type="button">
                                    <i class="zmdi zmdi-plus" style="color: white;"></i>
                                </button>
                            @endif
                        </li>
                    </ul>
                </div>
                <div class="body">
                    @if (checkmodulepermission(1, 'can_view') == 1)
                        <div class="table-responsive">

                                <table id="userTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;">S.No.</th>
                                            <th style="width:50px;">Pic</th>
                                            <th>Name/Role</th>
                                            <th>Site</th>
                                            <th>Company</th>
                                            <th>Team</th>
                                            <th>Status</th>
                                            <th>Username</th>
                                            <th>Contact</th>
                                            <th>PAN</th>
                                            @if (Session::get('role') == 1)
                                                <th>Pass</th>
                                            @endif
                                            <th>Created</th>
                                            <th style="width:100px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
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
    @if (checkmodulepermission(1, 'can_add') == 1)
        <div class="modal fade" id="addnewuser" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <form action="addnewuser" method="post" class="form" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New User</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-sm-4 text-center">
                                    <div class="form-group">
                                        <img height="150" width="150" id="user_image"
                                            src="{{ asset('/images/noprofile.jpg') }}" class="rounded-circle img-raised">
                                        <br><br>
                                        <input type="file" accept="Image/*" name="image" class="form-control"
                                            onchange="document.getElementById('user_image').src = window.URL.createObjectURL(this.files[0])">
                                    </div>
                                </div>
                                <div class="col-sm-8">
                                    <div class="row clearfix">
                                        <div class="col-sm-6"><b>Name</b>
                                            <input type="text" required name="name" class="form-control" placeholder="Full Name">
                                        </div>
                                        <div class="col-sm-6"><b>Phone Number</b>
                                            <input type="number" required name="contact_no" class="form-control" placeholder="10 Digit Mobile">
                                        </div>
                                    </div>
                                    <div class="row clearfix m-t-15">
                                        <div class="col-sm-6"><b>Username</b>
                                            <input type="text" name="username" class="form-control" required placeholder="Login Username">
                                        </div>
                                        <div class="col-sm-6"><b>Password</b>
                                            <input type="password" name="password" class="form-control" required placeholder="Login Password">
                                        </div>
                                    </div>
                                    <div class="row clearfix m-t-15">
                                        <div class="col-sm-6"><b>Site</b>
                                            <select name="site_id[]" class="form-control show-tick" data-live-search="true" required multiple data-placeholder="Select Sites">
                                                @foreach (getallsites() as $site)
                                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-sm-6"><b>Role</b>
                                            <select name="role_id" class="form-control show-tick" data-live-search="true" required>
                                                <option value="" selected disabled>--Select Role--</option>
                                                @foreach (getallRoles() as $role)
                                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row clearfix m-t-15">
                                        <div class="col-sm-6"><b>Pan No.</b>
                                            <input type="text" name="pan_no" class="form-control" required placeholder="PAN Card No">
                                        </div>
                                        <div class="col-sm-6"><b>Login Platform</b>
                                            <select name="mobile_only" class="form-control show-tick" required>                                       
                                                <option value="no">Web & Mobile Both</option>
                                                <option value="yes">Only Mobile App</option>
                                            </select>
                                        </div>
                                        <div class="col-sm-6"><b>Company</b>
                                            <select name="company_id" class="form-control show-tick" data-live-search="true" required>
                                                @foreach (getallCompanies() as $comp)
                                                    <option value="{{ $comp->id }}">{{ $comp->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row clearfix m-t-15">
                                        <div class="col-sm-6">
                                            <b>View Duration Range</b>
                                            <div class="row">
                                                <div class="col-6">
                                                    <small>From</small>
                                                    <input type="date" class="form-control date-range-from" data-target="#view_duration_add">
                                                </div>
                                                <div class="col-6">
                                                    <small>To</small>
                                                    <input type="date" class="form-control date-range-to" data-target="#view_duration_add">
                                                </div>
                                            </div>
                                            <input type="hidden" name="view_duration" id="view_duration_add" value="">
                                            <small class="text-muted">Optional: Defaults to Role setting if empty.</small>
                                        </div>
                                        <div class="col-sm-6">
                                            <b>Add Duration Range</b>
                                            <div class="row">
                                                <div class="col-6">
                                                    <small>From</small>
                                                    <input type="date" class="form-control date-range-from" data-target="#add_duration_add">
                                                </div>
                                                <div class="col-6">
                                                    <small>To</small>
                                                    <input type="date" class="form-control date-range-to" data-target="#add_duration_add">
                                                </div>
                                            </div>
                                            <input type="hidden" name="add_duration" id="add_duration_add" value="">
                                            <small class="text-muted">Optional: Defaults to Role setting if empty.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-round waves-effect" data-dismiss="modal">CLOSE</button>
                            <button type="submit" class="btn btn-primary btn-round waves-effect">SAVE CHANGES</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
<script type="text/javascript">
    function assignPerm(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Update This User Permissions?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#eda61a',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Update'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ url('/assign_permission/?id=') }}" + id;
            }
        });
    }

    function editdata(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This User?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#eda61a',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Edit'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ url('/edit_users/?id=') }}" + id;
            }
        });
    }

    function deleteUser(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#ff0000',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ url('/delete_users/?id=') }}" + id;
            }
        });
    }

    function updateuserstatus(id, status) {
        Swal.fire({
            title: 'Update Status?',
            text: "Mark this user as " + status + "?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: (status == 'Active' ? '#28a745' : '#dc3545'),
            cancelButtonColor: '#000000',
            confirmButtonText: 'Yes, ' + status
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ url('/update_user_status/?id=') }}" + id + "&status=" + status;
            }
        });
    }

    function assignPerm(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Update This User Permissions?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#eda61a',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Update'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ url('/assign_permission/?id=') }}" + id;
            }
        });
    }

    function editdata(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This User?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#eda61a',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Edit'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ url('/edit_users/?id=') }}" + id;
            }
        });
    }

    function deleteUser(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#ff0000',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ url('/delete_users/?id=') }}" + id;
            }
        });
    }

    function updateuserstatus(id, status) {
        Swal.fire({
            title: 'Update Status?',
            text: "Mark this user as " + status + "?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: (status == 'Active' ? '#28a745' : '#dc3545'),
            cancelButtonColor: '#000000',
            confirmButtonText: 'Yes, ' + status
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "{{ url('/update_user_status/?id=') }}" + id + "&status=" + status;
            }
        });
    }



    $(document).ready(function() {
        var newExportAction = function (e, dt, button, config) {
            var self = this;
            var oldStart = dt.settings()[0]._iDisplayStart;
            dt.one('preXhr', function (e, s, data) {
                data.start = 0;
                data.length = -1;
                dt.one('preDraw', function (e, settings) {
                    if (button[0].className.indexOf('buttons-csv') >= 0) {
                        $.fn.dataTable.ext.buttons.csvHtml5.available(dt, config) ?
                            $.fn.dataTable.ext.buttons.csvHtml5.action.call(self, e, dt, button, config) :
                            $.fn.dataTable.ext.buttons.csvFlash.action.call(self, e, dt, button, config);
                    } else if (button[0].className.indexOf('buttons-excel') >= 0) {
                        $.fn.dataTable.ext.buttons.excelHtml5.available(dt, config) ?
                            $.fn.dataTable.ext.buttons.excelHtml5.action.call(self, e, dt, button, config) :
                            $.fn.dataTable.ext.buttons.excelFlash.action.call(self, e, dt, button, config);
                    } else if (button[0].className.indexOf('buttons-pdf') >= 0) {
                        $.fn.dataTable.ext.buttons.pdfHtml5.available(dt, config) ?
                            $.fn.dataTable.ext.buttons.pdfHtml5.action.call(self, e, dt, button, config) :
                            $.fn.dataTable.ext.buttons.pdfFlash.action.call(self, e, dt, button, config);
                    }
                    dt.one('preXhr', function (e, s, data) {
                        settings._iDisplayStart = oldStart;
                        data.start = oldStart;
                    });
                    setTimeout(dt.ajax.reload, 0);
                    return false;
                });
            });
            dt.ajax.reload();
        };

        $('#userTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: "{{ url('/users_ajax') }}",
                type: "POST",
                data: { _token: "{{ csrf_token() }}" }
            },
            columns: [
                { data: 0, orderable: false },
                { data: 1, orderable: false },
                { data: 2 },
                { data: 3 },
                { data: 4 },
                { data: 5, orderable: false },
                { data: 6 },
                { data: 7 },
                { data: 8 },
                { data: 9 },
                @if (Session::get('role') == 1)
                { data: 9 },
                { data: 10 },
                { data: 11, orderable: false }
                @else
                { data: 9 },
                { data: 10, orderable: false }
                @endif
            ],
            responsive: true,
            dom: 'lBfrtip',
            buttons: [
                {
                    extend: 'csvHtml5',
                    action: newExportAction,
                    className: 'btn btn-round btn-custom-color'
                },
                {
                    extend: 'excelHtml5',
                    action: newExportAction,
                    className: 'btn btn-round btn-custom-color'
                },
                {
                    extend: 'pdfHtml5',
                    action: newExportAction,
                    className: 'btn btn-round btn-custom-color'
                }
            ],
            "oLanguage": {
                "oPaginate": {
                    "sFirst": '<i class="zmdi zmdi-fast-rewind"></i>',
                    "sLast": '<i class="zmdi zmdi-fast-forward"></i>',
                    "sPrevious": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>',
                    "sNext": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>'
                },
                "sInfo": "Showing ( <b>_START_ - _END_ </b>) Of <b> _TOTAL_ </b> Entries <br> Page<b> _PAGE_ </b>of <b>_PAGES_</b> Pages",
                "sSearch": '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-search"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
                "sSearchPlaceholder": "Search...",
                "sLengthMenu": "Results :  _MENU_",
                "sPadding": '2rem'
            },
            pagingType: "full_numbers",
            drawCallback: function(settings) {
            }
        });

        $(document).on('change', '.date-range-from, .date-range-to', function() {
            var container = $(this).closest('.form-group, .col-sm-6');
            var from = container.find('.date-range-from').val();
            var to = container.find('.date-range-to').val();
            var target = $(this).data('target');
            
            if (from || to) {
                $(target).val(from + ',' + to);
            } else {
                $(target).val('');
            }
        });
    });
</script>
<style>
    .btn-custom-color {
        background-color: #eda61a !important;
        color: white !important;
    }
    .btn-icon.btn-round {
        width: 35px;
        height: 35px;
        padding: 0;
        line-height: 35px;
        text-align: center;
    }
</style>
@endsection
