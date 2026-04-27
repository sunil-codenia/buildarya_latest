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
use App\Http\Controllers\api\ApiDashboardController;
use App\Http\Controllers\api\ApiExpenseController;
use App\Http\Controllers\api\ApiMaterialController;
use App\Http\Controllers\api\ApiManagementController;
use App\Http\Controllers\api\ApiDocumentController;
use App\Http\Controllers\api\ApiResourceController;
use App\Http\Controllers\api\ApiSiteBillsController;
use App\Http\Controllers\api\ApiAssetMachineryController;
use App\Http\Controllers\api\ApiSalesController;
use App\Http\Controllers\api\ApiPaymentVoucherController;
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
Route::post('/add_company_plan', [CompanyPlanController::class, 'add_company_plan']);
Route::post('/get_users', [UserController::class, 'get_users']);
Route::post('/get_sites', [UserController::class, 'get_sites']);
Route::get('/clear_config_cache', function() {
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    return response()->json(['status' => true, 'message' => 'Configuration cache cleared successfully']);
});
Route::post('/get_modules', [UserController::class, 'get_modules']);
Route::get('/get_all_modules', [UserController::class, 'get_all_modules']);
Route::post('/get_permission', [UserController::class, 'get_permission']);
Route::post('/get_site_transaction', [UserController::class, 'get_site_transaction']);
Route::post('/update_fcm_id', [UserController::class, 'update_fcm_id']);

  Route::post('/update_profile_picture', [UserController::class, 'update_profile_picture']);

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

        // Expenses
        Route::get('/expenses/summary', [ApiExpenseController::class, 'summary']);
        Route::get('/expenses', [ApiExpenseController::class, 'index']);
        Route::post('/expenses', [ApiExpenseController::class, 'store']);
        Route::post('/expenses/{id}', [ApiExpenseController::class, 'update']);
        Route::delete('/expenses/{id}', [ApiExpenseController::class, 'destroy']);

        // Materials
        Route::get('/materials/summary', [ApiMaterialController::class, 'summary']);
        Route::get('/materials', [ApiMaterialController::class, 'index']);
        Route::post('/materials', [ApiMaterialController::class, 'store']);
        Route::post('/materials/{id}', [ApiMaterialController::class, 'update']);
        Route::delete('/materials/{id}', [ApiMaterialController::class, 'destroy']);

        // Site Bills
        Route::get('/bills/summary', [ApiSiteBillsController::class, 'summary']);
        Route::get('/bills', [ApiSiteBillsController::class, 'index']);
        Route::get('/bills/{id}', [ApiSiteBillsController::class, 'show']);
        Route::post('/bills', [ApiSiteBillsController::class, 'store']);
        Route::post('/bills/{id}', [ApiSiteBillsController::class, 'update']);
        Route::delete('/bills/{id}', [ApiSiteBillsController::class, 'destroy']);

        // Assets
        Route::get('/assets/summary', [ApiAssetMachineryController::class, 'assetSummary']);
        Route::get('/assets', [ApiAssetMachineryController::class, 'listAssets']);
        Route::post('/assets', [ApiAssetMachineryController::class, 'storeAsset']);
        Route::post('/assets/{id}/transfer', [ApiAssetMachineryController::class, 'transferAsset']);

        // Machinery
        Route::get('/machinery/summary', [ApiAssetMachineryController::class, 'machinerySummary']);
        Route::get('/machinery', [ApiAssetMachineryController::class, 'listMachinery']);
        Route::post('/machinery', [ApiAssetMachineryController::class, 'storeMachinery']);
        Route::get('/machinery/{id}/documents', [ApiAssetMachineryController::class, 'machineryDocuments']);
        Route::post('/machinery/{id}/documents', [ApiAssetMachineryController::class, 'storeMachineryDocument']);
        Route::get('/machinery/{id}/services', [ApiAssetMachineryController::class, 'machineryServices']);
        Route::post('/machinery/{id}/services', [ApiAssetMachineryController::class, 'storeMachineryService']);

        // Users (Management)
        Route::get('/users', [ApiManagementController::class, 'listUsers']);
        Route::get('/users/export/csv', [ApiManagementController::class, 'exportUsersCsv']);
        Route::get('/users/export/excel', [ApiManagementController::class, 'exportUsersExcel']);
        Route::get('/users/export/pdf', [ApiManagementController::class, 'exportUsersPdf']);
        Route::post('/users', [ApiManagementController::class, 'storeUser']);
        Route::post('/users/{id}', [ApiManagementController::class, 'updateUser']);
        Route::delete('/users/{id}', [ApiManagementController::class, 'deleteUser']);
        // Sites (Management)
        Route::get('/sites', [ApiManagementController::class, 'listSites']);
        Route::get('/sites/export/csv', [ApiManagementController::class, 'exportSitesCsv']);
        Route::get('/sites/export/excel', [ApiManagementController::class, 'exportSitesExcel']);
        Route::get('/sites/export/pdf', [ApiManagementController::class, 'exportSitesPdf']);
        Route::post('/sites', [ApiManagementController::class, 'storeSite']);
        Route::post('/sites/{id}', [ApiManagementController::class, 'updateSite']);
        Route::delete('/sites/{id}', [ApiManagementController::class, 'deleteSite']);
        
        // Site Finances
        Route::get('/sites/payments', [ApiManagementController::class, 'listSitePayments']); // Coming soon in controller
        Route::post('/sites/payments', [ApiManagementController::class, 'recordSitePayment']);
        Route::post('/sites/transfers', [ApiManagementController::class, 'transferSiteCash']);
        Route::match(['get', 'post'], '/sites/statement', [ApiManagementController::class, 'siteStatement']);

        // Roles (Management)
        Route::get('/roles', [ApiManagementController::class, 'listRoles']);
        Route::get('/roles/export/csv', [ApiManagementController::class, 'exportRolesCsv']);
        Route::get('/roles/export/excel', [ApiManagementController::class, 'exportRolesExcel']);
        Route::get('/roles/export/pdf', [ApiManagementController::class, 'exportRolesPdf']);
        Route::post('/roles', [ApiManagementController::class, 'storeRole']);
        Route::post('/roles/{id}', [ApiManagementController::class, 'updateRole']);
        Route::delete('/roles/{id}', [ApiManagementController::class, 'deleteRole']);
        
        // Permissions (Management)
        Route::get('/roles/{id}/permissions', [ApiManagementController::class, 'listRolePermissions']);
        Route::post('/roles/{id}/permissions', [ApiManagementController::class, 'updateRolePermissions']);
        Route::post('/roles/manage/{id}', [ApiManagementController::class, 'updateRole']);

        // Permissions
        Route::get('/users/{id}/permissions', [ApiManagementController::class, 'listUserPermissions']);
        Route::post('/users/{id}/permissions', [ApiManagementController::class, 'updateUserPermissions']);

        // Expenses
        Route::get('expense-heads', [ApiManagementController::class, 'listExpenseHeads']);
        Route::get('bills-parties', [ApiManagementController::class, 'listBillsParties']);
        Route::post('expenses', [ApiManagementController::class, 'storeExpense']);

        // Expense Parties
        Route::get('expense-parties', [ApiManagementController::class, 'listExpenseParties']);
        Route::post('expense-parties', [ApiManagementController::class, 'storeExpenseParty']);
        Route::put('expense-parties/{id}', [ApiManagementController::class, 'updateExpenseParty']);
        Route::delete('expense-parties/{id}', [ApiManagementController::class, 'deleteExpenseParty']);
        Route::get('expense-parties/export', [ApiManagementController::class, 'exportExpenseParties']);
        Route::post('/documents', [ApiDocumentController::class, 'store']);
        Route::delete('/documents/{id}', [ApiDocumentController::class, 'destroy']);

        // Documents
        Route::get('/documents/summary', [ApiDocumentController::class, 'summary']);
        Route::get('/documents', [ApiDocumentController::class, 'index']);

        // Sales
        Route::get('/sales/projects', [ApiSalesController::class, 'listProjects']);
        Route::post('/sales/projects', [ApiSalesController::class, 'storeProject']);
        Route::get('/sales/invoices', [ApiSalesController::class, 'listInvoices']);
        Route::get('/sales/invoices/{id}', [ApiSalesController::class, 'invoiceDetails']);
        Route::post('/sales/invoices', [ApiSalesController::class, 'storeInvoice']);
        Route::post('/sales/adjustments', [ApiSalesController::class, 'storeAdjustment']);

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

