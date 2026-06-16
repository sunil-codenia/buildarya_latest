<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class TaskChatApiTest extends TestCase
{
    private $tokenValue;
    private $tokenId;
    private $testUserId = 82; // Let's use user 82 (sunil) who is assignee in company_rsgeotech
    private $connName = 'company_rsgeotech';
    private $createdFiles = [];
    private $taskId;

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

        // Ensure tasks table schema has assigned_to and assigned_by columns
        if (!\Illuminate\Support\Facades\Schema::connection($this->connName)->hasColumn('tasks', 'assigned_to')) {
            DB::connection($this->connName)->statement("ALTER TABLE `tasks` ADD COLUMN `assigned_to` INT NULL");
        }
        if (!\Illuminate\Support\Facades\Schema::connection($this->connName)->hasColumn('tasks', 'assigned_by')) {
            DB::connection($this->connName)->statement("ALTER TABLE `tasks` ADD COLUMN `assigned_by` INT NULL");
        }

        // 3. Create a test task in company_rsgeotech
        $taskData = [
            'title' => 'API Test Task',
            'description' => 'API Test Description',
            'site_id' => 0,
            'assigned_to' => $this->testUserId,
            'assigned_by' => 5, // Created by admin user 5
            'created_by' => 5,
            'status' => 'Pending',
            'priority' => 'Medium',
            'created_at' => now(),
        ];
        if (\Illuminate\Support\Facades\Schema::connection($this->connName)->hasColumn('tasks', 'updated_at')) {
            $taskData['updated_at'] = now();
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->connName)->hasColumn('tasks', 'project_id')) {
            $taskData['project_id'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->connName)->hasColumn('tasks', 'total_units')) {
            $taskData['total_units'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->connName)->hasColumn('tasks', 'unit_type')) {
            $taskData['unit_type'] = '';
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->connName)->hasColumn('tasks', 'task_type')) {
            $taskData['task_type'] = 'TASK';
        }
        if (\Illuminate\Support\Facades\Schema::connection($this->connName)->hasColumn('tasks', 'parent_task_id')) {
            $taskData['parent_task_id'] = 0;
        }
        $this->taskId = DB::connection($this->connName)->table('tasks')->insertGetId($taskData);

        // 4. Insert fake Sanctum token for user 82 (assignee) into central DB
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
        // 1. Clean up personal access token
        DB::connection('mysql')->table('personal_access_tokens')
            ->where('id', $this->tokenId)
            ->delete();

        // 2. Clean up task chats and task
        DB::connection($this->connName)->table('task_chats')
            ->where('task_id', $this->taskId)
            ->delete();
        DB::connection($this->connName)->table('tasks')
            ->where('id', $this->taskId)
            ->delete();

        // 3. Clean up uploaded files
        foreach ($this->createdFiles as $filePath) {
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }

        parent::tearDown();
    }

    public function test_task_chat_api_workflow()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenId . '|' . $this->tokenValue,
        ];

        // --- PART 1: SEND CHAT MESSAGE ---
        $response = $this->postJson('/api/v1/tasks/chats', [
            'task_id' => $this->taskId,
            'message' => 'Hello from assignee sunil via API',
        ], $headers);

        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'Ok', 'status_code' => '200', 'message' => 'Message sent successfully!']);
        $this->assertNotNull($response->json('data.id'));

        // --- PART 2: FETCH CHAT MESSAGES ---
        $response = $this->getJson('/api/v1/tasks/chats?task_id=' . $this->taskId, $headers);
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'Ok', 'status_code' => '200']);
        
        $history = $response->json('data.history');
        $this->assertNotEmpty($history);
        $this->assertEquals('Hello from assignee sunil via API', $history[0]['message']);
        $this->assertEquals($this->taskId, $history[0]['task_id']);
        $this->assertEquals($this->testUserId, $history[0]['user_id']);

        // --- PART 3: SEND CHAT MESSAGE WITH IMAGE ---
        $imageFile = UploadedFile::fake()->image('chat_attachment.png');
        $response = $this->postJson('/api/v1/tasks/chats', [
            'task_id' => $this->taskId,
            'message' => 'Chat with image',
            'image' => $imageFile,
        ], $headers);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('data.image'));
        
        $savedPath = public_path($response->json('data.image'));
        $this->createdFiles[] = $savedPath;
        $this->assertFileExists($savedPath);

        // --- PART 4: FETCH VALIDATION ERRORS ---
        // Missing task_id
        $response = $this->postJson('/api/v1/tasks/chats', [
            'message' => 'No task id',
        ], $headers);
        $response->assertStatus(422);

        // Empty message and image
        $response = $this->postJson('/api/v1/tasks/chats', [
            'task_id' => $this->taskId,
        ], $headers);
        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Please provide a message or an image.']);
    }

    public function test_unauthorized_user_cannot_access_or_send_task_chat()
    {
        // 1. Create a token for user 6 (intruder)
        $unauthorizedUserId = 6;
        $unauthVal = Str::random(40);
        $hashedToken = hash('sha256', $unauthVal);
        $unauthId = DB::connection('mysql')->table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\User',
            'tokenable_id' => $unauthorizedUserId,
            'name' => $this->connName,
            'token' => $hashedToken,
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $unauthId . '|' . $unauthVal,
        ];

        try {
            // Fetch messages for task assignee 82 should fail
            $response = $this->getJson('/api/v1/tasks/chats?task_id=' . $this->taskId . '&user_id=' . $this->testUserId, $headers);
            $response->assertStatus(403);
            $response->assertJsonFragment(['message' => 'Access denied. You do not have permission to view this task\'s chats.']);

            // Send message for task should fail
            $response = $this->postJson('/api/v1/tasks/chats', [
                'task_id' => $this->taskId,
                'message' => 'Im intruder',
            ], $headers);
            $response->assertStatus(403);
            $response->assertJsonFragment(['message' => 'Access denied. You do not have permission to send messages for this task.']);
        } finally {
            DB::connection('mysql')->table('personal_access_tokens')
                ->where('id', $unauthId)
                ->delete();
        }
    }
}
