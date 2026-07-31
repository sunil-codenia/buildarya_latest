   
        <div class="card">
            <div class="header">
                <h2><strong>Company Expenses</strong> Report</h2>
            </div>
            @php $data = get_company_expense_area_chart_widget($from ?? null, $to ?? null);
            $headData = get_company_monthlyExpenses_chart_head_table($from ?? null, $to ?? null);
            $count = 0;
            @endphp
            <div class="body" id="expense_area_chart_div">
                @if($filter_type != 'this_month' && $data['filteredExpense'])
                    <div class="text-center" style="margin-bottom: 15px;">
                        <span class="badge badge-info" style="font-size: 14px; padding: 5px 10px; display: inline-block;">Filtered Range: {{ $data['filteredExpense'] }}</span>
                    </div>
                @endif
                <div class="row text-center">
                    <div class="col-sm-4 col-6">
                        <h5 class="m-t-0">
                            {{$data['todayExpense']}}
                        </h5>
                        <p class="text-muted"> Today's</p>
                    </div>
                    <div class="col-sm-4 col-6">
                        <h5 class="m-t-0">
                            {{$data['weeklyExpenses']}}
                                                        
                        </h5>
                        <p class="text-muted">This Week's</p>
                    </div>
                    <div class="col-sm-4 col-6">
                        <h5 class="m-t-0">
                            {{$data['monthExpense']}}
                        </h5>
                        <p class="text-muted">This Month's</p>
                    </div>
                    <div class="col-sm-6 col-6">
                        <h5 class="m-t-0">
                            {{$data['yearExpense']}}
                            
                        </h5>
                        <p class="text-muted">This Year's</p>
                    </div>
                    <div class="col-sm-6 col-6">
                        <h5 class="m-t-0">
                            {{$data['completeExpense']}}
                            
                        </h5>
                        <p class="text-muted">Till Date</p>
                    </div>
                </div>
                <div id="expense_area_chart" class="graph"></div>
                <br>
                <table class="dashboardTable table table-hover">
                    <thead>
                        <th>#</th>
                        <th>Head Name</th>
                        <th>Amount</th>
                    </thead>
                    <tbody>
                       
                        @foreach ($headData as $dd)
                            <tr>
                                <td>{{++$count}}</td>
                                <td><?= $dd->label ?></td>
                                <td><?= $dd->value ?></td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

