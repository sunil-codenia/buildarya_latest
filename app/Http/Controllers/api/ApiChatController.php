<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ApiChatController extends Controller
{
    /**
     * Resolve active Database Connection and User ID from Request or Bearer Token fallback
     */
    private function resolveTenant(Request $request)
    {
        $conn = $request->get('conn') ?? $request->post('conn');
        $user_id = $request->get('uid') ?? $request->post('uid');

        // Fallback: Resolve from Bearer token
        if ((!$conn || !$user_id) && $request->bearerToken()) {
            $tokenStr = $request->bearerToken();
            $tokenId = null;
            if (strpos($tokenStr, '|') !== false) {
                [$tokenId, $tokenStr] = explode('|', $tokenStr, 2);
            }
            $token = DB::connection('mysql')->table('personal_access_tokens')->where('id', $tokenId)->first();
            if ($token) {
                $conn = $conn ?? $token->name;
                $user_id = $user_id ?? $token->tokenable_id;
            }
        }

        if (!$conn) {
            $conn = config('database.default');
        }

        return ['conn' => $conn, 'uid' => $user_id];
    }

    /**
     * Fetch chat history for a specific thread (identified by user_id)
     */
    public function index(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $currentUser = (int)$tenant['uid'];

            // 1. Determine admin status
            $userRecord = DB::connection($conn)->table('users')->where('id', $currentUser)->first();
            if (!$userRecord) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'User not found in tenant database.'
                ], 404);
            }

            $isAdmin = false;
            if ($userRecord->role_id == 1) {
                $isAdmin = true;
            } else {
                $perm = DB::connection($conn)->table('user_permission')
                    ->where('user_id', $currentUser)
                    ->where('module_id', 14) // Module 14 is Tasks/Chats
                    ->first();
                if ($perm && $perm->can_add == 1) {
                    $isAdmin = true;
                }
            }

            // 2. Resolve the target thread user_id
            // If user_id is provided in request, use it. If not, default to current user if they are not admin.
            $threadUserId = $request->input('user_id');
            if (!$threadUserId) {
                if ($isAdmin) {
                    return response()->json([
                        'status' => 'Failed',
                        'status_code' => '300',
                        'message' => 'The user_id parameter is required for admins to view a chat thread.'
                    ], 400);
                }
                $threadUserId = $currentUser;
            } else {
                $threadUserId = (int)$threadUserId;
            }

            // 3. Security Check: Non-admin users are only allowed to see their own thread history
            if (!$isAdmin && $currentUser !== $threadUserId) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Access denied. You can only view your own chat history.'
                ], 403);
            }

            // Ensure the table exists/has proper structure
            $this->ensureTableExists($conn);

            // 4. Fetch history
            $messages = DB::connection($conn)
                ->table('task_chats')
                ->leftJoin('users', 'users.id', '=', 'task_chats.sender_id')
                ->select(
                    'task_chats.id',
                    'task_chats.user_id as thread_user_id',
                    'task_chats.sender_id',
                    'users.name as sender_name',
                    'users.username as sender_username',
                    'task_chats.message',
                    'task_chats.image',
                    'task_chats.created_at',
                    'task_chats.updated_at'
                )
                ->where('task_chats.user_id', $threadUserId)
                ->orderBy('task_chats.created_at', 'asc')
                ->get();

            // Format image URLs
            foreach ($messages as $msg) {
                if ($msg->image) {
                    $msg->image_url = url($msg->image);
                } else {
                    $msg->image_url = null;
                }
            }

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => [
                    'thread_user_id' => $threadUserId,
                    'current_user_id' => $currentUser,
                    'is_admin' => $isAdmin,
                    'history' => $messages
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to retrieve chat history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send/Save a new chat message (from user or admin)
     */
    public function store(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $currentUser = (int)$tenant['uid'];

            // 1. Determine admin status
            $userRecord = DB::connection($conn)->table('users')->where('id', $currentUser)->first();
            if (!$userRecord) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'User not found in tenant database.'
                ], 404);
            }

            $isAdmin = false;
            if ($userRecord->role_id == 1) {
                $isAdmin = true;
            } else {
                $perm = DB::connection($conn)->table('user_permission')
                    ->where('user_id', $currentUser)
                    ->where('module_id', 14)
                    ->first();
                if ($perm && $perm->can_add == 1) {
                    $isAdmin = true;
                }
            }

            // 2. Validate input message and image
            $request->validate([
                'user_id' => 'nullable|integer',
                'message' => 'nullable|string|max:5000',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            ]);

            if (!$request->filled('message') && !$request->hasFile('image')) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Please provide a message or an image.'
                ], 400);
            }

            // 3. Resolve the target thread user_id
            $threadUserId = $request->input('user_id');
            if (!$threadUserId) {
                if ($isAdmin) {
                    return response()->json([
                        'status' => 'Failed',
                        'status_code' => '300',
                        'message' => 'The user_id parameter is required for admins to target a chat thread.'
                    ], 400);
                }
                $threadUserId = $currentUser;
            } else {
                $threadUserId = (int)$threadUserId;
            }

            // 4. Security Check: Non-admin users are only allowed to post in their own thread
            if (!$isAdmin && $currentUser !== $threadUserId) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Access denied. You can only send messages in your own chat thread.'
                ], 403);
            }

            // Ensure the table exists/has proper structure
            $this->ensureTableExists($conn);

            // 5. Handle image upload if present
            $imagePath = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                if ($file->isValid()) {
                    $path = public_path('uploads/chat');
                    if (!File::exists($path)) {
                        File::makeDirectory($path, 0777, true, true);
                    }
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move($path, $filename);
                    $imagePath = 'uploads/chat/' . $filename;
                }
            }

            // 6. Save message to DB
            $insertedData = [
                'user_id' => $threadUserId,
                'sender_id' => $currentUser,
                'message' => $request->filled('message') ? trim($request->message) : null,
                'image' => $imagePath,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ];

            $msgId = DB::connection($conn)->table('task_chats')->insertGetId($insertedData);

            $msgRecord = DB::connection($conn)->table('task_chats')->where('id', $msgId)->first();
            if ($msgRecord && $msgRecord->image) {
                $msgRecord->image_url = url($msgRecord->image);
            } else if ($msgRecord) {
                $msgRecord->image_url = null;
            }

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Message sent successfully!',
                'data' => $msgRecord
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch task-specific chat history for a particular task and optional/required user_id
     */
    public function fetchTaskChats(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $currentUser = (int)$tenant['uid'];

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'task_id' => 'required|integer',
                'user_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $taskId = (int)$request->input('task_id');

            // 1. Determine admin status
            $userRecord = DB::connection($conn)->table('users')->where('id', $currentUser)->first();
            if (!$userRecord) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'User not found in tenant database.'
                ], 404);
            }

            $isAdmin = false;
            if ($userRecord->role_id == 1) {
                $isAdmin = true;
            } else {
                $perm = DB::connection($conn)->table('user_permission')
                    ->where('user_id', $currentUser)
                    ->where('module_id', 14) // Module 14 is Tasks/Chats
                    ->first();
                if ($perm && $perm->can_add == 1) {
                    $isAdmin = true;
                }
            }

            // Fetch the task
            $task = DB::connection($conn)->table('tasks')->where('id', $taskId)->first();
            if (!$task) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task not found.'
                ], 404);
            }

            $isAssigned = ($task->assigned_to == $currentUser);
            $isCreator = ($task->assigned_by == $currentUser);

            // Access check: only admins, assignees, or creators can access
            if (!$isAdmin && !$isAssigned && !$isCreator) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Access denied. You do not have permission to view this task\'s chats.'
                ], 403);
            }

            // Resolve target thread user_id
            $threadUserId = $request->input('user_id');
            if (!$threadUserId) {
                if ($isAdmin || $isCreator) {
                    // For admins/creators, default to the task's assignee
                    $threadUserId = (int)$task->assigned_to;
                } else {
                    $threadUserId = $currentUser;
                }
            } else {
                $threadUserId = (int)$threadUserId;
            }

            // Non-admins/non-creators can only access their own thread under this task
            if (!$isAdmin && !$isCreator && $currentUser !== $threadUserId) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Access denied. You can only view your own chat thread under this task.'
                ], 403);
            }

            // Ensure the table exists/has proper structure
            $this->ensureTableExists($conn);

            // Fetch messages
            $messages = DB::connection($conn)
                ->table('task_chats')
                ->leftJoin('users', 'users.id', '=', 'task_chats.sender_id')
                ->select(
                    'task_chats.id',
                    'task_chats.task_id',
                    'task_chats.user_id',
                    'task_chats.sender_id',
                    'users.name as sender_name',
                    'users.username as sender_username',
                    'task_chats.message',
                    'task_chats.image',
                    'task_chats.created_at',
                    'task_chats.updated_at'
                )
                ->where('task_chats.task_id', $taskId)
                ->where('task_chats.user_id', $threadUserId)
                ->orderBy('task_chats.created_at', 'asc')
                ->get();

            // Format image URLs
            foreach ($messages as $msg) {
                if ($msg->image) {
                    $msg->image_url = url($msg->image);
                } else {
                    $msg->image_url = null;
                }
            }

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => [
                    'task_id' => $taskId,
                    'thread_user_id' => $threadUserId,
                    'current_user_id' => $currentUser,
                    'is_admin' => $isAdmin,
                    'history' => $messages
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to retrieve task chat history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message to a particular task chat for a particular user
     */
    public function sendTaskChat(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $currentUser = (int)$tenant['uid'];

            // Validate inputs
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'task_id' => 'required|integer',
                'user_id' => 'nullable|integer',
                'message' => 'nullable|string|max:5000',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!$request->filled('message') && !$request->hasFile('image')) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Please provide a message or an image.'
                ], 400);
            }

            $taskId = (int)$request->input('task_id');

            // Determine admin status
            $userRecord = DB::connection($conn)->table('users')->where('id', $currentUser)->first();
            if (!$userRecord) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'User not found in tenant database.'
                ], 404);
            }

            $isAdmin = false;
            if ($userRecord->role_id == 1) {
                $isAdmin = true;
            } else {
                $perm = DB::connection($conn)->table('user_permission')
                    ->where('user_id', $currentUser)
                    ->where('module_id', 14)
                    ->first();
                if ($perm && $perm->can_add == 1) {
                    $isAdmin = true;
                }
            }

            // Fetch the task
            $task = DB::connection($conn)->table('tasks')->where('id', $taskId)->first();
            if (!$task) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task not found.'
                ], 404);
            }

            $isAssigned = ($task->assigned_to == $currentUser);
            $isCreator = ($task->assigned_by == $currentUser);

            // Access check: only admins, assignees, or creators can send messages
            if (!$isAdmin && !$isAssigned && !$isCreator) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Access denied. You do not have permission to send messages for this task.'
                ], 403);
            }

            // Resolve target thread user_id
            $threadUserId = $request->input('user_id');
            if (!$threadUserId) {
                if ($isAdmin || $isCreator) {
                    $threadUserId = (int)$task->assigned_to;
                } else {
                    $threadUserId = $currentUser;
                }
            } else {
                $threadUserId = (int)$threadUserId;
            }

            // Non-admins/non-creators can only post messages to their own thread under this task
            if (!$isAdmin && !$isCreator && $currentUser !== $threadUserId) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Access denied. You can only send messages to your own chat thread under this task.'
                ], 403);
            }

            // Ensure the table exists/has proper structure
            $this->ensureTableExists($conn);

            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                if ($file->isValid()) {
                    $path = public_path('uploads/chat');
                    if (!File::exists($path)) {
                        File::makeDirectory($path, 0777, true, true);
                    }
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move($path, $filename);
                    $imagePath = 'uploads/chat/' . $filename;
                }
            }

            // Save message
            $insertedData = [
                'task_id' => $taskId,
                'user_id' => $threadUserId,
                'sender_id' => $currentUser,
                'message' => $request->filled('message') ? trim($request->message) : null,
                'image' => $imagePath,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ];

            $msgId = DB::connection($conn)->table('task_chats')->insertGetId($insertedData);

            $msgRecord = DB::connection($conn)->table('task_chats')->where('id', $msgId)->first();
            if ($msgRecord && $msgRecord->image) {
                $msgRecord->image_url = url($msgRecord->image);
            } else if ($msgRecord) {
                $msgRecord->image_url = null;
            }

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Message sent successfully!',
                'data' => $msgRecord
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ensure the task_chats table is structured properly
     */
    private function ensureTableExists($conn)
    {
        try {
            DB::connection($conn)->statement("
                CREATE TABLE IF NOT EXISTS `task_chats` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NULL,
                    `sender_id` INT NOT NULL,
                    `message` TEXT NULL,
                    `image` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (`user_id`),
                    INDEX (`sender_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            // Alter to add task_id column if it doesn't exist
            if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('task_chats', 'task_id')) {
                DB::connection($conn)->statement("ALTER TABLE `task_chats` ADD COLUMN `task_id` INT NULL AFTER `id`, ADD INDEX (`task_id`)");
            }

            // Alter to add image column if it doesn't exist
            if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('task_chats', 'image')) {
                DB::connection($conn)->statement("ALTER TABLE `task_chats` ADD COLUMN `image` VARCHAR(255) NULL AFTER `message`");
            }
            
            // Alter to make user_id nullable if it was not
            DB::connection($conn)->statement("ALTER TABLE `task_chats` MODIFY COLUMN `user_id` INT NULL");

            // Alter to make message nullable if it was not
            DB::connection($conn)->statement("ALTER TABLE `task_chats` MODIFY COLUMN `message` TEXT NULL");
        } catch (\Exception $e) {
            \Log::error("Failed to ensure task_chats table schema in ApiChatController: " . $e->getMessage());
        }
    }
}
