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

                    {{-- Row 2: Filters in a clean horizontal bar --}}
                    <form method="GET" action="{{ url('/tasks') }}">
                        <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">

                            {{-- Status --}}
                            <select name="status" class="form-control form-control-sm"
                                style="border-radius: 8px; border: 1px solid #dee2e6; font-size: 13px; width: 145px; height: 36px;">
                                <option value="">All Statuses</option>
                                <option value="Pending" {{ request('status') == 'Pending' ? 'selected' : '' }}>Pending</option>
                                <option value="In Progress" {{ request('status') == 'In Progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="Completed" {{ request('status') == 'Completed' ? 'selected' : '' }}>Completed</option>
                            </select>

                            {{-- Priority --}}
                            <select name="priority" class="form-control form-control-sm"
                                style="border-radius: 8px; border: 1px solid #dee2e6; font-size: 13px; width: 135px; height: 36px;">
                                <option value="">All Priorities</option>
                                <option value="Low" {{ request('priority') == 'Low' ? 'selected' : '' }}>Low</option>
                                <option value="Medium" {{ request('priority') == 'Medium' ? 'selected' : '' }}>Medium</option>
                                <option value="High" {{ request('priority') == 'High' ? 'selected' : '' }}>High</option>
                            </select>

                            {{-- Separator --}}
                            <span style="color: #ccc; font-size: 12px; font-weight: 600;">DUE DATE:</span>

                            {{-- From Date --}}
                            <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}"
                                title="From Date"
                                style="border-radius: 8px; border: 1px solid #dee2e6; font-size: 13px; width: 150px; height: 36px;">

                            <span style="color: #aaa; font-size: 13px;">→</span>

                            {{-- To Date --}}
                            <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}"
                                title="To Date"
                                style="border-radius: 8px; border: 1px solid #dee2e6; font-size: 13px; width: 150px; height: 36px;">

                            {{-- Filter Button --}}
                            <button type="submit" class="btn btn-sm"
                                style="height: 36px; padding: 0 18px; border-radius: 8px; font-weight: 600; font-size: 13px; border: none;
                                       background: linear-gradient(135deg, #667eea, #764ba2); color: white;
                                       box-shadow: 0 3px 10px rgba(102,126,234,0.35);">
                                <i class="zmdi zmdi-filter-list mr-1"></i> Filter
                            </button>

                            {{-- Clear Button --}}
                            @if(request()->hasAny(['status','priority','from_date','to_date']))
                                <a href="{{ url('/tasks') }}" class="btn btn-sm"
                                    style="height: 36px; padding: 0 14px; border-radius: 8px; font-weight: 600; font-size: 13px;
                                           border: 1px solid #dc3545; color: #dc3545; background: transparent;">
                                    <i class="zmdi zmdi-close mr-1"></i> Clear
                                </a>
                            @endif
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
                                    @if(checkmodulepermission(14, 'can_delete') == 1)
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
                                                if($task->status == 'In Progress') $statusClass = 'btn-warning';
                                                elseif($task->status == 'Completed') $statusClass = 'btn-success';
                                                $badgeClass = 'badge-secondary';
                                                if($task->status == 'In Progress') $badgeClass = 'badge-warning';
                                                elseif($task->status == 'Completed') $badgeClass = 'badge-success';
                                            @endphp

                                            @if($canChangeStatus)
                                                <div class="dropdown">
                                                    <button class="btn {{ $statusClass }} btn-sm dropdown-toggle btn-round" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        {{ $task->status }}
                                                    </button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item" href="{{ url('/tasks/status/'.$task->id.'?status=Pending') }}">Pending</a>
                                                        <a class="dropdown-item" href="{{ url('/tasks/status/'.$task->id.'?status=In Progress') }}">In Progress</a>
                                                        <a class="dropdown-item" href="{{ url('/tasks/status/'.$task->id.'?status=Completed') }}">Completed</a>
                                                    </div>
                                                </div>
                                            @elseif($isPastDue && ($isAdmin || $isAssignedUser))
                                                {{-- Past due-date task: show status badge with lock icon --}}
                                                <span class="badge {{ $badgeClass }} p-2" style="border-radius: 6px;" title="Cannot change status: Due date has passed">
                                                    <i class="zmdi zmdi-lock mr-1"></i> {{ $task->status }}
                                                </span>
                                            @else
                                                {{-- Not assigned to this user --}}
                                                <span class="badge {{ $badgeClass }} p-2" style="border-radius: 6px;">{{ $task->status }}</span>
                                            @endif
                                        </td>
                                        @if(checkmodulepermission(14, 'can_delete') == 1)
                                            <td>
                                                <form action="{{ url('/tasks/delete/'.$task->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this task?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-neutral btn-sm btn-round text-danger" style="box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                                                        <i class="zmdi zmdi-delete"></i>
                                                    </button>
                                                </form>
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
@endsection
