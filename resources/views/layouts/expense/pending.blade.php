@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Pending Expenses '])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Pending Expenses</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content">Expenses which are pending for approval will be listed here.</div>
                    </h2>

                </div>
                <div class="body">
                    @if (checkmodulepermission(2, 'can_view') == 1)
                        <div class="table-responsive">
                            <form action="{{ url('/updateExpenses') }}" method="POST">
                                @csrf
                                <div class="align-right">
                                    @if (checkmodulepermission(2, 'can_certify') == 1)
                                        <button type="submit" name="approve_expense" value="approve_expense"
                                            class="btn btn-success btn-simple btn-round waves-effect"><a>Approve</a></button>
                                    @endif
                                    @if (checkmodulepermission(2, 'can_certify') == 1)
                                        <button type="submit" name="reject_expense" value="reject_expense"
                                            class="btn btn-danger btn-simple btn-round waves-effect"><a>Reject</a></button>
                                    @endif
                                    @if (checkmodulepermission(2, 'can_edit') == 1)
                                        <button type="submit" formaction="{{ url('/pending_expense/bulk_edit_expense') }}"
                                            class="btn btn-warning btn-simple btn-round waves-effect"><a>Edit</a></button>
                                    @endif
                                </div>
                                <table id="dataTable" class="table table-hover">

                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="select_all"></th>
                                            <th>#</th>
                                            <th>Party</th>
                                            <th>Head</th>
                                            <th>Particular</th>
                                            <th>Amount</th>

                                            <th>Site</th>
                                            <th>User</th>
                                         
                                            <th>Location</th>
                                            <th>Status</th>
                                            <th>Remark</th>
                                            <th>Date</th>
                                            <th>Image</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @php
                                            
                                            $dataarray = json_decode($data, true);
                                            $i = 1;
                                        @endphp
                                        @foreach ($dataarray as $dd)
                                            @php
                                                $ddid = $dd['id'];
                                            @endphp
                                            <tr>
                                                <td><input type="checkbox" name="check_list[]" class="check_item" value="{{ $dd['id'] }}" onclick="event.stopPropagation()"></td>
                                                <td>{{ $i++ }}</td>
                                                <td>
                                                   {{getExpensePartyNameByPartyType($dd['party_id'],$dd['party_type'])}}
                                                </td>
                                                <td>
                                                    {{ $dd['head'] }}
                                                </td>
                                                <td>
                                                    {{ $dd['particular'] }}
                                                </td>
                                                <td>
                                                    {{ $dd['amount'] }}
                                                </td>

                                                <td>
                                                    {{ $dd['site'] }}
                                                </td>
                                                <td>
                                                    {{ $dd['user'] }}
                                                </td>
                                                <td>
                                                    {{ $dd['location'] }}
                                                </td>
                                                <td>
                                                    {{ $dd['status'] }}
                                                </td>
                                                <td>
                                                    {{ $dd['remark'] }}
                                                </td>
                                                <td>
                                                    {{ $dd['date'] }}
                                                </td>
                                                <td>
                                                    @php
                                                        $image = $dd['image'];
                                                    @endphp
                                                    <img class="lazy" data-src="{{ $dd['image'] }}"
                                                        onclick="enlargeImage('{{ $image }}')" height="50px"
                                                        width="50px" />

                                                </td>
                                                <td>
                                                    @if (is_asset_head($dd['head_id']) || is_machinery_head($dd['head_id']))
                                                        @if (is_asset_head($dd['head_id']))
                                                            @if (!empty($dd['asset_head']))
                                                                Asset Category -
                                                                {{ getAssetHeadsById($dd['asset_head'])->name }}<br>
                                                                 {{-- Checkbox removed --}}
                                                            @endif

                                                            @if (checkmodulepermission(2, 'can_certify') == 1)
                                                                <button type="button"
                                                                    onclick="openassignassetheadmodel('{{ $ddid }}')"
                                                                    style="all:unset"><i class="zmdi  zmdi-wrench"></i>
                                                                </button>
                                                            @endif
                                                        @elseif(is_machinery_head($dd['head_id']))
                                                            @if (is_machinery_head($dd['head_id']) && !empty($dd['machinery_head']))
                                                                Machinery Category -
                                                                {{ getMachineryHeadsById($dd['machinery_head'])->name }}<br>
                                                                 {{-- Checkbox removed --}}
                                                            @endif
                                                            @if (checkmodulepermission(2, 'can_certify') == 1)
                                                                <button type="button"
                                                                    onclick="openassignmachineryheadmodel('{{ $ddid }}')"
                                                                    style="all:unset"><img
                                                                        src="{{ asset('/images/gears.png') }}"
                                                                        style="width:20px" /> </button>
                                                            @endif
                                                        @endif
                                                    @else
                                                     {{-- Checkbox removed --}}
                                                    @endif
                                                    &nbsp;
                                                    <?php
                                                    $ddid = $dd['id'];
                                                    ?>
                                                    @if (checkmodulepermission(2, 'can_edit') == 1)
                                                        <button title="Edit" type="button" onclick="editexpense('{{ $ddid }}')"
                                                            style="all:unset"><i class="zmdi zmdi-edit"></i> </button>
                                                    @endif

                                                </td>
                                            </tr>
                                        @endforeach

                                    </tbody>


                                </table>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@section('models')
    @if (checkmodulepermission(2, 'can_certify') == 1)
        <div class="modal fade" id="assignassethead" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/updateexpenseAssetHead') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Assign Asset Head To Expense</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="email_address_2">Asset Head</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="hidden" name="asset_head_expense_id" id="asset_head_expense_id"
                                            required>
                                        <select name="asset_head" class="form-control show-tick" data-live-search="true"
                                            required>
                                            <option value="" selected disabled>--Select Head--</option>
                                            @php
                                                $heads = getAssetHeads();
                                            @endphp
                                            @foreach ($heads as $head)
                                                <option value="{{ $head->id }}">{{ $head->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>


                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit"
                                class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
    @if (checkmodulepermission(2, 'can_certify') == 1)
        <div class="modal fade" id="assignmachineryhead" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-md" role="document">
                <form action="{{ url('/updateexpenseMachineryHead') }}" method="post" class="form">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="title">Assign Machinery Head To Expense</h4>
                        </div>
                        <div class="modal-body">
                            <div class="row clearfix">
                                <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                                    <label for="email_address_2">Machinery Head</label>
                                </div>
                                <div class="col-lg-8 col-md-8 col-sm-8">
                                    <div class="form-group">
                                        <input type="hidden" name="machinery_head_expense_id"
                                            id="machinery_head_expense_id" required>
                                        <select name="machinery_head" class="form-control show-tick"
                                            data-live-search="true" required>
                                            <option value="" selected disabled>--Select Head--</option>
                                            @php
                                                $heads = getMachineryHeads();
                                            @endphp
                                            @foreach ($heads as $head)
                                                <option value="{{ $head->id }}">{{ $head->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>


                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary btn-simple waves-effect"
                                data-dismiss="modal"><a>CLOSE</a></button>
                            <button type="submit"
                                class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

@endsection
@section('scripts')
    <script>
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
        });

        function editexpense(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Expense ?",
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
                    var url = "{{ url('/edit_expense/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }



        function openassignassetheadmodel(id) {
            $('#asset_head_expense_id').val(id);
            $('#assignassethead').modal();
        }

        function openassignmachineryheadmodel(id) {
            $('#machinery_head_expense_id').val(id);
            $('#assignmachineryhead').modal();
        }
    </script>
@endsection
