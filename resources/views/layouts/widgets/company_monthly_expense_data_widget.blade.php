<div class="col-lg-4 col-md-6 col-sm-12">
    <div class="card" style="border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: none;">
        <div class="body d-flex align-items-center p-4">
            <div class="icon d-flex align-items-center justify-content-center" style="width: 65px; height: 65px; border-radius: 15px; background: rgba(76, 175, 80, 0.1);">
                <i class="zmdi zmdi-receipt col-green" style="font-size: 32px;"></i>
            </div>
            <div style="margin-left: 20px;">
                <p class="text-muted mb-1" style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">{{ isset($from) && isset($to) ? 'Selected Period' : 'Current Month' }} Expenses</p>
                <h3 class="number mb-0" style="font-weight: 700; color: #2c2c2c;">{{get_company_monthly_expense_data_widget($from ?? null, $to ?? null)}}</h3>
            </div>
        </div>
        <div class="progress" style="height: 4px; margin: 0; border-radius: 0 0 12px 12px; background-color: #f5f5f5;">
            <div class="progress-bar bg-green" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%; background-color: #4caf50;"></div>
        </div>
    </div>
</div>