<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\users\UserController;
use App\Http\Controllers\users\SiteController;
use App\Http\Controllers\expense\CostCategoryController;
use App\Http\Controllers\expense\ExpensePartyController;
use App\Http\Controllers\material\MaterialUnitController;
use App\Http\Controllers\material\MaterialController;
use App\Http\Controllers\material\MaterialSupplierController;
use App\Http\Controllers\material\MaterialEntryController;
use App\Http\Controllers\bills\BillPartyController;
use App\Http\Controllers\bills\BillsWorkController;
use App\Http\Controllers\bills\BillRateController;
use App\Http\Controllers\bills\NewBillController;
use App\Http\Controllers\assets\AssetController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\machinery\MachineryController;
use App\Http\Controllers\users\RoleController;
use App\Http\Controllers\expense\ExpenseController;
use App\Http\Controllers\management\SettingsController;
use App\Http\Controllers\management\CompanyController;
use App\Http\Controllers\sales\InvoiceController;
use App\Http\Controllers\sales\InvoiceHeadsController;
use App\Http\Controllers\sales\PartiesController;
use App\Http\Controllers\sales\ProjectController;
use App\Http\Controllers\paymentvoucher\OtherPartiesController;
use App\Http\Controllers\paymentvoucher\PaymentVoucherController;
use App\Http\Controllers\sales\InvoiceManageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExcelImportController;
use App\Http\Controllers\material\StockController;

Route::get('/', [LoginController::class, 'checkAuth']);

Route::get('/login', function () {
    return view('login');
});
Route::post('/loginf', [LoginController::class, 'loginfunc']);
Route::get('/logout', [LoginController::class, 'logout']);

//Temprory routes

Route::post('/register_new_user', [LoginController::class, 'register_new_user']);
Route::get('/register_user', [LoginController::class, 'register_user']);
// temprory routes ends


Route::group(['middleware' => ['auth']], function () {

    Route::get('/dashboard', [DashboardController::class, 'getCompanyDashboard']);
    Route::get('/switch_site/{id}', [DashboardController::class, 'switch_active_site']);
    Route::get('/dashboard/export', [DashboardController::class, 'exportCsv']);
    Route::post('/siteDashboard', [DashboardController::class, 'getSiteDashboardData']);


    Route::get('/pie', [ChartController::class, 'pieChart']);
    // Route::get('/dashboard/pie-chart', 'ChartController@pieChart')->name('dashboard.pieChart');



    // routes for application

    // Cost Category Module
    Route::group(['middleware' => ['module.access:12']], function () {
        Route::get('/cost_category', [CostCategoryController::class, 'index']);
        Route::post('/cost_category_ajax', [CostCategoryController::class, 'get_cost_category_ajax']);
        Route::post('/addcostcategory', [CostCategoryController::class, 'addcostcategory']);
        Route::get('/edit_cost_category', [CostCategoryController::class, 'edit_cost_category']);
        Route::get('/delete_cost_category', [CostCategoryController::class, 'delete_cost_category']);
        Route::post('/updatecostcategory', [CostCategoryController::class, 'updatecostcategory']);
        Route::post('/expense_head_report', [CostCategoryController::class, 'pdf_csv']);
        Route::post('/bulk_edit_head', [CostCategoryController::class, 'bulk_edit_head']);
        Route::post('/update_bulk_head', [CostCategoryController::class, 'update_bulk_head']);
        Route::post('/expenseHeadReport', [CostCategoryController::class, 'expenseHeadReport']);
    });

    //expense routes
    Route::group(['middleware' => ['module.access:2']], function () {
        Route::get('/expense_party', [ExpensePartyController::class, 'expense_party']);
        Route::post('/expense_party_ajax', [ExpensePartyController::class, 'get_expense_party_ajax']);
        Route::post('/addexpenseparty', [ExpensePartyController::class, 'addexpenseparty']);
        Route::get('/edit_expense_party', [ExpensePartyController::class, 'edit_expense_party']);
        Route::get('/delete_expense_party', [ExpensePartyController::class, 'delete_expense_party']);
        Route::post('/updateexpenseparty', [ExpensePartyController::class, 'updateexpenseparty']);
        Route::get('/update_expense_party_status', [ExpensePartyController::class, 'update_expense_party_status']);
        Route::post('/bulk_edit_party', [ExpensePartyController::class, 'bulk_edit_party']);
        Route::post('/update_bulk_party', [ExpensePartyController::class, 'update_bulk_party']);
        Route::post('/update_bulk_party_status', [ExpensePartyController::class, 'update_bulk_party_status']);
        Route::post('/expensespartyreport', [ExpensePartyController::class, 'expensespertyreports']);

        Route::get('/new_expense', [ExpenseController::class, 'new_expense']);
        Route::get('/edit_expense', [ExpenseController::class, 'edit_expense']);
        Route::post('/addnewExpenses', [ExpenseController::class, 'addnewExpenses']);
        Route::post('/updateExpenses', [ExpenseController::class, 'updateExpenses']);
        Route::post('/updateEditExpenses', [ExpenseController::class, 'updateEditExpenses']);
        Route::post('/pending_expense/bulk_edit_expense', [ExpenseController::class, 'bulk_edit_expense']);
        Route::post('/pending_expense/update_bulk', [ExpenseController::class, 'updateBulkExpenses']);
        Route::get('/verified_expense', [ExpenseController::class, 'verified_expense']);
        Route::post('/bulk_approve_verified', [ExpenseController::class, 'bulk_approve_verified']);
        Route::post('/bulk_reject_verified', [ExpenseController::class, 'bulk_reject_verified']);
        Route::get('/verified_expense/export/{type}', [ExpenseController::class, 'verified_expense_export']);
        Route::get('/reject_expense_by_id', [ExpenseController::class, 'reject_expense_by_id']);
        Route::get('/approve_expense_by_id', [ExpenseController::class, 'approve_expense_by_id']);
        Route::post('/expense_report', [ExpenseController::class, 'expenses_download']);
        Route::get('/pending_expense', [ExpenseController::class, 'pending_expense']);
        Route::post('/pending_expense_ajax', [ExpenseController::class, 'get_pending_expense_ajax']);
        Route::get('/return_expense', [ExpenseController::class, 'return_expense']);
        Route::post('/return_expense_ajax', [ExpenseController::class, 'get_return_expense_ajax']);
        Route::post('/return_expense_action', [ExpenseController::class, 'return_expense_action']);
        Route::post('/resubmit_returned_expense', [ExpenseController::class, 'resubmit_returned_expense']);
        Route::post('/bulk_resubmit_returned_expense', [ExpenseController::class, 'bulk_resubmit_returned_expense']);
        Route::post('/updateexpenseAssetHead', [ExpenseController::class, 'updateexpenseAssetHead']);
        Route::post('/updateexpenseMachineryHead', [ExpenseController::class, 'updateexpenseMachineryHead']);
        Route::get('/expense_reports', [ExpenseController::class, 'expense_reports']);
        Route::Post('/expensereports', [ExpenseController::class, 'expensereports']);
    });

    Route::get('/sendNotification', [NotificationController::class, 'sendNotification']);
    Route::get('/generateBackup', [BackupController::class, 'generateBackup']);



    //sites & users
    Route::group(['middleware' => ['module.access:1']], function () {
        Route::get('/sites', [SiteController::class, 'site']);
        Route::post('/addsites', [SiteController::class, 'addsites']);
        Route::get('/edit_site', [SiteController::class, 'edit_site']);
        Route::get('/delete_sites', [SiteController::class, 'delete_sites']);
        Route::post('/updatesites', [SiteController::class, 'updatesites']);
        Route::get('/update_site_status', [SiteController::class, 'update_site_status']);
        Route::post('/sites/bulk_action', [SiteController::class, 'bulk_action']);
        Route::post('/addsitesBalance', [SiteController::class, 'addsitesBalance']);
        Route::post('/siteStatement', [SiteController::class, 'siteStatement']);
        Route::get('/view_site_payments', [SiteController::class, 'view_site_payments']);
        
        //user route
        Route::get('/users', [UserController::class, 'users']);
        Route::post('/users_ajax', [UserController::class, 'get_users_ajax']);
        Route::post('/addnewuser', [UserController::class, 'addnewuser']);
        Route::get('/edit_users', [UserController::class, 'edit_users']);
        Route::get('/delete_users', [UserController::class, 'delete_users']);
        Route::post('/updateusers', [UserController::class, 'updateusers']);
        Route::get('/assign_permission', [UserController::class, 'assign_permission']);
        Route::post('/update_user_permission', [UserController::class, 'update_user_permission']);
        Route::get('/update_user_status', [UserController::class, 'update_user_status']);

        Route::post('/siteToSiteBalanceTransfer', [SiteController::class, 'siteToSiteBalanceTransfer']);

        // roles route
        Route::get('/user_roles', [RoleController::class, 'index']);
        Route::post('/addnewrole', [RoleController::class, 'addnewrole']);
        Route::get('/edit_role', [RoleController::class, 'edit_role']);
        Route::get('/edit_role_settings', [RoleController::class, 'edit_role_settings']);
        Route::get('/delete_role', [RoleController::class, 'delete_role']);
        Route::post('/updaterole', [RoleController::class, 'updaterole']);
        Route::post('/updaterolesetting', [RoleController::class, 'updaterolesetting']);
        Route::get('/assign_role_permission', [RoleController::class, 'assign_role_permission']);
        Route::post('/update_role_permission', [RoleController::class, 'update_role_permission']);
    });

    //material unit route
    Route::get('/materialunit', [MaterialUnitController::class, 'index']);
    Route::post('/materialunit_ajax', [MaterialUnitController::class, 'get_material_unit_ajax']);
    Route::post('/materialunit/bulk_action', [MaterialUnitController::class, 'bulk_action']);
    Route::post('/addmaterialunit', [MaterialUnitController::class, 'addmaterialunit']);
    Route::get('/edit_material_unit', [MaterialUnitController::class, 'edit_material_unit']);
    Route::get('/delete_material_unit', [MaterialUnitController::class, 'delete_material_unit']);
        Route::post('/updatematerialunit', [MaterialUnitController::class, 'updatematerialunit']);
    Route::post('/bulk_edit_unit', [MaterialUnitController::class, 'bulk_edit_unit']);
    Route::post('/update_bulk_unit', [MaterialUnitController::class, 'update_bulk_unit']);

    Route::get('/manage_unit_conversion', [MaterialUnitController::class, 'manage_unit_conversion']);
    Route::post('/add_unit_conversion', [MaterialUnitController::class, 'add_unit_conversion']);
    Route::get('/delete_unit_conversion', [MaterialUnitController::class, 'delete_unit_conversion']);


    //material route
    Route::get('/material', [MaterialController::class, 'index']);
    Route::post('/material_ajax', [MaterialController::class, 'get_material_ajax']);
    Route::post('/material/bulk_action', [MaterialController::class, 'bulk_action']);
    Route::post('/addmaterial', [MaterialController::class, 'addmaterial']);
    Route::get('/edit_material', [MaterialController::class, 'edit_material']);
    Route::get('/delete_material', [MaterialController::class, 'delete_material']);
        Route::post('/updatematerial', [MaterialController::class, 'updatematerial']);
    Route::post('/bulk_edit_material', [MaterialController::class, 'bulk_edit_material']);
    Route::post('/update_bulk_material', [MaterialController::class, 'update_bulk_material']);
    Route::post('/materialreport', [MaterialController::class, 'Material_Report']);
    Route::get('/materials_report', [MaterialController::class, 'materials_report']);
    Route::Post('/materialreports', [MaterialController::class, 'materialreports']);


    //material supplier route
    Route::get('/materialsupplier', [MaterialSupplierController::class, 'index']);
    Route::post('/materialsupplier_ajax', [MaterialSupplierController::class, 'get_material_supplier_ajax']);
    Route::post('/materialsupplier/bulk_action', [MaterialSupplierController::class, 'bulk_action']);
    Route::post('/addmaterialsupplier', [MaterialSupplierController::class, 'addmaterialsupplier']);
    Route::get('/edit_materialsupplier', [MaterialSupplierController::class, 'edit_materialsupplier']);
    Route::get('/delete_materialsupplier', [MaterialSupplierController::class, 'delete_materialsupplier']);
        Route::post('/updatematerialsupplier', [MaterialSupplierController::class, 'updatematerialsupplier']);
    Route::post('/bulk_edit_supplier', [MaterialSupplierController::class, 'bulk_edit_supplier']);
    Route::post('/update_bulk_supplier', [MaterialSupplierController::class, 'update_bulk_supplier']);

    Route::post('/material_supplier_report', [MaterialSupplierController::class, 'material_supplier_report']);

    Route::get('/update_material_supplier_status', [MaterialSupplierController::class, 'update_material_supplier_status']);

    //new materialentry
    Route::get('/new_material', [MaterialEntryController::class, 'new_material']);
    Route::post('/addnewmaterial', [MaterialEntryController::class, 'addnewmaterial']);
    Route::post('/update_material', [MaterialEntryController::class, 'update_material']);
    Route::post('/bulk_edit_pending_material', [MaterialEntryController::class, 'bulk_edit_pending_material']);
    Route::post('/update_bulk_pending_material', [MaterialEntryController::class, 'update_bulk_pending_material']);

        Route::get('/verified_material', [MaterialEntryController::class, 'verified_material']);
    Route::post('/verified_material_ajax', [MaterialEntryController::class, 'get_verified_material_ajax']);
        Route::get('/pending_material', [MaterialEntryController::class, 'pending_material']);
    Route::post('/pending_material_ajax', [MaterialEntryController::class, 'get_pending_material_ajax']);
    Route::get('/approve_material_by_id', [MaterialEntryController::class, 'approve_material_by_id']);
    Route::get('/edit_material_entry', [MaterialEntryController::class, 'edit_material_entry']);
    Route::get('/reject_material_by_id', [MaterialEntryController::class, 'reject_material_by_id']);
    Route::post('/updatematerialEntry', [MaterialEntryController::class, 'updatematerialEntry']);
    Route::post('/add_material_bill_info', [MaterialEntryController::class, 'add_material_bill_info']);
    Route::post('/update_material_bill_info', [MaterialEntryController::class, 'update_material_bill_info']);


    // Material Stock Routes
    Route::get('/new_consumption', [StockController::class, 'new_consumption']);
    Route::post('/add_new_consumption', [StockController::class, 'add_new_consumption']);
    Route::get('/verified_consumption', [StockController::class, 'verified_consumption']);
    Route::get('/pending_consumption', [StockController::class, 'pending_consumption']);
    Route::get('/reject_consumption_by_id', [StockController::class, 'reject_consumption_by_id']);
    Route::get('/reject_wastage_by_id', [StockController::class, 'reject_wastage_by_id']);
    Route::get('/edit_consumption_entry', [StockController::class, 'edit_consumption_entry']);
    Route::get('/edit_wastage_entry', [StockController::class, 'edit_wastage_entry']);
    Route::get('/approve_consumption_by_id', [StockController::class, 'approve_consumption_by_id']);
    Route::get('/approve_wastage_by_id', [StockController::class, 'approve_wastage_by_id']);
    Route::post('/updateconsumptionEntry', [StockController::class, 'updateconsumptionEntry']);
    Route::post('/updatewastageEntry', [StockController::class, 'updatewastageEntry']);

    Route::post('/update_consumption', [StockController::class, 'update_consumption']);
    Route::post('/update_wastage', [StockController::class, 'update_wastage']);

    Route::get('/stock_dashboard', [StockController::class, 'stock_dashboard']);
   
    Route::get('/stock_site_transfer', [StockController::class, 'stock_site_transfer']);

    Route::get('/newMaterialSiteTransfer', [StockController::class, 'newMaterialSiteTransfer']);
    Route::get('/deleteMaterialTransferForm', [StockController::class, 'deleteMaterialTransferForm']);    
    Route::post('/newMaterialTransferForm', [StockController::class, 'newMaterialTransferForm']);
   

    Route::get('/stock_unit_conversion', [StockController::class, 'stock_unit_conversion']);

    Route::get('/newStockUnitConversion', [StockController::class, 'newStockUnitConversion']);
    Route::get('/deleteStockUnitConversion', [StockController::class, 'deleteStockUnitConversion']);    
    Route::post('/newStockUnitConversionForm', [StockController::class, 'newStockUnitConversionForm']);
    
    Route::get('/view_mat_transaction', [StockController::class, 'view_mat_transaction']);    

    Route::get('/reconsilation_list', [StockController::class, 'reconsilation_list']);    
    Route::post('/request_reconsilation', [StockController::class, 'request_reconsilation']);    
    Route::get('/view_reconsilation_detail', [StockController::class, 'view_reconsilation_detail']);    
    Route::get('/approve_reconsilation_detail', [StockController::class, 'approve_reconsilation_detail']);    
    Route::get('/reject_reconsilation_detail', [StockController::class, 'reject_reconsilation_detail']);    
    Route::get('/update_stock_reconsilation', [StockController::class, 'update_stock_reconsilation']);    
    Route::get('/delete_reconsilation', [StockController::class, 'delete_reconsilation']);    
    Route::post('/upload_reconsilation', [StockController::class, 'upload_reconsilation']);    
    
    
    //billing routes
    Route::group(['middleware' => ['module.access:4']], function () {
        Route::get('/billparty', [BillPartyController::class, 'index']);
        Route::post('/addbillparty', [BillPartyController::class, 'addbillparty']);
        Route::get('/edit_billparty', [BillPartyController::class, 'edit_billparty']);
        Route::get('/delete_billparty', [BillPartyController::class, 'delete_billparty']);
        Route::post('/updatebillparty', [BillPartyController::class, 'updatebillparty']);
        Route::get('/update_bill_party_status', [BillPartyController::class, 'update_bill_party_status']);
        Route::post('/addBillPartyBalance', [BillPartyController::class, 'addBillPartyBalance']);
        Route::post('/updateBillPartyBalance', [BillPartyController::class, 'updateBillPartyBalance']);
        Route::get('/bill_report', [NewBillController::class, 'bill_report']);
        Route::get('/bill_party_payment', [BillPartyController::class, 'bill_party_payment']);
        Route::post('/sitebillreport', [NewBillController::class, 'sitebillreport']);

        Route::get('/billwork', [BillsWorkController::class, 'index']);
        Route::post('/addbillwork', [BillsWorkController::class, 'addbillwork']);
        Route::get('/edit_billwork', [BillsWorkController::class, 'edit_billwork']);
        Route::get('/delete_billwork', [BillsWorkController::class, 'delete_billwork']);
        Route::post('/updatebillwork', [BillsWorkController::class, 'updatebillwork']);
        Route::get('/getsitebillworks', [BillsWorkController::class, 'getsitebillworks']);
        Route::get('/getsitebillworkrates', [BillsWorkController::class, 'getsitebillworkrates']);

        Route::get('/billrate', [BillRateController::class, 'index']);
        Route::post('/addbillrate', [BillRateController::class, 'addbillrate']);
        Route::get('/edit_billrate', [BillRateController::class, 'edit_billrate']);
        Route::get('/delete_billrate', [BillRateController::class, 'delete_billrate']);
        Route::post('/updatebillrate', [BillRateController::class, 'updatebillrate']);
        Route::post('/ bills_rate_party_report', [BillRateController::class, ' bills_rate_party_report']);

        Route::get('/new_bill', [NewBillController::class, 'new_bill']);
        Route::post('/addnewbill', [NewBillController::class, 'addnewbill']);
        Route::get('/verified_bill', [NewBillController::class, 'verified_bill']);
        Route::get('/pending_bill', [NewBillController::class, 'pending_bill']);
        Route::get('/reject_bill_by_id', [NewBillController::class, 'reject_bill_by_id']);
        Route::get('/approve_bill_by_id', [NewBillController::class, 'approve_bill_by_id']);
        Route::get('/edit_bill', [NewBillController::class, 'edit_bill']);
        Route::get('/view_bill', [NewBillController::class, 'view_bill']);
        Route::get('/bill_pdf', [NewBillController::class, 'bill_pdf']);
        Route::post('/updateBill', [NewBillController::class, 'updateBill']);
        Route::post('/updateEditBill', [NewBillController::class, 'updateEditBill']);
        Route::post('/site_bills_report', [NewBillController::class, 'site_bills_report']);
    });


    //Assets route route
    Route::group(['middleware' => ['module.access:5']], function () {
        Route::get('/asset', [AssetController::class, 'asset']);
        Route::post('/add_newassets', [AssetController::class, 'add_newassets']);
        Route::post('/addasset', [AssetController::class, 'addasset']);
        Route::get('/edit_asset', [AssetController::class, 'edit_asset']);
        Route::get('/delete_asset', [AssetController::class, 'delete_asset']);
        Route::post('/updateasset', [AssetController::class, 'updateasset']);
        Route::post('/transferasset', [AssetController::class, 'transferasset']);
        Route::post('/searchasset', [AssetController::class, 'searchasset']);
        Route::post('/soldasset', [AssetController::class, 'soldasset']);
        Route::get('/assetTransferHistory', [AssetController::class, 'assetTransferHistory']);
        Route::get('/asset_head', [AssetController::class, 'asset_head']);
        Route::post('/search_asset_head_sites', [AssetController::class, 'search_asset_head_sites']);
        Route::post('/addassethead', [AssetController::class, 'addassethead']);
        Route::get('/edit_asset_head', [AssetController::class, 'edit_asset_head']);
        Route::get('/delete_asset_head', [AssetController::class, 'delete_asset_head']);
        Route::post('/updateassethead', [AssetController::class, 'updateassethead']);
        Route::post('/addassetExpensehead', [AssetController::class, 'addassetExpensehead']);
        Route::get('/asset_expense_head', [AssetController::class, 'asset_expense_head']);
        Route::get('/assets_report', [AssetController::class, 'assets_report']);
        Route::post('/assetreport', [AssetController::class, 'assetreport']);
        Route::Post('/asset_of_site_report', [AssetController::class, 'asset_of_site_report']);
        Route::get('/delete_assetExpense_head', [AssetController::class, 'delete_assetExpense_head']);
    });

    // machinery routes
    Route::group(['middleware' => ['module.access:6']], function () {
        Route::get('/machinery', [MachineryController::class, 'machinery']);
        Route::post('/add_newmechinery', [MachineryController::class, 'add_newmechinery']);
        Route::post('/addmachinery', [MachineryController::class, 'addmachinery']);
        Route::get('/edit_machinery', [MachineryController::class, 'edit_machinery']);
        Route::get('/delete_machinery', [MachineryController::class, 'delete_machinery']);
        Route::post('/updatemachinery', [MachineryController::class, 'updatemachinery']);
        Route::post('/transfermachinery', [MachineryController::class, 'transfermachinery']);
        Route::post('/search_machinery_head_sites', [MachineryController::class, 'search_machinery_head_sites']);
        Route::post('/soldmachinery', [MachineryController::class, 'soldmachinery']);
        Route::get('/machineryTransferHistory', [MachineryController::class, 'machineryTransferHistory']);
        Route::get('/machinery_head', [MachineryController::class, 'machinery_head']);
        Route::post('/addmachineryhead', [MachineryController::class, 'addmachineryhead']);
        Route::get('/edit_machinery_head', [MachineryController::class, 'edit_machinery_head']);
        Route::get('/delete_machinery_head', [MachineryController::class, 'delete_machinery_head']);
        Route::post('/updatemachineryhead', [MachineryController::class, 'updatemachineryhead']);
        Route::post('/addmachineryExpensehead', [MachineryController::class, 'addmachineryExpensehead']);
        Route::get('/machinery_expense_head', [MachineryController::class, 'machinery_expense_head']);
        Route::get('/machinery_report', [MachineryController::class, 'machinery_report']);
        Route::Post('/machinery_of_site_report', [MachineryController::class, 'machinery_of_site_report']);
        Route::Post('/machineryexport', [MachineryController::class, 'machineryexport']);
        Route::get('/delete_machineryExpense_head', [MachineryController::class, 'delete_machineryExpense_head']);
        Route::get('/machineryDocuments', [MachineryController::class, 'machineryDocuments']);
        Route::get('/delete_machinery_document', [MachineryController::class, 'delete_machinery_document']);
        Route::get('/edit_machinery_document', [MachineryController::class, 'edit_machinery_document']);
        Route::post('/addmachinerydocument', [MachineryController::class, 'addmachinerydocument']);
        Route::post('/updatemachinerydocument', [MachineryController::class, 'updatemachinerydocument']);
        Route::get('/machineryService', [MachineryController::class, 'machineryService']);
        Route::get('/delete_machinery_service', [MachineryController::class, 'delete_machinery_service']);
        Route::get('/edit_machinery_service', [MachineryController::class, 'edit_machinery_service']);
        Route::post('/addmachineryservice', [MachineryController::class, 'addmachineryservice']);
        Route::post('/updatemachineryservice', [MachineryController::class, 'updatemachineryservice']);
    });

    //sales module routes 
    Route::group(['middleware' => ['module.access:7']], function () {
        Route::get('/sales_parties', [PartiesController::class, 'sales_parties']);
        Route::get('/delete_sales_party', [PartiesController::class, 'delete_sales_party']);
        Route::get('/update_sales_party_status', [PartiesController::class, 'update_sales_party_status']);
        Route::get('/edit_sales_party', [PartiesController::class, 'edit_sales_party']);
        Route::post('/addsalesparty', [PartiesController::class, 'addsalesparty']);
        Route::post('/updatesalesparty', [PartiesController::class, 'updatesalesparty']);

        Route::get('/sales_project', [ProjectController::class, 'sales_project']);
        Route::get('/delete_sales_project', [ProjectController::class, 'delete_sales_project']);
        Route::get('/update_sales_project_status', [ProjectController::class, 'update_sales_project_status']);
        Route::get('/edit_sales_project', [ProjectController::class, 'edit_sales_project']);
        Route::post('/addsalesproject', [ProjectController::class, 'addsalesproject']);
        Route::post('/updatesalesproject', [ProjectController::class, 'updatesalesproject']);

        Route::get('/sales_inv_head', [InvoiceHeadsController::class, 'sales_inv_head']);
        Route::get('/delete_sales_inv_head', [InvoiceHeadsController::class, 'delete_sales_inv_head']);
        Route::get('/edit_sales_inv_head', [InvoiceHeadsController::class, 'edit_sales_inv_head']);
        Route::post('/addsalesinv_head', [InvoiceHeadsController::class, 'addsalesinv_head']);
        Route::post('/updatesalesinv_head', [InvoiceHeadsController::class, 'updatesalesinv_head']);

        Route::get('/sales_invoice', [InvoiceController::class, 'sales_invoice']);
        Route::get('/delete_sales_invoice', [InvoiceController::class, 'delete_sales_invoice']);
        Route::get('/edit_sales_invoice', [InvoiceController::class, 'edit_sales_invoice']);
        Route::post('/addsalesinvoice', [InvoiceController::class, 'addsalesinvoice']);
        Route::post('/updatesalesinvoice', [InvoiceController::class, 'updatesalesinvoice']);
        Route::get('/update_sales_invoice_status', [InvoiceController::class, 'update_sales_invoice_status']);
        Route::get('/all_sales_invoice', [InvoiceController::class, 'all_sales_invoice']);
        Route::get('/sales_pdf', [InvoiceController::class, 'sales_pdf']);
        Route::get('/sales_report', [InvoiceController::class, 'sales_report']);

        Route::get('/sales_manage_invoice', [InvoiceManageController::class, 'sales_manage_invoice']);
        Route::get('/delete_sales_manage_invoice', [InvoiceManageController::class, 'delete_sales_manage_invoice']);
        Route::get('/edit_sales_manage_invoice', [InvoiceManageController::class, 'edit_sales_manage_invoice']);
        Route::post('/addsales_manage_invoice', [InvoiceManageController::class, 'addsales_manage_invoice']);
        Route::post('/updatesales_manage_invoice', [InvoiceManageController::class, 'updatesales_manage_invoice']);
    });

    //payment voucher routes
    Route::group(['middleware' => ['module.access:8']], function () {
        Route::get('/otherparty', [OtherPartiesController::class, 'index']);
        Route::post('/addotherparty', [OtherPartiesController::class, 'addotherparty']);
        Route::get('/edit_otherparty', [OtherPartiesController::class, 'edit_otherparty']);
        Route::get('/delete_otherparty', [OtherPartiesController::class, 'delete_otherparty']);
        Route::post('/updateotherparty', [OtherPartiesController::class, 'updateotherparty']);
        Route::get('/update_other_party_status', [OtherPartiesController::class, 'update_other_party_status']);

        Route::get('/voucher_pdf', [PaymentVoucherController::class, 'voucher_pdf']);
        Route::get('/new_paymentvoucher', [PaymentVoucherController::class, 'new_paymentvoucher']);
        Route::get('/edit_paymentvoucher', [PaymentVoucherController::class, 'edit_paymentvoucher']);
        Route::post('/addnewpaymentvouchers', [PaymentVoucherController::class, 'addnewpaymentvouchers']);
        Route::post('/updatepaymentvouchers', [PaymentVoucherController::class, 'updatepaymentvouchers']);
        Route::post('/updateEditpaymentvouchers', [PaymentVoucherController::class, 'updateEditpaymentvouchers']);
        Route::get('/verified_paymentvoucher', [PaymentVoucherController::class, 'verified_paymentvoucher']);
        Route::post('/addpaymentvoucherpayment', [PaymentVoucherController::class, 'addpaymentvoucherpayment']);
        Route::get('/paid_paymentvoucher', [PaymentVoucherController::class, 'paid_paymentvoucher']);
        Route::get('/reject_paymentvoucher_by_id', [PaymentVoucherController::class, 'reject_paymentvoucher_by_id']);
        Route::get('/approve_paymentvoucher_by_id', [PaymentVoucherController::class, 'approve_paymentvoucher_by_id']);
        Route::get('/pending_paymentvoucher', [PaymentVoucherController::class, 'pending_paymentvoucher']);
        Route::get('/payment_report', [PaymentVoucherController::class, 'payment_report']);
        Route::post('/paymentvoucherreport', [PaymentVoucherController::class, 'paymentvoucherreport']);
        Route::get('/reject_Paidpaymentvoucher_by_id', [PaymentVoucherController::class, 'reject_Paidpaymentvoucher_by_id']);
    });

    // management routes route
    Route::group(['middleware' => ['module.access:9']], function () {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::get('/changetheme/{color}', [SettingsController::class, 'changetheme']);
        Route::get('/menutheme/{themecolor}', [SettingsController::class, 'menutheme']);
        Route::post('/changecolor', [SettingsController::class, 'changecolor']);
        Route::post('/updatebillsequence', [SettingsController::class, 'updatebillsequence']);
        Route::post('/updatepaymentvouchersequence', [SettingsController::class, 'updatepaymentvouchersequence']);
        Route::post('/updatecurrency', [SettingsController::class, 'updatecurrency']);
        Route::post('/updateuploadsrc', [SettingsController::class, 'updateuploadsrc']);
        
        Route::get('/sales_companies', [CompanyController::class, 'sales_companies']);
        Route::get('/delete_sales_company', [CompanyController::class, 'delete_sales_company']);
        Route::get('/update_sales_company_status', [CompanyController::class, 'update_sales_company_status']);
        Route::get('/edit_sales_company', [CompanyController::class, 'edit_sales_company']);
        Route::post('/addsalescompany', [CompanyController::class, 'addsalescompany']);
        Route::post('/updatesalescompany', [CompanyController::class, 'updatesalescompany']);
        Route::get('/sales_report', [CompanyController::class, 'sales_report']);
        Route::post('/salesreport', [CompanyController::class, 'salesreport']);

        Route::get('/activity', [CompanyController::class, 'activity']);
        Route::post('/moduleActivity', [CompanyController::class, 'moduleActivity']);
    });

    // contact routes
    Route::group(['middleware' => ['module.access:10']], function () {
        Route::get('/contacts', [ContactController::class, 'index']);
        Route::post('/addnew_company', [ContactController::class, 'addcompany']);
        Route::get('/edit_company', [ContactController::class, 'edit_company']);
        Route::post('/updatecompany', [ContactController::class, 'update_company']);
        Route::get('/delete_company_profile', [ContactController::class, 'delete_company_profile']);
        Route::get('/delete_contact', [ContactController::class, 'delete_contact']);
        Route::post('/update_contact', [ContactController::class, 'update_contact']);
        Route::get('/get_contact_data', [ContactController::class, 'getContactdata']);
        Route::get('/edit_contact', [ContactController::class, 'edit_contact']);
        Route::get('/contact_form', [ContactController::class, 'contact_form']);
        Route::post('/add_contact', [ContactController::class, 'addcontact']);
    });

    // document module
    Route::group(['middleware' => ['module.access:11']], function () {
        Route::get('/file-structure', [DocumentController::class, 'structure']);
        Route::post('/adddochead', [DocumentController::class, 'adddochead']);
        Route::post('/adddocheadoption', [DocumentController::class, 'adddocheadoption']);
        Route::post('/updatedochead', [DocumentController::class, 'updatedochead']);
        Route::get('/delete_doc_head', [DocumentController::class, 'delete_doc_head']);
        Route::get('/delete_doc_head_option', [DocumentController::class, 'delete_doc_head_option']);
        Route::post('/updatedocheadoption', [DocumentController::class, 'updatedocheadoption']);
        Route::post('/my_doc_upload_file', [DocumentController::class, 'my_doc_upload_file']);
        Route::post('/update_my_doc_upload_file', [DocumentController::class, 'update_my_doc_upload_file']);
        Route::get('/fetchLinkedData', [DocumentController::class, 'fetchLinkedData']);
        Route::get('/getDocListByHeadId', [DocumentController::class, 'getDocListByHeadId']);
        Route::get('deleteDoc', [DocumentController::class, 'deleteDoc']);
        Route::get('approveDoc', [DocumentController::class, 'approveDoc']);
    });

    // excelimport file------------
    Route::post('/excel-import', [ExcelImportController::class, 'import']);
});
