@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Pending Bills '])

<div class="row clearfix">
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
                <h2><strong>Pending Bills</strong> List&nbsp;<i class="zmdi zmdi-info info-hover"></i>
                    <div class="info-content" >Bills which are pending or approval will be listed here.</div></h2>
                
            </div>
            <div class="body">
            @if(checkmodulepermission(4,'can_edit') == 1)
                <div class="table-responsive">
                    <form action="{{url('/updateBill')}}" method="POST">
                        @csrf
                        <div class="align-right">
                        @if(checkmodulepermission(4,'can_certify') == 1)
                        <button type="submit" name="approve_bill" value = "approve_expense" class="btn btn-success btn-simple btn-round waves-effect"><a>Approve</a></button>
                        <button type="submit" name="reject_bill"  value="reject_expense" class="btn btn-danger btn-simple btn-round waves-effect"><a>Reject</a></button>
                        @endif    
                    </div>                  
                        <table id="dataTable" class="table table-hover">
                     
                        <thead>
                            <tr>           
                                <th><input type="checkbox" id="select_all" onclick="selectAll(this)"></th>
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
                                            <input type="checkbox" name="check_list[]" class="check_item" value="{{$dd['id']}}" onclick="updateSelectAll()"> 
                                            &nbsp;
                                            <?php
                                            $ddid = $dd['id'];
                                            ?>
                                             <a title="View" href="{{url('/view_bill/?id='.$ddid)}}" style="all:unset" ><i class="zmdi zmdi-eye"></i> </a>
                                             &nbsp; 
                                             <a title="PDF" href="{{url('/bill_pdf/?id='.$ddid)}}" style="all:unset" ><i class="zmdi zmdi-collection-pdf"></i> </a>
                                             &nbsp; 
                                             @if(checkmodulepermission(4,'can_edit') == 1)
                                            <button title="Edit" type="button" onclick="editbill('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button>
                                            @endif
                                        </td>
                                       </tr>  
                       @endforeach
                            
                        </tbody>


                    </table>
                </form>
                </div>
                @else
                <div class="alert alert-danger">You Don't Have Permission to View</div>
                @endif
            </div>
        </div>
    </div>
</div>
<script>
        function selectAll(source) {
            var checkboxes = document.getElementsByClassName('check_item');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }

        function updateSelectAll() {
            var source = document.getElementById('select_all');
            var checkboxes = document.getElementsByClassName('check_item');
            var allChecked = true;
            if (checkboxes.length == 0) allChecked = false;
            for (var i = 0; i < checkboxes.length; i++) {
                if (!checkboxes[i].checked) {
                    allChecked = false;
                    break;
                }
            }
            source.checked = allChecked;
        }

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
        </script>
@endsection