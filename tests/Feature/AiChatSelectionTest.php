<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\api\AiChatQueryController;

class AiChatSelectionTest extends TestCase
{
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
}
