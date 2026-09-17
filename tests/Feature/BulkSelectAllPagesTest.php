<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\User;

class BulkSelectAllPagesTest extends TestCase
{
    protected $conn = 'company_rsgeotech';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.connections.company_rsgeotech' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'company_rsgeotech',
                'username' => 'root',
                'password' => '',
            ]
        ]);
    }

    protected function getBaseSession()
    {
        return [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'company_modules' => [1, 2, 3, 4, 5],
            'view_duration' => 'all',
            'visiblity_at_site' => 'all',
        ];
    }

    public function test_users_ajax_with_all_ids()
    {
        $user = User::first() ?? new User(['id' => 1]);
        $response = $this->actingAs($user)
            ->withSession($this->getBaseSession())
            ->post('/users_ajax', [
                'all_ids' => 1
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals('Ok', $data['status']);
        $this->assertIsArray($data['ids']);
        $this->assertArrayHasKey('total', $data);
    }

    public function test_pending_expense_ajax_with_all_ids()
    {
        $user = User::first() ?? new User(['id' => 1]);
        $response = $this->actingAs($user)
            ->withSession($this->getBaseSession())
            ->post('/pending_expense_ajax', [
                'all_ids' => 1
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals('Ok', $data['status']);
        $this->assertIsArray($data['ids']);
        $this->assertArrayHasKey('total', $data);
    }

    public function test_verified_expense_ajax_with_all_ids()
    {
        $user = User::first() ?? new User(['id' => 1]);
        $response = $this->actingAs($user)
            ->withSession($this->getBaseSession())
            ->post('/verified_expense_ajax', [
                'all_ids' => 1
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals('Ok', $data['status']);
        $this->assertIsArray($data['ids']);
        $this->assertArrayHasKey('total', $data);
    }

    public function test_pending_material_ajax_with_all_ids()
    {
        $user = User::first() ?? new User(['id' => 1]);
        $response = $this->actingAs($user)
            ->withSession($this->getBaseSession())
            ->post('/pending_material_ajax', [
                'all_ids' => 1
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals('Ok', $data['status']);
        $this->assertIsArray($data['ids']);
        $this->assertArrayHasKey('total', $data);
    }

    public function test_verified_material_ajax_with_all_ids()
    {
        $user = User::first() ?? new User(['id' => 1]);
        $response = $this->actingAs($user)
            ->withSession($this->getBaseSession())
            ->post('/verified_material_ajax', [
                'all_ids' => 1
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals('Ok', $data['status']);
        $this->assertIsArray($data['ids']);
        $this->assertArrayHasKey('total', $data);
    }

    public function test_material_ajax_with_all_ids()
    {
        $user = User::first() ?? new User(['id' => 1]);
        $response = $this->actingAs($user)
            ->withSession($this->getBaseSession())
            ->post('/material_ajax', [
                'all_ids' => 1
            ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals('Ok', $data['status']);
        $this->assertIsArray($data['ids']);
        $this->assertArrayHasKey('total', $data);
    }

    public function test_bulk_delete_roles_safety_and_deletion()
    {
        $user = User::first() ?? new User(['id' => 1]);

        // 1. Attempting to delete role 1 (SuperAdmin) must be rejected
        $res = $this->actingAs($user)
            ->withSession($this->getBaseSession())
            ->post('/bulk_delete_roles', [
                'ids' => [1]
            ]);

        $res->assertStatus(400);

        // 2. Insert a temporary deletable role with an unused ID
        $maxRoleId = max(
            DB::connection($this->conn)->table('roles')->max('id') ?? 0,
            DB::connection($this->conn)->table('users')->max('role_id') ?? 0
        ) + 1000;

        DB::connection($this->conn)->table('roles')->insert([
            'id' => $maxRoleId,
            'name' => 'TEMP_TEST_ROLE_' . time(),
            'is_superadmin' => 'no'
        ]);

        // Delete the temp role via bulk delete
        $res2 = $this->actingAs($user)
            ->withSession($this->getBaseSession())
            ->post('/bulk_delete_roles', [
                'ids' => [$maxRoleId]
            ]);

        $res2->assertStatus(200);
        $data2 = $res2->json();
        $this->assertEquals('Ok', $data2['status']);

        // Verify it was removed from database
        $exists = DB::connection($this->conn)->table('roles')->where('id', $maxRoleId)->exists();
        $this->assertFalse($exists);
    }
}
