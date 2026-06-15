@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Task Management'])

    <!-- Stats Grid -->
    <div class="row clearfix">
        <div class="col-lg-3 col-md-6 col-sm-12">
            <div class="card overflowhidden" style="background: linear-gradient(135deg, #00c6ff 0%, #0072ff 100%) !important; color: white !important; border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,198,255,0.35);">
                <div class="body text-center p-4">
                    <h3 class="m-b-0 number count-to" style="font-weight: 800; font-size: 2.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">{{ $totalTasks }}</h3>
                    <p class="m-b-0 font-15" style="letter-spacing: 1px; font-weight: 600; opacity: 0.9;">TOTAL TASKS</p>
                    <div class="sparkline m-t-20" data-type="bar" data-width="97%" data-height="40px" data-bar-Width="3" data-bar-Spacing="10" data-bar-Color="#fff">4,7,5,8,9,6,4,8,7,5</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
            <div class="card overflowhidden" style="background: linear-gradient(135deg, #f857a6 0%, #ff5858 100%) !important; color: white !important; border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(248,87,166,0.35);">
                <div class="body text-center p-4">
                    <h3 class="m-b-0 number count-to" style="font-weight: 800; font-size: 2.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">{{ $pending }}</h3>
                    <p class="m-b-0 font-15" style="letter-spacing: 1px; font-weight: 600; opacity: 0.9;">PENDING</p>
                    <div class="sparkline m-t-20" data-type="bar" data-width="97%" data-height="40px" data-bar-Width="3" data-bar-Spacing="10" data-bar-Color="#fff">2,3,1,4,2,3,1,4,2,3</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
            <div class="card overflowhidden" style="background: linear-gradient(135deg, #ff9966 0%, #ff5e62 100%) !important; color: white !important; border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(255,153,102,0.35);">
                <div class="body text-center p-4">
                    <h3 class="m-b-0 number count-to" style="font-weight: 800; font-size: 2.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">{{ $inProgress }}</h3>
                    <p class="m-b-0 font-15" style="letter-spacing: 1px; font-weight: 600; opacity: 0.9;">IN PROGRESS</p>
                    <div class="sparkline m-t-20" data-type="bar" data-width="97%" data-height="40px" data-bar-Width="3" data-bar-Spacing="10" data-bar-Color="#fff">1,2,3,1,2,0,2,1,3,1</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12">
            <div class="card overflowhidden" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%) !important; color: white !important; border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(17,153,142,0.35);">
                <div class="body text-center p-4">
                    <h3 class="m-b-0 number count-to" style="font-weight: 800; font-size: 2.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">{{ $completed }}</h3>
                    <p class="m-b-0 font-15" style="letter-spacing: 1px; font-weight: 600; opacity: 0.9;">COMPLETED</p>
                    <div class="sparkline m-t-20" data-type="bar" data-width="97%" data-height="40px" data-bar-Width="3" data-bar-Spacing="10" data-bar-Color="#fff">3,4,5,6,3,4,5,6,3,4</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Tasks Board -->
    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list" style="border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: none;">
                <div class="p-4" style="border-bottom: 1px solid rgba(255,255,255,0.07);">
                    {{-- Row 1: Title + Add Task Button --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="m-0"><strong style="color: #333;">Task Tracker</strong></h2>
                        @if(checkmodulepermission(14, 'can_add') == 1)
                            <button class="btn btn-primary btn-round waves-effect" data-toggle="modal" data-target="#addTaskModal"
                                style="background: linear-gradient(135deg, #eda61a 0%, #f5af19 100%); border: none; box-shadow: 0 4px 15px rgba(237,166,26,0.3); font-weight: 600;">
                                <i class="zmdi zmdi-plus-circle mr-1"></i> Add Task
                            </button>
                        @endif
                    </div>

                    {{-- Row 2: Filters in a clean horizontal grid --}}
                    <form method="GET" action="{{ url('/tasks') }}">
                        <div class="row clearfix align-items-end">
                            {{-- Status --}}
                            <div class="col-lg-3 col-md-3 col-sm-6 mb-3">
                                <label style="font-weight: 600; color: #555; font-size: 12px; margin-bottom: 5px;">Status</label>
                                <select name="status" class="form-control show-tick">
                                    <option value="">All Statuses</option>
                                    <option value="Pending" {{ request('status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="Progress" {{ request('status') == 'Progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="Completed" {{ request('status') == 'Completed' ? 'selected' : '' }}>Completed</option>
                                </select>
                            </div>

                            {{-- Priority --}}
                            <div class="col-lg-3 col-md-3 col-sm-6 mb-3">
                                <label style="font-weight: 600; color: #555; font-size: 12px; margin-bottom: 5px;">Priority</label>
                                <select name="priority" class="form-control show-tick">
                                    <option value="">All Priorities</option>
                                    <option value="Low" {{ request('priority') == 'Low' ? 'selected' : '' }}>Low</option>
                                    <option value="Medium" {{ request('priority') == 'Medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="High" {{ request('priority') == 'High' ? 'selected' : '' }}>High</option>
                                </select>
                            </div>

                            {{-- From Date --}}
                            <div class="col-lg-2 col-md-2 col-sm-6 mb-3">
                                <label style="font-weight: 600; color: #555; font-size: 12px; margin-bottom: 5px;">From Date</label>
                                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                            </div>

                            {{-- To Date --}}
                            <div class="col-lg-2 col-md-2 col-sm-6 mb-3">
                                <label style="font-weight: 600; color: #555; font-size: 12px; margin-bottom: 5px;">To Date</label>
                                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                            </div>

                            {{-- Filter and Clear Buttons --}}
                            <div class="col-lg-2 col-md-2 col-sm-12 mb-3 d-flex" style="gap: 5px;">
                                <button type="submit" class="btn btn-primary btn-block m-0 d-flex align-items-center justify-content-center"
                                    style="height: 38px; border-radius: 4px; font-weight: 600; font-size: 13px; border: none;
                                           background: linear-gradient(135deg, #eda61a 0%, #f5af19 100%); color: white;
                                           box-shadow: 0 4px 15px rgba(237,166,26,0.25);">
                                    <i class="zmdi zmdi-filter-list mr-1"></i> Filter
                                </button>
                                @if(request()->hasAny(['status','priority','from_date','to_date']))
                                    <a href="{{ url('/tasks') }}" class="btn btn-neutral m-0 d-flex align-items-center justify-content-center"
                                        style="height: 38px; width: 42px; border-radius: 4px; border: 1px solid #dc3545; color: #dc3545; background: transparent; padding: 0;">
                                        <i class="zmdi zmdi-close"></i>
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
                <div class="body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="taskTable">
                            <thead>
                                <tr style="color: #888; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">
                                    <th>Title</th>
                                    <th>Assigned To</th>
                                    <th>Assigned Site</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    @if(checkmodulepermission(14, 'can_edit') == 1 || checkmodulepermission(14, 'can_delete') == 1)
                                        <th>Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($tasks as $task)
                                    <tr style="border-bottom: 1px solid #f9f9fc;">
                                        <td>
                                            <h6 class="m-0 font-weight-bold" style="color: #333;">{{ $task->title }}</h6>
                                            <small class="text-muted">{{ Str::limit($task->description, 50) }}</small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle mr-2" style="background-color: #f1f2f6; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; color: #eda61a;">
                                                    {{ strtoupper(substr($task->assigned_name, 0, 2)) }}
                                                </div>
                                                <span class="font-weight-bold" style="font-size: 13px;">{{ $task->assigned_name }}</span>
                                            </div>
                                        </td>
                                        <td><strong>{{ $task->site_name ?? 'Global' }}</strong></td>
                                        <td>
                                            @php
                                                $priorityClass = 'badge-success';
                                                if($task->priority == 'High') $priorityClass = 'badge-danger';
                                                elseif($task->priority == 'Medium') $priorityClass = 'badge-warning';
                                            @endphp
                                            <span class="badge {{ $priorityClass }} p-2" style="border-radius: 6px;">{{ $task->priority }}</span>
                                        </td>
                                        <td><span class="text-muted"><i class="zmdi zmdi-calendar mr-1"></i> {{ $task->due_date ? date('d M, Y', strtotime($task->due_date)) : 'No Limit' }}</span></td>
                                        <td>
                                            @php
                                                $today = \Carbon\Carbon::today();
                                                $isPastDue = $task->due_date && \Carbon\Carbon::parse($task->due_date)->startOfDay()->lt($today);
                                                $isAssignedUser = (session('uid') == $task->assigned_to);
                                                $canChangeStatus = ($isAdmin || $isAssignedUser) && !$isPastDue;
                                                $statusClass = 'btn-secondary';
                                                if($task->status == 'Progress' || $task->status == 'In Progress') $statusClass = 'btn-warning';
                                                elseif($task->status == 'Completed') $statusClass = 'btn-success';
                                                $badgeClass = 'badge-secondary';
                                                if($task->status == 'Progress' || $task->status == 'In Progress') $badgeClass = 'badge-warning';
                                                elseif($task->status == 'Completed') $badgeClass = 'badge-success';
                                            @endphp

                                            @if($canChangeStatus)
                                                <div class="dropdown">
                                                    <button class="btn {{ $statusClass }} btn-sm dropdown-toggle btn-round" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        {{ $task->status == 'Progress' ? 'In Progress' : $task->status }}
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="{{ url('/tasks/status/'.$task->id.'?status=Pending') }}">Pending</a>
                                                        <a class="dropdown-item" href="{{ url('/tasks/status/'.$task->id.'?status=Progress') }}">In Progress</a>
                                                        <a class="dropdown-item" href="{{ url('/tasks/status/'.$task->id.'?status=Completed') }}">Completed</a>
                                                    </div>
                                                </div>
                                            @elseif($isPastDue && ($isAdmin || $isAssignedUser))
                                                {{-- Past due-date task: show status badge with lock icon --}}
                                                <span class="badge {{ $badgeClass }} p-2" style="border-radius: 6px;" title="Cannot change status: Due date has passed">
                                                    <i class="zmdi zmdi-lock mr-1"></i> {{ $task->status == 'Progress' ? 'In Progress' : $task->status }}
                                                </span>
                                            @else
                                                {{-- Not assigned to this user --}}
                                                <span class="badge {{ $badgeClass }} p-2" style="border-radius: 6px;">{{ $task->status == 'Progress' ? 'In Progress' : $task->status }}</span>
                                            @endif
                                        </td>
                                        @if(checkmodulepermission(14, 'can_edit') == 1 || checkmodulepermission(14, 'can_delete') == 1)
                                            <td>
                                                <div class="d-flex align-items-center">
                                                @if(checkmodulepermission(14, 'can_edit') == 1)
                                                    <button type="button" class="btn btn-neutral btn-sm btn-round text-primary mr-2 edit-task-btn" 
                                                        data-id="{{ $task->id }}" 
                                                        data-title="{{ $task->title }}"
                                                        data-description="{{ $task->description }}"
                                                        data-assigned="{{ $task->assigned_to }}"
                                                        data-site="{{ $task->site_id }}"
                                                        data-priority="{{ $task->priority }}"
                                                        data-due="{{ $task->due_date ? date('Y-m-d', strtotime($task->due_date)) : '' }}"
                                                        style="box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                                                        <i class="zmdi zmdi-edit"></i>
                                                    </button>
                                                @endif
                                                @if(checkmodulepermission(14, 'can_delete') == 1)
                                                    <form action="{{ url('/tasks/delete/'.$task->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this task?')" style="margin: 0;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-neutral btn-sm btn-round text-danger" style="box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                                                            <i class="zmdi zmdi-delete"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center p-5">
                                            <img src="{{ asset('/images/permission.png') }}" style="width: 80px; opacity: 0.5;" class="mb-3"><br>
                                            <h6 class="text-muted">No tasks found. Keep it up!</h6>
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

    <!-- Discussion & Support Chat -->
    <div class="row clearfix m-t-20">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="card" style="border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: none; overflow: hidden;">
                <div class="header p-4" style="background: linear-gradient(135deg, #1f4068 0%, #162447 100%); color: white; display: flex; justify-content: space-between; align-items: center;">
                    <h2 class="m-0" style="color: white !important;"><i class="zmdi zmdi-comments mr-2"></i> <strong>Task Discussion & Support Chat</strong></h2>
                    <span class="badge badge-warning p-2" style="font-size: 11px;">Persistent History</span>
                </div>
                <div class="body p-0">
                    <div class="row no-gutters">
                        @if($isChatAdmin)
                            <!-- Users List Sidebar (Admin only) -->
                            <div class="col-lg-4 col-md-4 col-sm-12" style="border-right: 1px solid #eee; background: #fdfdfd; max-height: 500px; overflow-y: auto;">
                                <div class="p-3" style="border-bottom: 1px solid #f0f0f0;">
                                    <input type="text" id="chatUserSearch" class="form-control form-control-sm" placeholder="Search user..." style="border-radius: 20px;">
                                </div>
                                <div class="list-group list-group-flush" id="chatUsersList">
                                    @foreach($users as $user)
                                        @if($user->id != session('uid'))
                                            <a href="javascript:void(0);" class="list-group-item list-group-item-action d-flex align-items-center p-3 chat-user-item" data-id="{{ $user->id }}" data-name="{{ $user->name }}">
                                                <div class="avatar-circle mr-3" style="background-color: #e2e8f0; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; color: #162447;">
                                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                                </div>
                                                <div style="flex-grow: 1;">
                                                    <h6 class="m-0 font-weight-bold chat-user-name" style="font-size: 14px; color: #333;">{{ $user->name }}</h6>
                                                    <small class="text-muted">{{ $user->username }}</small>
                                                </div>
                                                <i class="zmdi zmdi-chevron-right text-muted"></i>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            
                            <!-- Chat Area (Admin Mode) -->
                            <div class="col-lg-8 col-md-8 col-sm-12 d-flex flex-column" style="height: 500px; overflow: hidden;">
                                <div id="adminNoChatSelected" class="d-flex flex-column align-items-center justify-content-center text-center p-5" style="flex-grow: 1; height: 100%;">
                                    <i class="zmdi zmdi-comments text-muted mb-3" style="font-size: 4rem; opacity: 0.3;"></i>
                                    <h5 class="text-muted">Select a user from the list to start chatting</h5>
                                </div>
                                <div id="adminChatContainer" class="d-flex flex-column" style="display: none !important; flex-grow: 1; height: 100%; max-height: 100%; overflow: hidden;">
                                    <div class="p-3" style="border-bottom: 1px solid #eee; background: #fafafa; display: flex; align-items: center;">
                                        <div class="avatar-circle mr-3" style="background-color: #eda61a; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; color: white;">
                                            <span id="activeChatAvatar">--</span>
                                        </div>
                                        <h6 class="m-0 font-weight-bold" id="activeChatName" style="color: #333;">Select User</h6>
                                    </div>
                                    <div class="p-4 chat-history-box" id="adminChatHistory" style="flex-grow: 1; overflow-y: auto; background: #f4f6f9; min-height: 0;">
                                        <!-- Messages will load dynamically -->
                                    </div>
                                    <form id="adminChatForm" class="p-3" style="border-top: 1px solid #eee; background: #fff; margin: 0; position: relative;" enctype="multipart/form-data">
                                        <div id="adminChatEmojiPicker" class="emoji-picker-popover" style="display: none; position: absolute; bottom: 60px; left: 10px; z-index: 1000; background: white; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); padding: 10px; width: 280px;">
                                            <div class="emoji-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; height: 180px; overflow-y: auto;">
                                                <!-- Emojis will be dynamically rendered here -->
                                            </div>
                                        </div>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <button type="button" class="btn btn-neutral m-0" id="adminChatImageBtn" style="border: 1px solid #ddd; border-right: none; border-radius: 30px 0 0 30px; height: 45px; width: 45px; display: flex; align-items: center; justify-content: center; background: #f9f9f9;"><i class="zmdi zmdi-camera" style="font-size: 18px; color: #555;"></i></button>
                                                <button type="button" class="btn btn-neutral m-0" id="adminChatEmojiBtn" style="border: 1px solid #ddd; border-right: none; border-left: none; border-radius: 0; height: 45px; width: 45px; display: flex; align-items: center; justify-content: center; background: #f9f9f9;"><i class="zmdi zmdi-mood" style="font-size: 18px; color: #555;"></i></button>
                                            </div>
                                            <input type="file" id="adminChatImage" name="image" accept="image/*" style="display: none;">
                                            <input type="text" id="adminChatMessage" class="form-control" placeholder="Type your message..." style="border-radius: 0; border: 1px solid #ddd; border-left: none; height: 45px; padding-left: 10px;">
                                            <div class="input-group-append">
                                                <button type="submit" class="btn btn-primary m-0" style="background: #162447; border: none; border-radius: 0 30px 30px 0; width: 80px; height: 45px; display: flex; align-items: center; justify-content: center;"><i class="zmdi zmdi-send" style="font-size: 18px; color: white;"></i></button>
                                            </div>
                                        </div>
                                        <div id="adminChatImagePreviewContainer" class="mt-2 p-2" style="display: none; border: 1px dashed #ddd; border-radius: 10px; position: relative; display: inline-block;">
                                            <img id="adminChatImagePreview" src="" style="max-height: 80px; border-radius: 5px;">
                                            <button type="button" id="adminChatImageClear" style="position: absolute; top: -5px; right: -5px; background: red; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer;">&times;</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @else
                            <!-- Regular User Mode (Direct Chat with Admin) -->
                            <div class="col-lg-12 col-md-12 col-sm-12 d-flex flex-column" style="height: 500px; overflow: hidden;">
                                <div class="p-3" style="border-bottom: 1px solid #eee; background: #fafafa; display: flex; align-items: center;">
                                    <div class="avatar-circle mr-3" style="background-color: #162447; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; color: white;">
                                        AD
                                    </div>
                                    <h6 class="m-0 font-weight-bold" style="color: #333;">Chat with Administration / Support</h6>
                                </div>
                                <div class="p-4 chat-history-box" id="userChatHistory" style="flex-grow: 1; overflow-y: auto; background: #f4f6f9; min-height: 0;">
                                    <!-- Messages will load dynamically -->
                                </div>
                                <form id="userChatForm" class="p-3" style="border-top: 1px solid #eee; background: #fff; margin: 0; position: relative;" enctype="multipart/form-data">
                                    <div id="userChatEmojiPicker" class="emoji-picker-popover" style="display: none; position: absolute; bottom: 60px; left: 10px; z-index: 1000; background: white; border: 1px solid #ccc; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); padding: 10px; width: 280px;">
                                        <div class="emoji-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; height: 180px; overflow-y: auto;">
                                            <!-- Emojis will be dynamically rendered here -->
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <button type="button" class="btn btn-neutral m-0" id="userChatImageBtn" style="border: 1px solid #ddd; border-right: none; border-radius: 30px 0 0 30px; height: 45px; width: 45px; display: flex; align-items: center; justify-content: center; background: #f9f9f9;"><i class="zmdi zmdi-camera" style="font-size: 18px; color: #555;"></i></button>
                                            <button type="button" class="btn btn-neutral m-0" id="userChatEmojiBtn" style="border: 1px solid #ddd; border-right: none; border-left: none; border-radius: 0; height: 45px; width: 45px; display: flex; align-items: center; justify-content: center; background: #f9f9f9;"><i class="zmdi zmdi-mood" style="font-size: 18px; color: #555;"></i></button>
                                        </div>
                                        <input type="file" id="userChatImage" name="image" accept="image/*" style="display: none;">
                                        <input type="text" id="userChatMessage" class="form-control" placeholder="Type your message..." style="border-radius: 0; border: 1px solid #ddd; border-left: none; height: 45px; padding-left: 10px;">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary m-0" style="background: #eda61a; border: none; border-radius: 0 30px 30px 0; width: 80px; height: 45px; display: flex; align-items: center; justify-content: center;"><i class="zmdi zmdi-send" style="font-size: 18px; color: white;"></i></button>
                                        </div>
                                    </div>
                                    <div id="userChatImagePreviewContainer" class="mt-2 p-2" style="display: none; border: 1px dashed #ddd; border-radius: 10px; position: relative; display: inline-block;">
                                        <img id="userChatImagePreview" src="" style="max-height: 80px; border-radius: 5px;">
                                        <button type="button" id="userChatImageClear" style="position: absolute; top: -5px; right: -5px; background: red; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer;">&times;</button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .chat-history-box {
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }
    .message-bubble {
        padding: 10px 15px;
        border-radius: 15px;
        margin-bottom: 12px;
        font-size: 13.5px;
        line-height: 1.4;
        word-break: break-word;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .message-bubble.sent {
        background: #1f4068;
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 2px;
    }
    .message-bubble.received {
        background: white;
        color: #333;
        align-self: flex-start;
        border-bottom-left-radius: 2px;
        border: 1px solid #e9ecef;
    }
    .message-time {
        font-size: 10px;
        margin-top: 4px;
        opacity: 0.7;
        text-align: right;
    }
    .chat-user-item.active {
        background-color: #edf2f7 !important;
        border-left: 4px solid #eda61a;
    }
    .chat-user-item:hover {
        background-color: #f7fafc;
        text-decoration: none;
    }
    .chat-uploaded-image {
        max-width: 220px;
        max-height: 180px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid rgba(0,0,0,0.1);
        display: block;
        margin-bottom: 5px;
        transition: transform 0.2s;
    }
    .chat-uploaded-image:hover {
        transform: scale(1.02);
    }
    .emoji-item:hover {
        background-color: #f0f2f5;
        transform: scale(1.2);
    }
    </style>
@endsection

@section('models')
    <!-- Add Task Modal -->
    <div class="modal fade" id="addTaskModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form action="{{ url('/tasks') }}" method="POST" class="form">
                @csrf
                <div class="modal-content" style="border-radius: 15px; overflow: hidden; border: none;">
                    <div class="modal-header p-4" style="background: linear-gradient(135deg, #eda61a 0%, #f5af19 100%); color: white;">
                        <h4 class="title m-0" style="font-weight: bold;"><i class="zmdi zmdi-playlist-plus mr-2"></i> Create New Task</h4>
                    </div>
                    <div class="modal-body p-4">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Task Title</label>
                            <input type="text" name="title" class="form-control" required placeholder="What needs to be done?">
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Add details..."></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Assign To</label>
                            <select name="assigned_to" class="form-control show-tick" data-live-search="true" required>
                                <option value="" disabled selected>-- Choose User --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Assigned Site</label>
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
                                    <label class="font-weight-bold" style="color: #555;">Priority</label>
                                    <select name="priority" class="form-control show-tick" required>
                                        <option value="Low">Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold" style="color: #555;">Due Date</label>
                                    <input type="date" name="due_date" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer p-4" style="border-top: 1px solid #f1f2f6;">
                        <button type="button" class="btn btn-secondary btn-round waves-effect" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary btn-round waves-effect" style="background: linear-gradient(135deg, #eda61a 0%, #f5af19 100%); border: none;">Create Task</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form id="editTaskForm" method="POST" class="form">
                @csrf
                <div class="modal-content" style="border-radius: 15px; overflow: hidden; border: none;">
                    <div class="modal-header p-4" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                        <h4 class="title m-0" style="font-weight: bold;"><i class="zmdi zmdi-edit mr-2"></i> Edit Task</h4>
                    </div>
                    <div class="modal-body p-4">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Task Title</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required placeholder="What needs to be done?">
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="Add details..."></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Assign To</label>
                            <select name="assigned_to" id="edit_assigned_to" class="form-control show-tick" required>
                                <option value="" disabled>-- Choose User --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-bold" style="color: #555;">Assigned Site</label>
                            <select name="site_id" id="edit_site_id" class="form-control show-tick" required>
                                <option value="" disabled>-- Choose Site --</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold" style="color: #555;">Priority</label>
                                    <select name="priority" id="edit_priority" class="form-control show-tick" required>
                                        <option value="Low">Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="font-weight-bold" style="color: #555;">Due Date</label>
                                    <input type="date" name="due_date" id="edit_due_date" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer p-4" style="border-top: 1px solid #f1f2f6;">
                        <button type="button" class="btn btn-secondary btn-round waves-effect" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary btn-round waves-effect" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none;">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
@section('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.edit-task-btn');
        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('editTaskForm').action = "{{ url('/tasks/update') }}/" + id;
                
                document.getElementById('edit_title').value = this.dataset.title;
                document.getElementById('edit_description').value = this.dataset.description;
                document.getElementById('edit_due_date').value = this.dataset.due || '';
                
                document.getElementById('edit_assigned_to').value = this.dataset.assigned;
                document.getElementById('edit_site_id').value = this.dataset.site;
                document.getElementById('edit_priority').value = this.dataset.priority;
                
                if (typeof $ !== 'undefined' && $.fn.selectpicker) {
                    $('#edit_assigned_to, #edit_site_id, #edit_priority').selectpicker('refresh');
                }
                
                if (typeof $ !== 'undefined') {
                    $('#editTaskModal').modal('show');
                }
            });
        });
    });

    $(document).ready(function() {
        let activeChatUserId = null;
        let chatInterval = null;
        const isAdmin = {{ $isChatAdmin ? 'true' : 'false' }};
        const currentUserId = {{ session('uid') ?? 'null' }};

        // User Search in Sidebar
        $('#chatUserSearch').on('keyup', function() {
            let value = $(this).val().toLowerCase();
            $('#chatUsersList .chat-user-item').filter(function() {
                $(this).toggle($(this).find('.chat-user-name').text().toLowerCase().indexOf(value) > -1);
            });
        });

        // Format Date/Time helper
        function formatTime(dateStr) {
            let date = new Date(dateStr);
            let hours = date.getHours();
            let minutes = date.getMinutes();
            let ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            minutes = minutes < 10 ? '0' + minutes : minutes;
            return hours + ':' + minutes + ' ' + ampm;
        }

        // Render messages
        function renderMessages(messages, historyBoxId) {
            let $historyBox = $('#' + historyBoxId);
            
            // Store current scroll position to check if user was scrolled to bottom
            let isAtBottom = $historyBox[0].scrollHeight - $historyBox.scrollTop() <= $historyBox.outerHeight() + 50;
            let wasEmpty = $historyBox.children().length === 0;

            $historyBox.empty();
            if (messages.length === 0) {
                $historyBox.append('<div class="text-center text-muted p-5"><i class="zmdi zmdi-info-outline" style="font-size: 2rem; opacity: 0.5;"></i><p class="m-t-10">No messages yet. Start the conversation!</p></div>');
                return;
            }
            messages.forEach(msg => {
                let isSent = (msg.sender_id == currentUserId);
                let bubbleClass = isSent ? 'sent' : 'received';
                let timeStr = formatTime(msg.created_at);
                let senderNameHtml = !isSent ? '<div style="font-size: 10px; font-weight: bold; margin-bottom: 2px; color: #555;">' + msg.sender_name + '</div>' : '';
                
                let messageHtml = '';
                if (msg.message !== null && msg.message !== undefined && msg.message !== '') {
                    messageHtml = '<div>' + escapeHtml(msg.message) + '</div>';
                }
                
                let imageHtml = '';
                if (msg.image) {
                    imageHtml = '<div class="message-image-container m-b-5"><a href="/' + msg.image + '" target="_blank"><img src="/' + msg.image + '" class="img-fluid chat-uploaded-image" alt="Uploaded Attachment"></a></div>';
                }

                $historyBox.append(
                    '<div class="message-bubble ' + bubbleClass + '">' +
                        senderNameHtml +
                        imageHtml +
                        messageHtml +
                        '<div class="message-time">' + timeStr + '</div>' +
                    '</div>'
                );
            });
            
            if (wasEmpty || isAtBottom) {
                $historyBox.scrollTop($historyBox[0].scrollHeight);
            }
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Fetch messages helper
        function fetchMessages(userId, historyBoxId) {
            $.ajax({
                url: '{{ url("/tasks/chat/messages") }}/' + userId,
                method: 'GET',
                success: function(res) {
                    if (res.status === 'success') {
                        renderMessages(res.messages, historyBoxId);
                    }
                }
            });
        }

        // Admin selects user to chat
        $('.chat-user-item').on('click', function() {
            $('.chat-user-item').removeClass('active');
            $(this).addClass('active');
            
            let userId = $(this).data('id');
            let userName = $(this).data('name');
            activeChatUserId = userId;

            // Update header details
            $('#activeChatName').text(userName);
            $('#activeChatAvatar').text(userName.substring(0, 2).toUpperCase());

            $('#adminNoChatSelected').attr('style', 'display: none !important;');
            $('#adminChatContainer').attr('style', 'display: flex !important; flex-grow: 1; height: 100%; max-height: 100%; overflow: hidden;');

            // Reset image attachment preview
            $('#adminChatImage').val('');
            $('#adminChatImagePreviewContainer').hide();
            $('#adminChatImagePreview').attr('src', '');

            // Initial load
            fetchMessages(userId, 'adminChatHistory');

            // Setup polling
            if (chatInterval) clearInterval(chatInterval);
            chatInterval = setInterval(function() {
                fetchMessages(userId, 'adminChatHistory');
            }, 3000);
        });

        // Admin camera/image button trigger
        $(document).on('click', '#adminChatImageBtn', function() {
            $('#adminChatImage').click();
        });

        // Admin file selection preview
        $(document).on('change', '#adminChatImage', function() {
            let file = this.files[0];
            if (file) {
                let reader = new FileReader();
                reader.onload = function(e) {
                    $('#adminChatImagePreview').attr('src', e.target.result);
                    $('#adminChatImagePreviewContainer').show();
                }
                reader.readAsDataURL(file);
            }
        });

        // Admin clear selected image
        $(document).on('click', '#adminChatImageClear', function() {
            $('#adminChatImage').val('');
            $('#adminChatImagePreviewContainer').hide();
            $('#adminChatImagePreview').attr('src', '');
        });

        // Admin send message
        $('#adminChatForm').on('submit', function(e) {
            e.preventDefault();
            let msg = $('#adminChatMessage').val().trim();
            let file = $('#adminChatImage')[0].files[0];
            if (!msg && !file) return;
            if (!activeChatUserId) return;

            let formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('user_id', activeChatUserId);
            formData.append('message', msg);
            if (file) {
                formData.append('image', file);
            }

            let $submitBtn = $(this).find('button[type="submit"]');
            $submitBtn.prop('disabled', true);

            $.ajax({
                url: '{{ url("/tasks/chat/send") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.status === 'success') {
                        $('#adminChatMessage').val('');
                        $('#adminChatImage').val('');
                        $('#adminChatImagePreviewContainer').hide();
                        $('#adminChatImagePreview').attr('src', '');
                        fetchMessages(activeChatUserId, 'adminChatHistory');
                    }
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                }
            });
        });

        // User camera/image button trigger
        $(document).on('click', '#userChatImageBtn', function() {
            $('#userChatImage').click();
        });

        // User file selection preview
        $(document).on('change', '#userChatImage', function() {
            let file = this.files[0];
            if (file) {
                let reader = new FileReader();
                reader.onload = function(e) {
                    $('#userChatImagePreview').attr('src', e.target.result);
                    $('#userChatImagePreviewContainer').show();
                }
                reader.readAsDataURL(file);
            }
        });

        // User clear selected image
        $(document).on('click', '#userChatImageClear', function() {
            $('#userChatImage').val('');
            $('#userChatImagePreviewContainer').hide();
            $('#userChatImagePreview').attr('src', '');
        });

        // Regular user mode initialization
        if (!isAdmin && currentUserId) {
            fetchMessages(currentUserId, 'userChatHistory');
            
            // Setup polling
            chatInterval = setInterval(function() {
                fetchMessages(currentUserId, 'userChatHistory');
            }, 3000);

            // User send message
            $('#userChatForm').on('submit', function(e) {
                e.preventDefault();
                let msg = $('#userChatMessage').val().trim();
                let file = $('#userChatImage')[0].files[0];
                if (!msg && !file) return;

                let formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('user_id', currentUserId);
                formData.append('message', msg);
                if (file) {
                    formData.append('image', file);
                }

                let $submitBtn = $(this).find('button[type="submit"]');
                $submitBtn.prop('disabled', true);

                $.ajax({
                    url: '{{ url("/tasks/chat/send") }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.status === 'success') {
                            $('#userChatMessage').val('');
                            $('#userChatImage').val('');
                            $('#userChatImagePreviewContainer').hide();
                            $('#userChatImagePreview').attr('src', '');
                            fetchMessages(currentUserId, 'userChatHistory');
                        }
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false);
                    }
                });
            });
        }

        // Emoji picker logic
        const emojis = ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖','🎃','😺','😸','😻','😼','😽','🙀','😿','😾','👋','🤚','🖐️','✋','🖖','👌','🤏','✌️','🤞','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👍','👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦾','👂','🦻','👃','🧠','🦷','🦴','👀','👁️','👅','👄','💋','🩸','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❤️‍🔥','❤️‍🩹','❣️','💕','💞','💓','💗','💖','💘','💝','💟','🌟','⭐','✨','⚡','💥','🔥','🌈','☀️','🌤️','⛅','🌥️','☁️','🌦️','🌧️','⛈️','🌩️','🌨️','❄️','💨','💧','💦','☔','🎈','🎉','🎊','🎁'];

        function initEmojiPicker(btnId, pickerId, inputId) {
            const $btn = $('#' + btnId);
            const $picker = $('#' + pickerId);
            const $input = $('#' + inputId);
            const $grid = $picker.find('.emoji-grid');

            if ($grid.children().length === 0) {
                emojis.forEach(emoji => {
                    $grid.append('<span class="emoji-item" style="font-size: 20px; padding: 5px; cursor: pointer; text-align: center; border-radius: 4px; display: inline-block; transition: all 0.1s ease-in-out;">' + emoji + '</span>');
                });
            }

            $btn.on('click', function(e) {
                e.stopPropagation();
                $('.emoji-picker-popover').not($picker).hide();
                $picker.toggle();
            });

            $grid.on('click', '.emoji-item', function(e) {
                e.stopPropagation();
                const emoji = $(this).text();
                const inputEl = $input[0];
                const startPos = inputEl.selectionStart;
                const endPos = inputEl.selectionEnd;
                const text = $input.val();

                $input.val(text.substring(0, startPos) + emoji + text.substring(endPos));
                $input.focus();
                
                const newCursorPos = startPos + emoji.length;
                inputEl.setSelectionRange(newCursorPos, newCursorPos);
                $picker.hide();
            });
        }

        initEmojiPicker('adminChatEmojiBtn', 'adminChatEmojiPicker', 'adminChatMessage');
        initEmojiPicker('userChatEmojiBtn', 'userChatEmojiPicker', 'userChatMessage');

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.emoji-picker-popover, #adminChatEmojiBtn, #userChatEmojiBtn').length) {
                $('.emoji-picker-popover').hide();
            }
        });
    });
    </script>
@endsection
