@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Attendance Management'])

    @php
        $add_duration = session()->get('add_duration');
        $duration     = getdurationdates($add_duration);
        $att_today    = substr($duration['today'], 0, 10);
        $att_min_date = substr($duration['min'],   0, 10);
        $att_max_date = substr($duration['max'],   0, 10);
    @endphp

    <!-- Self Attendance Check-In / Check-Out Section -->
    <div class="row clearfix">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="card" style="border-radius: 15px; box-shadow: 0 4px 25px rgba(0,0,0,0.06); border: none; background: linear-gradient(135deg, #1f4068 0%, #162447 100%); color: white;">
                <div class="body p-4 d-flex flex-wrap justify-content-between align-items-center">
                    <div class="d-flex align-items-center mb-3 mb-md-0">
                        <div class="icon-circle mr-3" style="background-color: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #eda61a; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                            <i class="zmdi zmdi-account-box-mail"></i>
                        </div>
                        <div>
                            <h4 class="m-0 font-weight-bold" style="letter-spacing: 0.5px;">Self Attendance Desk</h4>
                            <p class="m-0 font-14 text-white-50">
                                @if(!$userTodayLog)
                                    <span class="badge badge-warning text-dark font-weight-bold p-1">Not Checked In Yet</span> — Complete your check-in for today.
                                @elseif(!$userTodayLog->out_time)
                                    <span class="badge badge-success font-weight-bold p-1">Shift Active</span> — You checked in at {{ date('h:i A', strtotime($userTodayLog->in_time)) }}.
                                @else
                                    <span class="badge badge-primary font-weight-bold p-1">Shift Completed</span> — Checked In: {{ date('h:i A', strtotime($userTodayLog->in_time)) }} | Checked Out: {{ date('h:i A', strtotime($userTodayLog->out_time)) }}.
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        @if(!$userTodayLog)
                            <button class="btn btn-success btn-round px-4 py-2 font-weight-bold text-uppercase" onclick="openSelfAttendanceModal('in')" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; box-shadow: 0 4px 15px rgba(56,239,125,0.4); letter-spacing: 0.5px;">
                                <i class="zmdi zmdi-sign-in mr-1"></i> Check In
                            </button>
                        @elseif(!$userTodayLog->out_time)
                            <button class="btn btn-danger btn-round px-4 py-2 font-weight-bold text-uppercase" onclick="openSelfAttendanceModal('out')" style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); border: none; box-shadow: 0 4px 15px rgba(255,65,108,0.4); letter-spacing: 0.5px;">
                                <i class="zmdi zmdi-sign-out mr-1"></i> Check Out
                            </button>
                        @else
                            <button class="btn btn-neutral btn-round px-4 py-2 font-weight-bold text-muted text-uppercase disabled" style="background: rgba(255,255,255,0.1); border: none; color: white !important;">
                                <i class="zmdi zmdi-check-all mr-1"></i> Shift Logged
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row clearfix">
        <div class="col-lg-3 col-md-6 col-sm-12">
            <div class="card overflowhidden l-amber" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%) !important; color: white !important; border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(17,153,142,0.35);">
                <div class="body text-center p-4">
                    <h3 class="m-b-0 number count-to" style="font-weight: 800; font-size: 2.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">{{ $present }}</h3>
                    <p class="m-b-0 font-15" style="letter-spacing: 1px; font-weight: 600; opacity: 0.9;">PRESENT TODAY</p>
                    <div class="sparkline m-t-20" data-type="bar" data-width="97%" data-height="40px" data-bar-Width="3" data-bar-Spacing="10" data-bar-Color="#fff">5,6,8,2,4,8,6,7,9,5</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
            <div class="card overflowhidden l-blue" style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%) !important; color: white !important; border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(255,65,108,0.35);">
                <div class="body text-center p-4">
                    <h3 class="m-b-0 number count-to" style="font-weight: 800; font-size: 2.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">{{ $absent }}</h3>
                    <p class="m-b-0 font-15" style="letter-spacing: 1px; font-weight: 600; opacity: 0.9;">ABSENT TODAY</p>
                    <div class="sparkline m-t-20" data-type="bar" data-width="97%" data-height="40px" data-bar-Width="3" data-bar-Spacing="10" data-bar-Color="#fff">2,4,3,1,5,2,4,3,1,2</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
            <div class="card overflowhidden l-parpl" style="background: linear-gradient(135deg, #f12711 0%, #f5af19 100%) !important; color: white !important; border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(241,39,17,0.35);">
                <div class="body text-center p-4">
                    <h3 class="m-b-0 number count-to" style="font-weight: 800; font-size: 2.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">{{ $halfDay }}</h3>
                    <p class="m-b-0 font-15" style="letter-spacing: 1px; font-weight: 600; opacity: 0.9;">HALF DAY</p>
                    <div class="sparkline m-t-20" data-type="bar" data-width="97%" data-height="40px" data-bar-Width="3" data-bar-Spacing="10" data-bar-Color="#fff">1,2,0,1,2,3,1,0,2,1</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
            <div class="card overflowhidden l-green" style="background: linear-gradient(135deg, #4568dc 0%, #b06ab3 100%) !important; color: white !important; border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(69,104,220,0.35);">
                <div class="body text-center p-4">
                    <h3 class="m-b-0 number count-to" style="font-weight: 800; font-size: 2.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">{{ $onLeave }}</h3>
                    <p class="m-b-0 font-15" style="letter-spacing: 1px; font-weight: 600; opacity: 0.9;">ON LEAVE</p>
                    <div class="sparkline m-t-20" data-type="bar" data-width="97%" data-height="40px" data-bar-Width="3" data-bar-Spacing="10" data-bar-Color="#fff">0,1,1,2,0,1,1,2,0,1</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list" style="border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: none;">
                <div class="header d-flex justify-content-between align-items-center p-4" style="border-bottom: 1px solid #f1f2f6;">
                    <h2 class="m-b-0"><strong style="color: #333;">Daily Attendance Logs</strong></h2>
                    <div class="d-flex align-items-center">
                        <form method="GET" action="{{ url('/attendance') }}" class="d-flex mr-3">
                            <input type="date" name="date" class="form-control" value="{{ $date }}" onchange="this.form.submit()" style="border-radius: 20px; border: 1px solid #e1e8ed; padding: 8px 15px;">
                        </form>
                        <!-- Enforce module permission limit for adding manual logs -->
                        @if(checkmodulepermission(13, 'can_add') == 1)
                            <button class="btn btn-primary btn-round waves-effect" data-toggle="modal" data-target="#manualAttendanceModal" style="background: linear-gradient(135deg, #eda61a 0%, #f5af19 100%); border: none; box-shadow: 0 4px 15px rgba(237,166,26,0.3); font-weight: 600;">
                                <i class="zmdi zmdi-plus-circle mr-1"></i> Manual Entry
                            </button>
                        @endif
                    </div>
                </div>
                <div class="body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="attendanceTable">
                            <thead>
                                <tr style="color: #888; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">
                                    <th>S.No.</th>
                                    <th>User</th>
                                    <th>Assigned Site</th>
                                    <th>Status</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>GPS Location</th>
                                    @if(checkmodulepermission(13, 'can_delete') == 1 || checkmodulepermission(13, 'can_edit') == 1)
                                        <th>Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($attendanceLogs as $index => $log)
                                    <tr style="border-bottom: 1px solid #f9f9fc; transition: all 0.2s ease;">
                                        <td>{{ $index + 1 }}</td>
                                        <td class="d-flex align-items-center">
                                            <div class="avatar-circle mr-3" style="background-color: #f1f2f6; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #eda61a;">
                                                {{ strtoupper(substr($log->user_name, 0, 2)) }}
                                            </div>
                                            <div>
                                                <h6 class="m-0 font-weight-bold" style="color: #333;">{{ $log->user_name }}</h6>
                                                <small class="text-muted">{{ $log->user_username }}</small>
                                            </div>
                                        </td>
                                        <td><strong>{{ $log->site_name ?? 'N/A' }}</strong></td>
                                        <td>
                                            @php
                                                $badgeClass = 'badge-success';
                                                if($log->status == 'Absent') $badgeClass = 'badge-danger';
                                                elseif($log->status == 'Half Day') $badgeClass = 'badge-warning';
                                                elseif($log->status == 'Leave') $badgeClass = 'badge-info';
                                            @endphp
                                            <span class="badge {{ $badgeClass }} p-2" style="font-weight: 600; border-radius: 6px; font-size: 11px;">{{ $log->status }}</span>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="text-success font-weight-bold">{{ $log->in_time ? date('h:i A', strtotime($log->in_time)) : '--' }}</span>
                                                @if($log->image)
                                                    <a href="javascript:void(0);" class="ml-1 text-primary font-12" onclick="previewLogPhoto('{{ asset($log->image) }}')" title="View Snapshot">
                                                        <i class="zmdi zmdi-camera-mic"></i> View Photo
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="text-danger font-weight-bold">{{ $log->out_time ? date('h:i A', strtotime($log->out_time)) : '--' }}</span>
                                                @if($log->out_image)
                                                    <a href="javascript:void(0);" class="ml-1 text-primary font-12" onclick="previewLogPhoto('{{ asset($log->out_image) }}')" title="View Snapshot">
                                                        <i class="zmdi zmdi-camera-mic"></i> View Photo
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $isManual = (
                                                    !$log->in_location
                                                    || $log->in_location === 'Manual'
                                                    || str_contains(strtolower($log->remarks ?? ''), 'manual')
                                                );

                                                if (!$log->image && !$log->out_image && ($log->in_location === '0.0000,0.0000' || $log->in_location === '0,0')) {
                                                    $isManual = true;
                                                }
                                                
                                                // IN LOCATION
                                                $hasInGps = false; $inLat = '0'; $inLng = '0'; $inMapsUrl = '#';
                                                if (!$isManual && str_contains($log->in_location ?? '', ',')) {
                                                    $hasInGps = true;
                                                    $inCoords = explode(',', $log->in_location);
                                                    $inLat = trim($inCoords[0] ?? '0');
                                                    $inLng = trim($inCoords[1] ?? '0');
                                                    $inMapsUrl = "https://www.google.com/maps?q={$inLat},{$inLng}&z=17";
                                                }

                                                // OUT LOCATION
                                                $hasOutGps = false; $outLat = '0'; $outLng = '0'; $outMapsUrl = '#';
                                                if (!$isManual && str_contains($log->out_location ?? '', ',')) {
                                                    $hasOutGps = true;
                                                    $outCoords = explode(',', $log->out_location);
                                                    $outLat = trim($outCoords[0] ?? '0');
                                                    $outLng = trim($outCoords[1] ?? '0');
                                                    $outMapsUrl = "https://www.google.com/maps?q={$outLat},{$outLng}&z=17";
                                                }
                                            @endphp

                                            @if(!$hasInGps && !$hasOutGps)
                                                {{-- Manual admin entry: no GPS shown --}}
                                                <span style="display:inline-flex; align-items:center; background: rgba(255, 255, 255, 0.1); border:1px solid rgba(255, 255, 255, 0.2); border-radius:20px; padding:4px 10px; font-size:11px; color:#ccc !important; font-weight:600;" title="Manual Entry">
                                                    <i class="zmdi zmdi-assignment mr-1" style="font-size:12px;"></i> {{ $log->site_name ?? 'Manual Entry' }} (Manual)
                                                </span>
                                            @else
                                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                                    @if($hasInGps)
                                                        <div class="location-cell" data-lat="{{ $inLat }}" data-lng="{{ $inLng }}" data-map="{{ $inMapsUrl }}">
                                                            <a href="{{ $inMapsUrl }}" target="_blank"
                                                               class="location-pill d-inline-flex align-items-center"
                                                               style="background: rgba(76, 175, 80, 0.1); color: #81c784 !important; border: 1px solid rgba(76, 175, 80, 0.3); border-radius: 20px; padding: 5px 12px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all .2s ease; max-width: 210px; white-space: nowrap;"
                                                               onmouseover="this.style.boxShadow='0 4px 12px rgba(76,175,80,.2)';this.style.transform='translateY(-1px)'"
                                                               onmouseout="this.style.boxShadow='';this.style.transform=''">
                                                                <i class="zmdi zmdi-sign-in mr-1" style="font-size:14px; flex-shrink:0;"></i>
                                                                <span class="location-label" style="overflow:hidden; text-overflow:ellipsis; max-width:160px; display:inline-block;">
                                                                    {{ number_format((float)$inLat, 5) }}, {{ number_format((float)$inLng, 5) }}
                                                                </span>
                                                            </a>
                                                            <small class="location-address" style="display:block; margin-top:3px; padding-left:4px; font-size:10px; color:#aaa; max-width:210px; white-space:normal; line-height:1.3;">
                                                                <i class="zmdi zmdi-time-restore zmdi-hc-spin" style="font-size:9px; color:#81c784;"></i> Resolving address…
                                                            </small>
                                                        </div>
                                                    @endif

                                                    @if($hasOutGps)
                                                        <div class="location-cell" data-lat="{{ $outLat }}" data-lng="{{ $outLng }}" data-map="{{ $outMapsUrl }}">
                                                            <a href="{{ $outMapsUrl }}" target="_blank"
                                                               class="location-pill d-inline-flex align-items-center"
                                                               style="background: rgba(244, 67, 54, 0.1); color: #e57373 !important; border: 1px solid rgba(244, 67, 54, 0.3); border-radius: 20px; padding: 5px 12px; font-size: 12px; font-weight: 600; text-decoration: none; transition: all .2s ease; max-width: 210px; white-space: nowrap;"
                                                               onmouseover="this.style.boxShadow='0 4px 12px rgba(244,67,54,.2)';this.style.transform='translateY(-1px)'"
                                                               onmouseout="this.style.boxShadow='';this.style.transform=''">
                                                                <i class="zmdi zmdi-sign-out mr-1" style="font-size:14px; flex-shrink:0;"></i>
                                                                <span class="location-label" style="overflow:hidden; text-overflow:ellipsis; max-width:160px; display:inline-block;">
                                                                    {{ number_format((float)$outLat, 5) }}, {{ number_format((float)$outLng, 5) }}
                                                                </span>
                                                            </a>
                                                            <small class="location-address" style="display:block; margin-top:3px; padding-left:4px; font-size:10px; color:#aaa; max-width:210px; white-space:normal; line-height:1.3;">
                                                                <i class="zmdi zmdi-time-restore zmdi-hc-spin" style="font-size:9px; color:#e57373;"></i> Resolving address…
                                                            </small>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        @if(checkmodulepermission(13, 'can_delete') == 1 || checkmodulepermission(13, 'can_edit') == 1)
                                            <td class="d-flex">
                                                @if(checkmodulepermission(13, 'can_edit') == 1)
                                                    <button type="button" class="btn btn-neutral btn-sm btn-round text-primary mr-2" style="box-shadow: 0 2px 10px rgba(0,0,0,0.05);" onclick="openEditModal({{ $log->id }}, '{{ $log->user_id }}', '{{ $log->site_id }}', '{{ $log->date }}', '{{ $log->status }}', '{{ $log->in_time ? date('H:i', strtotime($log->in_time)) : '' }}', '{{ $log->out_time ? date('H:i', strtotime($log->out_time)) : '' }}', '{{ $log->bills_party_id }}', '{{ $log->image }}')">
                                                        <i class="zmdi zmdi-edit"></i>
                                                    </button>
                                                @endif
                                                @if(checkmodulepermission(13, 'can_delete') == 1)
                                                    <form action="{{ url('/attendance/delete/'.$log->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this record?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-neutral btn-sm btn-round text-danger" style="box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                                                            <i class="zmdi zmdi-delete"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center p-5">
                                            <img src="{{ asset('/images/permission.png') }}" style="width: 80px; opacity: 0.5;" class="mb-3"><br>
                                            <h6 class="text-muted">No attendance logs found for this date.</h6>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('models')
    <!-- Self Attendance Action Modal (Check In / Check Out) -->
    <div class="modal fade" id="selfAttendanceModal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog" role="document">
            <form id="selfAttendanceForm" method="POST" class="form">
                @csrf
                <!-- Lat/Lng and Base64 Photo hidden variables -->
                <input type="hidden" name="latitude" id="selfAttendanceLat" value="0.0000">
                <input type="hidden" name="longitude" id="selfAttendanceLng" value="0.0000">
                <input type="hidden" name="image_base64" id="selfAttendancePhoto">

                <div class="modal-content" style="border-radius: 15px; overflow: hidden; border: none;">
                    <div class="modal-header p-4" id="selfAttendanceHeader" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                        <h4 class="title m-0" style="font-weight: bold;"><i class="zmdi zmdi-face mr-2"></i> <span id="selfAttendanceTitle">Self Check-In</span></h4>
                    </div>
                    <div class="modal-body p-4 text-center">
                        <!-- Webcam streaming frame -->
                        <div class="video-container mb-3" style="position: relative; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); background-color: #000;">
                            <video id="selfWebcam" width="100%" height="auto" autoplay playsinline style="transform: scaleX(-1); max-height: 240px; display: block;"></video>
                            <canvas id="selfCanvas" style="display: none;"></canvas>
                            <img id="selfCapturedImage" style="display: none; width: 100%; height: auto; border-radius: 12px; transform: scaleX(-1);">
                        </div>

                        <!-- Status/Feedback panel -->
                        <div class="alert alert-info py-2" id="attendanceLocationStatus" style="font-size: 13px; font-weight: 600; border-radius: 10px; border: none; background: #e8f4fd; color: #1f4068;">
                            <i class="zmdi zmdi-gps-dot zmdi-hc-spin mr-1"></i> Requesting GPS coordinates...
                        </div>

                        <!-- Camera Action Bar -->
                        <button type="button" class="btn btn-info btn-round px-4" id="btnCapturePhoto" onclick="captureWebcamSnapshot()" style="font-weight: bold; background: #162447; border: none;">
                            <i class="zmdi zmdi-camera mr-1"></i> Take Photo
                        </button>
                        <button type="button" class="btn btn-warning btn-round px-4" id="btnRetakePhoto" onclick="retakeWebcamSnapshot()" style="display: none; font-weight: bold; border: none;">
                            <i class="zmdi zmdi-refresh mr-1"></i> Retake
                        </button>
                    </div>
                    <div class="modal-footer p-4" style="border-top: 1px solid #f1f2f6;">
                        <button type="button" class="btn btn-secondary btn-round" onclick="closeSelfAttendanceModal()">Close</button>
                        <button type="submit" class="btn btn-primary btn-round disabled" id="btnSubmitSelfAttendance" style="font-weight: bold;" disabled>Submit Attendance</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Photo Preview Modal -->
    <div class="modal fade" id="photoPreviewModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden; background: #1a1a2e; color: white;">
                <div class="modal-body p-0 text-center position-relative">
                    <button type="button" class="close position-absolute p-3 text-white" data-dismiss="modal" style="right: 0; z-index: 10; opacity: 0.8; outline: none; font-size: 30px;">&times;</button>
                    <img id="previewModalImage" style="width: 100%; height: auto; display: block;">
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Manual Attendance Modal -->
    @if(checkmodulepermission(13, 'can_edit') == 1)
        <div class="modal fade" id="editAttendanceModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form id="editAttendanceForm" method="POST" class="form" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-content" style="border-radius: 15px; overflow: hidden; border: none;">
                        <div class="modal-header p-4" style="background: linear-gradient(135deg, #1f4068 0%, #162447 100%); color: white;">
                            <h4 class="title m-0" style="font-weight: bold;"><i class="zmdi zmdi-edit mr-2"></i> Edit Attendance Log</h4>
                        </div>
                        <div class="modal-body p-4">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold" style="color: #555;">Select User</label>
                                <select name="user_id" id="edit_user_id" class="form-control show-tick" data-live-search="true" required>
                                    <option value="" disabled selected>-- Choose User --</option>
                                    <option value="labour_contractor" style="font-weight: bold; color: #eda61a;">Labour Contractor</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-3" id="edit_contractor_select_wrapper" style="display: none;">
                                <label class="font-weight-bold" style="color: #555;">Select Labour Contractor</label>
                                <select name="bills_party_id" id="edit_bills_party_id" class="form-control show-tick" data-live-search="true">
                                    <option value="" disabled selected>-- Choose Labour Contractor --</option>
                                    @foreach($billParties as $party)
                                        <option value="{{ $party->id }}">{{ $party->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-3" id="edit_contractor_image_wrapper" style="display: none;">
                                <label class="font-weight-bold" style="color: #555;">Upload Photo</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <div id="edit_image_preview_container" style="margin-top: 10px; display: none;">
                                    <small class="text-muted d-block mb-1">Current Photo:</small>
                                    <img id="edit_image_preview" src="" style="max-height: 100px; border-radius: 8px; border: 1px solid #ddd;">
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label class="font-weight-bold" style="color: #555;">Select Site</label>
                                <select name="site_id" id="edit_site_id" class="form-control show-tick" data-live-search="true" required>
                                    <option value="" disabled selected>-- Choose Site --</option>
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}">{{ $site->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold" style="color: #555;">Date</label>
                                        <input type="date" name="date" id="edit_date" class="form-control" required
                                            min="{{ $att_min_date }}" max="{{ $att_max_date }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold" style="color: #555;">Status</label>
                                        <select name="status" id="edit_status" class="form-control show-tick" required>
                                            <option value="Present">Present</option>
                                            <option value="Absent">Absent</option>
                                            <option value="Half Day">Half Day</option>
                                            <option value="Leave">On Leave</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold" style="color: #555;">Clock In Time</label>
                                        <input type="time" name="clock_in" id="edit_clock_in" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold" style="color: #555;">Clock Out Time</label>
                                        <input type="time" name="clock_out" id="edit_clock_out" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer p-4" style="border-top: 1px solid #f1f2f6;">
                            <button type="button" class="btn btn-secondary btn-round waves-effect" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary btn-round waves-effect" style="background: linear-gradient(135deg, #1f4068 0%, #162447 100%); border: none;">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Manual Entry Modal -->
    @if(checkmodulepermission(13, 'can_add') == 1)
        <div class="modal fade" id="manualAttendanceModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form action="{{ url('/attendance/manual') }}" method="POST" class="form" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-content" style="border-radius: 15px; overflow: hidden; border: none;">
                        <div class="modal-header p-4" style="background: linear-gradient(135deg, #eda61a 0%, #f5af19 100%); color: white;">
                            <h4 class="title m-0" style="font-weight: bold;"><i class="zmdi zmdi-calendar-check mr-2"></i> Add Manual Attendance</h4>
                        </div>
                        <div class="modal-body p-4">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold" style="color: #555;">Select User</label>
                                <select name="user_id" id="manual_user_id" class="form-control show-tick" data-live-search="true" required>
                                    <option value="" disabled selected>-- Choose User --</option>
                                    <option value="labour_contractor" style="font-weight: bold; color: #eda61a;">Labour Contractor</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->username }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-3" id="contractor_select_wrapper" style="display: none;">
                                <label class="font-weight-bold" style="color: #555;">Select Labour Contractor</label>
                                <select name="bills_party_id" id="manual_bills_party_id" class="form-control show-tick" data-live-search="true">
                                    <option value="" disabled selected>-- Choose Labour Contractor --</option>
                                    @foreach($billParties as $party)
                                        <option value="{{ $party->id }}">{{ $party->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-3" id="contractor_image_wrapper" style="display: none;">
                                <label class="font-weight-bold" style="color: #555;">Upload Photo</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                            </div>
                            <div class="form-group mb-3">
                                <label class="font-weight-bold" style="color: #555;">Select Site</label>
                                <select name="site_id" class="form-control show-tick" data-live-search="true" required>
                                    <option value="" disabled selected>-- Choose Site --</option>
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}">{{ $site->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold" style="color: #555;">Date</label>
                                        <input type="date" name="date" class="form-control"
                                            value="{{ $att_today }}" required
                                            min="{{ $att_min_date }}" max="{{ $att_max_date }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold" style="color: #555;">Status</label>
                                        <select name="status" class="form-control show-tick" required>
                                            <option value="Present">Present</option>
                                            <option value="Absent">Absent</option>
                                            <option value="Half Day">Half Day</option>
                                            <option value="Leave">On Leave</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold" style="color: #555;">Clock In Time</label>
                                        <input type="time" name="clock_in" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold" style="color: #555;">Clock Out Time</label>
                                        <input type="time" name="clock_out" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer p-4" style="border-top: 1px solid #f1f2f6;">
                            <button type="button" class="btn btn-secondary btn-round waves-effect" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary btn-round waves-effect" style="background: linear-gradient(135deg, #eda61a 0%, #f5af19 100%); border: none;">Save Record</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('scripts')
<script>
    /* ─────────────────────────────────────────────────────────
       Reverse Geocode all GPS location cells on page load
       Uses OpenStreetMap Nominatim (free, no API key needed)
    ───────────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        // Labour Contractor change handlers
        $('#manual_user_id').on('change', function() {
            if ($(this).val() === 'labour_contractor') {
                $('#contractor_select_wrapper').show();
                $('#contractor_image_wrapper').show();
                $('#manual_bills_party_id').attr('required', true);
            } else {
                $('#contractor_select_wrapper').hide();
                $('#contractor_image_wrapper').hide();
                $('#manual_bills_party_id').removeAttr('required').val('');
            }
            if ($('#manual_bills_party_id').hasClass('show-tick')) { $('#manual_bills_party_id').selectpicker('refresh'); }
        });

        $('#edit_user_id').on('change', function() {
            if ($(this).val() === 'labour_contractor') {
                $('#edit_contractor_select_wrapper').show();
                $('#edit_contractor_image_wrapper').show();
                $('#edit_bills_party_id').attr('required', true);
            } else {
                $('#edit_contractor_select_wrapper').hide();
                $('#edit_contractor_image_wrapper').hide();
                $('#edit_bills_party_id').removeAttr('required').val('');
            }
            if ($('#edit_bills_party_id').hasClass('show-tick')) { $('#edit_bills_party_id').selectpicker('refresh'); }
        });

        const cells = document.querySelectorAll('.location-cell');

        cells.forEach(function (cell) {
            const lat = cell.dataset.lat;
            const lng = cell.dataset.lng;
            const addressEl = cell.querySelector('.location-address');
            const labelEl   = cell.querySelector('.location-label');

            if (!lat || !lng || parseFloat(lat) === 0 || parseFloat(lng) === 0) {
                if (addressEl) addressEl.innerHTML = `<i class="zmdi zmdi-alert-circle mr-1" style="color:#e57373;font-size:10px;"></i><span style="color:#aaa;">No GPS Data</span>`;
                if (labelEl) labelEl.textContent = '0.0000, 0.0000';
                return;
            }

            // Throttle: stagger requests 300ms apart per row to avoid rate-limit
            const idx = Array.from(cells).indexOf(cell);
            setTimeout(function () {
                fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&addressdetails=1`, {
                    headers: { 'Accept-Language': 'en', 'User-Agent': 'BuildArya-App/1.0' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data && data.display_name) {
                        // Build a short address: road/locality, city, country
                        const a = data.address || {};
                        const short = [
                            a.road || a.pedestrian || a.suburb || '',
                            a.city || a.town || a.village || a.county || '',
                            a.state || '',
                            a.country_code ? a.country_code.toUpperCase() : ''
                        ].filter(Boolean).join(', ');

                        if (addressEl) {
                            addressEl.innerHTML = `<i class="zmdi zmdi-map mr-1" style="color:#81c784;font-size:10px;"></i><span style="color:#aaa;">${short || data.display_name.split(',').slice(0,3).join(',')}</span>`;
                        }
                        // Update label to show short city instead of raw coords
                        if (labelEl) {
                            const city = a.city || a.town || a.village || a.county || '';
                            const road = a.road || a.suburb || '';
                            labelEl.textContent = road ? `${road}, ${city}` : (city || `${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}`);
                            labelEl.title = data.display_name;
                        }
                    } else {
                        if (addressEl) addressEl.innerHTML = `<i class="zmdi zmdi-alert-circle mr-1" style="color:#e57373;"></i><span style="color:#aaa;">${lat}, ${lng}</span>`;
                    }
                })
                .catch(() => {
                    // Fallback: show raw coordinates cleanly
                    if (addressEl) addressEl.innerHTML = `<span style="color:#aaa;">${parseFloat(lat).toFixed(5)}, ${parseFloat(lng).toFixed(5)}</span>`;
                });
            }, idx * 350); // 350ms stagger per row
        });
    });

    let localStream = null;
    let locationResolved = false;

    function openSelfAttendanceModal(mode) {
        const modal = $('#selfAttendanceModal');
        const form = $('#selfAttendanceForm');
        const title = $('#selfAttendanceTitle');
        const header = $('#selfAttendanceHeader');

        // Reset variables
        $('#selfAttendancePhoto').val('');
        $('#selfAttendanceLat').val('0.0000');
        $('#selfAttendanceLng').val('0.0000');
        locationResolved = false;

        $('#selfWebcam').show();
        $('#selfCapturedImage').hide();
        $('#btnCapturePhoto').show();
        $('#btnRetakePhoto').hide();
        $('#btnSubmitSelfAttendance').addClass('disabled').attr('disabled', true);

        if (mode === 'in') {
            title.text('Self Check-In (Webcam)');
            header.css('background', 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)');
            form.attr('action', '{{ url("/attendance/clock-in") }}');
        } else {
            title.text('Self Check-Out (Webcam)');
            header.css('background', 'linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%)');
            form.attr('action', '{{ url("/attendance/clock-out") }}');
        }

        // Trigger Modal Open
        modal.modal('show');

        // Request Location & Start Webcam
        resolveGeoLocation();
        startWebcam();
    }

    function closeSelfAttendanceModal() {
        stopWebcam();
        $('#selfAttendanceModal').modal('hide');
    }

    function startWebcam() {
        const video = document.getElementById('selfWebcam');
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                .then(function(stream) {
                    localStream = stream;
                    video.srcObject = stream;
                })
                .catch(function(err) {
                    console.error("Camera access failed:", err);
                    $('#attendanceLocationStatus')
                        .removeClass('alert-info')
                        .addClass('alert-warning')
                        .html('<i class="zmdi zmdi-alert-triangle mr-1"></i> Camera access denied or unavailable. Please submit fallback attendance.');
                    enableFallbackCheckIn();
                });
        } else {
            enableFallbackCheckIn();
        }
    }

    function stopWebcam() {
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
        }
    }

    function captureWebcamSnapshot() {
        const video = document.getElementById('selfWebcam');
        const canvas = document.getElementById('selfCanvas');
        const img = document.getElementById('selfCapturedImage');
        const context = canvas.getContext('2d');

        // Match dimensions
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;

        // Draw image
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Convert base64 data URL
        const dataUrl = canvas.toDataURL('image/png');
        $('#selfAttendancePhoto').val(dataUrl);

        // Render preview
        img.src = dataUrl;
        $(video).hide();
        $(img).show();

        $('#btnCapturePhoto').hide();
        $('#btnRetakePhoto').show();

        checkFormSubmissionReady();
    }

    function retakeWebcamSnapshot() {
        $('#selfWebcam').show();
        $('#selfCapturedImage').hide();
        $('#btnCapturePhoto').show();
        $('#btnRetakePhoto').hide();
        $('#selfAttendancePhoto').val('');

        $('#btnSubmitSelfAttendance').addClass('disabled').attr('disabled', true);
    }

    function resolveGeoLocation() {
        const statusBox = $('#attendanceLocationStatus');
        statusBox.removeClass('alert-success alert-danger alert-warning').addClass('alert-info')
            .html('<i class="zmdi zmdi-gps-dot zmdi-hc-spin mr-1"></i> Requesting GPS coordinates...');

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    $('#selfAttendanceLat').val(lat);
                    $('#selfAttendanceLng').val(lng);
                    locationResolved = true;

                    statusBox.removeClass('alert-info')
                        .addClass('alert-success')
                        .html(`<i class="zmdi zmdi-check-circle mr-1"></i> GPS Coordinates loaded successfully!`);
                    
                    checkFormSubmissionReady();
                },
                function(error) {
                    console.warn("Geolocation failed: ", error.message);
                    let errorMsg = "GPS blocked or unavailable. Using 0,0.";
                    if(error.code === 1) errorMsg = "Location Permission Denied by User.";
                    if(error.code === 2) errorMsg = "Location Unavailable (Turn on Device GPS).";
                    if(error.code === 3) errorMsg = "Location Request Timed Out.";

                    statusBox.removeClass('alert-info')
                        .addClass('alert-danger')
                        .html(`<i class="zmdi zmdi-info-outline mr-1"></i> ${errorMsg}`);
                    
                    // Fallback to manual location
                    $('#selfAttendanceLat').val('0.0000');
                    $('#selfAttendanceLng').val('0.0000');
                    locationResolved = true;

                    checkFormSubmissionReady();
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        } else {
            statusBox.removeClass('alert-info')
                .addClass('alert-danger')
                .html('<i class="zmdi zmdi-info-outline mr-1"></i> Geolocation unsupported by browser.');
            
            $('#selfAttendanceLat').val('0.0000');
            $('#selfAttendanceLng').val('0.0000');
            locationResolved = true;
            checkFormSubmissionReady();
        }
    }

    function checkFormSubmissionReady() {
        const photoVal = $('#selfAttendancePhoto').val();
        if (photoVal && locationResolved) {
            $('#btnSubmitSelfAttendance').removeClass('disabled').removeAttr('disabled');
        }
    }

    function enableFallbackCheckIn() {
        // Fallback dummy image if no camera exists
        const dummyCanvas = document.createElement('canvas');
        dummyCanvas.width = 300;
        dummyCanvas.height = 300;
        const ctx = dummyCanvas.getContext('2d');
        ctx.fillStyle = '#162447';
        ctx.fillRect(0, 0, 300, 300);
        ctx.fillStyle = '#ffffff';
        ctx.font = '14px Arial';
        ctx.fillText('Web Self Sign-In (No Webcam Available)', 20, 150);

        $('#selfAttendancePhoto').val(dummyCanvas.toDataURL('image/png'));
        $('#selfWebcam').hide();
        $('#btnCapturePhoto').hide();

        locationResolved = true;
        checkFormSubmissionReady();
    }

    function previewLogPhoto(url) {
        $('#previewModalImage').attr('src', url);
        $('#photoPreviewModal').modal('show');
    }

    function openEditModal(id, userId, siteId, date, status, clockIn, clockOut, billsPartyId, imagePath) {
        $('#editAttendanceForm').attr('action', '{{ url("/attendance/update") }}/' + id);
        
        // Reset file input
        $('#edit_contractor_image_wrapper input[type="file"]').val('');
        
        if (billsPartyId && billsPartyId !== '') {
            $('#edit_user_id').val('labour_contractor');
            $('#edit_contractor_select_wrapper').show();
            $('#edit_contractor_image_wrapper').show();
            $('#edit_bills_party_id').val(billsPartyId).attr('required', true);
            
            // Show image preview if it exists
            if (imagePath && imagePath !== '') {
                $('#edit_image_preview').attr('src', '{{ asset("/") }}' + imagePath);
                $('#edit_image_preview_container').show();
            } else {
                $('#edit_image_preview_container').hide();
            }
        } else {
            $('#edit_user_id').val(userId || '');
            $('#edit_contractor_select_wrapper').hide();
            $('#edit_contractor_image_wrapper').hide();
            $('#edit_bills_party_id').val('').removeAttr('required');
            $('#edit_image_preview_container').hide();
        }
        
        if ($('#edit_user_id').hasClass('show-tick')) { $('#edit_user_id').selectpicker('refresh'); }
        if ($('#edit_bills_party_id').hasClass('show-tick')) { $('#edit_bills_party_id').selectpicker('refresh'); }
        
        $('#edit_site_id').val(siteId);
        if ($('#edit_site_id').hasClass('show-tick')) { $('#edit_site_id').selectpicker('refresh'); }
        
        $('#edit_date').val(date);
        
        $('#edit_status').val(status);
        if ($('#edit_status').hasClass('show-tick')) { $('#edit_status').selectpicker('refresh'); }
        
        $('#edit_clock_in').val(clockIn);
        $('#edit_clock_out').val(clockOut);
        
        $('#editAttendanceModal').modal('show');
    }
</script>
@endsection
