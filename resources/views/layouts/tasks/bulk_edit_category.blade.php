@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Bulk Edit Task Categories'])
@php
$categories = json_decode($data, true);
@endphp
<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header">
                <h2><strong>Bulk Edit</strong> Task Categories</h2>
            </div>
            <div class="body">
                @if(checkmodulepermission(15,'can_edit') == 1)
                <form method="post" action="{{url('/update_bulk_category')}}">
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
                                @foreach($categories as $index => $category)
                                @php $category = (array)$category; @endphp
                                <tr>
                                    <td>
                                        {{ $index + 1 }}
                                        <input type="hidden" name="id[]" value="{{$category['id']}}" />
                                    </td>
                                    <td>
                                        <input type="text" required class="form-control" name="name[]" value="{{$category['name']}}" placeholder="Category Name">
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
                            <a href="{{url('/task_category')}}" class="btn btn-default btn-round waves-effect">Cancel</a>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
