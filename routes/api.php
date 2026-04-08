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
use App\Http\Controllers\DocumentController;

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



Route::get('/get_all_data', [UserController::class, 'get_all_data']);
Route::get('/api_login', [UserController::class, 'login']);
Route::post('/register_company', [CompanyRegistrationController::class, 'register_company']);
Route::post('/get_users', [UserController::class, 'get_users']);
Route::post('/get_sites', [UserController::class, 'get_sites']);
Route::post('/get_modules', [UserController::class, 'get_modules']);
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

