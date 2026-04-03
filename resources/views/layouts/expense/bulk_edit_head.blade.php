@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Bulk Edit Expense Heads'])
@php
$heads = json_decode($data, true);
@endphp
<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header">
                <h2><strong>Bulk Edit</strong> Expense Heads</h2>
            </div>
            <div class="body">
                @if(checkmodulepermission(2,'can_edit') == 1)
                <form method="post" action="{{url('/update_bulk_head')}}">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($heads as $index => $head)
                                @php $head = (array)$head; @endphp
                                <tr>
                                    <td>
                                        {{ $index + 1 }}
                                        <input type="hidden" name="id[]" value="{{$head['id']}}" />
                                    </td>
                                    <td>
                                        <input type="text" required class="form-control" name="name[]" value="{{$head['name']}}" placeholder="Head Name">
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
                            <a href="{{url('/expense_head')}}" class="btn btn-default btn-round waves-effect">Cancel</a>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
