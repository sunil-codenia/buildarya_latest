@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Send Push Notification'])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Send Push Notification</strong> to Mobile App</h2>
                </div>
                <div class="body">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="post" action="{{ url('/sendNotification') }}">
                        @csrf
                        <div class="row clearfix">
                            <div class="col-lg-6 col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label><strong>Target Audience</strong></label>
                                    <select name="target_type" id="target_type" class="form-control show-tick" onchange="toggleTargetFields()" required>
                                        <option value="all">All Active Users</option>
                                        <option value="site">Users of a Specific Site</option>
                                        <option value="user">Specific Single User</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6 col-md-6 col-sm-12" id="site_group" style="display: none;">
                                <div class="form-group">
                                    <label><strong>Select Site</strong></label>
                                    <select name="site_id" id="site_id" class="form-control show-tick" data-live-search="true">
                                        <option value="" disabled selected>-- Select Site --</option>
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-6 col-md-6 col-sm-12" id="user_group" style="display: none;">
                                <div class="form-group">
                                    <label><strong>Select User</strong></label>
                                    <select name="user_id" id="user_id" class="form-control show-tick" data-live-search="true">
                                        <option value="" disabled selected>-- Select User --</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row clearfix">
                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label><strong>Notification Title</strong></label>
                                    <input type="text" name="title" class="form-control" placeholder="Enter title (e.g. Site Inspection Update)" required>
                                </div>
                            </div>
                        </div>

                        <div class="row clearfix">
                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label><strong>Notification Message</strong></label>
                                    <textarea name="message" rows="4" class="form-control" placeholder="Enter the detailed message..." required></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row clearfix">
                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label><strong>App Action URL / Route (Optional)</strong></label>
                                    <input type="text" name="url" class="form-control" value="/dashboard" placeholder="/dashboard or /paymentVouchers">
                                </div>
                            </div>
                        </div>

                        <div class="row clearfix">
                            <div class="col-lg-12 col-md-12 col-sm-12 text-right">
                                <button type="submit" class="btn btn-primary btn-round waves-effect">
                                    <i class="zmdi zmdi-send"></i> Send Push Notification
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script type="text/javascript">
        function toggleTargetFields() {
            var type = $('#target_type').val();
            if (type === 'site') {
                $('#site_group').show();
                $('#user_group').hide();
            } else if (type === 'user') {
                $('#user_group').show();
                $('#site_group').hide();
            } else {
                $('#site_group').hide();
                $('#user_group').hide();
            }
        }
    </script>
@endsection
