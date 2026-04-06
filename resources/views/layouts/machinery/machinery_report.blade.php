@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Machinery Report'])
    @php

        $site_id = session()->get('site_id');
        $role_details = getRoleDetailsById(session()->get('role'));
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $role_details->add_duration;
        $duration = getdurationdates($add_duration);
        $today = substr($duration['today'], 0, 10);
        $min_date = substr($duration['min'], 0, 10);
        $max_date = substr($duration['max'], 0, 10);

    @endphp
    @if (checkmodulepermission(6, 'can_report') == 1)
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <p class="header"><strong>Machinery Complete Report According To Site</strong></p>
                <form method="post" action="{{ url('/machinery_of_site_report') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="col-lg-12 col-md-12 col-sm-12">
                        <div class="row clearfix">
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>Site</label>
                                    <select name="site_id" class="form-control show-tick" data-live-search="true"
                                        required>
                                        <option value="" selected disabled>--Select Site--</option>

                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}
                                            </option>
                                        @endforeach
                                    </select>


                                </div>

                            </div>
                        
                            <div class="col-lg-3 col-md-3 col-sm-3">
                                <div class="form-group">
                                    <label>Report Type</label>
                                    <select name="Report_Type" class="form-control show-tick" required>
                                        <option value="" selected disabled>--Select Type--</option>
                                        <option value="0">PDF Format</option>
                                        <option value="1">Excel Format</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-2 col-sm-2">
                                <div class="form-group">
                                    <label> </label>
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Purchase Report According To Head</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <input type="hidden" name="type" value="1">
                                        <label>Machinery Head</label>
                                        <select name="head_id" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Head--</option>

                                            @foreach ($machinery_heads as $machinery_head)
                                                <option value="{{ $machinery_head->id }}">{{ $machinery_head->name }}
                                                </option>
                                            @endforeach
                                        </select>


                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Purchase Report According To Site</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <input type="hidden" name="type" value="2">
                                        <label>Site</label>
                                        <select name="site_id" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Site--</option>

                                            @foreach ($sites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }}
                                                </option>
                                            @endforeach
                                        </select>


                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Complete Machinery Purchase Report</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">

                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>
                                    <input type="hidden" name="type" value="3">
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Sale Report According To Head</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <input type="hidden" name="type" value="4">
                                        <label>Machinery Head</label>
                                        <select name="head_id" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Head--</option>

                                            @foreach ($machinery_heads as $machinery_head)
                                                <option value="{{ $machinery_head->id }}">{{ $machinery_head->name }}
                                                </option>
                                            @endforeach
                                        </select>


                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Sale Report According To Site</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <input type="hidden" name="type" value="5">
                                        <label>Site</label>
                                        <select name="site_id" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Site--</option>

                                            @foreach ($sites as $site)
                                                <option value="{{ $site->id }}">{{ $site->name }}
                                                </option>
                                            @endforeach
                                        </select>


                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>
                                
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Complete Sale Report</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">

                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>
                                    <input type="hidden" name="type" value="6">
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Transfer Report According To Head</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                      
                                        <label>Machinery Head</label>
                                        <select name="head_id" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Head--</option>

                                            @foreach ($machinery_heads as $machinery_head)
                                                <option value="{{ $machinery_head->id }}">{{ $machinery_head->name }}
                                                </option>
                                            @endforeach
                                        </select>


                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>
                                    <input type="hidden" name="type" value="7">
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Complete Transfer Report</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">

                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>
                                    <input type="hidden" name="type" value="8">
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Documents Report According To Head</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                      
                                        <label>Machinery Head</label>
                                        <select name="head_id" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Head--</option>

                                            @foreach ($machinery_heads as $machinery_head)
                                                <option value="{{ $machinery_head->id }}">{{ $machinery_head->name }}
                                                </option>
                                            @endforeach
                                        </select>


                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>
                                    <input type="hidden" name="type" value="9">
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Complete Documents Report</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">

                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>
                                    <input type="hidden" name="type" value="10">
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Service Report According To Head</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                      
                                        <label>Machinery Head</label>
                                        <select name="head_id" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Head--</option>

                                            @foreach ($machinery_heads as $machinery_head)
                                                <option value="{{ $machinery_head->id }}">{{ $machinery_head->name }}
                                                </option>
                                            @endforeach
                                        </select>


                                    </div>

                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>
                                    <input type="hidden" name="type" value="11">
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>



        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Machinery Complete Service Report</strong></p>
                    <form method="post" action="{{ url('/machineryexport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">

                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="start_date"
                                            name="start_date" >
                                    </div>
                                    <input type="hidden" name="type" value="12">
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" required class="form-control" min="{{ $min_date }}"
                                            max="{{ $max_date }}" value="{{ $today }}" id="end_date"
                                            name="end_date" >
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Report Type</label>
                                        <select name="Report_Type" class="form-control show-tick" required>
                                            <option value="" selected disabled>--Select Type--</option>
                                            <option value="0">PDF Format</option>
                                            <option value="1">Excel Format</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label> </label>
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
