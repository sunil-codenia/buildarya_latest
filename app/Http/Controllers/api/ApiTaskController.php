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
                ->leftJoin('users as assigned_to_user', 'assigned_to_user.id', '=', 'tasks.assigned_to')
                ->leftJoin('users as assigned_by_user', 'assigned_by_user.id', '=', 'tasks.assigned_by')
                ->select(
                    'tasks.*',
                    'sites.name as site_name',
                    'assigned_to_user.name as assigned_to_name',
                    'assigned_to_user.username as assigned_to_username',
                    'assigned_by_user.name as assigned_by_name',
                    'assigned_by_user.username as assigned_by_username'
                );

            if ($site_id) {
                $query->where('tasks.site_id', $site_id);
            }
            if ($assigned_to) {
                $query->where('tasks.assigned_to', $assigned_to);
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
            $site_id = $request->input('site_id');
            $assigned_to = $request->input('assigned_to');
            $priority = $request->input('priority') ?? 'Medium'; // Low, Medium, High, Urgent
            $due_date = $request->input('due_date');
            $remarks = $request->input('remarks');

            $data = [
                'title' => $title,
                'description' => $description,
                'site_id' => $site_id,
                'assigned_to' => $assigned_to,
                'assigned_by' => $uid,
                'priority' => $priority,
                'status' => 'Pending',
                'due_date' => $due_date,
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
                ->leftJoin('users as assigned_to_user', 'assigned_to_user.id', '=', 'tasks.assigned_to')
                ->leftJoin('users as assigned_by_user', 'assigned_by_user.id', '=', 'tasks.assigned_by')
                ->select(
                    'tasks.*',
                    'sites.name as site_name',
                    'assigned_to_user.name as assigned_to_name',
                    'assigned_to_user.username as assigned_to_username',
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
            if ($request->has('assigned_to')) {
                $updateData['assigned_to'] = $request->input('assigned_to');
            }
            if ($request->has('priority')) {
                $updateData['priority'] = $request->input('priority');
            }
            if ($request->has('status')) {
                $status = $request->input('status');
                if ($status === 'In Progress') {
                    $status = 'Progress';
                }
                $updateData['status'] = $status;
                
                if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'completed_at')) {
                    if ($status === 'Completed') {
                        $updateData['completed_at'] = now();
                    } else {
                        $updateData['completed_at'] = null;
                    }
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
}
