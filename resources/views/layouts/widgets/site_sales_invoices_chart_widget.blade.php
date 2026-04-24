<div class="col-lg-12 col-md-12 col-sm-12">
    <div class="card premium-card">
        <div class="header">
            <h2 class="col-blue"><strong>Sales </strong> Invoices</h2>
        </div>
        <div class="body" id="site_sales_chart_div">
            @php
            $data = get_site_sales_invoices_chart_data($id);
            @endphp
             <div class="row align-items-center">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="sales-info-box p-3 mb-3" style="background: #f8f9fa; border-radius: 12px; border-left: 4px solid #2196f3;">
                        <h6 class="mb-3 text-uppercase" style="font-weight: 700; font-size: 11px; letter-spacing: 1px;">Lifecycle Data</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Base Sales:</span>
                            <span class="font-weight-bold">₹{{$data['base']}}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (GST):</span>
                            <span class="font-weight-bold col-red">₹{{$data['tax']}}</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span class="font-weight-bold">Total Sales:</span>
                            <span class="font-weight-bold col-blue" style="font-size: 18px;">₹{{$data['amount']}}</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12 text-center">
                    <div id="sales_invoice_donut_chart" style="height:220px;"></div>
                </div>
             </div>

            <div id="site_sales_chart" class="graph mt-4" style="height: 300px;"></div>
        </div>
    </div>
</div>