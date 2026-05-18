<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\ExpenseController;
use App\Http\Controllers\api\UserController;
use App\Http\Controllers\api\MaterialController;
use App\Http\Controllers\api\SiteBillsController;
use App\Http\Controllers\api\AssetsController;
use App\Http\Controllers\api\MachineryController;
use App\Http\Controllers\api\ChatController;
use App\Http\Controllers\api\CompanyRegistrationController;
use App\Http\Controllers\api\CompanyPlanController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\api\ApiAuthController;
use App\Http\Controllers\api\ApiPaymentVoucherController;
use App\Http\Controllers\api\ApiDashboardController;
use App\Http\Controllers\api\ApiExpenseController;
use App\Http\Controllers\api\ApiMaterialController;
use App\Http\Controllers\api\ApiManagementController;
use App\Http\Controllers\api\ApiDocumentController;
use App\Http\Controllers\api\ApiResourceController;
use App\Http\Controllers\api\ApiSiteBillsController;
use App\Http\Controllers\api\ApiAssetMachineryController;
use App\Http\Controllers\api\ApiSalesController;
use App\Http\Controllers\api\ApiContactController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/



Route::get('/info', function() {
    return response()->json([
        'latest_commit' => trim(shell_exec('git log -1 --format="%H | %s | %cr"')),
        'server_time' => now()->toDateTimeString(),
        'status' => 'online'
    ]);
});

Route::get('/get_all_data', [UserController::class, 'get_all_data']);
Route::get('/api_login', [UserController::class, 'login']);
Route::post('/register_company', [CompanyRegistrationController::class, 'register_company']);
Route::match(['get', 'post'], '/get_companies', [CompanyRegistrationController::class, 'get_companies']);
Route::match(['get', 'post'], '/search_companies', [CompanyRegistrationController::class, 'search_companies']);
Route::post('/add_company_plan', [CompanyPlanController::class, 'add_company_plan']);
Route::post('/get_users', [UserController::class, 'get_users']);
Route::post('/get_sites', [UserController::class, 'get_sites']);
Route::get('/clear_config_cache', function() {
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    return response()->json(['status' => true, 'message' => 'Configuration cache cleared successfully']);
});
Route::post('/get_modules', [UserController::class, 'get_modules']);
Route::middleware(['api-tenant-bootstrap'])->group(function () {
    Route::match(['get', 'post'], '/get_roles', [UserController::class, 'get_roles']);
    Route::match(['get', 'post'], '/search_roles', [UserController::class, 'search_roles']);
});
Route::get('/get_all_modules', [UserController::class, 'get_all_modules']);
Route::post('/get_permission', [UserController::class, 'get_permission']);
Route::post('/get_site_transaction', [UserController::class, 'get_site_transaction']);
Route::post('/update_fcm_id', [UserController::class, 'update_fcm_id']);

  Route::post('/update_profile_picture', [UserController::class, 'update_profile_picture']);
  Route::post('/update_profile', [UserController::class, 'update_profile']);

Route::post('/get_chat_data', [ChatController::class, 'index']);

// expenses

Route::post('get_expense_head', [ExpenseController::class, 'get_expense_head']);
Route::post('get_expense_party', [ExpenseController::class, 'get_expense_party']);
Route::post('get_expense', [ExpenseController::class, 'get_expense']);
Route::post('addexpenseparty', [ExpenseController::class, 'addexpenseparty']);
Route::post('addexpense', [ExpenseController::class, 'addexpense']);

Route::post('uploadexpenseimage', [ExpenseController::class, 'uploadexpenseimage']);
// materials

Route::post('get_materials', [MaterialController::class, 'get_materials']);
Route::post('get_material_supplier', [MaterialController::class, 'get_material_supplier']);
Route::post('get_material_entry', [MaterialController::class, 'get_material_entry']);
Route::post('get_material_unit', [MaterialController::class, 'get_material_unit']);
Route::post('addmaterialentry', [MaterialController::class, 'addmaterialentry']);

Route::post('addmaterialentryImage', [MaterialController::class, 'addmaterialentryImage']);


//site bills
Route::post('get_site_bill_work', [SiteBillsController::class, 'get_site_bill_work']);
Route::post('get_bill_parties', [SiteBillsController::class, 'get_bill_parties']);
Route::post('get_bill_entries', [SiteBillsController::class, 'get_bill_entries']);
Route::post('get_bill_item_entries', [SiteBillsController::class, 'get_bill_item_entries']);
        Route::post('addbillparty', [SiteBillsController::class, 'addbillparty']);
Route::post('addnewbill', [SiteBillsController::class, 'addnewbill']);
Route::post('getBillPartyBalance', [SiteBillsController::class, 'getBillPartyBalance']);
Route::post('get_site_bill_work_name', [SiteBillsController::class, 'get_site_bill_work_name']);



//assetsController
Route::post('get_assets', [AssetsController::class, 'get_assets']);
Route::post('soldasset', [AssetsController::class, 'soldasset']);


// machinery Routes
Route::post('get_machineries', [MachineryController::class, 'get_machineries']);
Route::post('get_machinery_documents', [MachineryController::class, 'get_machinery_documents']);
Route::post('get_machinery_service', [MachineryController::class, 'get_machinery_service']);
Route::post('addmachinerydocument', [MachineryController::class, 'addmachinerydocument']);
Route::post('addmachineryservice', [MachineryController::class, 'addmachineryservice']);
Route::post('soldmachinery', [MachineryController::class, 'soldmachinery']);

// doc route

Route::post('searchDocByFilter', [DocumentController::class, 'searchDocByFilter']);
Route::post('my_doc_upload_file', [UserController::class, 'my_doc_upload_file']);

// --- New Flutter App APIs (v1) ---
Route::prefix('v1')->group(function () {
    // Public routes (tenant not needed yet as login handles switching)
    Route::post('/login', [ApiAuthController::class, 'login']);

    // Protected routes (tenant middleware handles DB switching)
    Route::middleware(['api-tenant-bootstrap', 'auth:sanctum', 'tenant'])->group(function () {
        Route::post('/logout', [ApiAuthController::class, 'logout']);
        Route::get('/dashboard', [ApiDashboardController::class, 'index']);
        Route::get('/dashboard/sales-invoices', [ApiDashboardController::class, 'salesInvoices']);
        Route::get('/dashboard/payment-vouchers', [ApiDashboardController::class, 'paymentVouchers']);
        Route::get('/dashboard/expenses', [ApiDashboardController::class, 'expenses']);
        Route::get('/dashboard/bills', [ApiDashboardController::class, 'bills']);
        Route::get('/dashboard/assets', [ApiDashboardController::class, 'assets']);
        Route::get('/dashboard/machinery', [ApiDashboardController::class, 'machinery']);
        // Expenses
        Route::get('/expenses/summary', [ApiExpenseController::class, 'summary']);
        Route::get('/expenses', [ApiExpenseController::class, 'index']);
        Route::get('/expenses/export', [ApiExpenseController::class, 'export']);
        Route::post('/expenses', [ApiManagementController::class, 'storeExpense']);
        Route::post('/expenses/bulk-status', [ApiManagementController::class, 'bulkUpdateExpenseStatus']);
        Route::post('/expenses/bulk', [ApiManagementController::class, 'bulkStoreExpenses']);
        Route::match(['get', 'post'], '/expenses/report', [ApiExpenseController::class, 'report']);
        Route::get('/expenses/{id}', [ApiExpenseController::class, 'show']);
        Route::post('/expenses/{id}', [ApiManagementController::class, 'updateExpense']);
        Route::delete('/expenses/{id}', [ApiExpenseController::class, 'destroy']);

        // Material Suppliers (Management) - MUST be above generic {id} routes to avoid collisions
        Route::get('/materials/suppliers', [ApiManagementController::class, 'listMaterialSuppliers']);
        Route::get('/materials/suppliers/{id}', [ApiManagementController::class, 'getMaterialSupplier']);
        Route::post('/materials/suppliers', [ApiManagementController::class, 'storeMaterialSupplier']);
        Route::post('/materials/suppliers/bulk-status', [ApiManagementController::class, 'bulkUpdateMaterialSuppliersStatus']);
        Route::get('/materials/suppliers/export/csv', [ApiManagementController::class, 'exportMaterialSuppliersCsv']);
        Route::post('/materials/suppliers/bulk-delete', [ApiManagementController::class, 'bulkDeleteMaterialSuppliers']);
        Route::post('/materials/suppliers/{id}', [ApiManagementController::class, 'updateMaterialSupplier']);
        Route::delete('/materials/suppliers/{id}', [ApiManagementController::class, 'deleteMaterialSupplier'])->where('id', '[0-9,]+');
        
        // Material Master (SKUs)
        Route::get('/materials-master', [ApiManagementController::class, 'listMaterialsMaster']);
        Route::get('/materials-master/export/csv', [ApiManagementController::class, 'exportMaterialsMasterCsv']);
        Route::get('/materials-master/{id}', [ApiManagementController::class, 'getMaterialMaster']);
        Route::post('/materials-master', [ApiManagementController::class, 'storeMaterialMaster']);
        Route::post('/materials-master/bulk-delete', [ApiManagementController::class, 'bulkDeleteMaterialsMaster']);
        Route::post('/materials-master/{id}', [ApiManagementController::class, 'updateMaterialMaster']);
        Route::delete('/materials-master/{id}', [ApiManagementController::class, 'deleteMaterialMaster'])->where('id', '[0-9,]+');
        
        // Material Conversions
        Route::get('/materials-master/{id}/conversions', [ApiManagementController::class, 'listMaterialConversions']);
        Route::get('/materials-master/{id}/conversions/export/csv', [ApiManagementController::class, 'exportMaterialConversionsCsv']);
        Route::post('/materials-master/{id}/conversions', [ApiManagementController::class, 'storeMaterialConversion']);
        Route::delete('/materials-master/{id}/conversions/{rule_id}', [ApiManagementController::class, 'deleteMaterialConversion']);

        // Material Units (Management)
        Route::get('/materials/units', [ApiManagementController::class, 'listMaterialUnits']);
        Route::get('/materials/units/{id}', [ApiManagementController::class, 'getMaterialUnit']);
        Route::get('/materials/units/export/csv', [ApiManagementController::class, 'exportMaterialUnitsCsv']);
        Route::post('/materials/units', [ApiManagementController::class, 'storeMaterialUnit']);
        Route::post('/materials/units/bulk-delete', [ApiManagementController::class, 'bulkDeleteMaterialUnits']);
        Route::post('/materials/units/{id}', [ApiManagementController::class, 'updateMaterialUnit']);
        Route::delete('/materials/units/{id}', [ApiManagementController::class, 'deleteMaterialUnit'])->where('id', '[0-9,]+');

        // Material Entries (Transactions)
        Route::get('/materials/entries/pending', [ApiManagementController::class, 'listPendingMaterialEntries']);
        Route::get('/materials/entries/pending/export/csv', [ApiManagementController::class, 'exportPendingMaterialEntriesCsv']);
        Route::get('/materials/entries/verified', [ApiManagementController::class, 'listVerifiedMaterialEntries']);
        Route::get('/materials/entries/verified/export/csv', [ApiManagementController::class, 'exportVerifiedMaterialEntriesCsv']);
        
        // Material Reports
        Route::match(['get', 'post'], '/materials/report', [ApiManagementController::class, 'generateMaterialReport']);
        Route::match(['get', 'post'], '/material/report', [ApiManagementController::class, 'generateMaterialReport']);

        Route::post('/materials/entries', [ApiManagementController::class, 'storeMaterialEntry']);
        Route::get('/materials/entries/{id}', [ApiManagementController::class, 'getMaterialEntry']);
        Route::post('/materials/entries/{id}', [ApiManagementController::class, 'updateMaterialEntry']);
        Route::delete('/materials/entries/{id}', [ApiManagementController::class, 'deleteMaterialEntry'])->where('id', '[0-9,]+');
        Route::post('/materials/entries/{id}/approve', [ApiManagementController::class, 'approveMaterialEntry'])->where('id', '[0-9,]+');
        Route::post('/materials/entries/{id}/reject', [ApiManagementController::class, 'rejectMaterialEntry'])->where('id', '[0-9,]+');

        // Explicit Bulk Routes
        Route::post('/materials/entries/bulk/approve', [ApiManagementController::class, 'approveMaterialEntry']);
        Route::post('/materials/entries/bulk/reject', [ApiManagementController::class, 'rejectMaterialEntry']);
        Route::post('/materials/entries/bulk/delete', [ApiManagementController::class, 'deleteMaterialEntry']);

        // Material Stock
        Route::get('/materials/stock/dashboard', [ApiManagementController::class, 'getStockDashboard']);
        Route::get('/materials/stock/transactions', [ApiManagementController::class, 'getStockTransactions']);
        Route::get('/materials/stock/transactions/export/csv', [ApiManagementController::class, 'exportStockTransactionsCsv']);
        Route::get('/materials/stock/transfers', [ApiManagementController::class, 'getStockSiteTransfers']);
        Route::post('/materials/stock/transfers', [ApiManagementController::class, 'storeStockSiteTransfer']);
        Route::delete('/materials/stock/transfers/{id}', [ApiManagementController::class, 'deleteStockSiteTransfer']);

        Route::get('/materials/stock/conversions', [ApiManagementController::class, 'getStockUnitConversions']);
        Route::post('/materials/stock/conversions', [ApiManagementController::class, 'storeStockUnitConversion']);
        Route::delete('/materials/stock/conversions/{id}', [ApiManagementController::class, 'deleteStockUnitConversion']);
        Route::get('/materials/reconciliation', [ApiManagementController::class, 'listReconciliation']);
        Route::post('/materials/reconciliation', [ApiManagementController::class, 'storeReconciliationRequest']);
        Route::get('/materials/reconciliation/export', [ApiManagementController::class, 'exportReconciliationCsv']);
        Route::get('/materials/reconciliation/{id}', [ApiManagementController::class, 'getReconciliationDetails']);
        Route::patch('/materials/reconciliation/{id}', [ApiManagementController::class, 'updateReconciliationData']);
        Route::delete('/materials/reconciliation/{id}', [ApiManagementController::class, 'deleteReconciliation']);
        Route::post('/materials/reconciliation/{id}/verify', [ApiManagementController::class, 'verifyReconciliation']);
        Route::post('/materials/reconciliation/{id}/reject', [ApiManagementController::class, 'rejectReconciliation']);
        Route::post('/materials/reconciliation/{id}/approve-update', [ApiManagementController::class, 'approveAndUpdateStock']);
        
        // Bill Parties
        Route::get('/bill-parties', [ApiManagementController::class, 'listBillParties']);
        Route::get('/bill-parties/export', [ApiManagementController::class, 'exportBillPartiesCsv']);
        Route::post('/bill-parties', [ApiManagementController::class, 'storeBillParty']);
        Route::get('/bill-parties/{id}', [ApiManagementController::class, 'getBillPartyDetails']);
        Route::patch('/bill-parties/{id}', [ApiManagementController::class, 'updateBillParty']);
        Route::delete('/bill-parties/{id}', [ApiManagementController::class, 'deleteBillParty']);
        Route::post('/bill-parties/{id}/status', [ApiManagementController::class, 'updateBillPartyStatus']);
    
        // Bill Party Payments
        Route::get('/bill-party-payments', [ApiManagementController::class, 'listBillPartyPayments']);
        Route::post('/bill-party-payments', [ApiManagementController::class, 'storeBillPartyPayment']);
        Route::get('/bill-party-payments/export', [ApiManagementController::class, 'exportBillPartyPaymentsCsv']);
        Route::get('/bill-party-payments/{id}', [ApiManagementController::class, 'getBillPartyPaymentDetails']);
        Route::patch('/bill-party-payments/{id}', [ApiManagementController::class, 'updateBillPartyPayment']);

        // Bill Works
        Route::get('/bill-works', [ApiManagementController::class, 'listBillWorks']);
        Route::post('/bill-works', [ApiManagementController::class, 'storeBillWork']);
        Route::get('/bill-works/export', [ApiManagementController::class, 'exportBillWorksCsv']);
        Route::get('/bill-works/{id}', [ApiManagementController::class, 'getBillWorkDetails']);
        Route::patch('/bill-works/{id}', [ApiManagementController::class, 'updateBillWork']);
        Route::delete('/bill-works/{id}', [ApiManagementController::class, 'deleteBillWork']);

        // Bill Rates
        Route::get('/bill-rates', [ApiManagementController::class, 'listBillRates']);
        Route::post('/bill-rates', [ApiManagementController::class, 'storeBillRate']);
        Route::get('/bill-rates/export', [ApiManagementController::class, 'exportBillRatesCsv']);
        Route::get('/bill-rates/{id}', [ApiManagementController::class, 'getBillRateDetails']);
        Route::patch('/bill-rates/{id}', [ApiManagementController::class, 'updateBillRate']);
        Route::delete('/bill-rates/{id}', [ApiManagementController::class, 'deleteBillRate']);

        Route::post('/materials/consumption', [ApiManagementController::class, 'storeMaterialConsumption']);
        Route::post('/materials/wastage', [ApiManagementController::class, 'storeMaterialWastage']);
        Route::get('/materials/consumption/pending', [ApiManagementController::class, 'getPendingConsumption']);

        Route::get('/materials/consumption/verified', [ApiManagementController::class, 'getVerifiedConsumption']);
        Route::get('/materials/wastage/pending', [ApiManagementController::class, 'getPendingWastage']);
        Route::get('/materials/wastage/verified', [ApiManagementController::class, 'getVerifiedWastage']);
        Route::post('/materials/consumption/bulk/approve', [ApiManagementController::class, 'bulkApproveConsumption']);
        Route::post('/materials/consumption/bulk/reject', [ApiManagementController::class, 'bulkRejectConsumption']);
        Route::post('/materials/wastage/bulk/approve', [ApiManagementController::class, 'bulkApproveWastage']);
        Route::post('/materials/wastage/bulk/reject', [ApiManagementController::class, 'bulkRejectWastage']);
        Route::get('/materials/consumption/{id}', [ApiManagementController::class, 'getConsumptionDetails']);
        Route::get('/materials/wastage/{id}', [ApiManagementController::class, 'getWastageDetails']);
        Route::post('/materials/consumption/update/{id}', [ApiManagementController::class, 'updateMaterialConsumption']);
        Route::patch('/materials/consumption/{id}', [ApiManagementController::class, 'updateMaterialConsumption']);

        // Materials
        Route::get('/materials/summary', [ApiMaterialController::class, 'summary']);
        Route::get('/materials', [ApiMaterialController::class, 'index']);
        Route::post('/materials', [ApiMaterialController::class, 'store']);
        Route::post('/materials/{id}', [ApiMaterialController::class, 'update']);
        Route::delete('/materials/{id}', [ApiMaterialController::class, 'destroy']);

        // Site Bills
        Route::get('/bills/summary', [ApiSiteBillsController::class, 'summary']);
        Route::get('/bills/works-by-site', [ApiSiteBillsController::class, 'getSiteWorks']);
        Route::get('/bills', [ApiSiteBillsController::class, 'index']);
        Route::match(['get', 'post'], '/bills/report', [ApiSiteBillsController::class, 'report']);
        Route::get('/bills/{id}', [ApiSiteBillsController::class, 'show']);
        Route::post('/bills', [ApiSiteBillsController::class, 'store']);
        Route::post('/bills/{id}', [ApiSiteBillsController::class, 'update']);
        Route::delete('/bills/{id}', [ApiSiteBillsController::class, 'destroy']);

        // Site Bills (Management)
        Route::get('/management/bills/pending', [ApiManagementController::class, 'listPendingSiteBills']);
        Route::get('/management/bills/verified', [ApiManagementController::class, 'listVerifiedSiteBills']);
        Route::get('/management/bills', [ApiManagementController::class, 'listSiteBills']);
        Route::post('/management/bills', [ApiManagementController::class, 'storeSiteBill']);
        Route::get('/management/bills/{id}', [ApiManagementController::class, 'getSiteBillDetails']);
        Route::post('/management/bills/bulk-status', [ApiManagementController::class, 'bulkUpdateSiteBillStatus']);
        Route::patch('/management/bills/{id}', [ApiManagementController::class, 'updateSiteBill']);
        Route::delete('/management/bills/{id}', [ApiManagementController::class, 'deleteSiteBill']);

        // Assets
        Route::get('/assets/summary', [ApiAssetMachineryController::class, 'assetSummary']);
        Route::get('/assets/transfer-history', [ApiAssetMachineryController::class, 'assetTransferHistory']);
        Route::get('/assets', [ApiAssetMachineryController::class, 'listAssets']);
        Route::post('/assets', [ApiAssetMachineryController::class, 'storeAsset']);
        Route::post('/assets/{id}/transfer', [ApiAssetMachineryController::class, 'transferAsset']);
        Route::post('/assets/{id}/sell', [ApiAssetMachineryController::class, 'sellAsset']);
        Route::match(['get', 'post'], '/assets/report', [ApiAssetMachineryController::class, 'assetReport']);

        // Asset Heads (Management)
        Route::get('/asset-heads', [ApiAssetMachineryController::class, 'listAssetHeads']);
        Route::get('/asset-heads/{id}', [ApiAssetMachineryController::class, 'getAssetHead']);
        Route::post('/asset-heads', [ApiAssetMachineryController::class, 'storeAssetHead']);
        Route::post('/asset-heads/{id}', [ApiAssetMachineryController::class, 'updateAssetHead']);
        Route::delete('/asset-heads/{id}', [ApiAssetMachineryController::class, 'deleteAssetHead']);

        // Machinery
        Route::get('/machinery/summary', [ApiAssetMachineryController::class, 'machinerySummary']);
        Route::get('/machinery', [ApiAssetMachineryController::class, 'listMachinery']);
        Route::get('/machinery-heads', [ApiAssetMachineryController::class, 'listMachineryHeads']);
        Route::get('/machinery-heads/{id}', [ApiAssetMachineryController::class, 'getMachineryHead'])->where('id', '[0-9]+');
        Route::post('/machinery-heads/{id}', [ApiAssetMachineryController::class, 'updateMachineryHead'])->where('id', '[0-9]+');
        Route::delete('/machinery-heads/{id}', [ApiAssetMachineryController::class, 'deleteMachineryHead'])->where('id', '[0-9]+');
        Route::match(['get', 'post'], '/machinery/report', [ApiAssetMachineryController::class, 'machineryReport']);
        Route::get('/machinery/export/csv', [ApiAssetMachineryController::class, 'exportMachineryCsv']);
        Route::post('/machinery/heads', [ApiAssetMachineryController::class, 'storeMachineryHead']);
        Route::get('/machinery/{id}', [ApiAssetMachineryController::class, 'getMachinery'])->where('id', '[0-9]+');
        Route::post('/machinery/{id}', [ApiAssetMachineryController::class, 'updateMachinery'])->where('id', '[0-9]+');
        Route::delete('/machinery/{id}', [ApiAssetMachineryController::class, 'deleteMachinery'])->where('id', '[0-9]+');
        Route::post('/machinery', [ApiAssetMachineryController::class, 'storeMachinery']);
        Route::get('/machinery/{id}/documents', [ApiAssetMachineryController::class, 'machineryDocuments'])->where('id', '[0-9]+');
        Route::post('/machinery/{id}/documents', [ApiAssetMachineryController::class, 'storeMachineryDocument'])->where('id', '[0-9]+');
        Route::get('/machinery/{machinery_id}/documents/{id}', [ApiAssetMachineryController::class, 'getMachineryDocument'])->where(['machinery_id' => '[0-9]+', 'id' => '[0-9]+']);
        Route::post('/machinery/{machinery_id}/documents/{id}', [ApiAssetMachineryController::class, 'updateMachineryDocument'])->where(['machinery_id' => '[0-9]+', 'id' => '[0-9]+']);
        Route::delete('/machinery/{machinery_id}/documents/{id}', [ApiAssetMachineryController::class, 'deleteMachineryDocument'])->where(['machinery_id' => '[0-9]+', 'id' => '[0-9]+']);
        Route::get('/machinery/{id}/services', [ApiAssetMachineryController::class, 'machineryServices'])->where('id', '[0-9]+');
        Route::post('/machinery/{id}/services', [ApiAssetMachineryController::class, 'storeMachineryService'])->where('id', '[0-9]+');
        Route::get('/machinery/{machinery_id}/services/{id}', [ApiAssetMachineryController::class, 'getMachineryService'])->where(['machinery_id' => '[0-9]+', 'id' => '[0-9]+']);
        Route::post('/machinery/{machinery_id}/services/{id}', [ApiAssetMachineryController::class, 'updateMachineryService'])->where(['machinery_id' => '[0-9]+', 'id' => '[0-9]+']);
        Route::delete('/machinery/{machinery_id}/services/{id}', [ApiAssetMachineryController::class, 'deleteMachineryService'])->where(['machinery_id' => '[0-9]+', 'id' => '[0-9]+']);
        Route::get('/machinery/{id}/transfer-history', [ApiAssetMachineryController::class, 'machineryTransferHistory'])->where('id', '[0-9]+');
        Route::post('/machinery/{id}/transfer', [ApiAssetMachineryController::class, 'transferMachinery'])->where('id', '[0-9]+');
        Route::post('/machinery/{id}/sell', [ApiAssetMachineryController::class, 'sellMachinery'])->where('id', '[0-9]+');
        Route::get('/machinery-expense-heads', [ApiAssetMachineryController::class, 'listMachineryExpenseHeads']);
        Route::post('/machinery-expense-heads', [ApiAssetMachineryController::class, 'storeMachineryExpenseHead']);
        Route::delete('/machinery-expense-heads/{id}', [ApiAssetMachineryController::class, 'deleteMachineryExpenseHead']);

        // Asset Expense Heads
        Route::get('/asset-expense-heads', [ApiAssetMachineryController::class, 'listAssetExpenseHeads']);
        Route::post('/asset-expense-heads', [ApiAssetMachineryController::class, 'storeAssetExpenseHead']);
        Route::delete('/asset-expense-heads/{id}', [ApiAssetMachineryController::class, 'deleteAssetExpenseHead']);

        // Users (Management)
        Route::get('/users', [ApiManagementController::class, 'listUsers']);
        Route::get('/users/{id}', [ApiManagementController::class, 'getUser']);
        Route::get('/users/export/csv', [ApiManagementController::class, 'exportUsersCsv']);
        Route::get('/users/export/excel', [ApiManagementController::class, 'exportUsersExcel']);
        Route::get('/users/export/pdf', [ApiManagementController::class, 'exportUsersPdf']);
        Route::post('/users', [ApiManagementController::class, 'storeUser']);
        Route::post('/users/bulk-status', [ApiManagementController::class, 'bulkUpdateUsersStatus']);
        Route::post('/users/bulk-delete', [ApiManagementController::class, 'bulkDeleteUsers']);
        Route::post('/users/{id}', [ApiManagementController::class, 'updateUser']);
        Route::post('/users/{id}/status', [ApiManagementController::class, 'updateUserStatus']);
        Route::delete('/users/{id}', [ApiManagementController::class, 'deleteUser'])->where('id', '[0-9,]+');
        // Site Finances (Must be above generic {id} routes to avoid collisions)
        Route::get('/sites/{id}/payments', [ApiManagementController::class, 'getSitePayments']);
        Route::get('/sites/{id}/payments/export', [ApiManagementController::class, 'exportSitePayments']);
        Route::match(['get', 'post'], '/sites/payments', [ApiManagementController::class, 'listSitePayments']);
        Route::post('/sites/transfers', [ApiManagementController::class, 'transferSiteCash']);
        Route::match(['get', 'post'], '/sites/statement', [ApiManagementController::class, 'siteStatement']);


        // Sites (Management)
        Route::get('/sites', [ApiManagementController::class, 'listSites']);
        Route::get('/sites/export/csv', [ApiManagementController::class, 'exportSitesCsv']);
        Route::get('/sites/export/excel', [ApiManagementController::class, 'exportSitesExcel']);
        Route::get('/sites/export/pdf', [ApiManagementController::class, 'exportSitesPdf']);
        Route::post('/sites', [ApiManagementController::class, 'storeSite']);
        Route::post('/sites/bulk-status', [ApiManagementController::class, 'bulkUpdateSitesStatus']);
        Route::post('/sites/bulk-delete', [ApiManagementController::class, 'bulkDeleteSites']);
        Route::post('/sites/{id}', [ApiManagementController::class, 'updateSite']);
        Route::delete('/sites/{id}', [ApiManagementController::class, 'deleteSite']);

        // Roles (Management)
        Route::get('/roles', [ApiManagementController::class, 'listRoles']);
        Route::get('/roles/export/csv', [ApiManagementController::class, 'exportRolesCsv']);
        Route::get('/roles/export/excel', [ApiManagementController::class, 'exportRolesExcel']);
        Route::get('/roles/export/pdf', [ApiManagementController::class, 'exportRolesPdf']);
        Route::post('/roles', [ApiManagementController::class, 'storeRole']);
        Route::post('/roles/{id}', [ApiManagementController::class, 'updateRole']);
        
        // Role Settings (Separate API)
        Route::get('/role_setting/{id}', [ApiManagementController::class, 'getRoleSetting']);
        Route::post('/role_setting/{id}', [ApiManagementController::class, 'updateRoleSetting']);
                Route::delete('/roles/{id}', [ApiManagementController::class, 'deleteRole']);
        
        // Permissions (Management)
        Route::get('/roles/{id}/permissions', [ApiManagementController::class, 'listRolePermissions']);
        Route::post('/roles/{id}/permissions', [ApiManagementController::class, 'updateRolePermissions']);
        Route::post('/roles/manage/{id}', [ApiManagementController::class, 'updateRole']);

        // Permissions
        Route::get('/users/{id}/permissions', [ApiManagementController::class, 'listUserPermissions']);
        Route::post('/users/{id}/permissions', [ApiManagementController::class, 'updateUserPermissions']);

        // Expenses (Consolidated above)
        Route::get('expense-heads', [ApiManagementController::class, 'listExpenseHeads']);
        Route::get('bills-parties', [ApiManagementController::class, 'listBillsParties']);

        // Expense Parties
        Route::get('expense-parties', [ApiManagementController::class, 'listExpenseParties']);
        Route::post('expense-parties', [ApiManagementController::class, 'storeExpenseParty']);
        Route::put('expense-parties/{id}', [ApiManagementController::class, 'updateExpenseParty']);
        Route::delete('expense-parties/{id}', [ApiManagementController::class, 'deleteExpenseParty']);
        Route::get('expense-parties/export', [ApiManagementController::class, 'exportExpenseParties']);
        Route::post('expense-parties/bulk-update', [ApiManagementController::class, 'bulkUpdateExpenseParties']);
        Route::post('expense-parties/bulk-delete', [ApiManagementController::class, 'bulkDeleteExpenseParties']);
        Route::post('expense-parties/bulk-status', [ApiManagementController::class, 'bulkUpdateExpensePartiesStatus']);
        Route::post('/documents', [ApiDocumentController::class, 'store']);
        Route::delete('/documents/{id}', [ApiDocumentController::class, 'destroy']);

        // Documents
        Route::get('/documents/summary', [ApiDocumentController::class, 'summary']);
        Route::get('/documents', [ApiDocumentController::class, 'index']);

        // Payment Vouchers API Suite
        Route::get("/payment-vouchers/pending", [ApiPaymentVoucherController::class, "listPending"]);
        Route::get("/payment-vouchers/verified", [ApiPaymentVoucherController::class, "listVerified"]);
        Route::get("/payment-vouchers/paid", [ApiPaymentVoucherController::class, "listPaid"]);
        Route::post("/payment-vouchers/bulk-approve", [ApiPaymentVoucherController::class, "bulkApprove"]);
        Route::post("/payment-vouchers/bulk-reject", [ApiPaymentVoucherController::class, "bulkReject"]);
        Route::post("/payment-vouchers", [ApiPaymentVoucherController::class, "store"]);
        Route::post("/payment-vouchers/bulk", [ApiPaymentVoucherController::class, "bulkStore"]);
        Route::get("/payment-vouchers/pdf", [ApiPaymentVoucherController::class, "generateVoucherPdf"]);
        Route::get("/payment-vouchers/{id}/pdf", [ApiPaymentVoucherController::class, "generateVoucherPdf"]);
        
        // Relaxed Aliases for robustness (handles singular, plural, hyphens, underscores, GET/POST, and /create suffixes)
        Route::match(['get', 'post'], "/payment_voucher", [ApiPaymentVoucherController::class, "storeOrBulkStore"]);
        Route::match(['get', 'post'], "/payment-voucher", [ApiPaymentVoucherController::class, "storeOrBulkStore"]);
        Route::match(['get', 'post'], "/payment_voucher/create", [ApiPaymentVoucherController::class, "storeOrBulkStore"]);
        Route::match(['get', 'post'], "/payment-voucher/create", [ApiPaymentVoucherController::class, "storeOrBulkStore"]);
        Route::match(['get', 'post'], "/payment-vouchers/create", [ApiPaymentVoucherController::class, "storeOrBulkStore"]);

        // Sales
        Route::get('/sales/projects', [ApiSalesController::class, 'listProjects']);
        Route::post('/sales/projects', [ApiSalesController::class, 'storeProject']);
        Route::get('/sales/projects/{id}', [ApiSalesController::class, 'projectDetails'])->where('id', '[0-9]+');
        Route::post('/sales/projects/{id}', [ApiSalesController::class, 'updateProject'])->where('id', '[0-9]+');
        Route::delete('/sales/projects/{id}', [ApiSalesController::class, 'deleteProject'])->where('id', '[0-9]+');
        Route::post('/sales/projects/{id}/status', [ApiSalesController::class, 'updateProjectStatus'])->where('id', '[0-9]+');

        // Nested Sales Project Invoices
        Route::get('/sales-project/{id}/invoices', [ApiSalesController::class, 'listInvoices']);
        Route::post('/sales-project/{id}/invoices', [ApiSalesController::class, 'storeInvoice']);
        Route::get("/sales-project/{id}/invoices/{invoice_id}", [ApiSalesController::class, "invoiceDetails"]);
        Route::post("/sales-project/{id}/invoices/{invoice_id}", [ApiSalesController::class, "updateInvoice"]);
        Route::delete("/sales-project/{id}/invoices/{invoice_id}", [ApiSalesController::class, "deleteInvoice"]);
        Route::post("/sales-project/{id}/invoices/{invoice_id}/status", [ApiSalesController::class, "updateInvoiceStatus"]);
        Route::post("/sales-project/{id}/invoices/{invoice_id}/adjustments", [ApiSalesController::class, "storeAdjustment"]);
        Route::get("/sales-project/{id}/invoices/{invoice_id}/adjustments/{adjustment_id}", [ApiSalesController::class, "adjustmentDetails"]);
        Route::post("/sales-project/{id}/invoices/{invoice_id}/adjustments/{adjustment_id}", [ApiSalesController::class, "updateAdjustment"]);
        Route::delete("/sales-project/{id}/invoices/{invoice_id}/adjustments/{adjustment_id}", [ApiSalesController::class, "deleteAdjustment"]);

        Route::get('/sales/invoices', [ApiSalesController::class, 'listInvoices']);
        Route::get("/sales/report", [ApiSalesController::class, "salesReport"]);
        Route::get('/sales/invoices/{id}', [ApiSalesController::class, 'invoiceDetails']);
        Route::post('/sales/invoices', [ApiSalesController::class, 'storeInvoice']);
        Route::post("/sales/invoices/{id}", [ApiSalesController::class, "updateInvoice"]);
        Route::delete("/sales/invoices/{id}", [ApiSalesController::class, "deleteInvoice"]);
        Route::post("/sales/invoices/{id}/status", [ApiSalesController::class, "updateInvoiceStatus"]);
        Route::get('/sales/adjustments', [ApiSalesController::class, 'listAdjustments']);
        Route::post('/sales/adjustments', [ApiSalesController::class, 'storeAdjustment']);

        // Payment Vouchers
        Route::get("/payment-vouchers/pending", [ApiPaymentVoucherController::class, "listPending"]);
        Route::post("/payment-vouchers", [ApiPaymentVoucherController::class, "store"]);

        // Sales Invoice Heads
        Route::get('/sales/invoice-heads', [ApiSalesController::class, 'listInvoiceHeads']);
        Route::post('/sales/invoice-heads', [ApiSalesController::class, 'storeInvoiceHead']);
        Route::get('/sales/invoice-heads/{id}', [ApiSalesController::class, 'invoiceHeadDetails'])->where('id', '[0-9]+');
        Route::post('/sales/invoice-heads/{id}', [ApiSalesController::class, 'updateInvoiceHead'])->where('id', '[0-9]+');
        Route::delete('/sales/invoice-heads/{id}', [ApiSalesController::class, 'deleteInvoiceHead'])->where('id', '[0-9]+');

        // Payment Vouchers
        Route::get("/payment-vouchers/pending", [ApiPaymentVoucherController::class, "listPending"]);
        Route::post("/payment-vouchers", [ApiPaymentVoucherController::class, "store"]);

        // Sales Parties
        Route::get('/sales/parties', [ApiSalesController::class, 'listParties']);
        Route::post('/sales/parties', [ApiSalesController::class, 'storeParty']);
        Route::get('/sales/parties/{id}', [ApiSalesController::class, 'partyDetails'])->where('id', '[0-9]+');
        Route::post('/sales/parties/{id}', [ApiSalesController::class, 'updateParty'])->where('id', '[0-9]+');
        Route::delete('/sales/parties/{id}', [ApiSalesController::class, 'deleteParty'])->where('id', '[0-9]+');
        Route::post('/sales/parties/{id}/status', [ApiSalesController::class, 'updatePartyStatus'])->where('id', '[0-9]+');

        // Payment Vouchers
        Route::get('/vouchers', [ApiPaymentVoucherController::class, 'index']);
        Route::post('/vouchers', [ApiPaymentVoucherController::class, 'store']);
        Route::post('/vouchers/{id}/status', [ApiPaymentVoucherController::class, 'updateStatus']);
        Route::post('/vouchers/credit-site', [ApiPaymentVoucherController::class, 'creditSiteBalance']);

        // Contacts
        Route::get('/contacts', [ApiContactController::class, 'index']);
        Route::post('/contacts', [ApiContactController::class, 'store']);
        Route::post('/contacts/{id}', [ApiContactController::class, 'update']);
        Route::delete('/contacts/{id}', [ApiContactController::class, 'destroy']);

        // Cost Categories (Management)
        Route::get('/cost-categories', [ApiManagementController::class, 'listCostCategories']);
        Route::get('/cost-categories/export', [ApiManagementController::class, 'exportCostCategories']);
        Route::post('/cost-categories', [ApiManagementController::class, 'storeCostCategory']);
        Route::post('/cost-categories/bulk-update', [ApiManagementController::class, 'bulkUpdateCostCategories']);
        Route::post('/cost-categories/bulk-delete', [ApiManagementController::class, 'bulkDeleteCostCategories']);
        Route::post('/cost-categories/{id}', [ApiManagementController::class, 'updateCostCategory'])->where('id', '[0-9]+');
        Route::delete('/cost-categories/{id}', [ApiManagementController::class, 'deleteCostCategory'])->where('id', '[0-9,]+');

        // Resources
        // Route::get('/sites', [ApiResourceController::class, 'sites']); // DUPLICATE REMOVED - Using Management Controller instead
        // Route::get('/users', [ApiResourceController::class, 'users']); // DUPLICATE REMOVED - Using Management Controller instead
        // Route::get('/roles', [ApiResourceController::class, 'roles']); // DUPLICATE REMOVED - Using Management Controller instead
        Route::get('/resources/sales-companies', [ApiResourceController::class, 'salesCompanies']);
        Route::get('/resources/sales-projects', [ApiResourceController::class, 'salesProjects']);
        Route::get('/resources/sales-parties', [ApiResourceController::class, 'salesParties']);
        Route::get('/resources/other-parties', [ApiResourceController::class, 'otherParties']);
        Route::get('/resources/adjustment-types', [ApiResourceController::class, 'adjustmentTypes']);
    });
});
