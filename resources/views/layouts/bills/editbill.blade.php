@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Edit Bill Entry'])
    @php
        
        $data = json_decode($data, true);
        $sites = $data['sites'];
        $bill_parties = $data['bill_parties'];
        $bill = $data['bill'];
        $bill_items = $data['bill_items'];
        $daterange = explode(' to ', $bill['bill_period']);
        
        $site_id = session()->get("site_id");
$role_details = getRoleDetailsById(session()->get('role'));
$entry_at_site = $role_details->entry_at_site;
$add_duration = session()->get('add_duration');
$duration = getdurationdates($add_duration);
$today = substr($duration['today'], 0, 10);
$min_date = substr($duration['min'], 0, 10);
$max_date = substr($duration['max'], 0, 10);

    @endphp

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
                <div class="modal-content">
                @if(checkmodulepermission(4,'can_view') == 1)
                @if(checkmodulepermission(4,'can_edit') == 1)
                    <div class="modal-body">
                        <form method="post" action="{{ url('/updateEditBill') }}" enctype="multipart/form-data">
                            @csrf
                            <hr>
                            <div class="row clearfix">

                                <div class="col-lg-12 col-md-12 col-sm-12">
                                    <div class="row clearfix">
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <input type="hidden" name="id" value="{{ $bill['id'] }}" />
                                                <label>Site &nbsp;<sub style="color:red;">Alert : Changing Site Will Remove
                                                        All Bill Items</sub></label>
                                                <select name="bill_site_id" onchange="bill_site_change()" id="bill_site_id"
                                                class="form-control show-tick" data-live-search="true" required>
                                                    <option value="" >--Select Site--</option>
                                             

                                                    @if ($entry_at_site == 'current')
                                    <option selected value="{{ $site_id }}">
                                       {{ getSiteDetailsById($site_id)->name }}
                                    </option>
                                    @else
                                    @foreach ($sites as $site)
                                    @if($bill['site_id'] == $site['id'])
                                    <option selected value="{{$site['id']}}">{{$site['name']}}</option>
                                    @else
                                    <option value="{{$site['id']}}">{{$site['name']}}</option>
                                    @endif
                                    @endforeach
                                    @endif

                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label>Bill Party</label>
                                                <select name="bill_party_id" id="bill_party_id" class="form-control show-tick" data-live-search="true"
                                                    required>
                                                    <option value="" disabled>--Select Bill Party--</option>
                                                    @foreach ($bill_parties as $party)
                                                        @if ($party['id'] == $bill['party_id'])
                                                            <option selected value="{{ $party['id'] }}">
                                                                {{ $party['name'] }}</option>
                                                        @else
                                                            <option value="{{ $party['id'] }}">{{ $party['name'] }}
                                                            </option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label>Bill No.</label>
                                                <input type="text" required class="form-control" name="bill_no"
                                                    value="{{ $bill['bill_no'] }}" placeholder="Enter The Bill No">

                                            </div>
                                        </div>
                                        <div class="col-lg-4  col-md-4  col-sm-4   ">
                                            <div class="form-group">
                                                <label>Date</label>
                                                <input type="date" required class="form-control"
                                                    min="{{ $min_date }}" max="{{ $max_date }}"
                                                    value="{{ $bill['billdate'] }}" name="bill_date">
                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label>From Date</label>
                                                <input type="date" required class="form-control"
                                                    value="{{ $daterange[0] }}" name="bill_from_date">

                                            </div>
                                        </div>
                                        <div class="col-lg-4 col-md-4 col-sm-4">
                                            <div class="form-group">
                                                <label>To Date</label>
                                                <input type="date" required class="form-control"
                                                    value="{{ $daterange[1] }}" name="bill_to_date">
                                            </div>
                                        </div>

                                    </div>

                                </div>
                            </div>
                            <hr>
                            <div class="row clearfix">
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group" id="item_group">
                                        <label>Item</label>
                                        <select id="item_id" onchange="itemchange()" class="form-control show-tick" data-live-search="true">
                                            <option value="" selected disabled>--Select Item--</option>
                                        </select>

                                    </div>

                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Quantity</label>
                                        <input type="text" class="form-control" id="qty"
                                            placeholder="Enter The Qty">

                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <label>Rate</label>
                                        <input type="text" disabled id="rate" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-2 col-sm-2">
                                    <div class="form-group">
                                        <label>Unit</label>
                                        <input type="text" disabled id="unit" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-lg-1 col-md-1 col-sm-1" style="align-self: center;">

                                    <button type="button" id="addrow" onclick="addnewrow()"
                                        class="btn btn-primary btn-simple btn-round waves-effect"><a>+</a></button>
                                </div>



                            </div>
                            <div id="rowData">

                            </div>


                            <hr>

                            <div class="row clearfix">
                                <div class="col-lg-6 col-md-6 col-sm-6">
                                    <div class="form-group">
                                        <label>Notes & Remark</label>
                                        <textarea id="remark" name="remark" class="form-control" placeholder="Notes & Remark">{{ $bill['remark'] }}</textarea>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-md-6 col-sm-6" style="align-self:center;">

                                    <div id="totaltablebody" style="text-align:center;"></div>
                                </div>

                            </div>
                            <hr>
                            <div class="row clearfix">
                                <div class="col-lg-9 col-md-9 col-sm-9">
                                </div>
                                <div class="col-lg-3 col-md-3 col-sm-3">
                                    <div class="form-group">
                                        <button type="submit"
                                            class="btn btn-primary btn-simple btn-round waves-effect"><a>Submit</a></button>
                                    </div>
                                </div>
                            </div>
                        </form>

                    </div>
                    @endif
                    @endif
                </div>

            </div>
        </div>
    </div>

    <script type="text/javascript">
        var work = new Array();
        var totalbasicrate = 0;
        const itemarray = new Array();

        function addnewrow() {
            const map1 = new Map();
            var itemtext = $('#item_id').find(":selected").text();
            var item = $('#item_id').val();
            var qty = $('#qty').val();
            var rate = $('#rate').val();
            var unit = $('#unit').val();
            if (checkitemexist(item)) {
                alert("Item already exists");
            } else {
                map1.set("itemtext", itemtext);
                map1.set("item", item);
                map1.set("qty", qty);
                map1.set("unit", unit);
                map1.set("rate", rate);
                if (item != "" && qty != "" && rate != "" && unit != "") {
                    itemarray.push(map1);
                    totalbasicrate += (rate.valueOf() * qty.valueOf());
                    getitemtablebody(itemarray);
                    $('#qty').val('');
                    $('#unit').val('');
                    $('#rate').val('');
                } else {
                    alert("Please Enter Complete Details.");
                }
            }
        }

        function checkitemexist(item_id) {
            itemarray.forEach(myFunction);
            var flag;

            function myFunction(item, index, arr) {
                if (item.get("item") === item_id) {

                    flag = true;
                }
            }

            return flag;
        }

        function bill_site_change() {
            var bill_site_id = $('#bill_site_id').find(":selected").val();
            var url = "{{ url('/getsitebillworks') }}";

            $.ajax({
                url: url,
                dataType: "JSON",
                method: 'GET',
                data: {
                    bill_site_id: bill_site_id
                },
                success: function(response) {
                    work = response.works;
                    var result = "";
                    result +=
                        "<label>Item</label><select onchange='itemchange()' id='item_id' class='select2 form-control show-tick'  data-live-search='true' >";
                    result += "<option selected disabled>--Select Item--</option>";
                    work.forEach(element => {
                        result += "<option value=\"" + element['id'] + "\">" + element['name'] +
                            " </option>";
                    });

                    result += "</select>";
                    $('#item_group').html(result);
                    itemarray.length = 0;
                    getitemtablebody(itemarray);
                    totalbasicrate = 0;
                    gettotaltablebody();
                    $("#item_id").selectpicker({
         liveSearch: true
      });
                },
                error: function(request, error) {
                    console.log(request); // server error

                }
            });
        }

        function fetch_bill_items_from_php(bill_site_id) {
            var url = "{{ url('/getsitebillworks') }}";

            $.ajax({
                url: url,
                dataType: "JSON",
                method: 'GET',
                data: {
                    bill_site_id: bill_site_id
                },
                success: function(response) {
                    work = response.works;
                    var result = "";
                    result +=
                        "<label>Item</label><select onchange='itemchange()' id='item_id' class='select2 form-control show-tick'  data-live-search='true' >";
                    result += "<option selected disabled>--Select Item--</option>";
                    work.forEach(element => {
                        result += "<option value=\"" + element['id'] + "\">" + element['name'] +
                            " </option>";
                    });

                    result += "</select>";
                    $('#item_group').html(result);
                    $("#item_id").selectpicker({
         liveSearch: true
      });
                },
                error: function(request, error) {
                    console.log(request); // server error

                }
            });
        }

        function itemchange() {
            var bill_site_id = $('#bill_site_id').find(":selected").val();
            var work_id = $('#item_id').find(":selected").val();
            var url = "{{ url('/getsitebillworkrates') }}";
            $.ajax({
                url: url,
                dataType: "JSON",
                method: 'GET',
                data: {
                    bill_site_id: bill_site_id,
                    bill_work_id: work_id
                },
                success: function(response) {
                    work = response.workdata;
                    $('#rate').val(work[0].rate);
                    $('#unit').val(work[0].unit);
                },
                error: function(request, error) {
                    console.log(request); // server error
                }
            });
          
        }


        function getitemtablebody(data) {
            var count = 1;
            var result = "";
            result +=
                "<div class='table-responsive'><table class='table table-hover' ><thead><tr><th>Count</th><th>Item</th><th>Quantity</th><th>Rate</th><th>Unit</th><th>Action</th></thead><tbody>"
            data.forEach(myFunction);

            function myFunction(item, index, arr) {
                result += "<tr><td class='text-center'>" + count + "  <input type='hidden' required name='item[]' value='" +
                    item.get("item") + "'/><input type='hidden' required name='qty[]' value='" + item.get("qty") +
                    "'/><input type='hidden' required name='unit[]' value='" + item.get("unit") +
                    "'/><input type='hidden' required name='rate[]' value='" + item.get("rate") + "'/></td><td>" + item.get(
                        "itemtext") +
                    "</td><td>" + item.get("qty") + " </td><td> " + item.get("rate") + "</td><td>" + item.get("unit") +
                    "</td>";
                result += "<td class='td-actions'> <button type='button' onclick='deletebillitem(" + index +
                    ")' style='all:unset' ><i class='zmdi zmdi-delete'></i> </button></td></tr>";
                count++;
            }
            result += "</tbody></table></div>";
            const element = document.getElementById("rowData");
            element.innerHTML = result;
            gettotaltablebody();
            getitemarray();
        }

        function getitemarray() {
            const testarray = new Array();
            itemarray.forEach(myFunction);

            function myFunction(item, index, arr) {
                testarray.push(JSON.stringify(item));
            }
        }

        function deletebillitem(id) {
            var basic_rate = itemarray[id].get("rate");

            var qty = itemarray[id].get("qty");
            totalbasicrate -= basic_rate.valueOf() * qty.valueOf();

            itemarray.splice(id, 1);
            getitemtablebody(itemarray);
        }

        function gettotaltablebody() {

            var result = "";
            var total = (totalbasicrate).valueOf();
            result += "<b>Total Amount   =>   ₹ " + total.toFixed(2) + "</b>";
            const element = document.getElementById("totaltablebody");
            element.innerHTML = result;
        }

        function addbillitemfromphp(name, id, qty, unit, rate) {
            const map1 = new Map();
            map1.set('itemtext', name);
            map1.set('item', id);
            map1.set('qty', qty);
            map1.set('unit', unit);
            map1.set('rate', rate);
            itemarray.push(map1);
            totalbasicrate += (rate.valueOf() * qty.valueOf());
            getitemtablebody(itemarray);

        }
    </script>
    <?php
    foreach ($bill_items as $bill_item) {
        $name = $bill_item['name'];
        $work_id = $bill_item['work_id'];
        $qty = $bill_item['qty'];
        $unit = $bill_item['unit'];
        $rate = $bill_item['rate'];
    
        echo "<script>
                addbillitemfromphp('$name','$work_id','$qty','$unit','$rate');
               
                             </script>";
    }
    
    ?>
@endsection
@section('scripts')
    <script type="text/javascript">
        $(document).ready(function() {
            var site_id = {{ $bill['site_id'] }}
            fetch_bill_items_from_php(site_id);
        })
    </script>
@endsection