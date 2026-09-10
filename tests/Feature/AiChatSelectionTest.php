<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\api\AiChatQueryController;

class AiChatSelectionTest extends TestCase
{
    public function test_process_query_requires_ai_generated_sql_without_manual_fallback(): void
    {
        putenv('OPENAI_API_KEY=');
        putenv('GEMINI_API_KEY=');
        putenv('GROQ_API_KEY=');
        putenv('DEEPSEEK_API_KEY=');

        $controller = new AiChatQueryController();
        $request = new \Illuminate\Http\Request();
        $request->merge(['query' => 'show attendance today']);

        $response = $controller->processQuery($request);
        $payload = $response->getData(true);

        $this->assertSame(422, $response->status());
        $this->assertSame('Failed', $payload['status']);
        $this->assertStringContainsString('AI could not generate', $payload['message']);
    }

    public function test_database_schema_context_lists_live_tables_and_columns(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'buildDatabaseSchemaContext');
        $method->setAccessible(true);

        $schema = strtolower($method->invoke($controller, config('database.default')));

        $this->assertIsString($schema);
        $this->assertStringContainsString('users', $schema);
        $this->assertStringContainsString('sites', $schema);
        $this->assertStringContainsString('expenses', $schema);
        $this->assertStringContainsString('name', $schema);
    }

    public function test_date_range_queries_are_parsed_as_between_ranges(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'generateDynamicSqlFromText');
        $method->setAccessible(true);

        $tenant = [
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $sql = strtolower($method->invoke($controller, 'show me the attendance list of date 10 july to 9 sep', $tenant));

        $this->assertStringContainsString('attendance', $sql);
        $this->assertStringContainsString('between', $sql);
        $this->assertStringContainsString('2026-07-10', $sql);
        $this->assertStringContainsString('2026-09-09', $sql);
    }

    public function test_month_year_date_range_queries_are_parsed_as_between_ranges(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'generateDynamicSqlFromText');
        $method->setAccessible(true);

        $tenant = [
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $sql = strtolower($method->invoke($controller, 'expense of january 2025 to january 2026', $tenant));

        $this->assertStringContainsString('from expenses', $sql);
        $this->assertStringContainsString('between', $sql);
        $this->assertStringContainsString('2025-01-01', $sql);
        $this->assertStringContainsString('2026-01-31', $sql);
    }

    public function test_show_me_verified_bill_uses_bills_party_status_filter(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'generateDynamicSqlFromText');
        $method->setAccessible(true);

        $tenant = [
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $sql = strtolower($method->invoke($controller, 'show me the verified bills', $tenant));

        $this->assertStringContainsString('from bills_party', $sql);
        $this->assertStringContainsString('status like', $sql);
        $this->assertStringContainsString('verified', $sql);
    }

    public function test_show_me_verified_expense_uses_expenses_status_filter(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'generateDynamicSqlFromText');
        $method->setAccessible(true);

        $tenant = [
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $sql = strtolower($method->invoke($controller, 'show me the verified expense', $tenant));

        $this->assertStringContainsString('from expenses', $sql);
        $this->assertStringContainsString('expenses.status', $sql);
    }

    public function test_expense_report_according_to_sites_is_treated_as_site_grouped_report(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'generateDynamicSqlFromText');
        $method->setAccessible(true);

        $tenant = [
            'site_id' => null,
            'site_name' => 'All Authorized Sites',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $sql = strtolower($method->invoke($controller, 'expense report according to the sites', $tenant));

        $this->assertStringContainsString('sum(expenses.amount)', $sql);
        $this->assertStringContainsString('group by', $sql);
        $this->assertStringContainsString('sites.name as site_name', $sql);
    }

    public function test_add_user_prompt_opens_user_create_form(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'detectCreateFormIntent');
        $method->setAccessible(true);

        $intent = $method->invoke($controller, 'I want to add user');

        $this->assertSame('user', $intent);

        $formMethod = new \ReflectionMethod($controller, 'renderCreateFormHtml');
        $formMethod->setAccessible(true);

        $tenant = [
            'conn' => config('database.default'),
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $html = strtolower($formMethod->invoke($controller, 'user', $tenant));

        $this->assertStringContainsString('add new user', $html);
        $this->assertStringContainsString('name', $html);
        $this->assertStringContainsString('role_id', $html);
        $this->assertStringContainsString('site_id', $html);
    }

    public function test_add_expense_prompt_opens_full_expense_form_fields(): void
    {
        $controller = new AiChatQueryController();
        $formMethod = new \ReflectionMethod($controller, 'renderCreateFormHtml');
        $formMethod->setAccessible(true);

        $tenant = [
            'conn' => config('database.default'),
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $html = strtolower($formMethod->invoke($controller, 'expense', $tenant));

        $this->assertStringContainsString('add new expense', $html);
        $this->assertStringContainsString('party_id[]', $html);
        $this->assertStringContainsString('head_id[]', $html);
        $this->assertStringContainsString('particular[]', $html);
        $this->assertStringContainsString('amount[]', $html);
        $this->assertStringContainsString('remark[]', $html);
        $this->assertStringContainsString('date[]', $html);
        $this->assertStringContainsString('image[]', $html);
    }

    public function test_user_form_matches_website_fields(): void
    {
        $controller = new AiChatQueryController();
        $formMethod = new \ReflectionMethod($controller, 'renderCreateFormHtml');
        $formMethod->setAccessible(true);

        $tenant = [
            'conn' => config('database.default'),
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => [],
            'comp_name' => 'Buildarya',
            'comp_db_id' => 1
        ];

        $html = strtolower($formMethod->invoke($controller, 'user', $tenant));

        $this->assertStringContainsString('image', $html);
        $this->assertStringContainsString('company_id', $html);
        $this->assertStringContainsString('view_duration', $html);
        $this->assertStringContainsString('add_duration', $html);
    }

    public function test_payment_voucher_form_matches_website_fields(): void
    {
        $controller = new AiChatQueryController();
        $formMethod = new \ReflectionMethod($controller, 'renderCreateFormHtml');
        $formMethod->setAccessible(true);

        $tenant = [
            'conn' => config('database.default'),
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => [],
            'comp_name' => 'Buildarya',
            'comp_db_id' => 1
        ];

        $html = strtolower($formMethod->invoke($controller, 'payment_voucher', $tenant));

        $this->assertStringContainsString('voucher image', $html);
        $this->assertStringContainsString('qr code image', $html);
        $this->assertStringContainsString('company', $html);
        $this->assertStringContainsString('voucher party', $html);
        $this->assertStringContainsString('voucher no', $html);
        $this->assertStringContainsString('payment details', $html);
        $this->assertStringContainsString('remark', $html);
        $this->assertStringContainsString('name="company_id[]"', $html);
        $this->assertStringContainsString('name="party_id[]"', $html);
        $this->assertStringContainsString('name="site_id[]"', $html);
        $this->assertStringContainsString('name="voucher_no[]"', $html);
        $this->assertStringContainsString('name="amount[]"', $html);
        $this->assertStringContainsString('name="date[]"', $html);
        $this->assertStringContainsString('name="payment_details[]"', $html);
        $this->assertStringContainsString('name="remark[]"', $html);
    }

    public function test_bill_party_form_matches_website_fields(): void
    {
        $controller = new AiChatQueryController();
        $formMethod = new \ReflectionMethod($controller, 'renderCreateFormHtml');
        $formMethod->setAccessible(true);

        $tenant = [
            'conn' => config('database.default'),
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $html = strtolower($formMethod->invoke($controller, 'bill_party', $tenant));

        $this->assertStringContainsString('add new bill party', $html);
        $this->assertStringContainsString('name', $html);
        $this->assertStringContainsString('address', $html);
        $this->assertStringContainsString('panno', $html);
        $this->assertStringContainsString('bank_ac', $html);
        $this->assertStringContainsString('ifsc', $html);
        $this->assertStringContainsString('bankname', $html);
        $this->assertStringContainsString('ac_holder_name', $html);
        $this->assertStringContainsString('cost_category_id', $html);
        $this->assertStringContainsString('qr_code', $html);
    }

    public function test_attendance_form_renders_user_dropdown_without_errors(): void
    {
        $controller = new AiChatQueryController();
        $formMethod = new \ReflectionMethod($controller, 'renderCreateFormHtml');
        $formMethod->setAccessible(true);

        $tenant = [
            'conn' => config('database.default'),
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $html = $formMethod->invoke($controller, 'attendance', $tenant);

        $this->assertStringContainsString('User / Labour', $html);
        $this->assertStringContainsString('name="user_id"', $html);
    }

    public function test_add_role_prompt_opens_role_form(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'detectCreateFormIntent');
        $method->setAccessible(true);

        $intent = $method->invoke($controller, 'add new roles');
        $this->assertSame('role', $intent);

        $formMethod = new \ReflectionMethod($controller, 'renderCreateFormHtml');
        $formMethod->setAccessible(true);

        $tenant = [
            'conn' => config('database.default'),
            'site_id' => null,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $html = strtolower($formMethod->invoke($controller, 'role', $tenant));

        $this->assertStringContainsString('add new role', $html);
        $this->assertStringContainsString('role name', $html);
        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('addnewrole', $html);
    }
}
