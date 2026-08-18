@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Pending Payment Voucher'])

<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
                <h2><strong>Pending Payment Vouchers</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                    <div class="info-content" >Payment voucher which are pending for verification will be listed here.</div></h2>
    </h2>
</div>
            @if(checkmodulepermission(8,'can_view') == 1)
            <div class="body">
            @if(checkmodulepermission(8,'can_edit') == 1)
                <div class="table-responsive">
                    <form action="{{url('/updatepaymentvouchers')}}" method="POST">
                        @csrf
                        <div class="align-right">
                        @if(checkmodulepermission(8,'can_certify') == 1)
                        <button type="submit" name="approve_paymentvoucher" value = "approve_paymentvoucher" class="btn btn-success btn-simple btn-round waves-effect"><a>Approve</a></button>
                        <button type="submit" name="reject_paymentvoucher"  value="reject_paymentvoucher" class="btn btn-danger btn-simple btn-round waves-effect"><a>Reject</a></button>
                        @endif    
                    </div>                  
                        <table id="dataTable" class="table table-hover">
                     
                        <thead>
                            <tr>           
                                <th>#</th>        
                                <th >Voucher No.</th>    
                                <th >Date</th>
                                <th >Company</th>                 
                                <th >Party Info</th>
                                <th>Amount</th>
                                <th>Payment Details</th>
                                <th>Site</th>
                                <th>User</th>
                                <th>Remark</th>
                                <th>Image</th>
                                <th>QR Code</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                       
                        <tbody>
                            @php
                    
                          $dataarray = json_decode($data, true);
                          $i=1;
                          @endphp
                          @foreach($dataarray as $dd)
                                         
                          <tr>
                                       <td>{{$i++}}
                                        
                                    </td>
                                        <td>
                                            {{$dd['voucher_no']}}
                                        </td>
                                        <td>
                                            {{$dd['date']}}
                                        </td>
                                        <td>
                                            {{$dd['company']}}
                                        </td>
                                        <td>
                                            @php
                                            $party = getPaymentVoucherPartyInfo( $dd['party_id'], $dd['party_type']);
                                            echo $party['type'] . ":-<br>" . $party['party_status']->name;
                                            @endphp
                                        </td>  
                                        <td>
                                            {{$dd['amount']}}
                                        </td>
                                        <td>
                                            {{$dd['payment_details']}}
                                        </td>
                                        <td>
                                            {{$dd['site']}}
                                        </td>
                                        
                                        <td>
                                           Created By -  {{getUserDetailsById($dd['created_by'])->name}}
                                        </td>
                                        <td>
                                            {{$dd['remark']}}
                                        </td>
                                        <td>
                                            @php 
                                            $image = $dd['image'];
                                            @endphp
                                      
                                            <img class="lazy" data-src="{{$dd['image']}}" height="50px" onclick="enlargeImage('{{$image}}')" width="50px" />
                                        </td>
                                        <td>
                                            @if(!empty($dd['qr_code']))
                                                <img class="lazy" data-src="{{$dd['qr_code']}}" height="50px" onclick="enlargeImage('{{$dd['qr_code']}}')" width="50px" />
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <input type="checkbox" name="check_list[]" value="{{$dd['id']}}"> 
                                            &nbsp;
                                            <?php
                                            $ddid = $dd['id'];
                                            ?>
                                             @if(checkmodulepermission(8,'can_edit') == 1)
                                            <button title="Edit" type="button" onclick="edit_paymentvoucher('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button>
                                            @endif 
                                            &nbsp;                                 
                                            <a href="{{url('/voucher_pdf/?id='.$ddid)}}" target="_blank" style="all:unset" ><i class="zmdi zmdi-collection-pdf"></i> </a> 
                                        </td>
                                       </tr>  
                       @endforeach
                            
                        </tbody>
                    </table>
                </form>
                </div>
                @else
            <div class="alert alert-danger"> You Don't Have Permission to Edit </div>
            @endif
            </div>
            @else
            <div class="alert alert-danger"> You Don't Have Permission to View </div>
            @endif
        </div>
    </div>
</div>
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
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/edit_paymentvoucher/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        </script>
@endsection