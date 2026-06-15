<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class CompanyRegistrationTest extends TestCase
{
    public function test_register_company_saves_uid()
    {
        // 1. Create a dummy company in the companies table
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Test Company Inc',
            'uid' => 'test_company_old',
            'db_name' => 'company_test_company_old',
            'db_conn_name' => 'company_test_company_old',
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_pass' => 'secret',
            'username' => 'test_user',
            'status' => 'Active',
            'max_users' => 10,
            'max_sites' => 5,
        ]);

        // 2. Call register_company with the company ID and a new UID
        $response = $this->postJson('/api/register_company', [
            'company_id' => $companyId,
            'uid' => 'test_company_new_uid',
            'subscription_plaatform_name' => 'Stripe',
            'plan_name' => 'Premium Plan',
            'plan_amount' => 99.00,
            'Expired' => '31-12-2027',
        ]);

        // 3. Assert successful response
        if ($response->status() !== 200) {
            dump($response->getContent());
        }
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => true]);

        // 4. Assert database reflects the new UID
        $this->assertDatabaseHas('companies', [
            'id' => $companyId,
            'uid' => 'test_company_new_uid',
        ]);

        // Cleanup
        DB::table('companies')->where('id', $companyId)->delete();
        DB::table('subscription_plans')->where('company_id', $companyId)->delete();
    }
}
