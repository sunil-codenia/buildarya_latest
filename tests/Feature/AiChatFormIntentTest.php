<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\api\AiChatQueryController;
use ReflectionMethod;

class AiChatFormIntentTest extends TestCase
{
    /**
     * Test detection of all 38 user commands
     */
    public function test_detects_all_38_user_commands_properly(): void
    {
        $controller = new AiChatQueryController();
        $detectMethod = new ReflectionMethod($controller, 'detectCreateFormIntent');
        $detectMethod->setAccessible(true);

        $expectedMappings = [
            'Add User' => 'user',
            'Add Site' => 'site',
            'Add Role' => 'role',
            'Add Expense parties' => 'expense_party',
            'Add New Expense' => 'expense',
            'Add Cost Category' => 'cost_category',
            'Add Material Suppliers' => 'material_supplier',
            'Add Materials' => 'material',
            'Add Units' => 'material_unit',
            'Add New Materials entry' => 'material_entry',
            'Add New Consuption/Wastage' => 'consumption_wastage',
            'Add Stocks Site Transfer' => 'stock_site_transfer',
            'Add Stocks Unit Conversion' => 'stock_unit_conversion',
            'Add StocksReconcia' => 'stock_reconciliation',
            'Add Bill Parties' => 'bill_party',
            'Add Works' => 'bill_work',
            'Add Works Rate' => 'bill_rate',
            'Add New Bill' => 'bill',
            'Add Machinerises' => 'machinery',
            'Add Machiney' => 'machinery',
            'Add expense head' => 'machinery_expense_head',
            'Add Assets' => 'asset',
            'Add Assets\'s Expense Head' => 'asset_expense_head',
            'Add Invoice Heads' => 'invoice_head',
            'Add sales Party' => 'sales_party',
            'Add project' => 'sales_project',
            'Add new payment voucher' => 'payment_voucher',
            'Add Other Party' => 'other_party',
            'Add upload file in my document section' => 'my_doc_upload_file',
            'Add Document Head' => 'doc_head',
            'Add New Contact' => 'contact',
            'Add Self Check In' => 'self_check_in',
            'Add Self Check Out' => 'self_check_out',
            'Add Manual Attendance' => 'manual_attendance',
            'Add Task' => 'task',
            'Add Task Category' => 'task_category',
            'Add New Company' => 'company',
            'Add Support Ticket' => 'ticket'
        ];

        foreach ($expectedMappings as $command => $expectedEntity) {
            $detected = $detectMethod->invoke($controller, $command);
            $this->assertEquals(
                $expectedEntity,
                $detected,
                "Command '{$command}' should detect entity '{$expectedEntity}', got '{$detected}'."
            );
        }
    }

    /**
     * Test that renderCreateFormHtml produces valid form HTML with correct actions and CSRF
     * for all 38 entities even without an active DB connection
     */
    public function test_renders_valid_form_html_for_all_entities(): void
    {
        $controller = new AiChatQueryController();
        $renderMethod = new ReflectionMethod($controller, 'renderCreateFormHtml');
        $renderMethod->setAccessible(true);

        $mockTenant = [
            'conn' => 'non_existent_mock_connection_for_test',
            'site_id' => 45,
            'site_name' => 'Test Site HQ',
            'comp_name' => 'RSG NIRMAN',
            'comp_db_id' => 1
        ];

        $entitiesAndActions = [
            'user' => '/addnewuser',
            'site' => '/addsites',
            'role' => '/addnewrole',
            'expense_party' => '/addexpenseparty',
            'expense' => '/addnewExpenses',
            'cost_category' => '/addcostcategory',
            'material_supplier' => '/addmaterialsupplier',
            'material' => '/addmaterial',
            'material_unit' => '/addmaterialunit',
            'material_entry' => '/addnewmaterial',
            'consumption_wastage' => '/add_new_consumption',
            'stock_site_transfer' => '/newMaterialTransferForm',
            'stock_unit_conversion' => '/newStockUnitConversionForm',
            'stock_reconciliation' => '/request_reconsilation',
            'bill_party' => '/addbillparty',
            'bill_work' => '/addbillwork',
            'bill_rate' => '/addbillrate',
            'bill' => '/addnewbill',
            'machinery' => '/add_newmechinery',
            'machinery_expense_head' => '/addmachineryExpensehead',
            'asset' => '/add_newassets',
            'asset_expense_head' => '/addassetExpensehead',
            'invoice_head' => '/addsalesinv_head',
            'sales_party' => '/addsalesparty',
            'sales_project' => '/addsalesproject',
            'payment_voucher' => '/addnewpaymentvouchers',
            'other_party' => '/addotherparty',
            'my_doc_upload_file' => '/my_doc_upload_file',
            'doc_head' => '/adddochead',
            'contact' => '/add_contact',
            'self_check_in' => '/attendance/clock-in',
            'self_check_out' => '/attendance/clock-out',
            'manual_attendance' => '/attendance/manual',
            'task' => '/tasks',
            'task_category' => '/addtaskcategory',
            'company' => '/addsalescompany',
            'ticket' => '/tickets',
            'machinery_head' => '/addmachineryhead'
        ];

        foreach ($entitiesAndActions as $entity => $expectedAction) {
            $html = $renderMethod->invoke($controller, $entity, $mockTenant);
            $this->assertNotEmpty($html, "HTML for entity '{$entity}' must not be empty.");
            $this->assertStringContainsString('<form', $html, "HTML for entity '{$entity}' must contain a form tag.");
            $this->assertStringContainsString($expectedAction, $html, "HTML for entity '{$entity}' must contain action '{$expectedAction}'.");
            $this->assertStringContainsString('submitAiForm', $html, "HTML for entity '{$entity}' must use submitAiForm.");
            $this->assertStringContainsString('AI Form Action', $html, "HTML for entity '{$entity}' must contain 'AI Form Action' badge.");
            $this->assertStringContainsString('Open Full Form', $html, "HTML for entity '{$entity}' must contain 'Open Full Form' link.");
        }
    }
}

