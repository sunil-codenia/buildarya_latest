<div class="overlay"></div>
<!-- Left Sidebar -->
<aside id="leftsidebar" class="sidebar">
    <div class="menu">
        <ul class="list">
            <li>
                <div class="user-info">
                    <div class="image"><a href="{{url('/dashboard')}}"><img src="{{ asset('/' . Session::get('image')) }}" alt="User"></a></div>
                    <div class="detail">
                        <h4>{{Session::get('name')}}</h4>
                        <small>{{Session::get('comp_name')}} ({{getRoleDetailsById(Session::get('role'))->name}})</small>                        
                    </div>
                    <a href="{{url('/dashboard')}}" title="Events"><i class="zmdi zmdi-home"></i></a>
                    <a href="{{url('/contacts')}}" title="Contact List"><i class="zmdi zmdi-account-box-phone"></i></a>
                    <a href="{{url('/file-structure')}}" title="Chat App"><i class="zmdi zmdi-folder-star"></i></a>
                    <a href="{{url('/activity')}}" title="Chat App"><i class="zmdi zmdi-chart"></i></a>
                   
                </div>
            </li>
            <li class="header">MAIN</li>
            <li class="{{ Request::is('dashboard') ? 'active open' : '' }}"><a href="{{url('/dashboard')}}"><i class="zmdi zmdi-view-dashboard"></i><span>Dashboard</span></a></li> 

            {{-- Module 1: Sites & Users --}}
            @if (canViewModule(1))
            <li class="{{ Request::is('users') || Request::is('sites') || Request::is('user_roles') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-city"></i><span>Sites & Users</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('users') || Request::is('sites') || Request::is('user_roles') ? 'block' : 'none' }};">
                    <li class="{{ Request::is('users') ? 'active' : '' }}"><a href="{{url('/users')}}"> <i class="zmdi zmdi-face"></i> Users</a></li>
                    <li class="{{ Request::is('sites') ? 'active' : '' }}"><a href="{{url('/sites')}}"> <i class="zmdi zmdi-city"></i> Sites</a></li>
                    <li class="{{ Request::is('user_roles') ? 'active' : '' }}"><a href="{{url('/user_roles')}}"> <i class="zmdi zmdi-accounts-list"></i> Roles</a></li>
                   
                </ul>
            </li>
            @endif

            {{-- Module 2: Expenses --}}
            @if (canViewModule(2))
            <li class="{{ Request::is('expense_party') || Request::is('expense_head') || Request::is('new_expense') || Request::is('pending_expense*') || Request::is('verified_expense') || Request::is('expense_reports') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-receipt"></i><span>Expenses</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('expense_party') || Request::is('expense_head') || Request::is('new_expense') || Request::is('pending_expense*') || Request::is('verified_expense') || Request::is('expense_reports') ? 'block' : 'none' }};">
                    <li class="{{ Request::is('expense_party') ? 'active' : '' }}"><a href="{{url('/expense_party')}}"> <i class="zmdi zmdi-face"></i> Expense Parties</a></li>
                    <li class="{{ Request::is('expense_head') ? 'active' : '' }}"><a href="{{url('/expense_head')}}"> <i class="zmdi zmdi-puzzle-piece"></i> Expense Head</a></li>
                    @if (isSuperAdmin() || checkmodulepermission(2, 'can_add') == 1)
                    <li class="{{ Request::is('new_expense') ? 'active' : '' }}"><a href="{{url('/new_expense')}}"> <i class="zmdi zmdi-plus-circle"></i> New Expenses</a></li>
                    @endif
                    <li class="{{ Request::is('pending_expense*') ? 'active' : '' }}"><a href="{{url('/pending_expense')}}"> <i class="zmdi zmdi-dot-circle"></i> Pending Expense</a></li>
                    <li class="{{ Request::is('verified_expense') ? 'active' : '' }}"><a href="{{url('/verified_expense')}}"> <i class="zmdi zmdi-check-circle"></i> Verified Expense</a></li>
                    @if (isSuperAdmin() || checkmodulepermission(2, 'can_report') == 1)
                    <li class="{{ Request::is('expense_reports') ? 'active' : '' }}"><a href="{{url('/expense_reports')}}"> <i class="zmdi zmdi-chart"></i> Reports</a></li>                   
                    @endif
                </ul>
            </li>
            @endif

            {{-- Module 3: Material Purchase --}}
            @if (canViewModule(3))
            <li class="{{ Request::is('materialsupplier') || Request::is('material') || Request::is('materialunit') || Request::is('new_material') || Request::is('pending_material') || Request::is('verified_material') || Request::is('materials_report') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-landscape"></i><span>Material Purchase</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('materialsupplier') || Request::is('material') || Request::is('materialunit') || Request::is('new_material') || Request::is('pending_material') || Request::is('verified_material') || Request::is('materials_report') ? 'block' : 'none' }};">
                    <li class="{{ Request::is('materialsupplier') ? 'active' : '' }}"><a href="{{url('/materialsupplier')}}"> <i class="zmdi zmdi-face"></i> Material Suppliers</a></li>
                    <li class="{{ Request::is('material') ? 'active' : '' }}"><a href="{{url('/material')}}"> <i class="zmdi zmdi-landscape"></i> Materials</a></li>
                    <li class="{{ Request::is('materialunit') ? 'active' : '' }}"><a href="{{url('/materialunit')}}"> <i class="zmdi zmdi-ruler"></i> Units</a></li>
                    @if (isSuperAdmin() || checkmodulepermission(3, 'can_add') == 1)
                    <li class="{{ Request::is('new_material') ? 'active' : '' }}"><a href="{{url('/new_material')}}"> <i class="zmdi zmdi-plus-circle"></i> New Materials Entry</a></li>
                    @endif
                    <li class="{{ Request::is('pending_material') ? 'active' : '' }}"><a href="{{url('/pending_material')}}"> <i class="zmdi zmdi-dot-circle"></i> Pending Materials Entry</a></li>
                    <li class="{{ Request::is('verified_material') ? 'active' : '' }}"><a href="{{url('/verified_material')}}"> <i class="zmdi zmdi-check-circle"></i> Verified Materials Entry</a></li>

                    @if (isSuperAdmin() || checkmodulepermission(3, 'can_report') == 1)
                    <li class="{{ Request::is('materials_report') ? 'active' : '' }}"><a href="{{url('/materials_report')}}"> <i class="zmdi zmdi-chart"></i>Reports</a></li>
                    @endif

                </ul>

            </li>
            @endif

            {{-- Module 3 (Stock): Manage Stock uses same module_id as Material --}}
            @if (canViewModule(3))
            <li class="{{ Request::is('stock_dashboard') || Request::is('new_consumption') || Request::is('pending_consumption') || Request::is('verified_consumption') || Request::is('stock_site_transfer') || Request::is('stock_unit_conversion') || Request::is('reconsilation_list') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-landscape"></i><span>Manage Stock</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('stock_dashboard') || Request::is('new_consumption') || Request::is('pending_consumption') || Request::is('verified_consumption') || Request::is('stock_site_transfer') || Request::is('stock_unit_conversion') || Request::is('reconsilation_list') ? 'block' : 'none' }};">
                    <li class="{{ Request::is('stock_dashboard') ? 'active' : '' }}"><a href="{{url('/stock_dashboard')}}"> <i class="zmdi zmdi-landscape"></i> Stock Dashboard</a></li>

                    @if (isSuperAdmin() || checkmodulepermission(3, 'can_add') == 1)
                    <li class="{{ Request::is('new_consumption') ? 'active' : '' }}"><a href="{{url('/new_consumption')}}"> <i class="zmdi zmdi-plus-circle"></i> New Consumption / Wastage</a></li>
                    @endif
                    <li class="{{ Request::is('pending_consumption') ? 'active' : '' }}"><a href="{{url('/pending_consumption')}}"> <i class="zmdi zmdi-dot-circle"></i> Pending Consumption / Wastage</a></li>
                    <li class="{{ Request::is('verified_consumption') ? 'active' : '' }}"><a href="{{url('/verified_consumption')}}"> <i class="zmdi zmdi-check-circle"></i> Verified Consumption / Wastage</a></li>

                    <li class="{{ Request::is('stock_site_transfer') ? 'active' : '' }}"><a href="{{url('/stock_site_transfer')}}"> <i class="zmdi zmdi-arrow-split"></i> Stock Site Transfer</a></li>
                    <li class="{{ Request::is('stock_unit_conversion') ? 'active' : '' }}"><a href="{{url('/stock_unit_conversion')}}"> <i class="zmdi zmdi-swap"></i> Stock Unit Conversion</a></li>

                    <li class="{{ Request::is('reconsilation_list') ? 'active' : '' }}"><a href="{{url('/reconsilation_list')}}"> <i class="zmdi zmdi-shape"></i> Stock Reconsilation</a></li>
                </ul>

            </li>
            @endif

            {{-- Module 4: Site Bills --}}
            @if (canViewModule(4))
            <li class="{{ Request::is('billparty') || Request::is('billwork') || Request::is('billrate') || Request::is('new_bill') || Request::is('pending_bill') || Request::is('verified_bill') || Request::is('bill_report') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-collection-text"></i><span>Site Bills</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('billparty') || Request::is('billwork') || Request::is('billrate') || Request::is('new_bill') || Request::is('pending_bill') || Request::is('verified_bill') || Request::is('bill_report') ? 'block' : 'none' }};">
                    <li class="{{ Request::is('billparty') ? 'active' : '' }}"><a href="{{url('/billparty')}}"> <i class="zmdi zmdi-face"></i> Bill Parties</a></li>
                    <li class="{{ Request::is('billwork') ? 'active' : '' }}"><a href="{{url('/billwork')}}"> <i class="zmdi zmdi-shape"></i> Works</a></li>
                    <li class="{{ Request::is('billrate') ? 'active' : '' }}"><a href="{{url('/billrate')}}"> <i class="zmdi zmdi-money-box"></i> Works Rate</a></li>
                    @if (isSuperAdmin() || checkmodulepermission(4, 'can_add') == 1)
                    <li class="{{ Request::is('new_bill') ? 'active' : '' }}"><a href="{{url('/new_bill')}}"> <i class="zmdi zmdi-plus-circle"></i> New Bill</a></li>
                    @endif
                    <li class="{{ Request::is('pending_bill') ? 'active' : '' }}"><a href="{{url('/pending_bill')}}"> <i class="zmdi zmdi-dot-circle"></i> Pending Bills</a></li>
                    <li class="{{ Request::is('verified_bill') ? 'active' : '' }}"><a href="{{url('/verified_bill')}}"> <i class="zmdi zmdi-check-circle"></i> Verified Bills</a></li>
                    @if (isSuperAdmin() || checkmodulepermission(4, 'can_report') == 1)
                    <li class="{{ Request::is('bill_report') ? 'active' : '' }}"><a href="{{url('/bill_report')}}"> <i class="zmdi zmdi-chart"></i>Reports</a></li>
                    @endif
                </ul>
            </li>
            @endif

            {{-- Module 6: Machinery --}}
            @if (canViewModule(6))
            <li class="{{ Request::is('machinery_head') || Request::is('machinery_expense_head') || Request::is('machinery_report') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-truck"></i><span>Machinery</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('machinery_head') || Request::is('machinery_expense_head') || Request::is('machinery_report') ? 'block' : 'none' }};">
                   
                    <li class="{{ Request::is('machinery_head') ? 'active' : '' }}"><a href="{{url('/machinery_head')}}"> <i class="zmdi zmdi-truck"></i> Machineries</a></li>
                    <li class="{{ Request::is('machinery_expense_head') ? 'active' : '' }}"><a href="{{url('/machinery_expense_head')}}"> <i class="zmdi zmdi-shape"></i> Machinery's Expense Head</a></li>
                    @if (isSuperAdmin() || checkmodulepermission(6, 'can_report') == 1)
                    <li class="{{ Request::is('machinery_report') ? 'active' : '' }}"><a href="{{url('/machinery_report')}}"> <i class="zmdi zmdi-chart"></i> Reports</a></li>
                    @endif
              
                </ul>
            </li>
            @endif

            {{-- Module 5: Assets --}}
            @if (canViewModule(5))
            <li class="{{ Request::is('asset_head') || Request::is('asset_expense_head') || Request::is('assets_report') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-wrench"></i><span>Assets</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('asset_head') || Request::is('asset_expense_head') || Request::is('assets_report') ? 'block' : 'none' }};">
                    <li class="{{ Request::is('asset_head') ? 'active' : '' }}"><a href="{{url('/asset_head')}}"> <i class="zmdi zmdi-wrench"></i> Assets</a></li>
                    <li class="{{ Request::is('asset_expense_head') ? 'active' : '' }}"><a href="{{url('/asset_expense_head')}}"> <i class="zmdi zmdi-shape"></i> Asset's Expense Head</a></li>
                    @if (isSuperAdmin() || checkmodulepermission(5, 'can_report') == 1)
                    <li class="{{ Request::is('assets_report') ? 'active' : '' }}"><a href="{{url('/assets_report')}}"> <i class="zmdi zmdi-chart"></i>Reports</a></li>
                  
                    @endif
                </ul>
            </li>
            @endif

            {{-- Module 7: Sales --}}
            @if (canViewModule(7))
            <li class="{{ Request::is('sales_inv_head') || Request::is('sales_parties') || Request::is('sales_project') || Request::is('all_sales_invoice') || Request::is('sales_report') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-city"></i><span>Sales</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('sales_inv_head') || Request::is('sales_parties') || Request::is('sales_project') || Request::is('all_sales_invoice') || Request::is('sales_report') ? 'block' : 'none' }};">
                    <li class="{{ Request::is('sales_inv_head') ? 'active' : '' }}"><a href="{{url('/sales_inv_head')}}"> <i class="zmdi zmdi-exposure"></i> Invoice Heads</a></li>
                    <li class="{{ Request::is('sales_parties') ? 'active' : '' }}"><a href="{{url('/sales_parties')}}"> <i class="zmdi zmdi-face"></i> Sales Party</a></li>
                    <li class="{{ Request::is('sales_project') ? 'active' : '' }}"><a href="{{url('/sales_project')}}"> <i class="zmdi zmdi-city"></i> Projects</a></li>
                    <li class="{{ Request::is('all_sales_invoice') ? 'active' : '' }}"><a href="{{url('/all_sales_invoice')}}"> <i class="zmdi zmdi-collection-text"></i> All Sale Invoice</a></li>
                    @if (isSuperAdmin() || checkmodulepermission(7, 'can_report') == 1)
                    <li class="{{ Request::is('sales_report') ? 'active' : '' }}"><a href="{{url('/sales_report')}}"> <i class="zmdi zmdi-chart"></i> Reports</a></li>
                    @endif
                </ul>
            </li>
            @endif

            {{-- Module 8: Payment Vouchers --}}
            @if (canViewModule(8))
            <li class="{{ Request::is('new_paymentvoucher') || Request::is('pending_paymentvoucher') || Request::is('verified_paymentvoucher') || Request::is('paid_paymentvoucher') || Request::is('otherparty') || Request::is('payment_report') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-balance-wallet"></i><span>Payment Vouchers</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('new_paymentvoucher') || Request::is('pending_paymentvoucher') || Request::is('verified_paymentvoucher') || Request::is('paid_paymentvoucher') || Request::is('otherparty') || Request::is('payment_report') ? 'block' : 'none' }};">
                    <li class="{{ Request::is('new_paymentvoucher') ? 'active' : '' }}"><a href="{{url('/new_paymentvoucher')}}"> <i class="zmdi zmdi-plus-circle"></i>Generate Voucher</a></li>
                    <li class="{{ Request::is('pending_paymentvoucher') ? 'active' : '' }}"><a href="{{url('/pending_paymentvoucher')}}"> <i class="zmdi zmdi-dot-circle"></i> Pending Voucher</a></li>
                    <li class="{{ Request::is('verified_paymentvoucher') ? 'active' : '' }}"><a href="{{url('/verified_paymentvoucher')}}"> <i class="zmdi zmdi-check-circle"></i> Verified Voucher</a></li>
                    <li class="{{ Request::is('paid_paymentvoucher') ? 'active' : '' }}"><a href="{{url('/paid_paymentvoucher')}}"> <i class="zmdi zmdi-balance-wallet"></i> Paid Voucher</a></li>
                    <li class="{{ Request::is('otherparty') ? 'active' : '' }}"><a href="{{url('/otherparty')}}"> <i class="zmdi zmdi-face"></i> Other Parties</a></li>
                    @if (isSuperAdmin() || checkmodulepermission(8, 'can_report') == 1)
                    <li class="{{ Request::is('payment_report') ? 'active' : '' }}"><a href="{{url('/payment_report')}}"> <i class="zmdi zmdi-chart"></i> Reports</a></li>
                    @endif
                </ul>
            </li>
            @endif

            {{-- Module 11: Document Management --}}
            @if (canViewModule(11))
            <li class="{{ Request::is('file-structure') ? 'active open' : '' }}"><a href="{{url('/file-structure')}}"><i class="zmdi zmdi-folder-star"></i><span>Document Management</span></a></li> 
            @endif

            {{-- Module 10: Contact Management --}}
            @if (canViewModule(10))
            <li class="{{ Request::is('contacts') ? 'active open' : '' }}"><a href="{{url('/contacts')}}"><i class="zmdi zmdi-account-box-phone"></i><span>Contact Management</span></a></li> 
            @endif

            {{-- Module 9: Management/Settings - SuperAdmin or can_view --}}
            @if (canViewModule(9))
            <li class="{{ Request::is('settings') || Request::is('sales_companies') || Request::is('activity') ? 'active open' : '' }}"><a href="javascript:void(0);" class="menu-toggle"><i class="zmdi zmdi-settings"></i><span>Management</span> </a>
                <ul style="list-style-type: none; display: {{ Request::is('settings') || Request::is('sales_companies') || Request::is('activity') ? 'block' : 'none' }};">
                    <li class="{{ Request::is('settings') ? 'active' : '' }}"><a href="{{url('/settings')}}"> <i class="zmdi zmdi-settings"></i> Settings</a></li>
                    <li class="{{ Request::is('sales_companies') ? 'active' : '' }}"><a href="{{url('/sales_companies')}}"> <i class="zmdi zmdi-city"></i> My Companies</a></li>
                    <li class="{{ Request::is('activity') ? 'active' : '' }}"><a href="{{url('/activity')}}"> <i class="zmdi zmdi-chart"></i> System Activity</a></li>

                    {{-- <li><a href="{{url('/management_report')}}"> <i class="zmdi zmdi-city"></i>Reports</a></li> --}}
                    
                </ul>
            </li>
            @endif
         </ul>
    </div>
</aside>
<!-- Right Sidebar -->
@if (isSuperAdmin() || checkmodulepermission(9, 'can_edit') == 1)

<aside id="rightsidebar" class="right-sidebar">
    <ul class="nav nav-tabs">
        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#setting"><i class="zmdi zmdi-settings zmdi-hc-spin"></i></a></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane active slideRight" id="setting">
            <div class="slim_scroll">
                <div class="card">
                    <h6>Skins</h6>
                    <form method="post" action="{{url('/changecolor')}}" enctype="multipart/form-data">
                        @csrf
                        <label for="primary_color">Primary Color</label>&nbsp;
                    <input type="color" name="primary_color" value="{{Session::get('primary_color')[0]}}" id="primary_color">
                    <br>
                    <label for="secondry_color">Secondry Color</label>&nbsp;
                    <input type="color" name="secondry_color" value="{{Session::get('secondry_color')[0]}}" id="secondry_color">
                    <br>
                    <label for="gradient_start">Gradient Start</label>&nbsp;
                    <input type="color" name="gradient_start" value="{{Session::get('gradient_start')[0]}}" id="gradient_start">
                    <br>
                    <label for="gradient_end">Gradient End</label>&nbsp;
                    <input type="color" name="gradient_end" value="{{Session::get('gradient_end')[0]}}" id="gradient_end">
                    <br>
                    <button type="submit" class="btn btn-primary btn-simple btn-round waves-effect"><a >Submit</a></button>
                    </form>
                </div>
                <div class="card">
                    <h6>Left Menu</h6>
                    <ul class="list-unstyled theme-light-dark">
                        <li>
                            <div class="t-light btn btn-default btn-simple btn-round">Light</div>
                        </li>
                        <li>
                            <div class="t-dark btn btn-default btn-round">Dark</div>
                        </li>
                    </ul>
                </div>
               
            </div>                
        </div>       
        
    </div>
</aside>
@endif