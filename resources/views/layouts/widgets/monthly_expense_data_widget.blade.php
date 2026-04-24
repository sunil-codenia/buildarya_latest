<div class="col-lg-3 col-md-6">
    <div class="card premium-card card-gradient-2">
        <div class="body text-center">
            <div class="icon-box mx-auto" style="background: rgba(255,255,255,0.2);">
                <i class="zmdi zmdi-receipt" style="font-size: 30px;"></i>
            </div>
            <h3 class="number count-to" data-from="0" data-to="758" data-speed="2000"
                data-fresh-interval="700">
               {{get_monthly_expense_data_widget($id, $from ?? null, $to ?? null)}} </h3>
            <p class="text-muted">{{ isset($from) && isset($to) ? 'Selected Period' : 'Current Month' }} Expenses</p>
            <div class="progress">
                <div class="progress-bar" role="progressbar" aria-valuenow="100" aria-valuemin="0"
                    aria-valuemax="100" style="width: 100%;"></div>
            </div>
        </div>
    </div>
</div>