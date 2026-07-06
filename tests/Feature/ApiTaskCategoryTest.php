<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiTaskCategoryTest extends TestCase
{
    private $tokenValue;
    private $tokenId;
    private $testUserId = 999;
    private $connName = 'company_rsgeotech';
    private $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Set up config for tenant database
        config([
            'database.connections.company_rsgeotech' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'company_rsgeotech',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]
        ]);

        // 2. Ensure company record exists in the main database
        $exists = DB::connection('mysql')->table('companies')
            ->where('db_conn_name', $this->connName)
            ->exists();

        if (!$exists) {
            DB::connection('mysql')->table('companies')->insert([
                'name' => 'RSGeotech',
                'uid' => 'rsgeotech',
                'db_conn_name' => $this->connName,
                'db_name' => 'company_rsgeotech',
                'db_host' => '127.0.0.1',
                'db_port' => '3306',
                'db_pass' => '',
                'username' => 'root',
                'status' => 'Active',
                'max_users' => 100,
                'max_sites' => 50,
            ]);
        }

        // 3. Ensure role and test user exist in tenant DB
        DB::connection($this->connName)->table('roles')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Admin',
                'is_superadmin' => 'yes',
                'add_duration' => 'all',
                'view_duration' => 'all',
                'initial_entry_status' => 'Approved',
                'entry_at_site' => 'all',
                'visiblity_at_site' => 'all',
            ]
        );

        DB::connection($this->connName)->table('users')->updateOrInsert(
            ['id' => $this->testUserId],
            [
                'name' => 'API Admin User',
                'username' => 'apiadmin',
                'pass' => 'adminpass',
                'role_id' => 1,
                'status' => 'Active',
                'mobile_only' => 'no',
                'site_id' => 'all'
            ]
        );

        // Ensure task_categories table exists
        DB::connection($this->connName)->statement("
            CREATE TABLE IF NOT EXISTS `task_categories` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Truncate task categories for clean run
        DB::connection($this->connName)->table('task_categories')->truncate();

        // 4. Insert Sanctum token
        $this->tokenValue = Str::random(40);
        $hashedToken = hash('sha256', $this->tokenValue);
        $this->tokenId = DB::connection('mysql')->table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\User',
            'tokenable_id' => $this->testUserId,
            'name' => $this->connName,
            'token' => $hashedToken,
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('mysql')->table('personal_access_tokens')
            ->where('id', $this->tokenId)
            ->delete();

        DB::connection($this->connName)->table('task_categories')->truncate();

        parent::tearDown();
    }

    public function test_task_category_api_workflow()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenId . '|' . $this->tokenValue,
        ];

        // 1. Create a task category
        $response = $this->postJson('/api/v1/task-categories', [
            'name' => 'Backend API Testing'
        ], $headers);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'Ok', 'status_code' => '200', 'message' => 'Task Category created successfully!']);
        $this->categoryId = $response->json('data.id');
        $this->assertNotNull($this->categoryId);
        $this->assertEquals('Backend API Testing', $response->json('data.name'));

        // 2. Try creating duplicate
        $response = $this->postJson('/api/v1/task-categories', [
            'name' => 'Backend API Testing'
        ], $headers);
        $response->assertJsonFragment(['status' => 'Failed', 'message' => 'Category Name already exists!']);

        // 3. List categories
        $response = $this->getJson('/api/v1/task-categories', $headers);
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'Ok', 'status_code' => '200']);
        $this->assertNotEmpty($response->json('data.data'));

        // 4. Update the category
        $response = $this->postJson('/api/v1/task-categories/update/' . $this->categoryId, [
            'name' => 'Updated Backend API Testing'
        ], $headers);
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'Ok', 'status_code' => '200', 'message' => 'Task Category updated successfully!']);
        $this->assertEquals('Updated Backend API Testing', $response->json('data.name'));

        // 5. Delete the category
        $response = $this->deleteJson('/api/v1/task-categories/' . $this->categoryId, [], $headers);
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'Ok', 'status_code' => '200', 'message' => 'Task Category deleted successfully!']);
        
        $this->assertDatabaseMissing('task_categories', ['id' => $this->categoryId], $this->connName);
    }
}
