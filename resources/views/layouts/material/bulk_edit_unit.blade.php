@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Bulk Edit Material Units'])
@php
$units = json_decode($data, true);
@endphp
<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header">
                <h2><strong>Bulk Edit</strong> Material Units</h2>
            </div>
            <div class="body">
                @if(checkmodulepermission(3,'can_edit') == 1)
                <form method="post" action="{{url('/update_bulk_unit')}}">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($units as $index => $unit)
                                @php $unit = (array)$unit; @endphp
                                <tr>
                                    <td>
                                        {{ $index + 1 }}
                                        <input type="hidden" name="id[]" value="{{$unit['id']}}" />
                                    </td>
                                    <td>
                                        <input type="text" required class="form-control" name="name[]" value="{{$unit['name']}}" placeholder="Unit Name">
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
                            <a href="{{url('/materialunit')}}" class="btn btn-default btn-round waves-effect">Cancel</a>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
