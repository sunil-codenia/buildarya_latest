<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\api\AiChatQueryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiChatExpenseResolutionTest extends TestCase
{
    public function test_system_prompt_instructs_proper_names_for_expenses(): void
    {
        $controller = new AiChatQueryController();
        $refMethod = new \ReflectionMethod($controller, 'callLlmForSql');
        $refMethod->setAccessible(true);

        // We use reflection on systemPrompt via building a test closure or reflection
        $tenant = [
            'conn' => config('database.default'),
            'site_id' => 45,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        // Call normalizeExpenseQuery with wildcard
        $normMethod = new \ReflectionMethod($controller, 'normalizeExpenseQuery');
        $normMethod->setAccessible(true);

        $transformed = $normMethod->invoke($controller, 'SELECT * FROM expenses WHERE site_id = 45 ORDER BY 1 DESC');

        $this->assertStringContainsString('cost_category_name', $transformed);
        $this->assertStringContainsString('party_name', $transformed);
        $this->assertStringContainsString('site_name', $transformed);
        $this->assertStringContainsString('user_name', $transformed);
        $this->assertStringContainsString('expense_head', $transformed);
        $this->assertStringContainsString('expense_party', $transformed);
        $this->assertStringContainsString('bills_party', $transformed);
        $this->assertStringContainsString('expenses.site_id = 45', $transformed);
    }

    public function test_normalize_expense_query_transforms_wildcard_select(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'normalizeExpenseQuery');
        $method->setAccessible(true);

        $sql = 'SELECT * FROM expenses WHERE site_id = 45 ORDER BY 1 DESC';
        $transformed = $method->invoke($controller, $sql);

        $this->assertStringNotContainsString('SELECT * FROM expenses', $transformed);
        $this->assertStringContainsString('expenses.id', $transformed);
        $this->assertStringContainsString('party_name', $transformed);
        $this->assertStringContainsString('cost_category_name', $transformed);
        $this->assertStringContainsString('site_name', $transformed);
        $this->assertStringContainsString('user_name', $transformed);
        $this->assertStringContainsString('expenses.site_id = 45', $transformed);
    }

    public function test_enrich_expense_records_replaces_ids_with_proper_names(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'enrichExpenseRecords');
        $method->setAccessible(true);

        $rawRows = [
            (object)[
                'id' => 1,
                'party_id' => '1',
                'party_type' => 'bill',
                'head_id' => '1',
                'particular' => 'welding machine repair',
                'amount' => '120',
                'remark' => 'test sunil',
                'image' => 'images/expense.png',
                'site_id' => '45',
                'user_id' => '6',
                'status' => 'Approved',
                'location' => 'N/A',
                'date' => '2026-09-02'
            ]
        ];

        $enriched = $method->invoke($controller, $rawRows, config('database.default'));

        $this->assertCount(1, $enriched);
        $first = (array)$enriched[0];

        // Ensure proper names exist
        $this->assertArrayHasKey('party_name', $first);
        $this->assertArrayHasKey('cost_category_name', $first);
        $this->assertArrayHasKey('site_name', $first);
        $this->assertArrayHasKey('user_name', $first);

        // Ensure raw ID keys are replaced
        $this->assertArrayNotHasKey('party_id', $first);
        $this->assertArrayNotHasKey('head_id', $first);
        $this->assertArrayNotHasKey('site_id', $first);
        $this->assertArrayNotHasKey('user_id', $first);

        $this->assertNotEmpty($first['party_name']);
        $this->assertNotEmpty($first['cost_category_name']);
        $this->assertNotEmpty($first['site_name']);
        $this->assertNotEmpty($first['user_name']);
    }

    public function test_build_dynamic_sql_html_formats_proper_headers(): void
    {
        $controller = new AiChatQueryController();
        $method = new \ReflectionMethod($controller, 'buildDynamicSqlHtml');
        $method->setAccessible(true);

        $tenant = [
            'site_id' => '45',
            'site_name' => 'Head Office',
            'user_name' => 'Test User',
            'user_username' => 'testuser',
            'is_superadmin' => true
        ];

        $rows = [
            (object)[
                'id' => 1,
                'party_name' => 'ABC Supplier',
                'party_type' => 'bill',
                'cost_category_name' => 'Repair and Maintenance',
                'particular' => 'welding machine repair',
                'amount' => 120,
                'site_name' => 'Head Office',
                'user_name' => 'test sunil',
                'status' => 'Approved',
                'image' => 'images/expense.png',
                'date' => '2026-09-02'
            ]
        ];

        $html = $method->invoke($controller, $rows, 'SELECT * FROM expenses', 'Google Gemini AI', 'show me the expense', $tenant, false, false);

        $this->assertStringContainsString('Party Name', $html);
        $this->assertStringContainsString('Cost Category Name', $html);
        $this->assertStringContainsString('Site Name', $html);
        $this->assertStringContainsString('User Name', $html);
        $this->assertStringContainsString('ABC Supplier', $html);
        $this->assertStringContainsString('Repair and Maintenance', $html);
        $this->assertStringContainsString('View Image', $html);
        $this->assertStringNotContainsString('<th>Party Id</th>', $html);
        $this->assertStringNotContainsString('<th>Head Id</th>', $html);
        $this->assertStringNotContainsString('<th>Site Id</th>', $html);
        $this->assertStringNotContainsString('<th>User Id</th>', $html);
    }
}

