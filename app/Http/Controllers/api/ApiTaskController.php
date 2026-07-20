<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApiTaskController extends Controller
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
     * Get Tasks List with flexible filters
     */
    public function index(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];

            $site_id = $request->input('site_id');
            $category_id = $request->input('category_id');
            $assigned_to = $request->input('assigned_to');
            $assigned_by = $request->input('assigned_by');
            $priority = $request->input('priority');
            $status = $request->input('status');
            $due_date_start = $request->input('due_date_start') ?? $request->input('start_date');
            $due_date_end = $request->input('due_date_end') ?? $request->input('end_date');

            $search = $request->input('search');
            $perPage = $request->input('per_page', 15);

            $userRecord = DB::connection($conn)->table('users')->where('id', $tenant['uid'])->first();
            $isSuperAdmin = $userRecord && ($userRecord->role_id == 1);

            $perm = DB::connection($conn)->table('user_permission')
                ->where('user_id', $tenant['uid'])
                ->where('module_id', 14)
                ->first();

            $canManage = $isSuperAdmin || ($perm && ($perm->can_add == 1 || $perm->can_edit == 1));

            if (!$canManage) {
                $assigned_to = $tenant['uid'];
            }

            $query = DB::connection($conn)->table('tasks')
                ->leftJoin('sites', 'sites.id', '=', 'tasks.site_id')
                ->leftJoin('task_categories', 'task_categories.id', '=', 'tasks.category_id')
                ->leftJoin('users as assigned_by_user', 'assigned_by_user.id', '=', 'tasks.assigned_by')
                ->select(
                    'tasks.*',
                    'sites.name as site_name',
                    'task_categories.name as category_name',
                    'assigned_by_user.name as assigned_by_name',
                    'assigned_by_user.username as assigned_by_username'
                );

            if ($site_id) {
                $query->where('tasks.site_id', $site_id);
            }
            if ($category_id) {
                $query->where('tasks.category_id', $category_id);
            }
            if ($assigned_to) {
                $query->whereRaw("FIND_IN_SET(?, tasks.assigned_to)", [$assigned_to]);
            }
            if ($assigned_by) {
                $query->where('tasks.assigned_by', $assigned_by);
            }
            if ($priority) {
                $query->where('tasks.priority', $priority);
            }
            if ($status) {
                $query->where('tasks.status', $status);
            }
            if ($due_date_start) {
                $query->where('tasks.due_date', '>=', $due_date_start);
            }
            if ($due_date_end) {
                $query->where('tasks.due_date', '<=', $due_date_end);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('tasks.title', 'like', '%' . $search . '%')
                      ->orWhere('tasks.description', 'like', '%' . $search . '%')
                      ->orWhere('sites.name', 'like', '%' . $search . '%');
                });
            }

            $tasks = $query->orderBy('tasks.id', 'desc')->paginate($perPage);

            $users = DB::connection($conn)->table('users')->get()->keyBy('id');
            foreach ($tasks->items() as $task) {
                $assignedIds = array_filter(explode(',', $task->assigned_to));
                $names = [];
                $usernames = [];
                foreach ($assignedIds as $id) {
                    if (isset($users[$id])) {
                        $names[] = $users[$id]->name;
                        $usernames[] = $users[$id]->username;
                    }
                }
                $task->assigned_to_name = implode(', ', $names);
                $task->assigned_to_username = implode(', ', $usernames);
            }

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $tasks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch tasks: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Store a new task
     */
    public function store(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            $title = $request->input('title');
            if (!$title) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task Title is required!'
                ]);
            }

            $description = $request->input('description');
            $category_id = $request->input('category_id');
            $site_id = $request->input('site_id');
            
            $assigned = $request->input('assigned_to');
            if (is_array($assigned)) {
                $assigned_to = implode(',', $assigned);
            } else {
                $assigned_to = $assigned;
            }
            
            $priority = $request->input('priority') ?? 'Medium'; // Low, Medium, High, Urgent
            $due_date = $request->input('due_date');
            $completed_at = $request->input('completed_at');
            $remarks = $request->input('remarks');

            $data = [
                'title' => $title,
                'category_id' => $category_id,
                'description' => $description,
                'site_id' => $site_id,
                'assigned_to' => $assigned_to,
                'assigned_by' => $uid,
                'priority' => $priority,
                'status' => 'Pending',
                'due_date' => $due_date,
                'completed_at' => $completed_at,
                'remarks' => $remarks,
                'created_at' => now(),
                'updated_at' => now()
            ];

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
                $data['created_by'] = $uid;
            }

            $task_id = DB::connection($conn)->table('tasks')->insertGetId($data);

            addActivity($task_id, 'tasks', "New task created: " . $title, 14, $uid, $conn);

            // Notify assigned users
            $assignedIds = array_filter(explode(',', $assigned_to));
            foreach ($assignedIds as $assignedId) {
                sendAlertNotification($assignedId, 'You have been assigned a new task: ' . $title, 'Task Assigned', $conn);
                saveWebNotification($assignedId, 'Task Assigned', 'You have been assigned a new task: ' . $title, '/tasks', $conn);
            }

            $record = DB::connection($conn)->table('tasks')->where('id', $task_id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Task created successfully!',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to create task: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show Details of a specific task
     */
    public function show(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];

            $task_id = $id ?? $request->input('id') ?? $request->get('id');
            if (!$task_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task ID is required!'
                ]);
            }

            $task = DB::connection($conn)->table('tasks')
                ->leftJoin('sites', 'sites.id', '=', 'tasks.site_id')
                ->leftJoin('task_categories', 'task_categories.id', '=', 'tasks.category_id')
                ->leftJoin('users as assigned_by_user', 'assigned_by_user.id', '=', 'tasks.assigned_by')
                ->select(
                    'tasks.*',
                    'sites.name as site_name',
                    'task_categories.name as category_name',
                    'assigned_by_user.name as assigned_by_name',
                    'assigned_by_user.username as assigned_by_username'
                )
                ->where('tasks.id', $task_id)
                ->first();

            if (!$task) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task not found!'
                ]);
            }

            $assignedIds = array_filter(explode(',', $task->assigned_to));
            if (!empty($assignedIds)) {
                $users = DB::connection($conn)->table('users')->whereIn('id', $assignedIds)->get()->keyBy('id');
                $names = [];
                $usernames = [];
                foreach ($assignedIds as $uid) {
                    if (isset($users[$uid])) {
                        $names[] = $users[$uid]->name;
                        $usernames[] = $users[$uid]->username;
                    }
                }
                $task->assigned_to_name = implode(', ', $names);
                $task->assigned_to_username = implode(', ', $usernames);
            } else {
                $task->assigned_to_name = null;
                $task->assigned_to_username = null;
            }

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $task
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to retrieve task details: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update details of a task
     */
    public function update(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            $task_id = $id ?? $request->input('id') ?? $request->get('id');
            if (!$task_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task ID is required!'
                ]);
            }

            $task = DB::connection($conn)->table('tasks')->where('id', $task_id)->first();
            if (!$task) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task not found!'
                ]);
            }

            $updateData = [];

            if ($request->has('title')) {
                $updateData['title'] = $request->input('title');
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->input('description');
            }
            if ($request->has('site_id')) {
                $updateData['site_id'] = $request->input('site_id');
            }
            if ($request->has('category_id')) {
                $updateData['category_id'] = $request->input('category_id');
            }
            if ($request->has('assigned_to')) {
                $assigned = $request->input('assigned_to');
                if (is_array($assigned)) {
                    $updateData['assigned_to'] = implode(',', $assigned);
                } else {
                    $updateData['assigned_to'] = $assigned;
                }
            }
            if ($request->has('priority')) {
                $updateData['priority'] = $request->input('priority');
            }
            if ($request->has('status')) {
                $status = $request->input('status');
                
                // Normalize status case
                $lowerStatus = strtolower($status);
                if ($lowerStatus === 'in progress' || $lowerStatus === 'progress') {
                    $status = 'Progress';
                } elseif ($lowerStatus === 'completed') {
                    $status = 'Completed';
                } elseif ($lowerStatus === 'hold' || $lowerStatus === 'on hold') {
                    $status = 'Hold';
                } elseif ($lowerStatus === 'pending') {
                    $status = 'Pending';
                } else {
                    // Fallback to ucfirst just in case
                    $status = ucfirst($status);
                }
                
                $updateData['status'] = $status;
                
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'completed_at')) {
                    if ($status === 'Completed') {
                        $updateData['completed_at'] = $request->input('completed_at') ?? now();
                    } else {
                        $updateData['completed_at'] = null;
                    }
                }
            } elseif ($request->has('completed_at')) {
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'completed_at')) {
                    $updateData['completed_at'] = $request->input('completed_at');
                }
            }
            if ($request->has('due_date')) {
                $updateData['due_date'] = $request->input('due_date');
            }
            if ($request->has('remarks')) {
                $updateData['remarks'] = $request->input('remarks');
            }

            if (empty($updateData)) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'No fields provided for update!'
                ]);
            }

            $updateData['updated_at'] = now();

            DB::connection($conn)->table('tasks')->where('id', $task_id)->update($updateData);

            addActivity($task_id, 'tasks', "Task updated: " . ($updateData['title'] ?? $task->title), 14, $uid, $conn);

            if (isset($updateData['status'])) {
                $assigned_to_use = isset($updateData['assigned_to']) ? $updateData['assigned_to'] : $task->assigned_to;
                $assignedIds = array_filter(explode(',', $assigned_to_use));
                foreach ($assignedIds as $assignedId) {
                    sendAlertNotification($assignedId, 'Task "' . ($updateData['title'] ?? $task->title) . '" status updated to: ' . $updateData['status'], 'Task Status Updated', $conn);
                    saveWebNotification($assignedId, 'Task Status Updated', 'Task "' . ($updateData['title'] ?? $task->title) . '" status updated to: ' . $updateData['status'], '/tasks', $conn);
                }
            }

            $record = DB::connection($conn)->table('tasks')->where('id', $task_id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Task updated successfully!',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to update task: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete a task
     */
    public function destroy(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            $task_id = $id ?? $request->input('id') ?? $request->get('id');
            if (!$task_id) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task ID is required!'
                ]);
            }

            $task = DB::connection($conn)->table('tasks')->where('id', $task_id)->first();
            if (!$task) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task not found!'
                ]);
            }

            DB::connection($conn)->table('tasks')->where('id', $task_id)->delete();

            addActivity(0, 'tasks', "Task deleted: " . $task->title, 14, $uid, $conn);

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Task deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to delete task: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * List task categories
     */
    public function listCategories(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $perPage = $request->input('per_page', 15);

            $categories = DB::connection($conn)->table('task_categories')
                ->orderBy('id', 'desc')
                ->paginate($perPage);

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to fetch task categories: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Store task category
     */
    public function storeCategory(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            $name = $request->input('name');
            if (!$name) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Category Name is required!'
                ]);
            }

            // Check duplicate
            $exists = DB::connection($conn)->table('task_categories')
                ->where('name', $name)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Category Name already exists!'
                ]);
            }

            $id = DB::connection($conn)->table('task_categories')->insertGetId([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            addActivity($id, 'task_categories', "Task category created: " . $name, 14, $uid, $conn);

            $record = DB::connection($conn)->table('task_categories')->where('id', $id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Task Category created successfully!',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to create task category: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update task category
     */
    public function updateCategory(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            $categoryId = $id ?? $request->input('id') ?? $request->get('id');
            if (!$categoryId) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Category ID is required!'
                ]);
            }

            $name = $request->input('name');
            if (!$name) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Category Name is required!'
                ]);
            }

            $category = DB::connection($conn)->table('task_categories')->where('id', $categoryId)->first();
            if (!$category) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task category not found!'
                ]);
            }

            // Check duplicate (excluding self)
            $exists = DB::connection($conn)->table('task_categories')
                ->where('name', $name)
                ->where('id', '!=', $categoryId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Category Name already exists!'
                ]);
            }

            DB::connection($conn)->table('task_categories')->where('id', $categoryId)->update([
                'name' => $name,
                'updated_at' => now()
            ]);

            addActivity($categoryId, 'task_categories', "Task category updated: " . $name, 14, $uid, $conn);

            $record = DB::connection($conn)->table('task_categories')->where('id', $categoryId)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Task Category updated successfully!',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to update task category: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete task category
     */
    public function destroyCategory(Request $request, $id = null)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            $categoryId = $id ?? $request->input('id') ?? $request->get('id');
            if (!$categoryId) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Category ID is required!'
                ]);
            }

            $category = DB::connection($conn)->table('task_categories')->where('id', $categoryId)->first();
            if (!$category) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Task category not found!'
                ]);
            }

            DB::connection($conn)->table('task_categories')->where('id', $categoryId)->delete();

            addActivity(0, 'task_categories', "Task category deleted: " . $category->name, 14, $uid, $conn);

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Task Category deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to delete task category: ' . $e->getMessage()
            ]);
        }
    }

    public function getNotifications(Request $request)
    {
        $uid = \Illuminate\Support\Facades\Auth::id();
        $conn = session()->get('comp_db_conn_name');

        if (!$uid || !$conn) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '401',
                'message' => 'Unauthorized or missing company context'
            ]);
        }

        if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasTable('web_notifications')) {
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => [],
                'unread_count' => 0
            ]);
        }

        $notifications = DB::connection($conn)->table('web_notifications')
            ->where('user_id', $uid)
            ->orderBy('id', 'desc')
            ->take(50)
            ->get();

        $unreadCount = DB::connection($conn)->table('web_notifications')
            ->where('user_id', $uid)
            ->where('is_read', 0)
            ->count();

        return response()->json([
            'status' => 'Ok',
            'status_code' => '200',
            'data' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    public function markNotificationsRead(Request $request)
    {
        $uid = \Illuminate\Support\Facades\Auth::id();
        $conn = session()->get('comp_db_conn_name');
        $notif_id = $request->input('notif_id'); // Optional, if provided mark single, else mark all

        if (!$uid || !$conn) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '401',
                'message' => 'Unauthorized or missing company context'
            ]);
        }

        if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasTable('web_notifications')) {
            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Notifications updated successfully'
            ]);
        }

        $query = DB::connection($conn)->table('web_notifications')->where('user_id', $uid);
        
        if ($notif_id) {
            $query->where('id', $notif_id);
        }

        $query->update(['is_read' => 1]);

        return response()->json([
            'status' => 'Ok',
            'status_code' => '200',
            'message' => 'Notifications marked as read'
        ]);
    }
}
