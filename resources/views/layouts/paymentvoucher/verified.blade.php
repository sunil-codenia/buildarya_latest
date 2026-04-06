@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Verified Payment Voucher'])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Verified Payment Vouchers</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content" >Payment voucher which are pending to be paid but verified by authority will be listed here.</div></h2>

                </div>
                <div class="body">
                @if(checkmodulepermission(8,'can_view') == 1)
                    <div class="row mb-3">
                        <div class="col-md-12">
                        </div>
                    </div>
                    <div class="table-responsive">
                         
                            <table id="dataTable" class="table table-hover">

                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Voucher No.</th>
                                        <th>Date</th>
                                        <th>Company</th>
                                        <th>Party Info</th>
                                        <th>Amount</th>
                                        <th>Payment Details</th>
                                        <th>Site</th>
                                        <th>User</th>
                                        <th>Status</th>
                                        <th>Remark</th>
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
                                        <tr>
                                            <td>{{ $i++ }}

                                            </td>
                                            <td>
                                                {{ $dd['voucher_no'] }}
                                            </td>
                                            <td>
                                                {{ $dd['date'] }}
                                            </td>
                                            <td>
                                                {{ $dd['company'] }}
                                            </td>
                                            <td>
                                                @php
                                                    $party = getPaymentVoucherPartyInfo($dd['party_id'], $dd['party_type']);
                                                    echo $party['type'] . ":-<br>" . $party['party_status']->name;
                                                @endphp
                                            </td>

                                            <td>
                                                {{ $dd['amount'] }}
                                            </td>
                                            <td>
                                                {{ $dd['payment_details'] }}
                                            </td>
                                            <td>
                                                {{ $dd['site'] }}
                                            </td>

                                            <td>
                                                Created By - {{ getUserDetailsById($dd['created_by'])->name }}
                                                Verified By - {{ getUserDetailsById($dd['approved_by'])->name }}
                                            </td>
                                            <td>
                                                {{ $dd['status'] }}
                                            </td>
                                            <td>
                                                {{ $dd['remark'] }}
                                            </td>
                                            <td>
                                                @php 
                                                $image = $dd['image'];
                                                @endphp
                                          
                                                <img class="lazy" data-src="{{ $dd['image'] }}" onclick="enlargeImage('{{$image}}')" height="50px"
                                                    width="50px" />
                                            </td>
                                            <td>

                                                <?php
                                                $ddid = $dd['id'];
                                                $ddamount = $dd['amount'];
                                                ?>
                                                <?php
                                              $ddid = $dd['id'];
                                              if($dd['status'] == 'Approved'):?>
                                               @if(checkmodulepermission(8,'can_certify') == 1)
                                                <button title="Reject" onclick="rejectpaymentvoucher({{ $ddid }})"
                                                    style="all:unset"><i class="zmdi zmdi-block"></i> </button>
                                                    @endif
                                                    @if(checkmodulepermission(8,'can_pay') == 1)

                                                    <button title="Wallet" onclick="openpaymentmodel({{ $ddid }},{{$ddamount}})"
                                                    style="all:unset"><i class="zmdi  zmdi-balance-wallet"></i> </button>  
                                                    @endif                           
                                                
                                                <?php else: ?>
                                                    @if(checkmodulepermission(8,'can_certify') == 1)
                                                <button title="Approve" onclick="approvepaymentvoucher('{{ $ddid }}')"
                                                    style="all:unset"><i class="zmdi zmdi-check-circle"></i> </button>
                                                    @endif
                                                &nbsp;
                                                @if(checkmodulepermission(8,'can_edit') == 1)
                                                <button title="Edit" onclick="edit_paymentvoucher('{{ $ddid }}')"
                                                    style="all:unset"><i class="zmdi zmdi-edit"></i> </button>
                                                @endif

                                                <?php endif;?>


                                                &nbsp;                                 
                                                <a href="{{url('/voucher_pdf/?id='.$ddid)}}" target="_blank" style="all:unset" ><i class="zmdi zmdi-collection-pdf"></i> </a> 
                                            </td>
                                        </tr>
                                    @endforeach

                                </tbody>


                            </table>
                        
                    </div>
                    @else
            <div class="alert alert-danger"> You Don't Have Permission to View </div>
            @endif
                </div>
            </div>
        </div>
    </div>
    @endsection
    @section('models')

    @if(checkmodulepermission(8,'can_pay') == 1)
<div class="modal fade" id="addpaymentmodel" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <form action="{{url('/addpaymentvoucherpayment')}}" method="post" enctype="multipart/form-data" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add Payment Voucher Payment</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                            <label for="Name">Amount</label>
                        </div>
                        <div class="col-lg-8 col-md-8 col-sm-8">
                            <div class="form-group">
                                <input type="hidden" id="pv_id" name="id"/>
                                <input type="text" disabled id="pv_amount" required class="form-control" name="amount" placeholder="Enter the Expense Head Name">
                            </div>
                        </div>
                    </div>
                    <div class="row clearfix">
                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                            <label for="Name">Payment details</label>
                        </div>
                        <div class="col-lg-8 col-md-8 col-sm-8">
                            <div class="form-group">
                                <input type="text"  id="pv_details" required class="form-control" name="payment_details" placeholder="Enter the Payment Details">
                            </div>
                        </div>
                    </div>
                    <div class="row clearfix">
                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                            <label for="Name">Payment Date</label>
                        </div>
                        <div class="col-lg-8 col-md-8 col-sm-8">
                            <div class="form-group">
                                <input type="date"  id="pv_date" required class="form-control" name="payment_date" >
                            </div>
                        </div>
                    </div>
                    <div class="row clearfix">
                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                            <label for="Name">Attachment</label>
                        </div>
                        <div class="col-lg-8 col-md-8 col-sm-8">
                            <div class="form-group">
                                <input type="file"  id="pv_image"  class="form-control" name="image" >
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-simple waves-effect" data-dismiss="modal"><a>CLOSE</a></button>
                    <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE CHANGES</a></button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
    @section('scripts')
    <script>
        function edit_paymentvoucher(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Edit This Payment Voucher ?",
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
                    var url = "{{ url('/edit_paymentvoucher/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function rejectpaymentvoucher(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Reject This Payment Voucher?",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#ff0000',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Reject',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/reject_paymentvoucher_by_id/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }

        function approvepaymentvoucher(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You Want To Approve This Payment Voucher ?",
                icon: 'success',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 8000,
                timerProgressBar: true,
                confirmButtonColor: '#17ce0a',
                cancelButtonColor: '#000000',
                confirmButtonText: 'Approve',
                cancelButtonText: 'Cancel',
                customClass: {
                    container: 'model-width-450px'
                },
            }).then((result) => {
                if (result.isConfirmed) {
                    var url = "{{ url('/approve_paymentvoucher_by_id/?id=') }}" + id;
                    window.location.href = url;
                }
            });
        }
        function openpaymentmodel(id, amount) {
            $('#addpaymentmodel').modal();
            $('#pv_id').val(id);
            $('#pv_amount').val(amount);
        }
    </script>
@endsection
