<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaskCategoryController extends Controller
{
    public function index(Request $request)
    {
        if (checkmodulepermission(15, 'can_view') != 1) {
            return redirect('/dashboard')->with('errorcode', 'You do not have permission to view task categories.');
        }
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login')->with('error', 'Please log in again.');
        }

        $this->ensureTableExists($user_db_conn_name);

        $data = [];

        return view('layouts.tasks.category')->with('data', json_encode($data));
    }

    public function get_task_category_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $this->ensureTableExists($user_db_conn_name);

        $query = DB::connection($user_db_conn_name)->table('task_categories');

        $totalRecords = $query->count();

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'asc');
        
        $columns = [
            2 => 'name'
        ];
        
        if (isset($columns[$orderColumnIndex])) {
            $query->orderBy($columns[$orderColumnIndex], $orderDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        
        if ($length != -1) {
            $query->skip($start)->take($length);
        }

        $data = $query->get();

        $formattedData = [];
        $i = $start + 1;
        
        $can_edit = checkmodulepermission(15, 'can_edit') == 1;
        $can_delete = checkmodulepermission(15, 'can_delete') == 1;

        foreach ($data as $row) {
            $ddid = $row->id;
            
            $checkbox = '<div class="checkbox"><input id="check_'.$ddid.'" name="check_list[]" class="item_checkbox check_item" type="checkbox" value="'.$ddid.'"><label for="check_'.$ddid.'">&nbsp;</label></div>';
            $name = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->name).'</a>';
            
            $actionHtml = '';
            if ($can_edit) {
                $actionHtml .= '<button title="Edit" type="button" onclick="editdata(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-edit"></i></button>&nbsp;';
            }
            if ($can_delete) {
                $actionHtml .= '<button title="Delete" type="button" onclick="deletedata(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-delete"></i></button>';
            }

            $formattedData[] = [
                $checkbox,
                $i++,
                $name,
                $actionHtml
            ];
        }

        return response()->json([
            "draw" => intval($request->input('draw')),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $filteredRecords,
            "data" => $formattedData
        ]);
    }

    public function store(Request $request)
    {
        // Enforce can_add module permission
        if (checkmodulepermission(15, 'can_add') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to create task categories.');
        }

        $name = $request->input('name');
        $data = [
            'name' => $name,
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString()
        ];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        try {
            $id = DB::connection($user_db_conn_name)->table('task_categories')->insertGetId($data);
            addActivity($id, 'task_categories', "New Task Category Created ", 15);

            return redirect('/task_category')
                ->with('success', 'Task Category Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/task_category')
                    ->with('error', 'Task Category Already Exists!');
            } else {
                return redirect('/task_category')
                    ->with('error', 'Error While Creating Task Category!');
            }
        }
    }

    public function update(Request $request)
    {
        // Enforce can_edit module permission
        if (checkmodulepermission(15, 'can_edit') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to edit task categories.');
        }

        $id = $request->input('id');
        $name = $request->input('name');

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        DB::connection($user_db_conn_name)->table('task_categories')->where('id', $id)->update([
            'name' => $name,
            'updated_at' => Carbon::now()->toDateTimeString()
        ]);
        addActivity($id, 'task_categories', "Task Category Updated ", 15);

        return redirect('/task_category')->with('success', 'Task Category Updated successfully!');
    }

    public function edit(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        $data['data'] = [];
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('task_categories')->where('id', '=', $id)->get();
        return view('layouts.tasks.category')->with('data', json_encode($data));
    }

    public function bulk_action(Request $request)
    {
        $ids = $request->input('check_list');
        $action = $request->input('bulk_action');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        if (empty($ids)) {
            return redirect('/task_category')->with('error', 'Please select at least one record.');
        }

        try {
            DB::connection($user_db_conn_name)->beginTransaction();

            if ($action == 'delete') {
                if (checkmodulepermission(15, 'can_delete') != 1) {
                    return redirect()->back()->with('errorcode', 'You do not have permission to delete task categories.');
                }
                foreach ($ids as $id) {
                    DB::connection($user_db_conn_name)->table('task_categories')->where('id', '=', $id)->delete();
                    addActivity($id, 'task_categories', "Bulk Deleted Task Category", 15);
                }
                DB::connection($user_db_conn_name)->commit();
                return redirect('/task_category')->with('success', 'Selected Task Categories Deleted Successfully!');
            }
            
            DB::connection($user_db_conn_name)->commit();
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/task_category')->with('error', 'Error processing bulk action!');
        }

        return redirect('/task_category');
    }

    public function delete(Request $request)
    {
        // Enforce can_delete module permission
        if (checkmodulepermission(15, 'can_delete') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to delete task categories.');
        }

        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        $category = DB::connection($user_db_conn_name)->table('task_categories')->where('id', '=', $id)->first();
        if ($category) {
            DB::connection($user_db_conn_name)->table('task_categories')->where('id', '=', $id)->delete();
            addActivity($id, 'task_categories', "Task Category Deleted - " . $category->name, 15);
            return redirect('/task_category')->with('success', 'Task Category Deleted Successfully!');
        }

        return redirect('/task_category')->with('error', 'Task Category Not Found!');
    }

    public function bulk_edit_category(Request $request)
    {
        if (checkmodulepermission(15, 'can_edit') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to edit task categories.');
        }
        $ids = $request->input('check_list');
        if (empty($ids)) {
            return redirect('/task_category')->with('error', 'Please select at least one category to edit!');
        }

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        $data = DB::connection($user_db_conn_name)->table('task_categories')->whereIn('id', $ids)->get();

        return view('layouts.tasks.bulk_edit_category')->with('data', json_encode($data));
    }

    public function update_bulk_category(Request $request)
    {
        if (checkmodulepermission(15, 'can_edit') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to edit task categories.');
        }
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        $ids = $request->input('id');
        $names = $request->input('name');

        try {
            DB::connection($user_db_conn_name)->beginTransaction();
            foreach ($ids as $key => $id) {
                DB::connection($user_db_conn_name)->table('task_categories')
                    ->where('id', $id)
                    ->update([
                        'name' => $names[$key],
                        'updated_at' => Carbon::now()->toDateTimeString()
                    ]);
                addActivity($id, 'task_categories', "Task Category Updated via Bulk Edit", 15);
            }
            DB::connection($user_db_conn_name)->commit();
            return redirect('/task_category')->with('success', 'Task Categories Updated Successfully!');
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/task_category')->with('error', 'Error while updating bulk categories!');
        }
    }

    private function ensureTableExists($conn)
    {
        try {
            DB::connection($conn)->statement("
                CREATE TABLE IF NOT EXISTS `task_categories` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(255) NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (\Exception $e) {
            \Log::error("Failed to ensure task_categories table schema: " . $e->getMessage());
        }
    }
}
