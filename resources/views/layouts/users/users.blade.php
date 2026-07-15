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
                                                        <label>Data View Duration (Days)</label>
                                                        @php
                                                            $view_dur = $editdata['view_duration'] ?? '';
                                                        @endphp
                                                        <input type="number" min="0" class="form-control" name="view_duration" id="view_duration_edit" value="{{ is_numeric($view_dur) ? $view_dur : '' }}" placeholder="Enter number of days (e.g. 5)">
                                                        @if(!empty($view_dur) && !is_numeric($view_dur))
                                                            <small class="text-muted">Current: {{ getviewdurations($view_dur) }}</small>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-lg-6 col-md-6 col-sm-12">
                                                    <div class="form-group">
                                                        <label>Data Creation Duration (Days)</label>
                                                        @php
                                                            $add_dur = $editdata['add_duration'] ?? '';
                                                        @endphp
                                                        <input type="number" min="0" class="form-control" name="add_duration" id="add_duration_edit" value="{{ is_numeric($add_dur) ? $add_dur : '' }}" placeholder="Enter number of days (e.g. 5)">
                                                        @if(!empty($add_dur) && !is_numeric($add_dur))
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
                                @if (isset($user_limit_reached) && $user_limit_reached)
                                    <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                        data-toggle="modal" data-target="#upgradePlanModal" type="button" title="Upgrade Plan to Add Users">
                                        <i class="zmdi zmdi-plus" style="color: white;"></i>
                                    </button>
                                @else
                                    <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                        data-toggle="modal" data-target="#addnewuser" type="button">
                                        <i class="zmdi zmdi-plus" style="color: white;"></i>
                                    </button>
                                @endif
                            @endif
                        </li>
                    </ul>
                </div>
                <div class="body">
                    <!-- Bulk Actions Bar -->
                    <div id="bulkActionsBar" class="p-2 mb-2 border rounded bg-white shadow-sm" style="display: none; border-left: 5px solid #eda61a !important;">
                        <div class="row align-items-center">
                            <div class="col-sm-4">
                                <span class="ml-2 font-weight-bold" style="color: #eda61a;"><span id="selectedCount">0</span> Users Selected</span>
                            </div>
                            <div class="col-sm-8 text-right">
                                <button onclick="handleBulkAction('Active')" class="btn btn-success btn-sm btn-round">
                                    <i class="zmdi zmdi-check"></i> Activate
                                </button>
                                <button onclick="handleBulkAction('Deactive')" class="btn btn-warning btn-sm btn-round">
                                    <i class="zmdi zmdi-block"></i> Deactivate
                                </button>
                                <button onclick="handleBulkDelete()" class="btn btn-danger btn-sm btn-round">
                                    <i class="zmdi zmdi-delete"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    @if (checkmodulepermission(1, 'can_view') == 1)
                        <div class="table-responsive">
                                <table id="userTable" class="table table-hover no-wrap">
                                    <thead>
                                        <tr>
                                            <th style="width:20px;"><input type="checkbox" id="selectAllUsers"></th>
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
                                        <tr class="search-row">
                                            <th></th>
                                            <th></th>
                                            <th></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Name" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Site" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Company" /></th>
                                            <th></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Status" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Username" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search Contact" /></th>
                                            <th><input type="text" class="form-control column-search" placeholder="Search PAN" /></th>
                                            @if (Session::get('role') == 1)
                                                <th><input type="text" class="form-control column-search" placeholder="Search Pass" /></th>
                                            @endif
                                            <th><input type="text" class="form-control column-search" placeholder="Search Created" /></th>
                                            <th></th>
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
                                            <input type="text" class="form-control" value="{{ session()->get('comp_name') }}" readonly>
                                            <input type="hidden" name="company_id" value="{{ session()->get('comp_db_id') }}">
                                        </div>
                                    </div>
                                    <div class="row clearfix m-t-15">
                                        <div class="col-sm-6">
                                            <b>View Duration (Days)</b>
                                            <input type="number" min="0" name="view_duration" id="view_duration_add" class="form-control" placeholder="Enter number of days (e.g. 5)">
                                            <small class="text-muted">Optional: Defaults to Role setting if empty.</small>
                                        </div>
                                        <div class="col-sm-6">
                                            <b>Creation Duration (Days)</b>
                                            <input type="number" min="0" name="add_duration" id="add_duration_add" class="form-control" placeholder="Enter number of days (e.g. 5)">
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
    function handleBulkAction(status) {
        let ids = [];
        $('.user-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;

        Swal.fire({
            title: 'Bulk Update Status?',
            text: "Mark " + ids.length + " users as " + status + "?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: (status == 'Active' ? '#28a745' : '#eda61a'),
            cancelButtonColor: '#000000',
            confirmButtonText: 'Yes, Update All'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post("{{ url('/bulk_update_users_status') }}", {
                    _token: "{{ csrf_token() }}",
                    ids: ids,
                    status: status
                }, function(res) {
                    if (res.status === 'Ok') {
                        Swal.fire('Updated!', res.message, 'success');
                        $('#userTable').DataTable().ajax.reload();
                        $('#bulkActionsBar').hide();
                        $('#selectAllUsers').prop('checked', false);
                    }
                });
            }
        });
    }

    function handleBulkDelete() {
        let ids = [];
        $('.user-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length === 0) return;

        Swal.fire({
            title: 'Are you sure?',
            text: "You want to delete " + ids.length + " users? This cannot be undone!",
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#ff0000',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Yes, Delete All'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post("{{ url('/bulk_delete_users') }}", {
                    _token: "{{ csrf_token() }}",
                    ids: ids
                }, function(res) {
                    if (res.status === 'Ok') {
                        Swal.fire('Deleted!', res.message, 'success');
                        $('#userTable').DataTable().ajax.reload();
                        $('#bulkActionsBar').hide();
                        $('#selectAllUsers').prop('checked', false);
                    } else {
                        Swal.fire('Error!', res.message, 'error');
                    }
                });
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
        // Select All handler
        $('#selectAllUsers').on('change', function() {
            $('.user-checkbox').prop('checked', $(this).prop('checked'));
            updateBulkBar();
        });

        // Row checkbox handler
        $(document).on('change', '.user-checkbox', function(e) {
            updateBulkBar();
        });

        function updateBulkBar() {
            let count = $('.user-checkbox:checked').length;
            if (count > 0) {
                $('#selectedCount').text(count);
                $('#bulkActionsBar').fadeIn();
            } else {
                $('#bulkActionsBar').fadeOut();
                $('#selectAllUsers').prop('checked', false);
            }
        }

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

        var table = $('#userTable').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: "{{ url('/users_ajax') }}",
                type: "POST",
                data: { _token: "{{ csrf_token() }}" }
            },
            columns: [
                { data: 0, orderable: false }, // Checkbox
                { data: 1, orderable: false }, // S.No
                { data: 2, orderable: false }, // Pic
                { data: 3 }, // Name
                { data: 4 }, // Site
                { data: 5 }, // Company
                { data: 6, orderable: false }, // Team
                { data: 7 }, // Status
                { data: 8 }, // Username
                { data: 9 }, // Contact
                { data: 10 }, // PAN
                @if (Session::get('role') == 1)
                { data: 11 }, // Pass
                { data: 12 }, // Created
                { data: 13, orderable: false } // Action
                @else
                { data: 11 }, // Created
                { data: 12, orderable: false } // Action
                @endif
            ],
            responsive: false, // Disabled to prevent expansion issues
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
                $('#bulkActionsBar').hide();
                $('#selectAllUsers').prop('checked', false);
            }
        });

        // Column Search Logic
        table.columns().every(function() {
            var that = this;
            $('input', $('.search-row th').get(this.index())).on('keyup change clear', function() {
                if (that.search() !== this.value) {
                    that.search(this.value).draw();
                }
            });
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
    .column-search {
        width: 100% !important;
        padding: 5px !important;
        height: auto !important;
        font-size: 12px !important;
    }
    .search-row th {
        padding: 5px !important;
    }
    .mr-1 { margin-right: 0.25rem; }
    .mr-3 { margin-right: 1rem; }
    .font-weight-bold { font-weight: 700; }
    .badge-success { background-color: #28a745; color: white; }
    .badge-danger { background-color: #dc3545; color: white; }
    #userTable tr { cursor: pointer; }
    .user-checkbox { cursor: pointer; width: 18px; height: 18px; }
    
    /* Team Info Horizontal Layout Fix */
    .team-info {
        padding: 0;
        margin: 0;
        list-style: none;
        display: flex;
        flex-direction: row;
        align-items: center;
    }
    .team-info li {
        margin-left: -10px;
        transition: all 0.3s ease;
    }
    .team-info li:first-child {
        margin-left: 0;
    }
    .team-info li:hover {
        transform: translateY(-5px);
        z-index: 10;
    }
    .team-info li img {
        width: 35px;
        height: 35px;
        border: 2px solid #fff;
        border-radius: 50%;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
</style>
@endsection
