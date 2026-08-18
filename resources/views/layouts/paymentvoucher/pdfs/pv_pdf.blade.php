<html>

<head>
    <style>
        .sub {
            display: flex;
            color: black;
            text-align: -webkit-center;
            place-content: space-between;
            justify-content: center;
            border-top: 2px solid black;
            padding-top: 10px;
            padding-bottom: 10px;
            width: 90%;
            margin-left: 5%;
            margin-right: 5%;
            height: fit-content;
            border-bottom: 2px solid black;
        }

        .half-div1 {
            width: 50%;
            display: inline-block;
            vertical-align: top;
        }

        .half-div2 {

            width: 50%;
            border-left: 2px solid black;
            display: inline-block;
            vertical-align: top;
        }


        h5,
        h6,
        h4 {
            margin: 0;
        }

        h5{
            font-size: 15px;
        }
        h6 {
            margin: 5px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #ccc;
            padding: 8px 0;
        }

        .header {
            font-weight: bold;
        }




        .customers {
            font-family: Arial, Helvetica, sans-serif;
            border-collapse: collapse;
            width: 90%;
            margin-left: 5%;
            margin-right: 5%;
            padding-bottom: 30px;

        }

        .customers td,
        .customers th {
            border: 1.5px solid black;
            font-size: 20px;
            padding: 8px;
        }




        .tmain {
            background-color: white;
            margin: 10px;

            padding: 2px;
            border-radius: 25px;
        }
.normal_text{
    font-weight: 500;
}
        .hmain {
            color: white;
            font-size: 25px;
            margin-top: -20px;
        }

        h4,
        h3 {
            text-align: center;
        }

        p {
            text-align: center;
        }
    </style>
</head>
@php
    $pv = $payment_voucher;
    $partyinfo = getPaymentVoucherPartyInfo($pv->party_id, $pv->party_type);
    $balance = getPaymentVoucherPartyBalance($pv->party_id, $pv->party_type);
    $party = $partyinfo['party_status'] ?? null;
@endphp

<body style="background-color:{{ session()->get('primary_color')[0] }}; border-radius: 25px;">



    <div class="hmain">

        <h3 style="font-size:40px;">Payment Voucher</h3>

    </div>

    <div class="tmain">
        <h3 style="font-size:30px;">{{ optional($company)->name ?? 'N/A' }}</h3>
        <h4 style="font-size:15px;margin-top:-25px; text-wrap:wrap; ">Address: {{ optional($company)->address ?? 'N/A' }} </h4>
        <p style="font-size:15px;">Mobile:{{ optional($company)->phone ?? 'N/A' }} &#160;&#160; GSTIN:
            {{ optional($company)->gst ?? 'N/A' }}</p>


        <table class="customers">

            <tr>
                <td style="width:50%; text-align:center;">
                    <h5>Site : <span class="normal_text">{{ $pv->site_name }} </span></h5>

                    <h5>Voucher Party : <span class="normal_text">{{ optional($party)->name ?? 'N/A' }} </span></h5>
                </td>
                <td style="width:50%; text-align:center;">
                    <h5>Voucher No. : <span class="normal_text">{{ $pv->voucher_no }}</span></h5>

                    <h5>Voucher Date : <span class="normal_text">{{ $pv->date }}</span></h5>
                </td>
            </tr>
        </table>
        <table class="customers">
            <tr>
                <td>
                    <h5> <b>Amount :</b><span class="normal_text"> {{ getStructuredAmount($pv->amount, false, false) }}</span></h5>
                </td>
                <td>
                    <h5>Party Balance :<span class="normal_text"> {{ getStructuredAmount($balance, false, false) }}</span></h5>
                </td>
                <td rowspan="2" style="width:33% !important;">
                    <h5>Party Banking Details :</h5>
                    @if ($pv->party_type == 'bill' && $party)
                        <h6>Bank Name - <span class="normal_text">{{ optional($party)->bankname ?? 'N/A' }}</span><br>A/C No. - <span class="normal_text">{{ optional($party)->bank_ac ?? 'N/A' }}</span> <br>IFSC Code -
                            <span class="normal_text"> {{ optional($party)->ifsc ?? 'N/A' }}</span> <br> A/C Holder
                            Name - <span class="normal_text">{{ optional($party)->ac_holder_name ?? 'N/A' }}</span></h6>
                    @elseif(($pv->party_type == 'material' || $pv->party_type == 'other') && $party)
                        <h6>Bank Name - <span class="normal_text">{{ optional($party)->bank_name ?? 'N/A' }}</span><br>A/C No. - <span class="normal_text">{{ optional($party)->bank_ac ?? 'N/A' }}</span> <br>IFSC Code -
                            <span class="normal_text">{{ optional($party)->bank_ifsc ?? 'N/A' }}</span> <br> A/C Holder
                            Name - <span class="normal_text">{{ optional($party)->bank_ac_holder ?? 'N/A' }}</span></h6>
                    @else
                        <h6>No Banking Details Found</h6>
                    @endif


                </td>
            </tr>
            <tr>
                <td>
                    <h5>Payment Details :</h5>
                    <h6><span class="normal_text">{{ $pv->payment_details }}</span></h6>
                </td>
                <td>
                    <h5>Remarks : </h5>
                    <h6><span class="normal_text">{{ $pv->remark }}</span></h6>
                </td>

            </tr>
            <tr>
                <td>
                    <h5>Image / QR Code :</h5><div style="display:flex">
                    @if( $pv->image != null &&  $pv->image  != '' && $pv->image != 'images/expense.png')
                    <h4><img src="{{ $pv->image }}" height="100px" width="100px" /></h4>
                    @endif
                    @if( $pv->payment_image != null &&  $pv->payment_image  != '')
                    <h4><img src="{{ $pv->payment_image }}" height="100px" width="100px" /></h4>
                    @endif
                    @if( $pv->qr_code != null &&  $pv->qr_code  != '')
                    <h4><img src="{{ $pv->qr_code }}" height="100px" width="100px" /></h4>
                    @endif
                    </div>
                </td>
                <td>
                    <h5>Status : <span class="normal_text">{{$pv->status}}</span></h5>
                    @if ($pv->status == 'Approved')
                        <h4> <img src="{{ url('/images/approved.jpg') }}" height="100px" width="100px" /></h4>
                    @endif

                    @if ($pv->status == 'Paid')
                        <h4> <img src="{{ url('/images/paid.png') }}" height="100px" width="100px" /></h4>
                    @endif

                    @if ($pv->status == 'Pending')
                        <h4> <img src="{{ url('/images/pending.jpg') }}" height="100px" width="100px" /></h4>
                    @endif

                    @if ($pv->status == 'Rejected')
                        <h4> <img src="{{ url('/images/rejected.png') }}" height="100px" width="100px" /></h4>
                    @endif
                </td>
                <td>

                    <h5 style="text-align:left;">Signatory : </h5>
                    <h6>Generated By : @if ($pv->created_by != null)
                            <span class="normal_text">{{ getUserDetailsById($pv->created_by)->name }}</span>
                        @endif
                    </h6>
                    <h6>Approved By : @if ($pv->approved_by != null)
                            <span class="normal_text">{{ getUserDetailsById($pv->approved_by)->name }}</span>
                        @endif
                    </h6>
                    <h6>Paid By : @if ($pv->paid_by != null)
                            <span class="normal_text">{{ getUserDetailsById($pv->paid_by)->name }}</span>
                        @endif
                    </h6>
                    <br>
                    <small style="font-size:10px;">This Is A Computer Generated PDF. It Does Not Require Physical
                        Signature.</small>

                </td>
            </tr>
        </table>
        <br>
    </div>

    <div class="hmain">
        <br>
        <h6 style="text-align:right; margin-right:5%">Voucher PDF Generated By <u><a
                    href="https://constructionmunshi.com/" target="_blank" style="color:white;">Construction
                    Munshi</a></u></h6>

    </div>

</body>

</html>
