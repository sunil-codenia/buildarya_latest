@extends('app')
@section('content')
@include('templates.blockheader', ['pagename' => 'Machineries List'])
@php
$edit=false;
$dataarray = json_decode($data, true);
                            if(isset(json_decode($data, true)['edit_data'])){
                            $editdata = $dataarray['edit_data'][0];
                            $edit=true;
                            }
@endphp

@php 
    $heads = getallMachineryHeads();
    $sites = getallActivesites();
@endphp
<div class="row clearfix">
@if($edit)

    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
        @if(checkmodulepermission(6,'can_edit') == 1)

        <form action="{{url('/updatemachineryhead')}}" method="post" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Edit machinery List</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                            <label for="Name">Name</label>
                        </div>
                        <div class="col-lg-8 col-md-8 col-sm-8">
                            <div class="form-group">
                                <input type="hidden" name="id" value="{{$editdata['id']}}">
                                <input type="text" id="Name" required class="form-control" value="{{$editdata['name']}}" name="name" placeholder="Enter the machinery Head Name">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>Update</a></button>
                </div>
            </div>
        </form>
        @endif
        </div>

    </div>
    <br>
    @endif
    <div class="col-md-12 col-sm-12 col-xs-12">
        <div class="card project_list">
            <div class="header">
                <h2><strong>Machinery</strong> List</h2>

                <ul class="header-dropdown" style="    display: flex;align-items: center;">
                    <li>
                        @if (checkmodulepermission(5, 'can_view') == 1)
                            <div class="card" style="width: auto;">
                                <div class="body" style="    text-align-last: center;">
                                    @if ($showing_data == 'all')
                                        Showing Machinery Of :- <u>Whole Company</u>
                                    @else
                                        @php $sitedetail = getSiteDetailsById($showing_data)->name;@endphp
                                        Showing Machinery Of :- <u>{{ $sitedetail }}</u>
                                    @endif
                                    <hr>
                                    <form method="post" action="{{ url('/search_machinery_head_sites') }}">
                                        @php
                                            $sites = getallsites();
                                        @endphp
                                        @csrf
                                        <select class="form-control show-tick" data-live-search="true" required
                                            name="display_site">
                                            <option selected value=""> Change Display Site</option>
                                            @foreach ($sites as $dd)
                                                <option value="{{ $dd->id }}">{{ $dd->name }}</option>
                                            @endforeach
                                        </select>

                                        <button type="submit" class="btn btn-primary  btn-round waves-effect"
                                            style="color:white !important;">Search</button>

                                    </form>

                                </div>

                            </div>
                        @endif
                    </li>
                    <li>

                        @if ($showing_data != 'all')
                        @if (checkmodulepermission(5, 'can_view') == 1)
                            <a href="{{ url('/machinery_head') }}" type="button"
                                class="btn btn-primary  btn-round waves-effect" style="color:white !important;">Show
                                All Machineries</a>
                        @endif
                    @endif
                    @if(checkmodulepermission(6,'can_add') == 1)
                    <button class="btn btn-primary" data-toggle="modal" data-target="#newmachinery" type="button">
                        <span class=" text-white"> Add New Machinery </span> 
                    </button>
                    @endif


                  

                    @if(checkmodulepermission(6,'can_add') == 1)
                        <button class="btn btn-primary " data-toggle="modal" data-target="#newmachineryhead1" type="button">
                            
                            <span class=" text-white"> Add Machinery Head </span>
                        </button>
                        @endif
                    </li>
                </ul>
            </div>
            <br><br> <br><br>
            <div class="body">
            @if(checkmodulepermission(6,'can_view') == 1)
            @if ($showing_data == 'all')
                <div class="table-responsive">
                    <table id="dataTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $i=1;
                            @endphp
                            @foreach($dataarray as $dd)
                            @php
                            $ddid = $dd['id'];
                            @endphp

                            <tr>
                                <td>{{$i++}}</td>
                                <td>
                                    <a class="single-user-name" href="#">{{$dd['name']}}</a>
                                </td>
                                <td>
                                    <a class="single-user-name" href="#">{{getmachineryHeadUsageCount($dd['id'])}}</a>
                                </td>
                                
                                <td>
                                @if(checkmodulepermission(6,'can_view') == 1)
                                        <a title="View" href="{{url('/machinery/?machinery_id=').$ddid}}" ><i class="zmdi zmdi-eye"></i>  </a>&nbsp;
                                @endif
                                @if(checkmodulepermission(6,'can_edit') == 1)                     
                                    <button title="Edit" onclick="editdata('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-edit"></i> </button> &nbsp;
                                    @endif
                                @if(checkmodulepermission(6,'can_delete') == 1)
                                    <button title="Delete" onclick="deletedata('{{$ddid}}')" style="all:unset" ><i class="zmdi zmdi-delete"></i> </button>
                                @endif
                                </td>

                            </tr>
                            @endforeach

                        </tbody>
                        <tbody>
                          

 
                    </table>
                </div>
                @else
                <div class="table-responsive">
                    <table id="dataTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Head</th>
                                <th>Cost Price</th>
                                <th>Site Name</th>
                                <th>Status</th>
                                <th>Next Service On</th>
                                <th>At Current Site Since</th>
                                <th>Purchase Date</th>
                                <th>Action</th>

                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $i = 1;
                                $data = json_decode($data, true);
                            @endphp

                            @foreach ($data as $dd)
                                @if (!empty($dd))
                                    @php
                                        $ddid = $dd['id'];
                                    @endphp

                                    <tr>
                                        <td>{{ $i++ }}</td>
                                        <td>
                                            {{ $dd['name'] }}
                                        </td>
                                        <td>
                                            {{ $dd['head'] }}
                                        </td>
                                        <td>
                                            @if ($dd['status'] == 'Sold')
                                                Cost Price - {{ $dd['cost_price'] }}<br>
                                                Sale Price - {{ $dd['sale_price'] }}
                                            @else
                                                {{ $dd['cost_price'] }}
                                            @endif


                                        </td>
                                        <td>
                                            {{ $dd['site'] }}
                                        </td>
                                        <td>
                                            {{ $dd['status'] }}
                                        </td>
                                        <td>
                                            @if ($dd['status'] != 'Sold')
                                                <a class="single-user-name"
                                                    href="#"><?= getMachineryNextService($dd['id']) ?></a>
                                            @else
                                                Already sold
                                            @endif
                                        </td>
                                        <td>
                                            @if ($dd['status'] != 'Sold')
                                                <a class="single-user-name"
                                                    href="#">{{ getmachineryLastTransfer($dd['id'])->create_datetime }}</a>
                                            @else
                                                Already sold
                                            @endif
                                        </td>
                                        <td>
                                            @if ($dd['status'] == 'Sold')
                                                Purchase - {{ $dd['create_datetime'] }}<br>
                                                Sold - {{ getmachineryLastTransfer($dd['id'])->create_datetime }}
                                            @else
                                                {{ $dd['create_datetime'] }}
                                            @endif
                                        </td>

                                        <td>
                                        @if(checkmodulepermission(6,'can_edit') == 1)
                                            <a href="{{ url('/machineryTransferHistory/?machinery_id=') . $ddid }}"><img
                                                    src="{{ asset('/images/transfer_history.png') }}"
                                                    style="width:20px" /></a>&nbsp;
                                                    <a href="{{ url('/machineryDocuments/?machinery_id=') . $ddid }}"><img
                                                        src="{{ asset('/images/document.png') }}"
                                                        style="width:20px" /></a>&nbsp;
                                                @endif

                                            @if ($dd['status'] != 'Sold')
                                            @if(checkmodulepermission(6,'can_edit') == 1)
                                                <button
                                                    onclick="transfermachinerymodel({{ $ddid }}, {{ $dd['site_id'] }},{{ $dd['head_id'] }})"
                                                    title="Transfer machinery" style="all:unset"><img
                                                        src="{{ asset('/images/transfer.png') }}" style="width:20px" />
                                                </button> &nbsp;
                                                <a href="{{ url('/machineryService/?machinery_id=') . $ddid }}"><img
                                                    src="{{ asset('/images/service.png') }}"
                                                    style="width:20px" /></a>&nbsp;
                                                <button type="button" title="Sell machinery"
                                                    onclick="soldmachinerymodel('{{ $ddid }}', {{ $dd['site_id'] }},{{ $dd['head_id'] }})"
                                                    style="all:unset"><img src="{{ asset('/images/sold.png') }}"
                                                        style="width:30px" />
                                                </button>
                                                @endif
                                            @endif

                                        </td>

                                    </tr>
                                @endif
                            @endforeach

                        </tbody>

                       
                                       
                                       

                    </table>
                </div>
                @endif
                @else 
                <div class="alert alert-danger">You Don't Have Permission To View</div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@section('models')


   
@if(checkmodulepermission(6,'can_add') == 1)
<div class="modal fade" id="newmachinery" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{url('/add_newmechinery')}}" method="post" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add New machinery</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <label for="Site Name"> Site Name </label>
                            <div class="form-group">
                            <select name="site_id" id="site_id" class="form-control show-tick"
                                data-live-search="true" required>
                                <option value="" selected disabled>--Select Site--</option>
                                 @foreach ($sites as $site)
                                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                                 @endforeach
                            
                            
                            </select>
                            </div>
                        </div>

                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <label for="Site Name"> Head Name </label>
                            <div class="form-group">
                            <select name="head_id" id="head_id" class="form-control show-tick"
                                data-live-search="true" required>
                                <option value="" selected disabled>--Select Head --</option>
                                @foreach ($heads as $head)
                                            <option value="{{ $head->id }}">{{ $head->name }}</option>
                                 @endforeach
                               
                            </select>
                            </div>
                        </div>


                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Name</label>
                                <input type="text" name="macname" id="macname" required class="form-control"  placeholder="Enter the machinery Name ">
                            </div>
                        </div>
                        <div class="col-lg-4 col-md-4 col-sm-4">
                            <div class="form-group">
                            <label for="Name">Cost Price </label>
                                <input type="text" id="costprice" required class="form-control" name="costprice" placeholder="Enter Cost Price ">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary btn-simple waves-effect" data-dismiss="modal"><a>CLOSE</a></button>
                    <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a>SAVE</a></button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif





@if(checkmodulepermission(6,'can_add') == 1)
<div class="modal fade" id="newmachineryhead1" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <form action="{{url('/addmachineryhead')}}" method="post" class="form">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="title">Add New machinery Head</h4>
                </div>
                <div class="modal-body">
                    <div class="row clearfix">
                        <div class="col-lg-2 col-md-2 col-sm-4 form-control-label">
                            <label for="Name">Name</label>
                        </div>
                        <div class="col-lg-8 col-md-8 col-sm-8">
                            <div class="form-group">
                                <input type="text" id="Name" required class="form-control" name="name" placeholder="Enter the machinery Head Name">
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
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/delete_machinery_head/?id=')}}" + id;
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
            customClass:{
                container: 'model-width-450px'
            },
        }).then((result) => {
            if (result.isConfirmed) {
                var url = "{{url('/edit_machinery_head/?id=')}}" + id;
                window.location.href = url;
            }
        });
        }
</script>

@endsection