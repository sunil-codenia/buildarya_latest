@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Machineries'])
@php
  $data = json_decode($data, true);
$machinery = $data['machinery'][0];
$history = $data['history'];
@endphp
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2>
                        <a href="{{ url('/machinery?machinery_id=' . $machinery['head_id']) }}" class="btn btn-primary btn-round waves-effect" style="color: white !important; margin-right: 10px; padding: 6px 12px; vertical-align: middle;">
                            <i class="zmdi zmdi-arrow-left"></i> Back
                        </a>
                        <strong>Machineries Transfer</strong> History
                    </h2>
                    <h2><strong>Machinery Name - </strong>{{$machinery['name']}}<br>
                        <strong> Head -</strong> {{$machinery['head']}}<br> 
                        <strong>Currently At Site - </strong>{{($machinery['status'] != "Sold") ? getSiteDetailsById($machinery['site_id'])->name : "Already Sold From Site (".getSiteDetailsById($machinery['site_id'])->name.")"}} </h2>
                </div>

                <div class="body">
                @if(checkmodulepermission(6,'can_view') == 1)
                    <div class="table-responsive">
                        <table id="dataTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>From Site</th>
                                    <th>To Site</th>
                                    <th>Transction Type</th>
                                    <th>Remark</th>
                                    <th>Transaction Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = 1;
                                  
                                @endphp

                                @foreach ($history as $dd)
                                    @if (!empty($dd))
                                      
                                        <tr>
                                            <td>{{ $i++ }}</td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ (!empty($dd['from_site'])) ? getSiteDetailsById($dd['from_site'])->name : "" }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ (!empty($dd['to_site'])) ? getSiteDetailsById($dd['to_site'])->name : "" }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['transaction_type'] }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['remark'] }}</a>
                                            </td>


                                            
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['create_datetime'] }}</a>
                                            </td>

                                        </tr>
                                    @endif
                                @endforeach

                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
@endsection


