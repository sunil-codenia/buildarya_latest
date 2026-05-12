@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Role Permissions'])
    @php
        $dataarray = json_decode($data, true);
        $modules = $dataarray['modules'];
        $permissions = $dataarray['permissions'];
        
        $role_id = $dataarray['role_id'];
        $role_name = $dataarray['role_name'] ?? 'Role';
        $result = [];
        $empty_perms = count($permissions) === 0;
        foreach ($permissions as $perm) {
            $result[$perm['module_id']] = [
                'can_view' => $perm['can_view'],
                'can_edit' => $perm['can_edit'],
                'can_certify' => $perm['can_certify'],
                'can_add' => $perm['can_add'],
                'can_delete' => $perm['can_delete'],
                'can_pay' => $perm['can_pay'],
                'can_report' => $perm['can_report'],
            ];
        }
    @endphp
    <style>
        .theme-custom .navbar-brand, .theme-custom .btn-primary {
    color: #fff !important;
}
        </style>
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Default Permissions for {{ $role_name }}</strong>&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">These default permissions will be automatically applied to any new user created with this role. Existing user permissions will not be affected.
                    </h2>
                    <ul class="header-dropdown">
                        <li>
                            <a href="{{ url('/user_roles') }}" class="btn btn-primary btn-round btn-simple">Back to Roles</a>
                        </li>
                    </ul>
                </div>
                @if(isSuperAdmin() || checkmodulepermission(1,'can_edit') == 1)
                <div class="table-responsive" style="padding: 20px;">
                    <form action="{{ url('/update_role_permission') }}" class="form" method="post">
                        @csrf
                        <input type="hidden" name="role_id" value="{{ $role_id }}">
                        <table style="width:100%" class="table mb-0 thead-border-top-0 table-nowrap">

                            <thead>
                                <tr style="text-align: center;">
                                    <th>Module </th>
                                    <th>View (Sidebar Access)<br><small class="text-muted" style="font-size: 9px; display: block; font-weight: normal; line-height: 1.1; margin-top: 4px;">List & Sidebar access</small></th>
                                    <th>Add<br><small class="text-muted" style="font-size: 9px; display: block; font-weight: normal; line-height: 1.1; margin-top: 4px;">Create new entries</small></th>
                                    <th>Edit<br><small class="text-muted" style="font-size: 9px; display: block; font-weight: normal; line-height: 1.1; margin-top: 4px;">Update existing data</small></th>
                                    <th>Approve / Certify<br><small class="text-muted" style="font-size: 9px; display: block; font-weight: normal; line-height: 1.1; margin-top: 4px;">Authorize/Verify actions</small></th>
                                    <th>Delete<br><small class="text-muted" style="font-size: 9px; display: block; font-weight: normal; line-height: 1.1; margin-top: 4px;">Remove entries</small></th>
                                    <th>Pay<br><small class="text-muted" style="font-size: 9px; display: block; font-weight: normal; line-height: 1.1; margin-top: 4px;">Process transactions</small></th>
                                    <th>Generate Report<br><small class="text-muted" style="font-size: 9px; display: block; font-weight: normal; line-height: 1.1; margin-top: 4px;">Export data/reports</small></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($modules as $module)
                                    @php
                                        $module_id = $module['id'];
                                    @endphp

                                    <tr style="text-align: center;">

                                        <td>{{ $module['name'] }}
                                        </td>
                                        <td>
                                            @if ((isset($result[$module_id]) && $result[$module_id]['can_view']) || $empty_perms)
                                                <input type="checkbox" checked value="{{ $module_id }}" name="view[]"
                                                    id="view_permission_checkbox_{{ $module_id }}">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}" name="view[]"
                                                    id="view_permission_checkbox_{{ $module_id }}">
                                            @endif
                                        </td>
                                        <td>
                                            @if ((isset($result[$module_id]) && $result[$module_id]['can_add']) || $empty_perms)
                                                <input type="checkbox" checked value="{{ $module_id }}"
                                                    id="add_permission_checkbox_{{ $module_id }}" name="add[]">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}"
                                                    id="add_permission_checkbox_{{ $module_id }}" name="add[]">
                                            @endif
                                        </td>
                                        <td>
                                            @if ((isset($result[$module_id]) && $result[$module_id]['can_edit']) || $empty_perms)
                                                <input type="checkbox" checked value="{{ $module_id }}"
                                                    id="edit_permission_checkbox_{{ $module_id }}" name="edit[]">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}"
                                                    id="edit_permission_checkbox_{{ $module_id }}" name="edit[]">
                                            @endif
                                        </td>
                                        <td>
                                            @if ((isset($result[$module_id]) && $result[$module_id]['can_certify']) || $empty_perms)
                                                <input type="checkbox" checked value="{{ $module_id }}"
                                                    id="certify_permission_checkbox_{{ $module_id }}" name="certify[]">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}"
                                                    id="certify_permission_checkbox_{{ $module_id }}" name="certify[]">
                                            @endif
                                        </td>
                                        <td>
                                            @if ((isset($result[$module_id]) && $result[$module_id]['can_delete']) || $empty_perms)
                                                <input type="checkbox" checked value="{{ $module_id }}"
                                                    id="delete_permission_checkbox_{{ $module_id }}" name="delete[]">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}"
                                                    id="delete_permission_checkbox_{{ $module_id }}" name="delete[]">
                                            @endif
                                        </td>
                                        <td>
                                            @if ($module_id == 8 || $module_id == 1 || $module_id == 4)
                                                @if ((isset($result[$module_id]) && $result[$module_id]['can_pay']) || $empty_perms)
                                                    <input type="checkbox" checked value="{{ $module_id }}"
                                                        id="pay_permission_checkbox_{{ $module_id }}" name="pay[]">
                                                @else
                                                    <input type="checkbox" value="{{ $module_id }}"
                                                        id="pay_permission_checkbox_{{ $module_id }}" name="pay[]">
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            
                                                @if ((isset($result[$module_id]) && $result[$module_id]['can_report']) || $empty_perms)
                                                    <input type="checkbox" checked value="{{ $module_id }}"
                                                        id="report_permission_checkbox_{{ $module_id }}" name="report[]">
                                                @else
                                                    <input type="checkbox" value="{{ $module_id }}"
                                                        id="report_permission_checkbox_{{ $module_id }}" name="report[]">
                                                @endif
                                           
                                        </td>
                                    </tr>
                                @endforeach

                            </tbody>

                        </table>
                        <br>
                        <div style="text-align:end;">
                            <button type="Submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE
                                    CHANGES</a></button>

                        </div>
                    </form>
                </div>
                @endif
            </div>
        </div>
    </div>

@endsection
