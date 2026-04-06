@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Bulk Users Permissions'])

<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
                <h2><strong>Bulk Assign Permissions</strong> to {{ count($check_list) }} Selected Users</h2>
            </div>
            @if(checkmodulepermission(1,'can_edit') == 1)
            <div class="table-responsive" style="padding: 20px;">
                <form action="{{ url('/users/update_bulk_permission') }}" class="form" method="post">
                    @csrf
                    @foreach($check_list as $id)
                        <input type="hidden" name="check_list[]" value="{{ $id }}">
                    @endforeach

                    <table style="width:100%" class="table mb-0 thead-border-top-0 table-nowrap">
                        <thead>
                            <tr style="text-align: center;">
                                <th>Module</th>
                                <th>View</th>
                                <th>Add</th>
                                <th>Edit</th>
                                <th>Certify</th>
                                <th>Delete</th>
                                <th>Pay</th>
                                <th>Generate Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($modules as $module)
                                @php $module_id = $module['id']; @endphp
                                <tr style="text-align: center;">
                                    <td>{{ $module['name'] }}</td>
                                    <td><input type="checkbox" value="{{ $module_id }}" name="view[]"></td>
                                    <td><input type="checkbox" value="{{ $module_id }}" name="add[]"></td>
                                    <td><input type="checkbox" value="{{ $module_id }}" name="edit[]"></td>
                                    <td><input type="checkbox" value="{{ $module_id }}" name="certify[]"></td>
                                    <td><input type="checkbox" value="{{ $module_id }}" name="delete[]"></td>
                                    <td>
                                        @if ($module_id == 8 || $module_id == 1 || $module_id == 4)
                                            <input type="checkbox" value="{{ $module_id }}" name="pay[]">
                                        @endif
                                    </td>
                                    <td><input type="checkbox" value="{{ $module_id }}" name="report[]"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <br>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect">APPLY TO ALL SELECTED</button>
                        <a href="{{ url('/users') }}" class="btn btn-danger btn-simple btn-round waves-effect">CANCEL</a>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
