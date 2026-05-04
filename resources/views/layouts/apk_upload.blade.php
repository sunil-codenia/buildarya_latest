@extends('app')
@section('content')
    @include('templates.header')
    @include('templates.blockheader', ['pagename' => 'Upload Android APK'])

    <div class="container-fluid">
        <div class="row clearfix">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="card">
                    <div class="header">
                        <h2><strong>Upload</strong> Latest App Version</h2>
                    </div>
                    <div class="body">
                        @if (session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif
                        @if (session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        <form action="{{ url('/dashboard/upload_apk') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row clearfix">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="apk_file">Select APK File</label>
                                        <input type="file" name="apk_file" class="form-control" accept=".apk" required>
                                        <small class="text-muted">Only .apk files are allowed.</small>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <button type="submit" class="btn btn-primary btn-round">Upload APK</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
