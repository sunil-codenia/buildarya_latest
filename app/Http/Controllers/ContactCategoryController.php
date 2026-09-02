<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ContactCategoryController extends Controller
{
    public function index(Request $request)
    {
        if (checkmodulepermission(10, 'can_view') != 1) {
            return redirect('/dashboard')->with('errorcode', 'You do not have permission to view contact categories.');
        }

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login')->with('error', 'Please log in again.');
        }

        $this->ensureTableExists($user_db_conn_name);

        $data = [];

        return view('layouts.users.contact_categories')->with('data', json_encode($data));
    }

    public function get_contact_category_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $this->ensureTableExists($user_db_conn_name);

        $query = DB::connection($user_db_conn_name)->table('contact_categories');

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
        
        $can_edit = checkmodulepermission(10, 'can_edit') == 1;
        $can_delete = checkmodulepermission(10, 'can_delete') == 1;

        foreach ($data as $row) {
            $ddid = $row->id;
            
            $checkbox = '<div class="checkbox"><input id="check_'.$ddid.'" name="check_list[]" class="item_checkbox check_item" type="checkbox" value="'.$ddid.'"><label for="check_'.$ddid.'">&nbsp;</label></div>';
            $name = htmlspecialchars((string)$row->name);
            
            $actionHtml = '';
            if ($can_edit) {
                $actionHtml .= '<button title="Edit" type="button" onclick="editdata(\''.$ddid.'\')" class="btn btn-neutral btn-sm btn-round text-primary mr-1"><i class="zmdi zmdi-edit"></i></button>';
            }
            if ($can_delete) {
                $actionHtml .= '<button title="Delete" type="button" onclick="deletedata(\''.$ddid.'\')" class="btn btn-neutral btn-sm btn-round text-danger"><i class="zmdi zmdi-delete"></i></button>';
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
        if (checkmodulepermission(10, 'can_add') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to create contact categories.');
        }

        $name = trim($request->input('name'));
        if (empty($name)) {
            return redirect('/contact_categories')->with('error', 'Category name is required.');
        }

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        $this->ensureTableExists($user_db_conn_name);

        $exists = DB::connection($user_db_conn_name)->table('contact_categories')->where('name', $name)->first();
        if ($exists) {
            return redirect('/contact_categories')->with('error', 'Contact Category Already Exists!');
        }

        try {
            $id = DB::connection($user_db_conn_name)->table('contact_categories')->insertGetId([
                'name' => $name,
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString()
            ]);
            addActivity($id, 'contact_categories', "New Contact Category Created - " . $name, 10);

            return redirect('/contact_categories')->with('success', 'Contact Category created successfully!');
        } catch (\Exception $e) {
            return redirect('/contact_categories')->with('error', 'Error while creating contact category: ' . $e->getMessage());
        }
    }

    public function edit(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        $this->ensureTableExists($user_db_conn_name);

        $data['edit_data'] = DB::connection($user_db_conn_name)->table('contact_categories')->where('id', '=', $id)->first();
        return view('layouts.users.contact_categories')->with('data', json_encode($data));
    }

    public function update(Request $request)
    {
        if (checkmodulepermission(10, 'can_edit') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to edit contact categories.');
        }

        $id = $request->input('id');
        $name = trim($request->input('name'));

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        $this->ensureTableExists($user_db_conn_name);

        $exists = DB::connection($user_db_conn_name)->table('contact_categories')
            ->where('name', $name)
            ->where('id', '!=', $id)
            ->first();

        if ($exists) {
            return redirect('/contact_categories')->with('error', 'Another Contact Category with this name already exists!');
        }

        DB::connection($user_db_conn_name)->table('contact_categories')->where('id', $id)->update([
            'name' => $name,
            'updated_at' => Carbon::now()->toDateTimeString()
        ]);
        addActivity($id, 'contact_categories', "Contact Category Updated - " . $name, 10);

        return redirect('/contact_categories')->with('success', 'Contact Category updated successfully!');
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
            return redirect('/contact_categories')->with('error', 'Please select at least one record.');
        }

        try {
            DB::connection($user_db_conn_name)->beginTransaction();

            if ($action == 'delete') {
                if (checkmodulepermission(10, 'can_delete') != 1) {
                    return redirect()->back()->with('errorcode', 'You do not have permission to delete contact categories.');
                }
                foreach ($ids as $id) {
                    DB::connection($user_db_conn_name)->table('contact_categories')->where('id', '=', $id)->delete();
                    addActivity($id, 'contact_categories', "Bulk Deleted Contact Category", 10);
                }
                DB::connection($user_db_conn_name)->commit();
                return redirect('/contact_categories')->with('success', 'Selected Contact Categories Deleted Successfully!');
            }
            
            DB::connection($user_db_conn_name)->commit();
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/contact_categories')->with('error', 'Error processing bulk action!');
        }

        return redirect('/contact_categories');
    }

    public function delete(Request $request)
    {
        if (checkmodulepermission(10, 'can_delete') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to delete contact categories.');
        }

        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!$user_db_conn_name) {
            return redirect('/login');
        }

        $this->ensureTableExists($user_db_conn_name);

        $category = DB::connection($user_db_conn_name)->table('contact_categories')->where('id', '=', $id)->first();
        if ($category) {
            DB::connection($user_db_conn_name)->table('contact_categories')->where('id', '=', $id)->delete();
            addActivity($id, 'contact_categories', "Contact Category Deleted - " . $category->name, 10);
            return redirect('/contact_categories')->with('success', 'Contact Category Deleted Successfully!');
        }

        return redirect('/contact_categories')->with('error', 'Contact Category Not Found!');
    }

    private function ensureTableExists($conn)
    {
        try {
            if (!Schema::connection($conn)->hasTable('contact_categories')) {
                DB::connection($conn)->statement("
                    CREATE TABLE IF NOT EXISTS `contact_categories` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `name` VARCHAR(255) NOT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                $defaultCategories = ['Expense Party', 'Material Supplier', 'Bills Party', 'Employees', 'Government Officials', 'Consultants', 'Legal Advisors', 'Officers', 'Other Party'];
                foreach ($defaultCategories as $cat) {
                    DB::connection($conn)->table('contact_categories')->insert([
                        'name' => $cat,
                        'created_at' => Carbon::now()->toDateTimeString(),
                        'updated_at' => Carbon::now()->toDateTimeString()
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error("Failed to ensure contact_categories table schema: " . $e->getMessage());
        }
    }
}
