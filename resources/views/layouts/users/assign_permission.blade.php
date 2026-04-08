@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Users Permissions'])
    @php
        $dataarray = json_decode($data, true);
        $modules = $dataarray['modules'];
        $permissions = $dataarray['permissions'];
        
        $user_id = $dataarray['user_id'];
        $result = [];
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
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>User Permissions</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Users Permissions will be listed here.
                    </h2>
                </div>
                @if(checkmodulepermission(1,'can_edit') == 1)
                <div class="table-responsive" style="padding: 20px;">
                    <form action="{{ url('/update_user_permission') }}" class="form" method="post">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $user_id }}">
                        <table style="width:100%" class="table mb-0 thead-border-top-0 table-nowrap">

                            <thead>
                                <tr style="text-align: center;">
                                    <th>Module </th>
                                    <th>View</th>
                                    <th>Add</th>
                                    <th>Edit</th>
                                    <th>Approve / Certify</th>
                                    <th>Delete</th>
                                    <th>Pay</th>
                                    <th>Generate Report</th>
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
                                            @if (isset($result[$module_id]) && $result[$module_id]['can_view'])
                                                <input type="checkbox" checked value="{{ $module_id }}" name="view[]"
                                                    id="view_permission_checkbox">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}" name="view[]"
                                                    id="view_permission_checkbox">
                                            @endif
                                        </td>
                                        <td>
                                            @if (isset($result[$module_id]) && $result[$module_id]['can_add'])
                                                <input type="checkbox" checked value="{{ $module_id }}"
                                                    id="add_permission_checkbox" name="add[]">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}"
                                                    id="add_permission_checkbox" name="add[]">
                                            @endif
                                        </td>
                                        <td>
                                            @if (isset($result[$module_id]) && $result[$module_id]['can_edit'])
                                                <input type="checkbox" checked value="{{ $module_id }}"
                                                    id="edit_permission_checkbox" name="edit[]">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}"
                                                    id="edit_permission_checkbox" name="edit[]">
                                            @endif
                                        </td>
                                        <td>
                                            @if (isset($result[$module_id]) && $result[$module_id]['can_certify'])
                                                <input type="checkbox" checked value="{{ $module_id }}"
                                                    id="certify_permission_checkbox" name="certify[]">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}"
                                                    id="certify_permission_checkbox" name="certify[]">
                                            @endif
                                        </td>
                                        <td>
                                            @if (isset($result[$module_id]) && $result[$module_id]['can_delete'])
                                                <input type="checkbox" checked value="{{ $module_id }}"
                                                    id="delete_permission_checkbox" name="delete[]">
                                            @else
                                                <input type="checkbox" value="{{ $module_id }}"
                                                    id="delete_permission_checkbox" name="delete[]">
                                            @endif
                                        </td>
                                        <td>
                                            @if ($module_id == 8 || $module_id == 1 || $module_id == 4)
                                                @if (isset($result[$module_id]) && $result[$module_id]['can_pay'])
                                                    <input type="checkbox" checked value="{{ $module_id }}"
                                                        id="pay_permission_checkbox" name="pay[]">
                                                @else
                                                    <input type="checkbox" value="{{ $module_id }}"
                                                        id="pay_permission_checkbox" name="pay[]">
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            
                                                @if (isset($result[$module_id]) && $result[$module_id]['can_report'])
                                                    <input type="checkbox" checked value="{{ $module_id }}"
                                                        id="report_permission_checkbox" name="report[]">
                                                @else
                                                    <input type="checkbox" value="{{ $module_id }}"
                                                        id="report_permission_checkbox" name="report[]">
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
