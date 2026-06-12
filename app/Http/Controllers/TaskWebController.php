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

        // Admins (can_add permission) see all tasks; regular users only see their own
        $isAdmin = (checkmodulepermission(14, 'can_add') == 1);

        // Query Tasks
        $query = DB::connection($conn)->table('tasks')
            ->leftJoin('users as assigned', 'assigned.id', '=', 'tasks.assigned_to')
            ->leftJoin('users as creators', 'creators.id', '=', 'tasks.assigned_by')
            ->leftJoin('sites', 'sites.id', '=', 'tasks.site_id')
            ->select('tasks.*', 'assigned.name as assigned_name', 'creators.name as creator_name', 'sites.name as site_name');

        // Non-admin users only see tasks assigned to them
        if (!$isAdmin) {
            $query->where('tasks.assigned_to', $uid);
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

        // Calculate Stats
        $totalTasks = $tasks->count();
        $pending = $tasks->where('status', 'Pending')->count();
        $inProgress = $tasks->where('status', 'Progress')->count();
        $completed = $tasks->where('status', 'Completed')->count();

        return view('layouts.tasks.index', compact('tasks', 'sites', 'users', 'totalTasks', 'pending', 'inProgress', 'completed', 'isAdmin'));
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
            'description' => 'nullable|string',
            'site_id' => 'required',
            'assigned_to' => 'required',
            'priority' => 'required|in:Low,Medium,High',
            'due_date' => 'nullable|date'
        ]);

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'site_id' => $request->site_id,
            'assigned_to' => $request->assigned_to,
            'assigned_by' => session()->get('uid') ?? 1,
            'priority' => $request->priority,
            'status' => 'Pending',
            'due_date' => $request->due_date,
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

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'site_id' => 'required',
            'assigned_to' => 'required',
            'priority' => 'required|in:Low,Medium,High',
            'due_date' => 'nullable|date'
        ]);

        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'site_id' => $request->site_id,
            'assigned_to' => $request->assigned_to,
            'priority' => $request->priority,
            'due_date' => $request->due_date,
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

        // Non-admin users can only update their own assigned tasks
        if (!$isAdmin && $task->assigned_to != $uid) {
            return redirect()->back()->with('errorcode', 'You can only update status of your own tasks.');
        }

        // Block status change if task due_date is in the past (and not an admin)
        if (!$isAdmin && $task->due_date && Carbon::parse($task->due_date)->startOfDay()->lt(Carbon::today())) {
            return redirect()->back()->with('errorcode', 'Cannot change status of a task whose due date has already passed.');
        }

        $request->validate([
            'status' => 'required|in:Pending,Progress,Completed'
        ]);

        DB::connection($conn)->table('tasks')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => Carbon::now()->toDateTimeString()
        ]);

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
        $isAdmin = (checkmodulepermission(14, 'can_add') == 1);

        // A non-admin user may only read their own thread.
        // An admin may read ANY thread (identified by the non-admin user's ID).
        if (!$isAdmin && (int)$uid !== (int)$userId) {
            return response()->json(['status' => 'error', 'message' => 'Access denied.'], 403);
        }

        $this->ensureTableExists($conn);

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
        $isAdmin = (checkmodulepermission(14, 'can_add') == 1);

        // The thread key (user_id) must either be the current user's own ID
        // OR the admin is sending to any user.
        if (!$isAdmin && (int)$uid !== (int)$request->user_id) {
            return response()->json(['status' => 'error', 'message' => 'Access denied.'], 403);
        }

        $this->ensureTableExists($conn);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid()) {
                $path = public_path('uploads/chat');
                if (!\Illuminate\Support\Facades\File::exists($path)) {
                    \Illuminate\Support\Facades\File::makeDirectory($path, 0777, true, true);
                }
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($path, $filename);
                $imagePath = 'uploads/chat/' . $filename;
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

        return response()->json(['status' => 'success']);
    }

    private function ensureTableExists($conn)
    {
        try {
            DB::connection($conn)->statement("
                CREATE TABLE IF NOT EXISTS `task_chats` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `sender_id` INT NOT NULL,
                    `message` TEXT NULL,
                    `image` VARCHAR(255) NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX (`user_id`),
                    INDEX (`sender_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            
            // Alter to add image column if it doesn't exist
            if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('task_chats', 'image')) {
                DB::connection($conn)->statement("ALTER TABLE `task_chats` ADD COLUMN `image` VARCHAR(255) NULL AFTER `message`");
            }
            
            // Alter to make message nullable if it was not
            DB::connection($conn)->statement("ALTER TABLE `task_chats` MODIFY COLUMN `message` TEXT NULL");
        } catch (\Exception $e) {
            \Log::error("Failed to ensure task_chats table schema: " . $e->getMessage());
        }
    }
}
