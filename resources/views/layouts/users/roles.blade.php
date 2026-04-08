@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Roles Section'])
    @php
        $roleedit = false;
        $settingedit = false;
        $dataarray = json_decode($data, true);
        if (isset(json_decode($data, true)['edit_data'])) {
            $editdata = $dataarray['edit_data'][0];
            $roleedit = true;
            $dataarray = $dataarray['data'];
        }
        if (isset(json_decode($data, true)['edit_setting'])) {
            $editdata = $dataarray['edit_setting'][0];
            $settingedit = true;
            $dataarray = $dataarray['data'];
        }
    @endphp
    <style>
        .theme-custom .navbar-brand, .theme-custom .btn-primary {
    color: #fff !important;
}
        </style>
    <div class="row clearfix">
        @if ($roleedit)
            @if (checkmodulepermission(1, 'can_edit') == 1)
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="card project_list">

                        <form action="{{ url('/updaterole') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit Role</h4>
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
        @if ($settingedit)
            @if (checkmodulepermission(1, 'can_edit') == 1)
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="card project_list">

                        <form action="{{ url('/updaterolesetting') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit Role Setting</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="row clearfix">
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Name</label>
                                                <input type="hidden" name="id" value="{{ $editdata['id'] }}">
                                                <input type="text" id="Name" readonly class="form-control"
                                                    value="{{ $editdata['name'] }}">
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
                                                        <input type="date" class="form-control date-range-from" data-target="#view_duration_final" value="{{ preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $from) ? $from : '' }}">
                                                    </div>
                                                    <div class="col-6">
                                                        <small>To (Optional)</small>
                                                        <input type="date" class="form-control date-range-to" data-target="#view_duration_final" value="{{ preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $to) ? $to : '' }}">
                                                    </div>
                                                </div>
                                                <input type="hidden" name="view_duration" id="view_duration_final" value="{{ $view_dur }}">
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
                                                        <input type="date" class="form-control date-range-from" data-target="#add_duration_final" value="{{ preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $add_from) ? $add_from : '' }}">
                                                    </div>
                                                    <div class="col-6">
                                                        <small>To (Optional)</small>
                                                        <input type="date" class="form-control date-range-to" data-target="#add_duration_final" value="{{ preg_match("/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/", $add_to) ? $add_to : '' }}">
                                                    </div>
                                                </div>
                                                <input type="hidden" name="add_duration" id="add_duration_final" value="{{ $add_dur }}">
                                                @if(!empty($add_dur) && !strpos($add_dur, '-') && !strpos($add_dur, ','))
                                                    <small class="text-muted">Current: {{ getadddurations($add_dur) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Initial Entry Status</label>
                                                <select class="form-control show-tick" data-live-search="true"
                                                    name="initial_entry_status" required>
                                                    <option value="" selected disabled>--Select Status--</option>

                                                    @if ($editdata['initial_entry_status'] == 'Pending')
                                                        <option selected value="Pending">Pending</option>
                                                        <option value="Approved">Approved</option>
                                                    @else
                                                        <option value="Pending">Pending</option>
                                                        <option selected value="Approved">Approved</option>
                                                    @endif
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Entry At Sites</label>
                                                <select class="form-control show-tick" data-live-search="true"
                                                    name="entry_at_site" required>
                                                    <option value="" selected disabled>--Select Site Accessbility--
                                                    </option>
                                                    @php
                                                        $durations = getsiteEntryAccess();
                                                    @endphp
                                                    @foreach ($durations as $key => $value)
                                                        @if ($key == $editdata['entry_at_site'])
                                                            <option selected value="{{ $key }}">
                                                                {{ $value }}
                                                            </option>
                                                        @else
                                                            <option value="{{ $key }}">{{ $value }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label for="Name">Data Visiblity At Sites</label>
                                                <select class="form-control show-tick" data-live-search="true"
                                                    name="visiblity_at_site" required>
                                                    <option value="" selected disabled>--Select Site Accessbility--
                                                    </option>
                                                    @php
                                                        $durations = getsiteEntryAccess();
                                                    @endphp
                                                    @foreach ($durations as $key => $value)
                                                        @if ($key == $editdata['visiblity_at_site'])
                                                            <option selected value="{{ $key }}">
                                                                {{ $value }}
                                                            </option>
                                                        @else
                                                            <option value="{{ $key }}">{{ $value }}
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

                </div>
            @endif
            <br>
        @endif
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Roles</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">User roles will be listed here you can manage permission of roles from
                            here.
                    </h2>
                    <ul class="header-dropdown">
                        <li>
                            @if (checkmodulepermission(1, 'can_add') == 1)
                                <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    data-toggle="modal" data-target="#addnewrole" type="button">
                                    <i class="zmdi zmdi-plus" style="color: white;"></i>
                                </button>
                            @endif
                        </li>
                    </ul>
                </div>
                <div class="body">
                    @if (checkmodulepermission(1, 'can_view') == 1)
                        <div class="table-responsive">
                            <table id="dataTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">Role</th>
                                        <th><strong>Default Role</strong></th>
                                        <th>Role Users</th>
                                        <th>Role Users Count</th>
                                        <th>View Duration</th>
                                        <th>Create Duration</th>
                                        <th>Initial Entry Status</th>
                                        <th>Entries At Site</th>
                                        <th>Data Visiblity At Site</th>
                                        <th>Create Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php

                                        // $dataarray = json_decode($data, true);
                                    @endphp
                                    @foreach ($dataarray as $roled)
                                        @php
                                            $count = 0;
                                            //   $rolesd = json_decode($roled,true);
                                            $roles = [];
                                            $users = [];
                                            //   print_r($rolesd);
                                            $role = $roled['roles'];
                                            $users = $roled['users'];
                                            $roleid = $role['id'];
                                        @endphp

                                        <tr>

                                            <td>
                                                <strong> <a class="single-user-name"
                                                        href="#">{{ $role['name'] }}</a>
                                                </strong>
                                            </td>
                                            <td>{{ $role['is_superadmin'] }}</td>
                                            <td class="hidden-md-down">
                                                <ul class="list-unstyled team-info margin-0">
                                                    @foreach ($users as $ul)
                                                        @php $count++ @endphp
                                                        <li>
                                                            <a title="{{ $ul['name'] }}"><img
                                                                    src="{{ asset($ul['image']) }}"
                                                                    alt="{{ $ul['name'] }}"></a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                            <td>{{ $count }}</td>
                                            <td>{{ getviewdurations($role['view_duration']) }}</td>
                                            <td>{{ getadddurations($role['add_duration']) }}</td>
                                            <td>{{ $role['initial_entry_status'] }}</td>
                                            <td>{{ getsiteEntryAccess($role['entry_at_site']) }}</td>

                                            <td>{{ getsiteEntryAccess($role['visiblity_at_site']) }}</td>
                                            <td>{{ $role['created_at'] }}</td>

                                            <td>
                                                @if ($role['is_superadmin'] == 'no')
                                                    @if (isSuperAdmin())
                                                        <a class="btn btn-primary btn-sm" title="Manage Permissions"
                                                            href="{{ url('/assign_role_permission?id=' . $role['id']) }}"><i
                                                                class="zmdi zmdi-lock"></i> Permissions</a> &nbsp;
                                                    @endif
                                                    @if (checkmodulepermission(1, 'can_edit') == 1)
                                                        <a title="Setting"
                                                            href="{{ url('/edit_role_settings?id=' . $role['id']) }}"><i
                                                                class="zmdi zmdi-settings"></i> </a> &nbsp;
                                                        <a title="Edit"
                                                            href="{{ url('/edit_role?id=' . $role['id']) }}"><i
                                                                class="zmdi zmdi-edit"></i> </a> &nbsp;
                                                    @endif
                                                    @if (checkmodulepermission(1, 'can_delete') == 1)
                                                        @if (isRoleDeletable($roleid))
                                                            <button title="Delete"
                                                                onclick="deletedata({{ $roleid }})"
                                                                style="all:unset"><i class="zmdi zmdi-delete"></i>
                                                            </button>
                                                        @endif
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
    @if (checkmodulepermission(1, 'can_add') == 1)
        <div class="modal fade" id="addnewrole" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/addnewrole') }}" method="post" class="form" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New Role</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-sm-12"><b>Role Name</b>
                                    <div class="input-group">
                                        <span class="input-group-addon"><i class="zmdi zmdi-user"></i></span>
                                        <input type="text" name="name" class="form-control" required
                                            placeholder="Role Name">
                                    </div>
                                </div>
                            </div>


                            <div class="modal-footer">

                                <button type="button" class="btn btn-primary btn-simple waves-effect"
                                    data-dismiss="modal"><a>Close</a></button>
                                <button type="submit"
                                    class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                            </div>
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
                    var url = "{{ url('/delete_role/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        $(document).on('change', '.date-range-from, .date-range-to', function() {
            var container = $(this).closest('.form-group');
            var from = container.find('.date-range-from').val();
            var to = container.find('.date-range-to').val();
            var target = $(this).data('target');
            
            if (from || to) {
                $(target).val(from + ',' + to);
            } else {
                $(target).val('');
            }
        });
    </script>
@endsection
