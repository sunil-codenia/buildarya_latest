<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class TaskChatTest extends TestCase
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

        // Seed site
        DB::connection($this->conn)->table('sites')->updateOrInsert(
            ['id' => 999],
            ['name' => 'QA-SITE-999', 'status' => 'Active', 'address' => 'Test Site 999']
        );

        // Seed task dynamically based on columns
        $taskData = [
            'title' => 'QA-TASK-999',
            'site_id' => 999,
            'assigned_to' => 1,
            'assigned_by' => 1,
            'priority' => 'High',
            'status' => 'Pending'
        ];
        if (\Illuminate\Support\Facades\Schema::connection($this->conn)->hasColumn('tasks', 'project_id')) {
            $taskData['project_id'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->conn)->hasColumn('tasks', 'total_units')) {
            $taskData['total_units'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->conn)->hasColumn('tasks', 'unit_type')) {
            $taskData['unit_type'] = '';
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->conn)->hasColumn('tasks', 'task_type')) {
            $taskData['task_type'] = 'TASK';
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->conn)->hasColumn('tasks', 'parent_task_id')) {
            $taskData['parent_task_id'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->conn)->hasColumn('tasks', 'created_by')) {
            $taskData['created_by'] = 1;
        }

        DB::connection($this->conn)->table('tasks')->updateOrInsert(
            ['id' => 999],
            $taskData
        );

        // Clean up chats for task 999
        DB::connection($this->conn)->table('task_chats')->where('task_id', 999)->delete();
    }

    public function test_send_task_message_with_image_upload()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'company_modules' => [14], // Tasks module access
        ];

        $postData = [
            'task_id' => 999,
            'message' => 'QA-REG-20260623-893869-TASKCHAT-IMAGE-TEST',
            'image' => UploadedFile::fake()->image('evidence.jpg')
        ];

        $response = $this->withSession($sessionData)
            ->post('/tasks/chat/task-send', $postData);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // Check it exists in the database
        $this->assertDatabaseHas('task_chats', [
            'task_id' => 999,
            'message' => 'QA-REG-20260623-893869-TASKCHAT-IMAGE-TEST',
        ], $this->conn);

        // Retrieve and check path prefix is valid
        $chat = DB::connection($this->conn)->table('task_chats')->where('task_id', 999)->first();
        $this->assertNotNull($chat->image);
        
        // Assert it starts with one of the allowed directories (uploads/chat or images/app_images)
        $this->assertTrue(
            str_starts_with($chat->image, 'uploads/chat/') || 
            str_starts_with($chat->image, 'images/app_images/')
        );
    }
}
