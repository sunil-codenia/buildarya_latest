@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Bulk Edit Pending Material'])

@php
    $dataarray = json_decode($data, true);
    $material_entries = $dataarray['material_entries'];
@endphp

<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
                <h2><strong>Bulk Edit</strong> Pending Material &nbsp;<i class="zmdi zmdi-info info-hover"></i>
                    <div class="info-content">Edit multiple pending material entries at once.</div>
                </h2>
            </div>
            <div class="body">
                <form action="{{ url('/update_bulk_pending_material') }}" method="POST">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Supplier / Material / Site</th>
                                    <th style="width: 120px;">Qty</th>
                                    <th style="width: 150px;">Vehicle</th>
                                    <th>Remark</th>
                                    <th style="width: 150px;">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($material_entries as $key => $entry)
                                    <tr>
                                        <td>{{ $key + 1 }}
                                            <input type="hidden" name="ids[]" value="{{ $entry['id'] }}">
                                        </td>
                                        <td>
                                            <strong>Supplier:</strong> {{ $entry['supplier'] }}<br>
                                            <strong>Material:</strong> {{ $entry['material'] }}<br>
                                            <strong>Site:</strong> {{ $entry['site'] }}
                                        </td>
                                        <td>
                                            <input type="number" step="any" name="qtys[]" class="form-control" 
                                                value="{{ $entry['qty'] }}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="vehicals[]" class="form-control" 
                                                value="{{ $entry['vehical'] }}">
                                        </td>
                                        <td>
                                            <textarea name="remarks[]" class="form-control" rows="1">{{ $entry['remark'] }}</textarea>
                                        </td>
                                        <td>
                                            <input type="date" name="dates[]" class="form-control" 
                                                value="{{ $entry['date'] }}" required>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="text-right m-t-20">
                        <button type="submit" class="btn btn-primary btn-round waves-effect">Update All</button>
                        <a href="{{ url('/pending_material') }}" class="btn btn-default btn-round waves-effect">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
