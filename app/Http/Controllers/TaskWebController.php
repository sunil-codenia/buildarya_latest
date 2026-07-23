<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskWebController extends Controller
{
    public function index(Request $request)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return redirect('/login')->with('error', 'Please log in again.');
        }

        $uid = session()->get('uid');

        $this->ensureTableExists($conn);

        // Fetch all sites and users
        $sites = DB::connection($conn)->table('sites')->get();
        $users = DB::connection($conn)->table('users')->get();
        $categories = DB::connection($conn)->table('task_categories')->get();

        // Admins see all tasks; regular users only see their own / involved tasks
        $isSuperAdmin = isSuperAdmin();
        $isChatAdmin = $isSuperAdmin;
        $isAdmin = $isSuperAdmin;

        // Query Tasks
        $query = DB::connection($conn)->table('tasks')
            ->leftJoin('users as creators', 'creators.id', '=', 'tasks.assigned_by')
            ->leftJoin('sites', 'sites.id', '=', 'tasks.site_id')
            ->leftJoin('task_categories', 'task_categories.id', '=', 'tasks.category_id')
            ->select('tasks.*', 'creators.name as creator_name', 'sites.name as site_name', 'task_categories.name as category_name');

        // Non-superadmin users only see tasks they are involved in (assigned to them or created by them)
        if (!$isSuperAdmin) {
            $query->where(function($q) use ($uid) {
                $q->whereRaw("FIND_IN_SET(?, tasks.assigned_to)", [$uid])
                  ->orWhere('tasks.assigned_by', '=', $uid);
            });
        }

        // Apply filters if present
        if ($request->filled('site_id')) {
            $query->where('tasks.site_id', $request->site_id);
        }
        if ($request->filled('priority')) {
            $query->where('tasks.priority', $request->priority);
        }
        if ($request->filled('status')) {
            $query->where('tasks.status', $request->status);
        }
        if ($request->filled('from_date')) {
            $query->where('tasks.due_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('tasks.due_date', '<=', $request->to_date);
        }

        $tasks = $query->orderBy('tasks.id', 'desc')->get();

        // Dynamically resolve assigned_name (comma separated user names) for each task
        $userMap = $users->pluck('name', 'id')->toArray();
        foreach ($tasks as $task) {
            $assignedIds = array_filter(explode(',', $task->assigned_to));
            $names = [];
            foreach ($assignedIds as $id) {
                if (isset($userMap[$id])) {
                    $names[] = $userMap[$id];
                }
            }
            $task->assigned_name = implode(', ', $names);
        }

        // Calculate Stats
        $totalTasks = $tasks->count();
        $pending = $tasks->where('status', 'Pending')->count();
        $inProgress = $tasks->where('status', 'Progress')->count();
        $completed = $tasks->where('status', 'Completed')->count();

        return view('layouts.tasks.index', compact('tasks', 'sites', 'users', 'categories', 'totalTasks', 'pending', 'inProgress', 'completed', 'isAdmin', 'isChatAdmin'));
    }

    public function store(Request $request)
    {
        // Enforce can_add module permission
        if (checkmodulepermission(14, 'can_add') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to create tasks.');
        }

        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return redirect('/login');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'site_id' => 'required',
            'assigned_to' => 'required',
            'priority' => 'required|in:Low,Medium,High',
            'due_date' => 'nullable|date',
            'completed_at' => 'nullable|date'
        ]);

        $assigned = $request->assigned_to;
        if (is_array($assigned)) {
            $assigned = implode(',', $assigned);
        }

        $data = [
            'title' => $request->title,
            'category_id' => $request->category_id,
            'description' => $request->description,
            'site_id' => $request->site_id,
            'assigned_to' => $assigned,
            'assigned_by' => session()->get('uid') ?? 1,
            'priority' => $request->priority,
            'status' => 'Pending',
            'due_date' => $request->due_date,
            'completed_at' => $request->completed_at,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString()
        ];

        // Safely add legacy columns to prevent strict mode errors on live, while avoiding unknown column errors on local
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'project_id')) {
            $data['project_id'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'total_units')) {
            $data['total_units'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'unit_type')) {
            $data['unit_type'] = '';
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'task_type')) {
            $data['task_type'] = 'TASK';
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'parent_task_id')) {
            $data['parent_task_id'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'created_by')) {
            $data['created_by'] = session()->get('uid') ?? 1;
        }

        DB::connection($conn)->table('tasks')->insert($data);

        // Notify assigned users
        $assignedIds = array_filter(explode(',', $assigned));
        foreach ($assignedIds as $assignedId) {
            sendAlertNotification($assignedId, 'You have been assigned a new task: ' . $request->title, 'Task Assigned');
            saveWebNotification($assignedId, 'Task Assigned', 'You have been assigned a new task: ' . $request->title, '/tasks', $conn);
        }

        return redirect()->back()->with('success', 'Task created successfully!');
    }

    public function update(Request $request, $id)
    {
        // Enforce can_edit module permission
        if (checkmodulepermission(14, 'can_edit') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to edit tasks.');
        }

        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return redirect('/login');
        }

        $task = DB::connection($conn)->table('tasks')->where('id', $id)->first();
        if (!$task) {
            return redirect()->back()->with('errorcode', 'Task not found.');
        }

        if (!isSuperAdmin()) {
            $uid = session()->get('uid');
            $assignedIds = array_filter(explode(',', $task->assigned_to));
            if (!in_array($uid, $assignedIds) && $task->assigned_by != $uid) {
                return redirect()->back()->with('errorcode', 'You do not have permission to modify this task.');
            }
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'site_id' => 'required',
            'assigned_to' => 'required',
            'priority' => 'required|in:Low,Medium,High',
            'due_date' => 'nullable|date',
            'completed_at' => 'nullable|date'
        ]);

        $assigned = $request->assigned_to;
        if (is_array($assigned)) {
            $assigned = implode(',', $assigned);
        }

        $data = [
            'title' => $request->title,
            'category_id' => $request->category_id,
            'description' => $request->description,
            'site_id' => $request->site_id,
            'assigned_to' => $assigned,
            'priority' => $request->priority,
            'due_date' => $request->due_date,
            'completed_at' => $request->completed_at,
            'updated_at' => Carbon::now()->toDateTimeString()
        ];

        DB::connection($conn)->table('tasks')->where('id', $id)->update($data);

        return redirect()->back()->with('success', 'Task updated successfully!');
    }

    public function updateStatus(Request $request, $id)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized'], 401);
        }

        $uid = session()->get('uid');
        $isAdmin = (checkmodulepermission(14, 'can_add') == 1);

        // Fetch the task
        $task = DB::connection($conn)->table('tasks')->where('id', $id)->first();
        if (!$task) {
            return redirect()->back()->with('errorcode', 'Task not found.');
        }

        if (!isSuperAdmin()) {
            $assignedIds = array_filter(explode(',', $task->assigned_to));
            if (!in_array($uid, $assignedIds) && $task->assigned_by != $uid) {
                return redirect()->back()->with('errorcode', 'You do not have permission to modify this task.');
            }
        }

        // Removed restrictions to allow users to freely update status

        $request->validate([
            'status' => 'required|in:Pending,Progress,Completed,On Hold',
            'completed_at' => 'nullable|date',
            'remarks' => 'nullable|string'
        ]);

        $updateData = [
            'status' => $request->status,
            'updated_at' => Carbon::now()->toDateTimeString()
        ];

        if ($request->status == 'Completed') {
            $updateData['completed_at'] = $request->completed_at ?? Carbon::now()->toDateTimeString();
        }

        if ($request->status == 'On Hold') {
            $updateData['remarks'] = $request->remarks;
        }

        DB::connection($conn)->table('tasks')->where('id', $id)->update($updateData);

        // Notify assigned users of status change
        $assignedIds = array_filter(explode(',', $task->assigned_to));
        foreach ($assignedIds as $assignedId) {
            sendAlertNotification($assignedId, 'Task "' . $task->title . '" status updated to: ' . $request->status, 'Task Status Updated');
            saveWebNotification($assignedId, 'Task Status Updated', 'Task "' . $task->title . '" status updated to: ' . $request->status, '/tasks', $conn);
        }

        return redirect()->back()->with('success', 'Task status updated to "' . $request->status . '" successfully!');
    }

    public function delete($id)
    {
        // Enforce can_delete module permission
        if (checkmodulepermission(14, 'can_delete') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to delete tasks.');
        }

        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return redirect('/login');
        }

        $task = DB::connection($conn)->table('tasks')->where('id', $id)->first();
        if (!$task) {
            return redirect()->back()->with('errorcode', 'Task not found.');
        }

        if (!isSuperAdmin()) {
            $uid = session()->get('uid');
            $assignedIds = array_filter(explode(',', $task->assigned_to));
            if (!in_array($uid, $assignedIds) && $task->assigned_by != $uid) {
                return redirect()->back()->with('errorcode', 'You do not have permission to delete this task.');
            }
        }

        DB::connection($conn)->table('tasks')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Task deleted successfully!');
    }

    public function fetchMessages(Request $request, $userId)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $uid    = session()->get('uid');
        if (!$uid) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $isChatAdmin = isSuperAdmin();

        // A non-admin user may only read their own thread.
        // An admin may read ANY thread (identified by the non-admin user's ID).
        if (!$isChatAdmin && (int)$uid !== (int)$userId) {
            return response()->json(['status' => 'error', 'message' => 'Access denied.'], 403);
        }

        $this->ensureTableExists($conn);

        // Mark support chat notifications as read for this user
        DB::connection($conn)->table('web_notifications')
            ->where('user_id', $uid)
            ->where('title', 'like', '%Support Message%')
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        $messages = DB::connection($conn)
            ->table('task_chats')
            ->leftJoin('users', 'users.id', '=', 'task_chats.sender_id')
            ->select('task_chats.*', 'users.name as sender_name')
            ->where('task_chats.user_id', (int)$userId)
            ->orderBy('task_chats.created_at', 'asc')
            ->get();

        return response()->json([
            'status'          => 'success',
            'messages'        => $messages,
            'current_user_id' => (int)$uid,
        ]);
    }

    public function sendMessage(Request $request)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'user_id' => 'required|integer',
            'message' => 'nullable|string|max:5000',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        if (!$request->filled('message') && !$request->hasFile('image')) {
            return response()->json(['status' => 'error', 'message' => 'Please type a message or upload an image.'], 422);
        }

        $uid     = session()->get('uid');
        if (!$uid) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $isChatAdmin = isSuperAdmin();

        // The thread key (user_id) must either be the current user's own ID
        // OR the admin is sending to any user.
        if (!$isChatAdmin && (int)$uid !== (int)$request->user_id) {
            return response()->json(['status' => 'error', 'message' => 'Access denied.'], 403);
        }

        $this->ensureTableExists($conn);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid()) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $uploaded = false;
                try {
                    $path = public_path('uploads/chat');
                    if (!\Illuminate\Support\Facades\File::exists($path)) {
                        \Illuminate\Support\Facades\File::makeDirectory($path, 0777, true, true);
                    }
                    $file->move($path, $filename);
                    $imagePath = 'uploads/chat/' . $filename;
                    $uploaded = true;
                } catch (\Exception $e) {
                    \Log::warning("Primary chat upload path uploads/chat is not writable: " . $e->getMessage());
                }

                if (!$uploaded) {
                    try {
                        $path = public_path('images/app_images/' . $conn . '/chat');
                        if (!\Illuminate\Support\Facades\File::exists($path)) {
                            \Illuminate\Support\Facades\File::makeDirectory($path, 0777, true, true);
                        }
                        $file->move($path, $filename);
                        $imagePath = 'images/app_images/' . $conn . '/chat/' . $filename;
                    } catch (\Exception $e) {
                        \Log::error("Fallback chat upload path is also not writable: " . $e->getMessage());
                        throw $e;
                    }
                }
            }
        }

        DB::connection($conn)->table('task_chats')->insert([
            'user_id'    => (int)$request->user_id,   // always = non-admin user ID (thread key)
            'sender_id'  => (int)$uid,                // actual sender
            'message'    => $request->filled('message') ? trim($request->message) : null,
            'image'      => $imagePath,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);

        $notifMsg = $request->filled('message') ? \Illuminate\Support\Str::limit($request->message, 30) : 'Sent an image';
        $senderName = DB::connection($conn)->table('users')->where('id', $uid)->value('name');
        
        if ($isChatAdmin) {
            // Notify the specific user
            if ((int)$request->user_id != (int)$uid) {
                saveWebNotification((int)$request->user_id, "New Support Message", $senderName . ": " . $notifMsg, '/tasks', $conn);
            }
        } else {
            // Notify super admins or users with role_id = 1
            $admins = DB::connection($conn)->table('users')->where('role_id', 1)->get();
            foreach($admins as $admin) {
                if ($admin->id != $uid) {
                    saveWebNotification($admin->id, "New Support Message from " . $senderName, $notifMsg, '/tasks', $conn);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    public function fetchTaskMessages(Request $request, $taskId)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $uid = session()->get('uid');
        if (!$uid) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $task = DB::connection($conn)->table('tasks')->where('id', $taskId)->first();
        if (!$task) {
            return response()->json(['status' => 'error', 'message' => 'Task not found.'], 404);
        }

        $isChatAdmin = isSuperAdmin();
        $assignedIds = array_filter(explode(',', $task->assigned_to));
        $isAssigned = in_array($uid, $assignedIds);
        $isCreator = ($task->assigned_by == $uid);

        // A user can only fetch messages for a task if they are admin, assignee, or creator.
        if (!$isChatAdmin && !$isAssigned && !$isCreator) {
            return response()->json(['status' => 'error', 'message' => 'Access denied.'], 403);
        }

        $this->ensureTableExists($conn);

        // Mark task chat notifications as read for this user
        DB::connection($conn)->table('web_notifications')
            ->where('user_id', $uid)
            ->where('title', 'like', 'Task Chat: %')
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        $messages = DB::connection($conn)
            ->table('task_chats')
            ->leftJoin('users', 'users.id', '=', 'task_chats.sender_id')
            ->select('task_chats.*', 'users.name as sender_name')
            ->where('task_chats.task_id', (int)$taskId)
            ->orderBy('task_chats.created_at', 'asc')
            ->get();

        return response()->json([
            'status'          => 'success',
            'messages'        => $messages,
            'current_user_id' => (int)$uid,
        ]);
    }

    public function sendTaskMessage(Request $request)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'task_id' => 'required|integer',
            'message' => 'nullable|string|max:5000',
            'image'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
        ]);

        if (!$request->filled('message') && !$request->hasFile('image')) {
            return response()->json(['status' => 'error', 'message' => 'Please type a message or upload an image.'], 422);
        }

        $uid = session()->get('uid');
        if (!$uid) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $taskId = $request->task_id;
        $task = DB::connection($conn)->table('tasks')->where('id', $taskId)->first();
        if (!$task) {
            return response()->json(['status' => 'error', 'message' => 'Task not found.'], 404);
        }

        $isChatAdmin = isSuperAdmin();
        $assignedIds = array_filter(explode(',', $task->assigned_to));
        $isAssigned = in_array($uid, $assignedIds);
        $isCreator = ($task->assigned_by == $uid);

        // A user can only send messages to a task if they are admin, assignee, or creator.
        if (!$isChatAdmin && !$isAssigned && !$isCreator) {
            return response()->json(['status' => 'error', 'message' => 'Access denied.'], 403);
        }

        $this->ensureTableExists($conn);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid()) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $uploaded = false;
                try {
                    $path = public_path('uploads/chat');
                    if (!\Illuminate\Support\Facades\File::exists($path)) {
                        \Illuminate\Support\Facades\File::makeDirectory($path, 0777, true, true);
                    }
                    $file->move($path, $filename);
                    $imagePath = 'uploads/chat/' . $filename;
                    $uploaded = true;
                } catch (\Exception $e) {
                    \Log::warning("Primary chat upload path uploads/chat is not writable: " . $e->getMessage());
                }

                if (!$uploaded) {
                    try {
                        $path = public_path('images/app_images/' . $conn . '/chat');
                        if (!\Illuminate\Support\Facades\File::exists($path)) {
                            \Illuminate\Support\Facades\File::makeDirectory($path, 0777, true, true);
                        }
                        $file->move($path, $filename);
                        $imagePath = 'images/app_images/' . $conn . '/chat/' . $filename;
                    } catch (\Exception $e) {
                        \Log::error("Fallback chat upload path is also not writable: " . $e->getMessage());
                        throw $e;
                    }
                }
            }
        }

        $firstAssignedId = count($assignedIds) > 0 ? (int)$assignedIds[0] : null;

        DB::connection($conn)->table('task_chats')->insert([
            'task_id'    => (int)$taskId,
            'user_id'    => $firstAssignedId,
            'sender_id'  => (int)$uid,
            'message'    => $request->filled('message') ? trim($request->message) : null,
            'image'      => $imagePath,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);

        $notifMsg = $request->filled('message') ? \Illuminate\Support\Str::limit($request->message, 30) : 'Sent an image';
        $senderName = DB::connection($conn)->table('users')->where('id', $uid)->value('name');
        
        // Notify all assigned users
        foreach ($assignedIds as $assignedId) {
            if ((int)$assignedId != (int)$uid) {
                saveWebNotification((int)$assignedId, "Task Chat: " . $task->title, $senderName . ": " . $notifMsg, '/tasks', $conn);
            }
        }
        
        // If sender is not an admin, notify admins too
        if (!$isChatAdmin) {
            $admins = DB::connection($conn)->table('users')->where('role_id', 1)->get();
            foreach($admins as $admin) {
                if ($admin->id != $uid && !in_array($admin->id, $assignedIds)) {
                    saveWebNotification($admin->id, "Task Chat: " . $task->title, $senderName . ": " . $notifMsg, '/tasks', $conn);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

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

            // Ensure task_categories table exists
            DB::connection($conn)->statement("
                CREATE TABLE IF NOT EXISTS `task_categories` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(255) NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Ensure category_id column in tasks exists
            if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'category_id')) {
                DB::connection($conn)->statement("ALTER TABLE `tasks` ADD COLUMN `category_id` INT NULL DEFAULT NULL AFTER `title`");
                DB::connection($conn)->statement("ALTER TABLE `tasks` ADD INDEX `idx_category_id` (`category_id`)");
            }

            // Ensure status enum is expanded in tasks table
            try {
                DB::connection($conn)->statement("ALTER TABLE `tasks` MODIFY COLUMN `status` ENUM('Pending','Progress','In Progress','Completed','Hold','On Hold','Cancelled') NOT NULL DEFAULT 'Pending'");
            } catch (\Exception $ex) {
                // Ignore if it fails
            }
        } catch (\Exception $e) {
            \Log::error("Failed to ensure task_chats table schema: " . $e->getMessage());
        }
    }
}
