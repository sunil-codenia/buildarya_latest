@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Bulk Edit Expense Parties'])
@php
$parties = json_decode($data, true);
@endphp
<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header">
                <h2><strong>Bulk Edit</strong> Expense Parties</h2>
            </div>
            <div class="body">
                @if(checkmodulepermission(2,'can_edit') == 1)
                <form method="post" action="{{url('/update_bulk_party')}}">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Address</th>
                                    <th>Pan No</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($parties as $index => $party)
                                @php $party = (array)$party; @endphp
                                <tr>
                                    <td>
                                        {{ $index + 1 }}
                                        <input type="hidden" name="id[]" value="{{$party['id']}}" />
                                    </td>
                                    <td>
                                        <input type="text" required class="form-control" name="name[]" value="{{$party['name']}}" placeholder="Name">
                                    </td>
                                    <td>
                                        <input type="text" required class="form-control" name="address[]" value="{{$party['address']}}" placeholder="Address">
                                    </td>
                                    <td>
                                        <input type="text" required class="form-control" name="pan_no[]" value="{{$party['pan_no']}}" placeholder="Pan No">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="row clearfix">
                        <div class="col-md-12 text-right">
                            <hr>
                             <button type="submit" class="btn btn-warning btn-round waves-effect"><a>Update All</a></button>
                            <a href="{{url('/expense_party')}}" class="btn btn-default btn-round waves-effect">Cancel</a>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
