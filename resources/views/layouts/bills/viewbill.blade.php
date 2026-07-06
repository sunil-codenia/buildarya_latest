@extends('app')
@section('content')
    @include('templates.blockheader', ['pagename' => 'Bill Details'])
    @php
        $data = json_decode($data, true);
        $bill = $data['bill'];
        $bill_items = $data['bill_items'];
        $balance = $data['balance'];
        $bill_party = $data['bill_party'];
        $attachments = !empty($bill['attachments']) ? json_decode($bill['attachments'], true) : [];
    @endphp

    <div class="row clearfix">
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="card project_list">
            @if(checkmodulepermission(4,'can_view') == 1)

                <div class="card">
                    <div class="header">
                        <h2><strong>Bill</strong> Details</h2>
                        <h3 class="m-b-0">Invoice No : <strong class="text-primary">{{ $bill['bill_no'] }}</strong></h3>
                    </div>
                </div>
                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane in active" id="details" aria-expanded="true">
                        <div class="card" id="details">
                            <div class="body">
                                <div class="row">
                                    <div class="col-md-6 col-sm-6">
                                        <address>
                                            <strong>{{ $bill_party['name'] }}</strong><br>
                                            <b title="Phone">Pan No : </b> {{ $bill_party['panno'] }}6
                                        </address>
                                    </div>
                                    <div class="col-md-6 col-sm-6 text-right">
                                        <p class="m-b-0"><strong>Bill Date: </strong> {{ $bill['billdate'] }}</p>
                                        <p class="m-b-0"><strong>Bill Status: </strong> <span
                                                class="badge bg-{{ getStatusColor($bill['status']) }}">{{ $bill['status'] }}</span>
                                        </p>
                                        <p><strong>Bill Period: </strong> {{ $bill['bill_period'] }}</p>
                                    </div>
                                </div>
                                <div class="mt-40"></div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="table-responsive">
                                            <table  class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th class="hidden-sm-down">Item</th>


                                                        <th>Quantity</th>
                                                        <th>Unit</th>
                                                        <th class="hidden-sm-down">Unit Cost</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php
                                                        $count = 1;
                                                    @endphp
                                                    @foreach ($bill_items as $bill_item)
                                                        @php
                                                            $amount = $bill_item['rate'] * $bill_item['qty'];
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $count++ }}</td>
                                                            <td>{{ $bill_item['name'] }}</td>
                                                            <td>{{ $bill_item['qty'] }}</td>
                                                            <td>{{ $bill_item['unit'] }}</td>
                                                            <td class="hidden-sm-down">
                                                                {{ getStructuredAmount($bill_item['rate'], true, false) }}
                                                            </td>
                                                            <td>{{ getStructuredAmount($amount, true, false) }}</td>
                                                        </tr>
                                                    @endforeach

                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                @if(count($attachments) > 0)
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-12" style="text-align: start;">
                                            <h5><b>Attachments</b></h5>
                                            <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px;">
                                                @foreach($attachments as $path)
                                                    @php
                                                        $filename = basename($path);
                                                        $is_pdf = strtolower(pathinfo($path, PATHINFO_EXTENSION)) == 'pdf';
                                                    @endphp
                                                    <div style="border: 1px solid #ddd; padding: 10px; border-radius: 4px; display: flex; align-items: center; background: #f9f9f9; min-width: 200px; max-width: 300px;">
                                                        @if($is_pdf)
                                                            <a href="{{ asset($path) }}" target="_blank" style="text-decoration: none; color: #007bff; display: flex; align-items: center; gap: 8px; width: 100%;">
                                                                <i class="zmdi zmdi-file-text" style="font-size: 24px; min-width: 24px;"></i>
                                                                <span style="word-break: break-all; font-size: 13px;">{{ $filename }}</span>
                                                            </a>
                                                        @else
                                                            <a href="{{ asset($path) }}" target="_blank" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 8px; width: 100%;">
                                                                <img src="{{ asset($path) }}" alt="{{ $filename }}" style="height: 40px; width: 40px; border-radius: 4px; object-fit: cover; min-width: 40px;">
                                                                <span style="word-break: break-all; font-size: 13px;">{{ $filename }}</span>
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <hr>
                                <div class="row">
                                    <div class="col-md-4" style="text-align:start;">
                                        <h5><b>Notes & Remarks</b></h5>
                                        <p>{{ $bill['remark'] }}</p>
                                    </div>
                                    <div class="col-md-4" style="text-align: start;">
                                        <h5><b>Bank Details</b></h5>
                                        <p>Bank Name : {{ $bill_party['bankname'] }}<br>
                                            A/C No : {{ $bill_party['bank_ac'] }}<br>
                                            A/C Holder : {{ $bill_party['ac_holder_name'] }}<br>
                                            Bank IFSC : {{ $bill_party['ifsc'] }}</p>

                                    </div>
                                    <div class="col-md-4" style="text-align:start;">

                                        <h5 class="m-b-0"><b>Grand Total =>
                                                {{ getStructuredAmount($bill['amount'], true, false) }}</b></h5>
                                        <br>
                                        <h5 class="m-b-0">Created By => {{ $bill['user'] }}</h5>
                                        <h5 class="m-b-0">Created At => {{ $bill['site'] }}</h5>
                                        <h5 class="m-b-0">Party Balance => {{ getStructuredAmount($balance, true, false) }}
                                        </h5>
                                        <br>
                                    </div>
                                </div>
                                <hr>
                                <div class="hidden-print col-md-12 text-right">
                                    <a href="javascript:void(0);" class="btn btn-info btn-round"><i
                                            class="zmdi zmdi-print"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

@endif
            </div>
        </div>
    </div>
    <script type="text/javascript">
        var work = new Array();
        const itemarray = new Array();
        var totalbasicrate = 0;


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
                    $('#sitealert').css('display', 'block');
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
                        "<label>Item</label><select onchange='itemchange()' id='item_id' class='select2 form-control show-tick' data-live-search='true'  >";
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
            if (itemarray.length == 0) {
                $('#sitealert').css('display', 'none');
            }
        }

        function gettotaltablebody() {
            console.log("gng");
            var result = "";
            var total = (totalbasicrate).valueOf();
            result += "<b>Total Amount   =>   ₹ " + total.toFixed(2) + "</b>";
            const element = document.getElementById("totaltablebody");
            element.innerHTML = result;
        }
    </script>
@endsection

@section('scripts')
@endsection
