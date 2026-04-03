@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Expense head'])
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
            @if (checkmodulepermission(2, 'can_edit') == 1)
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="card project_list">

                        <form action="{{ url('/updateexpensehead') }}" method="post" class="form">
                            @csrf
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="title">Edit Expense Head</h4>
                                </div>
                                <div class="modal-body">
                                    <div class="row clearfix">
                                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                            <label for="Name">Name</label>
                                        </div>
                                        <div class="col-lg-8 col-md-8 col-sm-8">
                                            <div class="form-group">
                                                <input type="hidden" name="id" value="{{ $editdata['id'] }}">
                                                <input type="text" id="Name" required class="form-control"
                                                    value="{{ $editdata['name'] }}" name="name"
                                                    placeholder="Enter the Expense Head Name">
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
                <br>
            @endif
        @endif
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Expense Head</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Head will be listed here.</div>
                    </h2>
                    <ul class="header-dropdown">
                        <li id="bulkActions" style="display: none;">
                            @if (checkmodulepermission(2, 'can_edit') == 1)
                                <button class="btn btn-warning btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    title="Bulk Edit" type="button" onclick="submitBulkEdit()">
                                    <i class="zmdi zmdi-edit" style="color: white;"></i>
                                </button>
                            @endif
                        </li>
                        <li>
                            @if (checkmodulepermission(2, 'can_add') == 1)
                                <button class="btn btn-primary btn-icon btn-round hidden-sm-down float-right m-l-10"
                                    data-toggle="modal" data-target="#newexpensehead1" type="button">
                                    <i class="zmdi zmdi-plus" style="color: white;"></i>
                                </button>
                            @endif
                        </li>
                    </ul>
                </div>

                <div class="body">
                    @if (checkmodulepermission(2, 'can_view') == 1)
                        <form action="{{ url('/bulk_edit_head') }}" method="POST" id="bulkEditForm">
                            @csrf
                            <input type="hidden" name="type" value="head">
                            <div class="table-responsive">
                                <table id="dataTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 20px;">
                                                <div class="checkbox">
                                                    <input id="select_all" type="checkbox">
                                                    <label for="select_all">&nbsp;</label>
                                                </div>
                                            </th>
                                            <th style="width: 50px;">#</th>
                                            <th>Name</th>
                                            <th style="width: 100px;">Action</th>
                                        </tr>
                                    </thead>

                                    {{-- <a class="btn btn-success" href="{{ url('/sendNotification') }}"> Send Notification </a> --}}
                                    <tbody>
                                        @php
                                            $i = 1;
                                        @endphp
                                        @foreach ($dataarray as $dd)
                                            @php
                                                $ddid = $dd['id'];
                                            @endphp

                                            <tr>
                                                <td style="padding-left: 10px;">
                                                    <div class="checkbox">
                                                        <input id="check_{{ $ddid }}" name="check_list[]" class="item_checkbox" type="checkbox" value="{{ $ddid }}">
                                                        <label for="check_{{ $ddid }}">&nbsp;</label>
                                                    </div>
                                                </td>
                                                <td>{{ $i++ }}</td>
                                            <td>
                                                <a class="single-user-name" href="#">{{ $dd['name'] }}</a>
                                            </td>
                                            <td>
                                                @if (checkmodulepermission(2, 'can_edit') == 1)
                                                    <button title="Edit" onclick="editdata('{{ $ddid }}')"
                                                        style="all:unset"><i class="zmdi zmdi-edit"></i> </button> &nbsp;
                                                @endif
                                                @if (checkmodulepermission(2, 'can_delete') == 1)
                                                    @if (isExpenseHeadDeletable($ddid))
                                                        <button title="Delete" onclick="deletedata('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-delete"></i> </button>
                                                    @endif
                                                @endif
                                            </td>

                                        </tr>
                                    @endforeach

                                    </tbody>
                                </table>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('models')
    @if (checkmodulepermission(2, 'can_add') == 1)
        <div class="modal fade" id="newexpensehead1" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/addexpensehead') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Add New Expense Head</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="Name">Name</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="text" id="Name" required class="form-control" name="name"
                                            placeholder="Enter the Expense Head Name">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE
                                    CHANGES</a></button>
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
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/delete_expense_head/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function editdata(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Head ?",
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
                    var url = "{{ url('/edit_expense_head/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        $("#select_all").click(function() {
            $('.item_checkbox').prop('checked', this.checked);
            toggleBulkActions();
        });

        $(document).on('change', '.item_checkbox', function() {
            toggleBulkActions();
            if ($('.item_checkbox:checked').length == $('.item_checkbox').length) {
                $('#select_all').prop('checked', true);
            } else {
                $('#select_all').prop('checked', false);
            }
        });

        function toggleBulkActions() {
            var checkedCount = $(".item_checkbox:checked").length;
            if (checkedCount > 0) {
                $("#bulkActions").show();
            } else {
                $("#bulkActions").hide();
            }
        }

        function submitBulkEdit() {
            $("#bulkEditForm").submit();
        }
    </script>
@endsection
