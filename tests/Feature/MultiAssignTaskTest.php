<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class MultiAssignTaskTest extends TestCase
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

        // Seed users to assign
        DB::connection($this->conn)->table('users')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'pass' => 'pass123',
                'role_id' => 1,
            ]
        );
        DB::connection($this->conn)->table('users')->updateOrInsert(
            ['id' => 82],
            [
                'name' => 'Sunil User',
                'username' => 'sunil',
                'pass' => 'pass123',
                'role_id' => 2,
            ]
        );
        DB::connection($this->conn)->table('users')->updateOrInsert(
            ['id' => 83],
            [
                'name' => 'Demo User',
                'username' => 'demo',
                'pass' => 'pass123',
                'role_id' => 2,
            ]
        );

        // Ensure tasks table exists and has assigned_to as VARCHAR
        DB::connection($this->conn)->statement("DROP TABLE IF EXISTS `tasks` ");
        DB::connection($this->conn)->statement("
            CREATE TABLE IF NOT EXISTS `tasks` (
                `id`           INT AUTO_INCREMENT PRIMARY KEY,
                `title`        VARCHAR(255) NOT NULL,
                `description`  TEXT DEFAULT NULL,
                `site_id`      INT DEFAULT NULL,
                `project_id`   INT DEFAULT NULL,
                `assigned_to`  VARCHAR(255) DEFAULT NULL,
                `assigned_by`  INT DEFAULT NULL,
                `created_by`   INT DEFAULT NULL,
                `priority`     ENUM('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
                `status`       ENUM('Pending','In Progress','Completed','On Hold','Cancelled') NOT NULL DEFAULT 'Pending',
                `due_date`     DATE DEFAULT NULL,
                `completed_at` TIMESTAMP NULL DEFAULT NULL,
                `remarks`      TEXT DEFAULT NULL,
                `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function test_multi_assignee_web_workflows()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'company_modules' => [14],
        ];


        // 1. Create a task with multi-select array [82, 83]
        $response = $this->withSession($sessionData)->post('/tasks', [
            'title' => 'Multi Assignee Task',
            'description' => 'Test multiple assignees',
            'site_id' => 1,
            'assigned_to' => [82, 83],
            'priority' => 'Medium',
            'due_date' => '2026-12-31'
        ]);

        $response->assertRedirect();

        // 2. Assert database has the comma-separated string '82,83'
        $this->assertDatabaseHas('tasks', [
            'title' => 'Multi Assignee Task',
            'assigned_to' => '82,83'
        ], $this->conn);
    }
}
