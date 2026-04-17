@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Sites Section'])
    @php
        $edit = false;
        $dataarray = json_decode($data, true);
        if (isset(json_decode($data, true)['edit_data'])) {
            $editdata = $dataarray['edit_data'][0];
            $edit = true;
            $dataarray = $dataarray['data'];
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
                                <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    data-toggle="modal" data-target="#newsitemodel" type="button">
                                    <i class="zmdi zmdi-plus" style="color: white;"></i>
                                </button>
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
                        <div id="bulkActions" class="align-right mb-2" style="display: none; padding-bottom: 10px;">
                            @if(checkmodulepermission(1,'can_edit') == 1)
                                <button type="button" onclick="bulkEditData()" title="Edit" class="btn btn-warning btn-simple btn-round waves-effect">
                                    <i class="zmdi zmdi-edit"></i>
                                </button>
                            @endif
                            @if(checkmodulepermission(1,'can_certify') == 1)
                                <button type="button" onclick="bulkUpdateStatus('Active')" title="Mark Active" class="btn btn-success btn-simple btn-round waves-effect">
                                    <i class="zmdi zmdi-check-circle"></i>
                                </button>
                                <button type="button" onclick="bulkUpdateStatus('Deactive')" title="Mark Deactive" class="btn btn-danger btn-simple btn-round waves-effect">
                                    <i class="zmdi zmdi-close-circle"></i>
                                </button>
                            @endif
                            <button type="button" onclick="bulkSitePayments()" title="Site Payments" class="btn btn-info btn-simple btn-round waves-effect">
                                <i class="zmdi zmdi-balance-wallet"></i>
                            </button>
                        </div>

                        <form id="bulkActionForm" action="{{ url('/sites/bulk_action') }}" method="POST">
                            @csrf
                            <input type="hidden" name="bulk_action_type" id="bulk_action_type" value="">
                            <table id="dataTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width:30px;"><input type="checkbox" id="select_all"></th>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Address</th>
                                        <th>Balance</th>
                                        <th>Site Type</th>
                                        <th>Project</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $i = 1;
                                        $dataarray = json_decode($data, true)['data'];
                                    @endphp

                                    @foreach ($dataarray as $dd)
                                        @php
                                            $ddid = $dd['id'];
                                        @endphp

                                        <tr>
                                            <td><input type="checkbox" name="check_list[]" class="check_item" value="{{$ddid}}" onclick="event.stopPropagation()"></td>
                                            <td>{{ $i++ }}</td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['name'] }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['address'] }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ getSiteBalance($ddid) }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['sites_type'] }}</a>
                                            </td>
                                            <td>
                                                <a class="single-user-name" href="#">
                                                    @php $project_info = getSalesProjects($dd['project_id']); @endphp
                                                    {{ ($dd['project_id'] == '0' || !$project_info || is_a($project_info, 'Illuminate\Support\Collection')) ? "No Project" : $project_info->name }}
                                                </a>
                                            </td>
                                            <td>

                                                @if ($dd['status'] == 'Active')
                                                    @if (checkmodulepermission(1, 'can_certify') == 1)
                                                        <button onclick="updatestatus('{{ $ddid }}','Deactive')"
                                                            style="all:unset"><span
                                                                class="badge badge-success">{{ $dd['status'] }}</span></button>
                                                    @endif
                                                @else
                                                    @if (checkmodulepermission(1, 'can_certify') == 1)
                                                        <button onclick="updatestatus('{{ $ddid }}','Active')"
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
                                                        <button title="Delete" onclick="deletedata('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-delete"></i> </button>
                                                    @endif
                                                @endif
                                                <a title="Site Payments" href="{{ url('/view_site_payments?id='.$ddid) }}"
                                                style="all:unset"><i class="zmdi zmdi-balance-wallet"></i> </a>
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
                cancelButtonText: 'Cancel',
                customClass: {
                    cancelButton: 'order-1 margin-10p',
                    confirmButton: 'order-2 margin-10p',
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/delete_sites/?id=') }}" + id;
                    window.location.href = url;
                }
            })
        }

        function updatestatus(id, status) {
            Swal.fire({
                title: 'Are you sure?',
                text: "Do You Really Want To Update Its Status!",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Update',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/update_site_status/?id=') }}" + id + "&status=" + status;
                    window.location.href = url;
                }
            });
        }
        function editdata(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Site ?",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#eda61a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Edit',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/edit_site/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        $('#select_all').on('click', function() {
            if (this.checked) {
                $('.check_item').each(function() {
                    this.checked = true;
                });
            } else {
                $('.check_item').each(function() {
                    this.checked = false;
                });
            }
            toggleBulkActions();
        });

        $('.check_item').on('change', function() {
            toggleBulkActions();
        });

        function toggleBulkActions() {
            if ($('.check_item:checked').length > 0) {
                $('#bulkActions').show();
            } else {
                $('#bulkActions').hide();
            }
        }
        
        function getCheckedIds() {
            var selected = [];
            $('.check_item:checked').each(function() {
                selected.push($(this).val());
            });
            return selected;
        }

        function getSingleCheckedId() {
            var selected = getCheckedIds();
            if (selected.length === 1) {
                return selected[0];
            } else {
                Swal.fire('Notice', 'Please select exactly one site for this specific action.', 'info');
                return null;
            }
        }

        function bulkEditData() {
            var id = getSingleCheckedId();
            if(id) { editdata(id); }
        }

        function bulkSitePayments() {
            var id = getSingleCheckedId();
            if(id) { window.location.href = "{{ url('/view_site_payments?id=') }}" + id; }
        }

        function bulkUpdateStatus(status) {
            var selected = getCheckedIds();
            if (selected.length === 0) return;
            $('#bulk_action_type').val('status_' + status);
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to update status for selected sites?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, update!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#bulkActionForm').submit();
                }
            })
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
