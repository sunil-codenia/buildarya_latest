@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Sites Section'])
    @php
        $edit = false;
        $dataarray = json_decode($data, true);
        if (isset($dataarray['edit_data'])) {
            $editdata = $dataarray['edit_data'][0];
            $edit = true;
        }
    @endphp
    <div class="row clearfix">

        @if ($edit)
            @if (checkmodulepermission(1, 'can_edit') == 1)
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="card project_list">

                        <form action="{{ url('/updatesites') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit Site</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="row clearfix">
                                        <div class="col-lg-6 col-md-6 col-sm-6">
                                            <div class="form-group">
                                                <label for="Name">Name</label>
                                                <input type="hidden" name="id" value="{{ $editdata['id'] }}">
                                                <input type="text" id="Name" required class="form-control"
                                                    value="{{ $editdata['name'] }}" name="name">
                                            </div>
                                        </div>
                                        <div class="col-lg-6 col-md-6 col-sm-6">
                                            <div class="form-group">
                                                <label for="Name">Address</label>

                                                <input type="text" id="adress" required class="form-control"
                                                    value="{{ $editdata['address'] }}" name="address">
                                            </div>
                                        </div>

                                        <div class="col-lg-6 col-md-6 col-sm-6">
                                            <div class="form-group">
                                                <label for="Name">Sites Type </label>
                                                <select name="sitestype" class="form-control show-tick"  required>
                                                    @if($editdata['sites_type'] == 'Offical Site')
                                                    <option value="Official Site" selected>Official Site</option>
                                                    <option value="Working Site">Working Site</option>
     
                                                    @else
                                                    <option value="Official Site">Official Site</option>
                                                    <option value="Working Site" selected>Working Site</option>
     
                                                    @endif
                                                 </select>
                                            </div>
                                        </div>

                                        <div class="col-lg-6 col-md-6 col-sm-6">
                                            <div class="form-group">
                                                <label for="Name">Project </label>
                                                <select name="project_id" class="form-control show-tick"
                                                    data-live-search="true" required>
        
                                                    <option value="" selected disabled>--Select Project--</option>

                                                    @php $projects = getSalesProjects('ALL_PROJECTS');@endphp
                                                    @foreach($projects as $project)
                                                    @if($project->id == $editdata['project_id'])
                                                    <option selected value="{{$project->id}}">{{$project->name}}</option>
                                                    @else
                                                    <option value="{{$project->id}}">{{$project->name}}</option>
                                                    @endif
                                                    @endforeach
                                                    <option value='0'>No Project</option>
                                                </select>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>Update</a></button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            @endif
            <br>
        @endif
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Site</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">All sites will be listed here.
                    </h2>
                    <ul class="header-dropdown">
                        <li>
                            @if (checkmodulepermission(1, 'can_report') == 1)
                            <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                            data-toggle="modal" data-target="#statementModal" type="button">
                            <i class="zmdi zmdi-chart" style="color: white;"></i>
                        </button>
                            @endif

                            @if (checkmodulepermission(1, 'can_add') == 1)
                                @if (isset($site_limit_reached) && $site_limit_reached)
                                    <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                        data-toggle="modal" data-target="#upgradePlanModal" type="button" title="Upgrade Plan to Add Sites">
                                        <i class="zmdi zmdi-plus" style="color: white;"></i>
                                    </button>
                                @else
                                    <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                        data-toggle="modal" data-target="#newsitemodel" type="button">
                                        <i class="zmdi zmdi-plus" style="color: white;"></i>
                                    </button>
                                @endif
                            @endif
                            @if (checkmodulepermission(1, 'can_pay') == 1)
                                <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    data-toggle="modal" data-target="#sitebalancetransfermodel" type="button">
                                    <i class="zmdi zmdi-swap-alt" style="color: white;"></i>
                                </button>
                            @endif
                        </li>
                    </ul>
                </div>

                <div class="body">
                    @if (checkmodulepermission(1, 'can_view') == 1)
                        <div class="table-responsive">
                        <!-- Modern Bulk Actions Bar -->
                        <div id="bulkActions" class="p-2 mb-2 border rounded bg-white shadow-sm" style="display: none; border-left: 5px solid #eda61a !important;">
                            <div class="row align-items-center">
                                <div class="col-sm-4">
                                    <span class="ml-2 font-weight-bold" style="color: #eda61a;"><span id="selectedCount">0</span> Sites Selected</span>
                                </div>
                                <div class="col-sm-8 text-right">
                                    @if(checkmodulepermission(1,'can_certify') == 1)
                                        <button type="button" onclick="bulkUpdateStatus('Active')" class="btn btn-success btn-sm btn-round">
                                            <i class="zmdi zmdi-check-circle"></i> Activate
                                        </button>
                                        <button type="button" onclick="bulkUpdateStatus('Deactive')" class="btn btn-danger btn-sm btn-round">
                                            <i class="zmdi zmdi-close-circle"></i> Deactivate
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <form id="bulkActionForm" action="{{ url('/sites/bulk_action') }}" method="POST">
                            @csrf
                            <input type="hidden" name="bulk_action_type" id="bulk_action_type" value="">
                            <table id="dataTable" class="table table-hover no-wrap">
                                <thead>
                                    <tr>
                                        <th style="width:20px;"><input type="checkbox" id="select_all"></th>
                                        <th style="width:30px;">#</th>
                                        <th>Name</th>
                                        <th>Address</th>
                                        <th>Balance</th>
                                        <th>Site Type</th>
                                        <th>Project</th>
                                        <th>Status</th>
                                        <th style="width:100px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 1;
                                        $sites = isset($dataarray['data']) ? $dataarray['data'] : $dataarray;
                                    @endphp

                                    @foreach ($sites as $dd)
                                        @php
                                            $ddid = $dd['id'];
                                        @endphp

                                        <tr>
                                            <td><input type="checkbox" name="check_list[]" class="check_item" value="{{$ddid}}" onclick="event.stopPropagation()"></td>
                                            <td>{{ $i++ }}</td>
                                            <td>{{ $dd['name'] }}</td>
                                            <td>{{ $dd['address'] }}</td>
                                            <td><span class="text-primary font-weight-bold">{{ getSiteBalance($ddid) }}</span></td>
                                            <td><span class="badge badge-info">{{ $dd['sites_type'] }}</span></td>
                                            <td>
                                                @php $project_info = getSalesProjects($dd['project_id']); @endphp
                                                <span class="badge badge-default">{{ ($dd['project_id'] == '0' || !$project_info || is_a($project_info, 'Illuminate\Support\Collection')) ? "No Project" : $project_info->name }}</span>
                                            </td>
                                            <td>
                                                @if ($dd['status'] == 'Active')
                                                    @if (checkmodulepermission(1, 'can_certify') == 1)
                                                        <button type="button" onclick="updatestatus('{{ $ddid }}','Deactive')"
                                                            style="all:unset"><span
                                                                class="badge badge-success">{{ $dd['status'] }}</span></button>
                                                    @endif
                                                @else
                                                    @if (checkmodulepermission(1, 'can_certify') == 1)
                                                        <button type="button" onclick="updatestatus('{{ $ddid }}','Active')"
                                                            style="all:unset"><span
                                                                class="badge badge-danger">{{ $dd['status'] }}</span></button>
                                                    @endif
                                                @endif
                                            </td>

                                            <td>
                                                @if (checkmodulepermission(1, 'can_edit') == 1)
                                                    <button title="Edit" type="button" onclick="editdata('{{$ddid}}')" style="all:unset;"><i
                                                            class="zmdi zmdi-edit"></i>
                                                    </button> &nbsp;
                                                @endif

                                                @if (isSiteDeletable($ddid))
                                                    @if (checkmodulepermission(1, 'can_delete') == 1)
                                                        <button title="Delete" type="button" onclick="deletedata('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-delete"></i> </button> &nbsp;
                                                    @endif
                                                @endif
                                                <a title="Site Payments" href="{{ url('/view_site_payments?id='.$ddid) }}"
                                                style="all:unset"><i class="zmdi zmdi-balance-wallet text-info"></i> </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            
                        </form>
                        </div>
                    @else
                        <div class="alert alert-danger">You Don't Have Permission to View </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection


@section('models')
   
    @if (checkmodulepermission(1, 'can_add') == 1)
        <div class="modal fade" id="newsitemodel" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <form action="{{ url('/addsites') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New Site</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-6 col-md-6 col-sm-6">
                                    <div class="form-group">
                                        <label for="Name">Name</label>

                                        <input type="text" id="Name" required class="form-control"
                                            name="name">
                                    </div>
                                </div>
                                <div class="col-lg-6 col-md-6 col-sm-6">
                                    <div class="form-group">
                                        <label for="Name">Address</label>
                                        <input type="text" id="address" required class="form-control"
                                            name="address">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Opening Balance </label>
                                        <input type="text" id="open_balance" required class="form-control"
                                            name="open_balance">
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Sites Type </label>
                                        <select name="sitestype" class="form-control show-tick"
                                            data-live-search="true" required>
                                            <option value="" selected disabled>--Select Sites type--</option>
                                            <option value="Official Site">Official Site</option>
                                            <option value="Working Site">Working Site</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-4">
                                    <div class="form-group">
                                        <label for="Name">Project </label>
                                        <select name="project_id" class="form-control show-tick"
                                            data-live-search="true" required>

                                            <option value="" selected disabled>--Select Project--</option>
                                            @php $projects = getSalesProjects('ALL_PROJECTS');@endphp
                                            @foreach($projects as $project)
                                            <option value="{{$project->id}}">{{$project->name}}</option>
                                            @endforeach
                                            <option value='0'>No Project</option>
                                        </select>
                                    </div>
                                </div>


                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit"
                                class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE</a></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @if (checkmodulepermission(1, 'can_pay') == 1)
    <div class="modal fade" id="sitebalancetransfermodel" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-md" role="document">
            <form action="{{ url('/siteToSiteBalanceTransfer') }}" method="post" class="form">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="title"> Site To Site Balance Transfer</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row clearfix">
                            <h5 sm>From</h5>
                            <div class="col-sm-12"><b>Site</b>
                                <div class="input-group">
                                    <select name="from_site_id" class="form-control show-tick" data-live-search="true"
                                        required>
                                        <option value="" selected disabled>--Select Site--</option>
                                        @php
                                            $sites = getallsites();
                                        @endphp
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                           <h5 sm>To</h5>
                            <div class="col-sm-12"><b>Site</b>
                                <div class="input-group">
                                    <select name="to_site_id" class="form-control show-tick" data-live-search="true"
                                        required>
                                        <option value="" selected disabled>--Select Site--</option>
                                        @php
                                            $sites = getallsites();
                                        @endphp
                                        @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="amount">Amount</label>
                                   
                                    <input type="number" id="amount" required class="form-control" name="amount">
                                </div>
                            </div>
                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="Name">Remark</label>
                                    <input type="text" id="remark" required class="form-control" name="remark">
                                </div>
                            </div>

                            <div class="col-lg-12 col-md-12 col-sm-12">
                                <div class="form-group">
                                    <label for="Name">Date</label>
                                    <input type="date" id="date" required class="form-control" name="date">
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary btn-simple waves-effect"
                            data-dismiss="modal"><a>CLOSE</a></button>
                        <button type="submit"
                            class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE</a></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif
    @if (checkmodulepermission(1, 'can_report') == 1)
    <div class="modal fade" id="statementModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <form action="{{ url('/siteStatement') }}" method="post" class="form">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="title">Generate Site Statement</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row clearfix">
                             <div class="col-lg-6 col-md-6 col-sm-6"><b>Choose Site</b>
                                 <div class="input-group">
                                     <select name="site_id" class="form-control show-tick" data-live-search="true"
                                         required>
                                         <option value="" selected disabled>--Select Site--</option>
                                         @php
                                             $sites = getallsites();
                                         @endphp
                                         @foreach ($sites as $site)
                                             <option value="{{ $site->id }}">{{ $site->name }}</option>
                                         @endforeach
                                     </select>
                                 </div>
                             </div>
                        
                             <div class="col-lg-6 col-md-6 col-sm-6"><b>Choose File Type</b>
                                 <div class="input-group">
                                     <select name="type" class="form-control show-tick" data-live-search="true"
                                         required>
                                         <option value="1" selected>Excel</option>
                                         <option value="2" >PDF</option>
                                        </select>
                                 </div>
                             </div>
                             <div class="col-lg-6 col-md-6 col-sm-6">
                                 <div class="form-group">
                                     <label>From Date</label>
                                     <input type="date" required class="form-control" 
                                         id="start_date1" name="start_date" >
                                 </div>
                             </div>
                             <div class="col-lg-6 col-md-6 col-sm-6">
                                 <div class="form-group">
                                     <label>To Date</label>
                                     <input type="date" required class="form-control" 
                                         id="end_date1" name="end_date" >
                                 </div>
                             </div>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary btn-simple waves-effect"
                            data-dismiss="modal"><a>CLOSE</a></button>
                        <button type="submit"
                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Download</a></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif
@endsection

@section('scripts')
    <script type="text/javascript">
        function deletedata(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ url('/delete_sites/?id=') }}" + id;
                }
            })
        }

        function updatestatus(id, status) {
            Swal.fire({
                title: 'Update Status?',
                text: "Mark this site as " + status + "?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#eda61a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Update'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ url('/update_site_status/?id=') }}" + id + "&status=" + status;
                }
            });
        }

        function editdata(id) {
            Swal.fire({
                title: 'Edit Site?',
                text: "You want to edit this site details?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#eda61a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Edit'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "{{ url('/edit_site/?id=') }}" + id;
                }
            });
        }

        $(document).ready(function() {
            // Select All handler
            $('#select_all').on('change', function() {
                $('.check_item').prop('checked', $(this).prop('checked'));
                updateBulkBar();
            });

            // Row checkbox handler
            $(document).on('change', '.check_item', function() {
                updateBulkBar();
            });
        });

        function updateBulkBar() {
            let count = $('.check_item:checked').length;
            if (count > 0) {
                $('#selectedCount').text(count);
                $('#bulkActions').fadeIn();
            } else {
                $('#bulkActions').fadeOut();
                $('#select_all').prop('checked', false);
            }
        }
        
        function getCheckedIds() {
            var selected = [];
            $('.check_item:checked').each(function() {
                selected.push($(this).val());
            });
            return selected;
        }



        function bulkUpdateStatus(status) {
            var selected = getCheckedIds();
            if (selected.length === 0) return;
            
            $('#bulk_action_type').val('status_' + status);
            Swal.fire({
                title: 'Bulk Update Status?',
                text: "Mark " + selected.length + " sites as " + status + "?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#eda61a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Update Status'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#bulkActionForm').submit();
                }
            });
        }
        
        @if(request()->has('action') && request()->get('action') == 'add_new')
        $(document).ready(function() {
            setTimeout(function() {
                $('#newsitemodel').modal('show');
                @if(request()->has('project_id'))
                    var projectId = "{{request()->get('project_id')}}";
                    $('#newsitemodel select[name="project_id"]').val(projectId).selectpicker('refresh');
                @endif
            }, 300);
        });
        @endif
    </script>
@endsection
