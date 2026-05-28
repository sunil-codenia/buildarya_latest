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
        $inProgress = $tasks->where('status', 'In Progress')->count();
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

        DB::connection($conn)->table('tasks')->insert($data);

        return redirect()->back()->with('success', 'Task created successfully!');
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
            'status' => 'required|in:Pending,In Progress,Completed'
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
}
