@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Verified Bills '])

<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
            <h2><strong>Verified Bills</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                    <div class="info-content" >Bills which are approved or rejected will be listed here.</div></h2>
                 
            </div>
            <div class="body">
            @if(checkmodulepermission(4,'can_view') == 1)
                <div class="row mb-3">
                    <div class="col-md-12">
                    </div>
                </div>
                <div class="table-responsive">
                   
                                    
                        <table id="dataTable" class="table table-hover">
                     
                        <thead>
                            <tr>           
                                <th>#</th>                            
                                <th >Party</th>
                                <th>Bill No</th>
                                <th>Site</th>
                                <th>Bill Date</th>
                                <th>Bill Period</th>
                                <th>User</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Remark</th>
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
                                            {{$dd['party']}}
                                        </td>
                                        <td>
                                            {{$dd['bill_no']}}
                                        </td>
                                        <td>
                                            {{$dd['site']}}
                                        </td>
                                        <td>
                                            {{$dd['billdate']}}
                                        </td>
                                        
                                        <td>
                                            {{$dd['bill_period']}}
                                        </td>
                                        <td>
                                            {{$dd['user']}}
                                        </td>
                                        <td>
                                            {{$dd['location']}}
                                        </td>
                                        <td>
                                            {{$dd['status']}}
                                        </td>
                                        <td>
                                            {{$dd['amount']}}
                                        </td>
                                        <td>
                                            {{$dd['remark']}}
                                        </td>
                                        <td>
                                        <?php
                                        $ddid = $dd['id'];?>
                                   
                                <a title="View" href="{{url('/view_bill/?id='.$ddid)}}" style="all:unset" ><i class="zmdi zmdi-eye"></i> </a>
                                &nbsp; 
                                <a title="PDF" href="{{url('/bill_pdf/?id='.$ddid)}}" style="all:unset" ><i class="zmdi zmdi-collection-pdf"></i> </a>
                                &nbsp; 
                                <?php    if($dd['status'] == 'Approved'):?>
                                    @if(checkmodulepermission(4,'can_certify') == 1)
                                         <button onclick="rejectbill('{{$ddid}}')" title="Reject" style="all:unset" ><i class="zmdi zmdi-block"></i> </button>
                                            @endif
                                         <?php else: ?>
                                            @if(checkmodulepermission(4,'can_certify') == 1)
                                        <button onclick="approvebill('{{$ddid}}')" title="Approve" style="all:unset" ><i class="zmdi zmdi-check-circle"></i> </button>
                                            @endif
                                        &nbsp;
                                        @if(checkmodulepermission(4,'can_edit') == 1)
                                        <button onclick="editbill('{{$ddid}}')" title="Edit" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button>
                                        @endif
                                        <?php endif;?> 

                            </td>
                                       </tr>  
                       @endforeach
                            
                        </tbody>


                    </table>
             
                </div>
                @else
                <div class="alert alert-danger">You Don't Have Permission to View </div>
                @endif
            </div>
        </div>
    </div>
</div>
<script>
         function editbill(id) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Edit This Bill ?",
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
            focusConfirm:true,
            cancelButtonText: 'Cancel',
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/edit_bill/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        function rejectbill(id) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Reject This Bill?",
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
            focusConfirm:true,
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/reject_bill_by_id/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        function approvebill(id) {
         Swal.fire({
            title: 'Are you sure?',
            text: "You Want To Approve This Bill ?",
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
            focusConfirm:true,
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/approve_bill_by_id/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
        </script>
@endsection
