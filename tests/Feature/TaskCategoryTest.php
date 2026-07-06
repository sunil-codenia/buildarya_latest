<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class TaskCategoryTest extends TestCase
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

        // Seed default admin role if missing
        DB::connection($this->conn)->table('roles')->updateOrInsert(
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

        // Ensure task_categories table exists
        DB::connection($this->conn)->statement("
            CREATE TABLE IF NOT EXISTS `task_categories` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Clean up task categories
        DB::connection($this->conn)->table('task_categories')->truncate();
    }

    public function test_task_category_crud_operations()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'company_modules' => [14], // Tasks module access
        ];

        // 1. Visit /task_category page
        $response = $this->withSession($sessionData)->get('/task_category');
        $response->assertStatus(200);
        $response->assertViewIs('layouts.tasks.category');

        // 2. Add task category
        $response = $this->withSession($sessionData)->post('/addtaskcategory', [
            'name' => 'Development Tasks'
        ]);
        $response->assertRedirect('/task_category');
        $this->assertDatabaseHas('task_categories', ['name' => 'Development Tasks'], $this->conn);

        // Get ID
        $category = DB::connection($this->conn)->table('task_categories')->where('name', 'Development Tasks')->first();
        $categoryId = $category->id;

        // 3. Edit task category view
        $response = $this->withSession($sessionData)->get('/edit_task_category?id=' . $categoryId);
        $response->assertStatus(200);
        $response->assertViewIs('layouts.tasks.category');

        // 4. Update task category
        $response = $this->withSession($sessionData)->post('/updatetaskcategory', [
            'id' => $categoryId,
            'name' => 'Updated Dev Tasks'
        ]);
        $response->assertRedirect('/task_category');
        $this->assertDatabaseHas('task_categories', ['id' => $categoryId, 'name' => 'Updated Dev Tasks'], $this->conn);

        // 5. Datatable AJAX request
        $response = $this->withSession($sessionData)->post('/task_category_ajax', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'Updated Dev Tasks']
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        // 6. Add another category for bulk test
        $response = $this->withSession($sessionData)->post('/addtaskcategory', [
            'name' => 'Testing Tasks'
        ]);
        $response->assertRedirect('/task_category');
        $category2 = DB::connection($this->conn)->table('task_categories')->where('name', 'Testing Tasks')->first();
        $categoryId2 = $category2->id;

        // 7. Bulk edit view
        $response = $this->withSession($sessionData)->post('/bulk_edit_category', [
            'check_list' => [$categoryId, $categoryId2]
        ]);
        $response->assertStatus(200);
        $response->assertViewIs('layouts.tasks.bulk_edit_category');

        // 8. Bulk update
        $response = $this->withSession($sessionData)->post('/update_bulk_category', [
            'id' => [$categoryId, $categoryId2],
            'name' => ['Bulk 1', 'Bulk 2']
        ]);
        $response->assertRedirect('/task_category');
        $this->assertDatabaseHas('task_categories', ['id' => $categoryId, 'name' => 'Bulk 1'], $this->conn);
        $this->assertDatabaseHas('task_categories', ['id' => $categoryId2, 'name' => 'Bulk 2'], $this->conn);

        // 9. Bulk delete
        $response = $this->withSession($sessionData)->post('/task_category/bulk_action', [
            'check_list' => [$categoryId, $categoryId2],
            'bulk_action' => 'delete'
        ]);
        $response->assertRedirect('/task_category');
        $this->assertDatabaseMissing('task_categories', ['id' => $categoryId], $this->conn);
        $this->assertDatabaseMissing('task_categories', ['id' => $categoryId2], $this->conn);
    }
}
