@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Paid Payment Voucher'])

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="header">
                    <h2><strong>Paid Payment Vouchers</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                        <div class="info-content" >Payment voucher which are already paid will be listed here.</div></h2>

                </div>
                <div class="body">
                @if(checkmodulepermission(8,'can_view') == 1)
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
                                        <th>QR Code</th>
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
                                                $payment_image = $dd['payment_image'];
                                                @endphp

                                        
                                                <img class="lazy" data-src="{{ $dd['image'] }}" onclick="enlargeImage('{{$image}}')" height="50px"
                                                    width="50px" />
                                                    <img class="lazy" data-src="{{ $dd['payment_image'] }}" onclick="enlargeImage('{{$payment_image}}')" height="50px"
                                                    width="50px" />

                                            </td>
                                            <td>
                                                @if(!empty($dd['qr_code']))
                                                    <img class="lazy" data-src="{{ $dd['qr_code'] }}" onclick="enlargeImage('{{$dd['qr_code']}}')" height="50px" width="50px" />
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <?php
                                            $ddid = $dd['id'];?>
                                            <td>                                                                          
                                                <a href="{{url('/voucher_pdf/?id='.$ddid)}}" target="_blank" style="all:unset" ><i class="zmdi zmdi-collection-pdf"></i> </a> 
                                                @if(checkmodulepermission(8,'can_pay') == 1)
                                            <button title="Reject" onclick="rejectpaymentvoucher({{ $ddid }})"
                                                style="all:unset"><i class="zmdi zmdi-block"></i> </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach

                                </tbody>


                            </table>
                        
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
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
                text: "This Voucher Is Already Paid. If You Reject This Payment, History Will Be Clear From Party Statement Of This Transaction. Do You Really Want To Reject This Payment Voucher?",
                icon: 'warning',
                showCancelButton: true,
                toast: true,
                position: 'center',
                showConfirmButton: true,
                timer: 10000,
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
                    var url = "{{ url('/reject_Paidpaymentvoucher_by_id/?id=') }}" + id;
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
