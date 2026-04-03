@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Bulk Edit Expense Entries'])
@php
$data = json_decode($data, true);
$expenses = $data['expenses'];
$sites = $data['sites'];
$parties = $data['expense_party'];
$bill_parties = $data['bill_party'];
$heads = $data['expense_head'];
$site_id = session()->get("site_id");
$role_details = getRoleDetailsById(session()->get('role'));
$entry_at_site = $role_details->entry_at_site;
$add_duration = $role_details->add_duration;
$duration = getdurationdates($add_duration);
$today = $duration['today'];
$min_date = $duration['min'];
$max_date = $duration['max'];

@endphp
<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header">
                <h2><strong>Bulk Edit</strong> Expenses</h2>
            </div>
            <div class="body">
{{-- Debug: Total Expenses = {{ count($expenses) }} --}}
                @if(checkmodulepermission(2,'can_edit') == 1)
                <form method="post" action="{{url('/pending_expense/update_bulk')}}" enctype="multipart/form-data">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="min-width: 150px;">Site</th>
                                    <th style="min-width: 150px;">Expense Party</th>
                                    <th style="min-width: 150px;">Expense Head</th>
                                    <th style="min-width: 150px;">Particular</th>
                                    <th style="min-width: 100px;">Amount</th>
                                    <th style="min-width: 150px;">Remark</th>
                                    <th style="min-width: 150px;">Date</th>
                                    <th style="min-width: 150px;">Image</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($expenses as $index => $expense)
                                @php $expense = (array)$expense; @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="id[]" value="{{$expense['id']}}" />
                                        <select name="site_id[]" class="form-control" required>
                                            <option value="" selected disabled>--Select Site--</option>
                                            @if ($entry_at_site == 'current')
                                                <option selected value="{{ $site_id }}">
                                                    {{ getSiteDetailsById($site_id)->name }}
                                                </option>
                                            @else
                                                @foreach ($sites as $site)
                                                    @php $site = (array)$site; @endphp
                                                    <option value="{{$site['id']}}" {{$expense['site_id'] == $site['id'] ? 'selected' : ''}}>{{$site['name']}}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </td>
                                    <td>
                                        <select name="party_id[]" class="form-control" required>
                                            <option disabled>--Expense Parties--</option>
                                            @php
                                            $current_party_id = $expense['party_id']."||".$expense['party_type'];
                                            @endphp
                                            @foreach($parties as $party)
                                                @php $party = (array)$party; @endphp
                                                <option value="{{$party['id']}}||expense" {{$current_party_id == $party['id']."||expense" ? 'selected' : ''}}>{{$party['name']}}</option>
                                            @endforeach
                                            <option disabled>--Bill Parties--</option>
                                            @foreach($bill_parties as $party)
                                                @php $party = (array)$party; @endphp
                                                <option value="{{$party['id']}}||bill" {{$current_party_id == $party['id']."||bill" ? 'selected' : ''}}>{{$party['name']}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="head_id[]" class="form-control" required>
                                            <option value="" selected disabled>--Select Head--</option>
                                            @foreach($heads as $head)
                                                @php $head = (array)$head; @endphp
                                                <option value="{{$head['id']}}" {{$head['id'] == $expense['head_id'] ? 'selected' : ''}}>{{$head['name']}}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" required class="form-control" name="particular[]" value="{{$expense['particular']}}" placeholder="Particular">
                                    </td>
                                    <td>
                                        <input type="number" placeholder="0.00" required class="form-control" name="amount[]" min="0" value="{{$expense['amount']}}" step="0.01">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control" name="remark[]" value="{{$expense['remark']}}" placeholder="Remark">
                                    </td>
                                    <td>
                                        <input type="date" required class="form-control" min="{{$min_date}}" max="{{$max_date}}" value="{{$expense['date']}}" name="date[]">
                                    </td>
                                    <td>
                                        @if($expense['image'] && $expense['image'] != 'images/expense.png')
                                            <img src="{{ url($expense['image']) }}" width="50" height="50" class="img-thumbnail mb-1">
                                        @endif
                                        <input type="file" class="form-control" name="image_{{ $index }}">
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
                            <a href="{{url('/pending_expense')}}" class="btn btn-default btn-round waves-effect">Cancel</a>
                        </div>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
