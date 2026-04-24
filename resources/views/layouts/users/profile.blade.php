@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'User Profile'])
    
    <div class="row clearfix">
        <div class="col-md-4">
            <div class="card">
                <div class="header">
                    <h2><strong>Account</strong> Info</h2>
                </div>
                <div class="body text-center">
                    <img src="{{ asset('/' . $user->image) }}" class="rounded-circle img-raised m-b-15" style="width: 150px; height: 150px; object-fit: cover;">
                    <h3>{{ $user->name }}</h3>
                    <p class="text-muted">{{ Session::get('comp_name') }}</p>
                    <hr>
                    <div class="text-left">
                        <p><strong>Username:</strong> {{ $user->username }}</p>
                        <p><strong>Contact:</strong> {{ $user->contact_no }}</p>
                        <p><strong>Role:</strong> {{ getRoleDetailsById($user->role_id)->name }}</p>
                        <p><strong style="color: #eda61a;">Subscription Expires:</strong> {{ Session::get('expiry_date') }}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="header">
                    <h2><strong>Change</strong> Password</h2>
                </div>
                <div class="body">
                    <form action="{{ url('/update_password') }}" method="post">
                        @csrf
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required>
                            <small class="text-muted">Minimum 5 characters.</small>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="new_password_confirmation" class="form-control" placeholder="Repeat new password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-round waves-effect">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
