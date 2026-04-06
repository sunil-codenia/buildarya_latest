@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Sales Report'])
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
    @if (checkmodulepermission(3, 'can_report') == 1)
    <div class="alert " style="color:black;background:white;"><i class="zmdi zmdi-info"></i> &nbsp;  Sales Report Only Available In Excel Format Due To Unpredictable Column Count</div>
        <div class="row clearfix">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="card project_list">
                    <p class="header"><strong>Sales Invoices Report According To Party</strong></p>
                    <form method="post" action="{{ url('/salesreport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">

                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Sales Party</label>
                                        <select  class="form-control show-tick" data-live-search="true" name="party_id">
                                            <option disabled value="" selected>--Select Party--</option>
                                            @foreach ($parties as $party)
                                           
                                                <option value="{{ $party->id}}">{{ $party->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="hidden" name="type" value="1">
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
                    <p class="header"><strong>Sales Invoice Report According To Project</strong></p>
                    <form method="post" action="{{ url('/salesreport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <input type="hidden" name="type" value="2">
                                        <label>Project Name</label>
                                        <select name="project_id" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Project--</option>
                                            @foreach ($projects as $project)
                                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                                            @endforeach
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
                    <p class="header"><strong>Sales Invoice Report According To Financial Year</strong></p>
                    <form method="post" action="{{ url('/salesreport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <input type="hidden" name="type" value="3">
                                        <label>Financial Year</label>
                                        <select name="financial_year" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Year--</option>
                                            @php
                                                $years = getFinancialYear();
                                            @endphp
                                            @foreach ($years as $year)
                                            <option value="{{ $year }}">{{ $year }}</option>
                                            @endforeach
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
                    <p class="header"><strong>Sales Invoices Report According To Company</strong></p>
                    <form method="post" action="{{ url('/salesreport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">

                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Sales Company</label>
                                        <select  class="form-control show-tick" data-live-search="true" name="company_id">
                                            <option disabled value="" selected>--Select Company--</option>
                                            @foreach ($companies as $company)
                                           
                                                <option value="{{ $company->id}}">{{ $company->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="hidden" name="type" value="4">
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                <label>Financial Year</label>
                                <select name="financial_year" class="form-control show-tick" data-live-search="true"
                                    required>
                                    <option value="" selected disabled>--Select Year--</option>
                                    @php
                                        $years = getFinancialYear();
                                    @endphp
                                    @foreach ($years as $year)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
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
                    <p class="header"><strong>Sales Invoice Report According To Invoice Heads</strong></p>
                    <form method="post" action="{{ url('/salesreport') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="col-lg-12 col-md-12 col-sm-12">
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <input type="hidden" name="type" value="5">
                                        <label>Invoice Head </label>
                                        <select name="head_id" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Head--</option>
                                         
                                            @foreach ($heads as $head)
                                                <option value="{{ $head->id }}">{{ $head->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                <label>Financial Year</label>
                                <select name="financial_year" class="form-control show-tick" data-live-search="true"
                                    required>
                                    <option value="" selected disabled>--Select Year--</option>
                                    @php
                                        $years = getFinancialYear();
                                    @endphp
                                    @foreach ($years as $year)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                    @endforeach
                                </select>
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
