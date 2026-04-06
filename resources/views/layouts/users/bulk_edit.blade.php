@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Bulk Edit Users'])

<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header">
                <h2><strong>Bulk Edit</strong> {{ count($check_list) }} Selected Users</h2>
            </div>
            <div class="body">
                <form action="{{ url('/users/update_bulk') }}" method="POST">
                    @csrf
                    @foreach($check_list as $id)
                        <input type="hidden" name="check_list[]" value="{{ $id }}">
                    @endforeach

                    <div class="row clearfix">
                        <!-- Site -->
                        <div class="col-lg-1 col-md-1 col-sm-1 text-center">
                            <div class="checkbox m-t-35">
                                <input id="update_site_check" name="update_site" type="checkbox">
                                <label for="update_site_check">&nbsp;</label>
                            </div>
                        </div>
                        <div class="col-lg-5 col-md-5 col-sm-5">
                            <div class="form-group">
                                <label><b>New Site</b> (Check to update)</label>
                                <select name="site_id" class="form-control show-tick" data-live-search="true">
                                    <option value="" selected disabled>--Select Site--</option>
                                    @foreach(getallsites() as $site)
                                        <option value="{{ $site->id }}">{{ $site->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Role -->
                        <div class="col-lg-1 col-md-1 col-sm-1 text-center">
                            <div class="checkbox m-t-35">
                                <input id="update_role_check" name="update_role" type="checkbox">
                                <label for="update_role_check">&nbsp;</label>
                            </div>
                        </div>
                        <div class="col-lg-5 col-md-5 col-sm-5">
                            <div class="form-group">
                                <label><b>New Role</b> (Check to update)</label>
                                <select name="role_id" class="form-control show-tick" data-live-search="true">
                                    <option value="" selected disabled>--Select Role--</option>
                                    @foreach(getallRoles() as $role)
                                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row clearfix m-t-20">
                        <!-- Status -->
                        <div class="col-lg-1 col-md-1 col-sm-1 text-center">
                            <div class="checkbox m-t-35">
                                <input id="update_status_check" name="update_status" type="checkbox">
                                <label for="update_status_check">&nbsp;</label>
                            </div>
                        </div>
                        <div class="col-lg-5 col-md-5 col-sm-5">
                            <div class="form-group">
                                <label><b>New Status</b> (Check to update)</label>
                                <select name="status" class="form-control show-tick">
                                    <option value="Active">Active</option>
                                    <option value="Deactive">Deactive</option>
                                </select>
                            </div>
                        </div>

                        <!-- Platform -->
                        <div class="col-lg-1 col-md-1 col-sm-1 text-center">
                            <div class="checkbox m-t-35">
                                <input id="update_platform_check" name="update_platform" type="checkbox">
                                <label for="update_platform_check">&nbsp;</label>
                            </div>
                        </div>
                        <div class="col-lg-5 col-md-5 col-sm-5">
                            <div class="form-group">
                                <label><b>Login Platform</b> (Check to update)</label>
                                <select name="mobile_only" class="form-control show-tick">
                                    <option value="no">Web & Mobile Both</option>
                                    <option value="yes">Only Mobile App</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row clearfix m-t-20">
                        <!-- View Duration -->
                        <div class="col-lg-1 col-md-1 col-sm-1 text-center">
                            <div class="checkbox m-t-35">
                                <input id="update_view_duration_check" name="update_view_duration" type="checkbox">
                                <label for="update_view_duration_check">&nbsp;</label>
                            </div>
                        </div>
                        <div class="col-lg-5 col-md-5 col-sm-5">
                            <div class="form-group">
                                <label><b>Data View Duration</b> (Check to update)</label>
                                <select class="form-control show-tick" name="view_duration">
                                    <option value="">Default (From Role)</option>
                                    @foreach(getviewdurations() as $key => $value)
                                        <option title="{{ $value }}" value="{{ $key }}">{{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Add Duration -->
                        <div class="col-lg-1 col-md-1 col-sm-1 text-center">
                            <div class="checkbox m-t-35">
                                <input id="update_add_duration_check" name="update_add_duration" type="checkbox">
                                <label for="update_add_duration_check">&nbsp;</label>
                            </div>
                        </div>
                        <div class="col-lg-5 col-md-5 col-sm-5">
                            <div class="form-group">
                                <label><b>Data Creation Duration</b> (Check to update)</label>
                                <select class="form-control show-tick" name="add_duration">
                                    <option value="">Default (From Role)</option>
                                    @foreach(getadddurations() as $key => $value)
                                        <option title="{{ $value }}" value="{{ $key }}">{{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row clearfix m-t-30">
                        <div class="col-sm-12 text-center">
                            <button type="submit" class="btn btn-primary btn-round btn-simple waves-effect">UPDATE ALL SELECTED</button>
                            <a href="{{ url('/users') }}" class="btn btn-danger btn-round btn-simple waves-effect">CANCEL</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
