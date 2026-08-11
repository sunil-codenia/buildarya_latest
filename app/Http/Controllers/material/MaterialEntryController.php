<?php

namespace App\Http\Controllers\material;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MaterialEntryController extends Controller
{
    //
    public function verified_material(Request $request)
    {
        return view('layouts.material.verified');
    }

    public function get_verified_material_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('material_entry', 'converted_qty')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('material_entry', function ($table) {
                    $table->string('converted_qty', 255)->nullable()->after('qty');
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding converted_qty column in verified AJAX: " . $e->getMessage());
            }
        }
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        if ($from_date && $to_date) {
            $min_date = date('Y-m-d', strtotime($from_date));
            $max_date = date('Y-m-d', strtotime($to_date));
        } else {
            $dates = getdurationdates($view_duration);
            $min_date = date('Y-m-d', strtotime($dates['min']));
            $max_date = date('Y-m-d', strtotime($dates['max']));
        }

        $query = DB::connection($user_db_conn_name)->table('material_entry')
            ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
            ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
            ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
            ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
            ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
            ->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
            ->where('material_entry.status', '!=', 'Pending');

        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'material_entry.site_id');
        }

        $query->whereBetween('material_entry.date', [$min_date, $max_date]);

        $totalRecords = $query->count();

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('material_supplier.name', 'LIKE', "%{$search}%")
                    ->orWhere('materials.name', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.bill_no', 'LIKE', "%{$search}%")
                    ->orWhere('sites.name', 'LIKE', "%{$search}%")
                    ->orWhere('users.name', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.remark', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.date', 'LIKE', "%{$search}%");
            });
        }

        // Individual Column Searching
        $columns_search = $request->input('columns');
        if ($columns_search) {
            foreach ($columns_search as $index => $column) {
                $search_val = $column['search']['value'];
                if (!empty($search_val)) {
                    switch ($index) {
                        case 2: $query->where('material_supplier.name', 'LIKE', "%{$search_val}%"); break;
                        case 3: $query->where('materials.name', 'LIKE', "%{$search_val}%"); break;
                        case 4: $query->where('units.name', 'LIKE', "%{$search_val}%"); break;
                        case 5: $query->where('material_entry.qty', 'LIKE', "%{$search_val}%"); break;
                        case 6: $query->where('material_entry.converted_qty', 'LIKE', "%{$search_val}%"); break;
                        case 7: $query->where('material_entry.rate', 'LIKE', "%{$search_val}%"); break;
                        case 8: $query->where('material_entry.amount', 'LIKE', "%{$search_val}%"); break;
                        case 9: $query->where('material_entry.vehical', 'LIKE', "%{$search_val}%"); break;
                        case 10: $query->where('material_entry.status', 'LIKE', "%{$search_val}%"); break;
                        case 11: $query->where('material_entry.remark', 'LIKE', "%{$search_val}%"); break;
                        case 12: $query->where('sites.name', 'LIKE', "%{$search_val}%"); break;
                        case 13: $query->where('users.name', 'LIKE', "%{$search_val}%"); break;
                        case 14: $query->where('material_entry.location', 'LIKE', "%{$search_val}%"); break;
                        case 15: $query->where('material_entry.bill_no', 'LIKE', "%{$search_val}%"); break;
                        case 16: $query->where('material_entry.date', 'LIKE', "%{$search_val}%"); break;
                    }
                }
            }
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc');

        $columns = [
            2 => 'material_supplier.name',
            3 => 'materials.name',
            5 => 'qty',
            6 => 'converted_qty',
            16 => 'date'
        ];

        if (isset($columns[$orderColumnIndex])) {
            $query->orderBy($columns[$orderColumnIndex], $orderDir);
        } else {
            $query->orderBy('material_entry.id', 'desc');
        }

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);

        if ($length != -1) {
            $query->skip($start)->take($length);
        }

        $data = $query->get();
        $formattedData = [];
        $i = $start + 1;

        $can_certify = checkmodulepermission(3, 'can_certify') == 1;
        $can_edit = checkmodulepermission(3, 'can_edit') == 1;

        foreach ($data as $row) {
            $ddid = $row->id;
            
            $checkbox = '';
            if ($row->status == 'Approved') {
                $checkbox = '<div class="checkbox"><input id="check_'.$ddid.'" name="check_list[]" class="check_item" type="checkbox" value="'.$ddid.'"><label for="check_'.$ddid.'">&nbsp;</label></div>';
            }

            $supplier = $row->supplier;
            $material = $row->material;
            $unit = $row->unit;
            $qty = $row->qty;
            $converted_qty = $row->converted_qty;
            $rate = $row->rate;
            $amount = $row->amount;
            $vehical = $row->vehical;
            $status = $row->status;
            $remark = $row->remark;
            $site = $row->site;
            $user = $row->user;
            $location = $row->location;
            $bill_no = $row->bill_no;
            $date = $row->date;

            $imageHtml = '<div class="d-flex">';
            if (!empty($row->image)) {
                $images = explode(',', $row->image);
                foreach ($images as $img) {
                    $img = trim($img);
                    if ($img && $img != 'images/expense.png') {
                        $imageHtml .= '<img class="lazy" src="'.asset($img).'" onclick="enlargeImage(\''.asset($img).'\')" height="50px" width="50px" />&nbsp;';
                    }
                }
            }
            // Legacy column support
            if (!empty($row->image2) && $row->image2 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image2).'" onclick="enlargeImage(\''.asset($row->image2).'\')" height="50px" width="50px" />&nbsp;';
            }
            if (!empty($row->image3) && $row->image3 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image3).'" onclick="enlargeImage(\''.asset($row->image3).'\')" height="50px" width="50px" />&nbsp;';
            }
            if (!empty($row->image4) && $row->image4 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image4).'" onclick="enlargeImage(\''.asset($row->image4).'\')" height="50px" width="50px" />&nbsp;';
            }
            if (!empty($row->image5) && $row->image5 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image5).'" onclick="enlargeImage(\''.asset($row->image5).'\')" height="50px" width="50px" />&nbsp;';
            }
            if ($imageHtml == '<div class="d-flex">') {
                $imageHtml .= '<img class="lazy" src="'.asset('images/expense.png').'" onclick="enlargeImage(\''.asset('images/expense.png').'\')" height="50px" width="50px" />';
            }
            $imageHtml .= '</div>';

            $actionHtml = '';
            if ($row->status == 'Approved') {
                if ($can_certify) {
                    $actionHtml .= '<button title="Reject" type="button" onclick="rejectmaterial(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-block"></i></button>';
                }
            } else {
                if ($can_certify) {
                    $actionHtml .= '<button title="Approve" type="button" onclick="approvematerial(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-check-circle"></i></button>&nbsp;';
                }
                if ($bill_no) {
                    $actionHtml .= '<a href="'.url('/material_pdf/?id='.$ddid).'" target="_blank" style="all:unset"><i class="zmdi zmdi-collection-pdf"></i></a>&nbsp;';
                }
                if ($can_edit) {
                    $actionHtml .= '<button title="Edit" type="button" onclick="editmaterial(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-edit"></i></button>';
                }
            }

            $formattedData[] = [
                $checkbox,
                $i++,
                $supplier,
                $material,
                $unit,
                $qty,
                $converted_qty,
                $rate,
                $amount,
                $vehical,
                $status,
                $remark,
                $site,
                $user,
                $location,
                $bill_no,
                $date,
                $imageHtml,
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
    public function pending_material(Request $request)
    {
        return view('layouts.material.pending');
    }

    public function get_pending_material_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('material_entry', 'converted_qty')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('material_entry', function ($table) {
                    $table->string('converted_qty', 255)->nullable()->after('qty');
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding converted_qty column in pending AJAX: " . $e->getMessage());
            }
        }
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        if ($from_date && $to_date) {
            $min_date = date('Y-m-d', strtotime($from_date));
            $max_date = date('Y-m-d', strtotime($to_date));
        } else {
            $dates = getdurationdates($view_duration);
            $min_date = date('Y-m-d', strtotime($dates['min']));
            $max_date = date('Y-m-d', strtotime($dates['max']));
        }

        $req_site_id = $request->input('site_id');
        
        $query = DB::connection($user_db_conn_name)->table('material_entry')
            ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
            ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
            ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
            ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
            ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
            ->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
            ->where('material_entry.status', '=', 'Pending');

        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'material_entry.site_id');
        } else {
            if ($req_site_id && $req_site_id != 'all') {
                $query->where('material_entry.site_id', '=', $req_site_id);
            }
        }

        $query->whereBetween('material_entry.date', [$min_date, $max_date]);

        $totalRecords = $query->count();

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('material_supplier.name', 'LIKE', "%{$search}%")
                    ->orWhere('materials.name', 'LIKE', "%{$search}%")
                    ->orWhere('sites.name', 'LIKE', "%{$search}%")
                    ->orWhere('users.name', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.vehical', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.remark', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.date', 'LIKE', "%{$search}%");
            });
        }

        // Individual Column Searching
        $columns_search = $request->input('columns');
        if ($columns_search) {
            foreach ($columns_search as $index => $column) {
                $search_val = $column['search']['value'];
                if (!empty($search_val)) {
                    switch ($index) {
                        case 2: $query->where('material_supplier.name', 'LIKE', "%{$search_val}%"); break;
                        case 3: $query->where('materials.name', 'LIKE', "%{$search_val}%"); break;
                        case 4: $query->where('units.name', 'LIKE', "%{$search_val}%"); break;
                        case 5: $query->where('material_entry.qty', 'LIKE', "%{$search_val}%"); break;
                        case 6: $query->where('material_entry.converted_qty', 'LIKE', "%{$search_val}%"); break;
                        case 7: $query->where('material_entry.vehical', 'LIKE', "%{$search_val}%"); break;
                        case 8: $query->where('material_entry.status', 'LIKE', "%{$search_val}%"); break;
                        case 9: $query->where('material_entry.remark', 'LIKE', "%{$search_val}%"); break;
                        case 10: $query->where('sites.name', 'LIKE', "%{$search_val}%"); break;
                        case 11: $query->where('users.name', 'LIKE', "%{$search_val}%"); break;
                        case 12: $query->where('material_entry.location', 'LIKE', "%{$search_val}%"); break;
                        case 13: $query->where('material_entry.date', 'LIKE', "%{$search_val}%"); break;
                    }
                }
            }
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc');
        
        $columns = [
            1 => 'material_supplier.name',
            2 => 'materials.name',
            3 => 'units.name',
            4 => 'qty',
            5 => 'converted_qty',
            12 => 'date'
        ];
        
        if (isset($columns[$orderColumnIndex])) {
            $query->orderBy($columns[$orderColumnIndex], $orderDir);
        } else {
            $query->orderBy('material_entry.id', 'desc');
        }

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        
        if ($length != -1) {
            $query->skip($start)->take($length);
        }

        $data = $query->get();
        $formattedData = [];
        $i = $start + 1;
        
        $can_certify = checkmodulepermission(3, 'can_certify') == 1;
        $can_edit = checkmodulepermission(3, 'can_edit') == 1;

        foreach ($data as $row) {
            $ddid = $row->id;
            
            $checkbox = '';
            if ($can_certify) {
                $checkbox = '<div class="checkbox"><input id="check_'.$ddid.'" name="check_list[]" class="check_item" type="checkbox" value="'.$ddid.'"><label for="check_'.$ddid.'">&nbsp;</label></div>';
            }

            $supplier = $row->supplier;
            $material = $row->material;
            $unit = $row->unit;
            $qty = $row->qty;
            $converted_qty = $row->converted_qty;
            $vehical = $row->vehical;
            $status = $row->status;
            $remark = $row->remark;
            $site = $row->site;
            $user = $row->user;
            $location = $row->location;
            $date = $row->date;
            
            $imageHtml = '<div class="d-flex">';
            if (!empty($row->image)) {
                $images = explode(',', $row->image);
                foreach ($images as $img) {
                    $img = trim($img);
                    if ($img && $img != 'images/expense.png') {
                        $imageHtml .= '<img class="lazy" src="'.asset($img).'" onclick="enlargeImage(\''.asset($img).'\')" height="50px" width="50px" />&nbsp;';
                    }
                }
            }
            // Legacy column support
            if (!empty($row->image2) && $row->image2 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image2).'" onclick="enlargeImage(\''.asset($row->image2).'\')" height="50px" width="50px" />&nbsp;';
            }
            if (!empty($row->image3) && $row->image3 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image3).'" onclick="enlargeImage(\''.asset($row->image3).'\')" height="50px" width="50px" />&nbsp;';
            }
            if (!empty($row->image4) && $row->image4 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image4).'" onclick="enlargeImage(\''.asset($row->image4).'\')" height="50px" width="50px" />&nbsp;';
            }
            if (!empty($row->image5) && $row->image5 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image5).'" onclick="enlargeImage(\''.asset($row->image5).'\')" height="50px" width="50px" />&nbsp;';
            }
            if ($imageHtml == '<div class="d-flex">') {
                $imageHtml .= '<img class="lazy" src="'.asset('images/expense.png').'" onclick="enlargeImage(\''.asset('images/expense.png').'\')" height="50px" width="50px" />';
            }
            $imageHtml .= '</div>';

            $actionHtml = '';
            if ($can_certify) {
                $actionHtml .= '<button title="Approve" type="button" onclick="approvematerial(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-check-circle"></i></button>&nbsp;';
                $actionHtml .= '<button title="Reject" type="button" onclick="rejectmaterial(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-block"></i></button>&nbsp;';
                $actionHtml .= '<button title="Return" type="button" onclick="returnmaterial(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-undo"></i></button>&nbsp;';
            }
            if ($can_edit) {
                $actionHtml .= '<button title="Edit" type="button" onclick="editmaterial(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-edit"></i></button>';
            }

            $formattedData[] = [
                $checkbox,
                $i++,
                $supplier,
                $material,
                $unit,
                $qty,
                $converted_qty,
                $vehical,
                $status,
                $remark,
                $site,
                $user,
                $location,
                $date,
                $imageHtml,
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

    public function return_material(Request $request)
    {
        return view('layouts.material.return');
    }

    public function get_return_material_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('material_entry', 'converted_qty')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('material_entry', function ($table) {
                    $table->string('converted_qty', 255)->nullable()->after('qty');
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding converted_qty column in return AJAX: " . $e->getMessage());
            }
        }
        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('material_entry', 'return_comment')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('material_entry', function ($table) {
                    $table->text('return_comment')->nullable();
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding return_comment column: " . $e->getMessage());
            }
        }
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        if ($from_date && $to_date) {
            $min_date = date('Y-m-d', strtotime($from_date));
            $max_date = date('Y-m-d', strtotime($to_date));
        } else {
            $dates = getdurationdates($view_duration);
            $min_date = date('Y-m-d', strtotime($dates['min']));
            $max_date = date('Y-m-d', strtotime($dates['max']));
        }

        $req_site_id = $request->input('site_id');

        $query = DB::connection($user_db_conn_name)->table('material_entry')
            ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
            ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
            ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
            ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
            ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
            ->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
            ->where('material_entry.status', '=', 'Returned');

        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'material_entry.site_id');
        } else {
            if ($req_site_id && $req_site_id != 'all') {
                $query->where('material_entry.site_id', '=', $req_site_id);
            }
        }

        $query->whereBetween('material_entry.date', [$min_date, $max_date]);

        $totalRecords = $query->count();

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('material_supplier.name', 'LIKE', "%{$search}%")
                    ->orWhere('materials.name', 'LIKE', "%{$search}%")
                    ->orWhere('sites.name', 'LIKE', "%{$search}%")
                    ->orWhere('users.name', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.vehical', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.remark', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.return_comment', 'LIKE', "%{$search}%")
                    ->orWhere('material_entry.date', 'LIKE', "%{$search}%");
            });
        }

        // Individual Column Searching
        $columns_search = $request->input('columns');
        if ($columns_search) {
            foreach ($columns_search as $index => $column) {
                $search_val = $column['search']['value'];
                if (!empty($search_val)) {
                    switch ($index) {
                        case 2: $query->where('material_supplier.name', 'LIKE', "%{$search_val}%"); break;
                        case 3: $query->where('materials.name', 'LIKE', "%{$search_val}%"); break;
                        case 4: $query->where('units.name', 'LIKE', "%{$search_val}%"); break;
                        case 5: $query->where('material_entry.qty', 'LIKE', "%{$search_val}%"); break;
                        case 6: $query->where('material_entry.converted_qty', 'LIKE', "%{$search_val}%"); break;
                        case 7: $query->where('material_entry.vehical', 'LIKE', "%{$search_val}%"); break;
                        case 8: $query->where('material_entry.status', 'LIKE', "%{$search_val}%"); break;
                        case 9: $query->where(function($q) use ($search_val) {
                            $q->where('material_entry.remark', 'LIKE', "%{$search_val}%")
                              ->orWhere('material_entry.return_comment', 'LIKE', "%{$search_val}%");
                        }); break;
                        case 10: $query->where('sites.name', 'LIKE', "%{$search_val}%"); break;
                        case 11: $query->where('users.name', 'LIKE', "%{$search_val}%"); break;
                        case 12: $query->where('material_entry.location', 'LIKE', "%{$search_val}%"); break;
                        case 13: $query->where('material_entry.date', 'LIKE', "%{$search_val}%"); break;
                    }
                }
            }
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc');

        $columns = [
            1 => 'material_supplier.name',
            2 => 'materials.name',
            3 => 'units.name',
            4 => 'qty',
            5 => 'converted_qty',
            13 => 'date'
        ];

        if (isset($columns[$orderColumnIndex])) {
            $query->orderBy($columns[$orderColumnIndex], $orderDir);
        } else {
            $query->orderBy('material_entry.id', 'desc');
        }

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);

        if ($length != -1) {
            $query->skip($start)->take($length);
        }

        $data = $query->get();
        $formattedData = [];
        $i = $start + 1;

        $can_edit = checkmodulepermission(3, 'can_edit') == 1;

        foreach ($data as $row) {
            $ddid = $row->id;

            $checkbox = '<div class="checkbox"><input id="check_'.$ddid.'" name="check_list[]" class="check_item" type="checkbox" value="'.$ddid.'"><label for="check_'.$ddid.'">&nbsp;</label></div>';

            $supplier = $row->supplier;
            $material = $row->material;
            $unit = $row->unit;
            $qty = $row->qty;
            $converted_qty = $row->converted_qty;
            $vehical = $row->vehical;
            $status = '<span class="badge badge-warning">Returned</span>';

            $remark_comment = '';
            if (!empty($row->remark)) {
                $remark_comment .= '<div><strong>Remark:</strong> ' . e($row->remark) . '</div>';
            }
            if (!empty($row->return_comment)) {
                $remark_comment .= '<div class="text-danger"><strong>Return Reason:</strong> ' . e($row->return_comment) . '</div>';
            }
            if (empty($remark_comment)) {
                $remark_comment = '-';
            }

            $site = $row->site;
            $user = $row->user;
            $location = $row->location;
            $date = $row->date;

            $imageHtml = '<div class="d-flex">';
            if (!empty($row->image)) {
                $images = explode(',', $row->image);
                foreach ($images as $img) {
                    $img = trim($img);
                    if ($img && $img != 'images/expense.png') {
                        $imageHtml .= '<img class="lazy" src="'.asset($img).'" onclick="enlargeImage(\''.asset($img).'\')" height="50px" width="50px" />&nbsp;';
                    }
                }
            }
            if (!empty($row->image2) && $row->image2 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image2).'" onclick="enlargeImage(\''.asset($row->image2).'\')" height="50px" width="50px" />&nbsp;';
            }
            if (!empty($row->image3) && $row->image3 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image3).'" onclick="enlargeImage(\''.asset($row->image3).'\')" height="50px" width="50px" />&nbsp;';
            }
            if (!empty($row->image4) && $row->image4 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image4).'" onclick="enlargeImage(\''.asset($row->image4).'\')" height="50px" width="50px" />&nbsp;';
            }
            if (!empty($row->image5) && $row->image5 != 'images/expense.png') {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image5).'" onclick="enlargeImage(\''.asset($row->image5).'\')" height="50px" width="50px" />&nbsp;';
            }
            if ($imageHtml == '<div class="d-flex">') {
                $imageHtml .= '<img class="lazy" src="'.asset('images/expense.png').'" onclick="enlargeImage(\''.asset('images/expense.png').'\')" height="50px" width="50px" />';
            }
            $imageHtml .= '</div>';

            $actionHtml = '';
            if ($can_edit) {
                $actionHtml .= '<button title="Edit" type="button" onclick="editmaterial(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-edit"></i></button>&nbsp;';
                $actionHtml .= '<button title="Resubmit" type="button" onclick="resubmitmaterial(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-refresh-sync"></i></button>';
            }

            $formattedData[] = [
                $checkbox,
                $i++,
                $supplier,
                $material,
                $unit,
                $qty,
                $converted_qty,
                $vehical,
                $status,
                $remark_comment,
                $site,
                $user,
                $location,
                $date,
                $imageHtml,
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

    public function return_material_action(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('material_entry', 'return_comment')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('material_entry', function ($table) {
                    $table->text('return_comment')->nullable();
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding return_comment column in return_material_action: " . $e->getMessage());
            }
        }
        $ids = $request->input('check_list');
        $comment = $request->input('return_comment');

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Please select at least one material entry!']);
        }

        try {
            DB::connection($user_db_conn_name)->table('material_entry')
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'Returned',
                    'return_comment' => $comment
                ]);

            foreach ($ids as $id) {
                addActivity($id, 'material_entry', "Material Entry Returned with comment: " . $comment, 3);
                $mat_entry = DB::connection($user_db_conn_name)->table('material_entry')->where('id', $id)->first();
                if ($mat_entry && $mat_entry->user_id) {
                    sendAlertNotification($mat_entry->user_id, 'Your material entry has been returned. Comment: ' . $comment, 'Material Returned');
                }
            }

            return response()->json(['status' => 'success', 'message' => 'Selected Material Entries Returned Successfully!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error while returning material entries: ' . $e->getMessage()]);
        }
    }

    public function resubmit_returned_material(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $id = $request->input('id');

        try {
            DB::connection($user_db_conn_name)->table('material_entry')
                ->where('id', $id)
                ->update(['status' => 'Pending']);

            addActivity($id, 'material_entry', "Material Entry Resubmitted to Pending", 3);

            return response()->json(['status' => 'success', 'message' => 'Material Entry Resubmitted Successfully!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error while resubmitting material entry!']);
        }
    }

    public function bulk_resubmit_returned_material(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $ids = $request->input('check_list');

        if (empty($ids)) {
            return response()->json(['status' => 'error', 'message' => 'Please select at least one material entry!']);
        }

        try {
            DB::connection($user_db_conn_name)->table('material_entry')
                ->whereIn('id', $ids)
                ->update(['status' => 'Pending']);

            foreach ($ids as $id) {
                addActivity($id, 'material_entry', "Material Entry Resubmitted to Pending (Bulk)", 3);
            }

            return response()->json(['status' => 'success', 'message' => 'Selected Material Entries Resubmitted Successfully!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error while resubmitting material entries!']);
        }
    }
    public function new_material(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        // Dynamic schema update for tenant database to ensure is_royalty column exists
        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('materials', 'is_royalty')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('materials', function ($table) {
                    $table->boolean('is_royalty')->default(0)->after('name')->comment('Whether this material requires cubic meter conversion for royalty purposes');
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding is_royalty column to materials table: " . $e->getMessage());
            }
        }

        $data['material_supplier'] = DB::connection($user_db_conn_name)->table('material_supplier')->whereIn('status', ['Active', 'Pending'])->get();
        $data['materials'] = DB::connection($user_db_conn_name)->table('materials')->get();
        $data['units'] = DB::connection($user_db_conn_name)->table('units')->get();
        $data['sites'] = getallActivesites();
        $data['conversion_format'] = DB::connection($user_db_conn_name)->table('material_conversion_rules')->join('units', 'units.id', '=', 'material_conversion_rules.to_unit')->select('material_conversion_rules.*', 'units.name as to_unit_name')->get();
        return  view('layouts.material.newmaterial')->with('data', json_encode($data));
    }
    public function addnewmaterial(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'site_id' => 'required|array',
            'site_id.*' => 'required',
            'date' => 'required|array',
            'date.*' => 'required|date',
            'supplier' => 'required|array',
            'supplier.*' => 'required',
            'material_id' => 'required|array',
            'material_id.*' => 'required',
            'qty' => 'required|array',
            'qty.*' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Validation Error: Invalid Material payload.');
        }

        $result = false;
        $data = $request->input();
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('material_entry', 'image3')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('material_entry', function ($table) {
                    $table->string('image3', 2000)->nullable();
                    $table->string('image4', 2000)->nullable();
                    $table->string('image5', 2000)->nullable();
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding columns: " . $e->getMessage());
            }
        }

        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('material_entry', 'converted_qty')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('material_entry', function ($table) {
                    $table->string('converted_qty', 255)->nullable()->after('qty');
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding converted_qty column: " . $e->getMessage());
            }
        }

        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = $duration['max'];

        DB::connection($user_db_conn_name)->beginTransaction();
        try {
            foreach ($data['site_id'] as $i => $site_id) {
                $item_date = isset($data['date'][$i]) ? $data['date'][$i] : date('Y-m-d');
                if (strtotime($item_date) < strtotime($min_date) || strtotime($item_date) > strtotime($max_date)) {
                    DB::connection($user_db_conn_name)->rollBack();
                    return redirect()->back()->with('error', "You don't have permission to add entry for date: " . $item_date);
                }
                $uploadedImages = [];
                if (isset($request->image[$i])) {
                    $file = $request->image[$i];
                    if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                        $file->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName);
                        $uploadedImages[] = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName;
                    }
                }

                if (isset($request->image2[$i])) {
                    $file2 = $request->image2[$i];
                    if ($file2 instanceof \Illuminate\Http\UploadedFile && $file2->isValid()) {
                        $imageName2 = time() . rand(10000, 1000000) . '.' . $file2->extension();
                        $file2->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName2);
                        $uploadedImages[] = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName2;
                    }
                }

                if (isset($request->image3[$i])) {
                    $file3 = $request->image3[$i];
                    if ($file3 instanceof \Illuminate\Http\UploadedFile && $file3->isValid()) {
                        $imageName3 = time() . rand(10000, 1000000) . '.' . $file3->extension();
                        $file3->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName3);
                        $uploadedImages[] = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName3;
                    }
                }

                if (isset($request->image4[$i])) {
                    $file4 = $request->image4[$i];
                    if ($file4 instanceof \Illuminate\Http\UploadedFile && $file4->isValid()) {
                        $imageName4 = time() . rand(10000, 1000000) . '.' . $file4->extension();
                        $file4->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName4);
                        $uploadedImages[] = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName4;
                    }
                }

                if (isset($request->image5[$i])) {
                    $file5 = $request->image5[$i];
                    if ($file5 instanceof \Illuminate\Http\UploadedFile && $file5->isValid()) {
                        $imageName5 = time() . rand(10000, 1000000) . '.' . $file5->extension();
                        $file5->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName5);
                        $uploadedImages[] = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName5;
                    }
                }

                $imagePath = count($uploadedImages) > 0 ? implode(',', $uploadedImages) : "images/expense.png";

                $rawd = [
                    'supplier' => isset($data['supplier'][$i]) ? $data['supplier'][$i] : '',
                    'material_id' => isset($data['material_id'][$i]) ? $data['material_id'][$i] : null,
                    'unit' => isset($data['unit'][$i]) ? $data['unit'][$i] : '',
                    'qty' => isset($data['qty'][$i]) ? $data['qty'][$i] : 0,
                    'converted_qty' => isset($data['converted_qty'][$i]) ? $data['converted_qty'][$i] : null,
                    'vehical' => isset($data['vehical'][$i]) ? $data['vehical'][$i] : '',
                    'image' => $imagePath,
                    'remark' => isset($data['remark'][$i]) ? $data['remark'][$i] : '',
                    'site_id' => $site_id,
                    'status' => $status,
                    'user_id' => $user_id,
                    'date' => $item_date,
                ];

                $id =  DB::connection($user_db_conn_name)->table('material_entry')->insertGetId($rawd);
                addActivity($id, 'material_entry', "New Material Entry Created", 3);

                if ($status == 'Approved') {
                    $this->approve_material_entry($id, $user_db_conn_name);
                }
            }
            DB::connection($user_db_conn_name)->commit();
            $result = true;
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            \Log::error("addnewmaterial failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $result = false;
        }

        if ($result) {
            return redirect('/verified_material')
                ->with('success', 'Material Entries Created successfully!');
        } else {
            return redirect('/verified_material')
                ->with('error', 'Error While Creating Material Entries. Please Try Again After Reconciling The Statement.!');
        }
    }
    public function updatematerialEntry(Request $request)
    {
        $data = $request->input();
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('material_entry', 'image3')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('material_entry', function ($table) {
                    $table->string('image3', 2000)->nullable();
                    $table->string('image4', 2000)->nullable();
                    $table->string('image5', 2000)->nullable();
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding columns during update: " . $e->getMessage());
            }
        }

        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('material_entry', 'converted_qty')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('material_entry', function ($table) {
                    $table->string('converted_qty', 255)->nullable()->after('qty');
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding converted_qty column: " . $e->getMessage());
            }
        }

        $id = $data['id'];
        $material_entry = DB::connection($user_db_conn_name)->table('material_entry')->where('id', $id)->first();

        if (!$material_entry) {
            return redirect('/pending_material')->with('error', 'Material entry not found!');
        }

        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = $duration['max'];

        if (strtotime($data['date']) < strtotime($min_date) || strtotime($data['date']) > strtotime($max_date)) {
            return redirect()->back()->with('error', "You don't have permission to update entry for date: " . $data['date']);
        }

                $existingImages = [];
        if (!empty($material_entry->image)) {
            $existingImages = explode(',', $material_entry->image);
        }
        // Fallback for older database style
        if (empty($existingImages[1]) && !empty($material_entry->image2)) {
            $existingImages[1] = $material_entry->image2;
        }
        if (empty($existingImages[2]) && !empty($material_entry->image3)) {
            $existingImages[2] = $material_entry->image3;
        }
        if (empty($existingImages[3]) && !empty($material_entry->image4)) {
            $existingImages[3] = $material_entry->image4;
        }
        if (empty($existingImages[4]) && !empty($material_entry->image5)) {
            $existingImages[4] = $material_entry->image5;
        }

        $imagePath = isset($existingImages[0]) ? $existingImages[0] : 'images/expense.png';
        if (isset($request->image)) {
            if (File::exists($imagePath) && $imagePath != 'images/expense.png') {
                File::delete($imagePath);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName);
            $imagePath = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName;
        }

        $clear_image2 = $request->input('clear_image2', '0');
        $imagePath2 = isset($existingImages[1]) ? $existingImages[1] : null;
        if ($clear_image2 == '1') {
            if (File::exists($imagePath2) && $imagePath2 != 'images/expense.png') {
                File::delete($imagePath2);
            }
            $imagePath2 = null;
        } else if (isset($request->image2)) {
            if (File::exists($imagePath2) && $imagePath2 != 'images/expense.png') {
                File::delete($imagePath2);
            }
            $imageName2 = time() . rand(10000, 1000000) . '.' . $request->image2->extension();
            $request->image2->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName2);
            $imagePath2 = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName2;
        }

        $clear_image3 = $request->input('clear_image3', '0');
        $imagePath3 = isset($existingImages[2]) ? $existingImages[2] : null;
        if ($clear_image3 == '1') {
            if (File::exists($imagePath3) && $imagePath3 != 'images/expense.png') {
                File::delete($imagePath3);
            }
            $imagePath3 = null;
        } else if (isset($request->image3)) {
            if (File::exists($imagePath3) && $imagePath3 != 'images/expense.png') {
                File::delete($imagePath3);
            }
            $imageName3 = time() . rand(10000, 1000000) . '.' . $request->image3->extension();
            $request->image3->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName3);
            $imagePath3 = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName3;
        }

        $clear_image4 = $request->input('clear_image4', '0');
        $imagePath4 = isset($existingImages[3]) ? $existingImages[3] : null;
        if ($clear_image4 == '1') {
            if (File::exists($imagePath4) && $imagePath4 != 'images/expense.png') {
                File::delete($imagePath4);
            }
            $imagePath4 = null;
        } else if (isset($request->image4)) {
            if (File::exists($imagePath4) && $imagePath4 != 'images/expense.png') {
                File::delete($imagePath4);
            }
            $imageName4 = time() . rand(10000, 1000000) . '.' . $request->image4->extension();
            $request->image4->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName4);
            $imagePath4 = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName4;
        }

        $clear_image5 = $request->input('clear_image5', '0');
        $imagePath5 = isset($existingImages[4]) ? $existingImages[4] : null;
        if ($clear_image5 == '1') {
            if (File::exists($imagePath5) && $imagePath5 != 'images/expense.png') {
                File::delete($imagePath5);
            }
            $imagePath5 = null;
        } else if (isset($request->image5)) {
            if (File::exists($imagePath5) && $imagePath5 != 'images/expense.png') {
                File::delete($imagePath5);
            }
            $imageName5 = time() . rand(10000, 1000000) . '.' . $request->image5->extension();
            $request->image5->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName5);
            $imagePath5 = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName5;
        }

        $updatedImages = [];
        if ($imagePath) {
            $updatedImages[] = $imagePath;
        }
        if ($imagePath2) {
            $updatedImages[] = $imagePath2;
        }
        if ($imagePath3) {
            $updatedImages[] = $imagePath3;
        }
        if ($imagePath4) {
            $updatedImages[] = $imagePath4;
        }
        if ($imagePath5) {
            $updatedImages[] = $imagePath5;
        }

        $finalImageStr = count($updatedImages) > 0 ? implode(',', $updatedImages) : 'images/expense.png';

        try {
            DB::connection($user_db_conn_name)->table('material_entry')->where('id', $id)->update([
                'supplier' => $data['supplier'],
                'material_id' => $data['material_id'],
                'unit' => $data['unit'],
                'qty' => $data['qty'],
                'converted_qty' => isset($data['converted_qty']) ? $data['converted_qty'] : null,
                'vehical' => $data['vehical'],
                'image' => $finalImageStr,
                'remark' => $data['remark'],
                'site_id' => $data['site_id'],
                'status' => $status,
                'user_id' => $user_id,
                'date' => $data['date'],
            ]);
            addActivity($id, 'material_entry', "Material Entry Data Updated ", 3);
            if ($status == 'Approved') {
                $this->approve_material_entry($id, $user_db_conn_name);
            }
            return redirect('/verified_material')
                ->with('success', 'Material Entries Updated successfully!');
        } catch (\Exception $e) {
            return redirect('/verified_material')
                ->with('error', 'Error While Updating Material Entries: ' . $e->getMessage());
        }
    }
    public function reject_material_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $this->reject_material_entry($id, $user_db_conn_name);
        return redirect('/verified_material')
            ->with('success', 'Material Entries Rejected Successfully!');
    }
    public function edit_material_entry(Request $request)
    {
        $data = array();
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        // Dynamic schema update for tenant database to ensure is_royalty column exists
        if (!\Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->hasColumn('materials', 'is_royalty')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($user_db_conn_name)->table('materials', function ($table) {
                    $table->boolean('is_royalty')->default(0)->after('name')->comment('Whether this material requires cubic meter conversion for royalty purposes');
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding is_royalty column to materials table in edit: " . $e->getMessage());
            }
        }

        $data['material_supplier'] = DB::connection($user_db_conn_name)->table('material_supplier')->whereIn('status', ['Active', 'Pending'])->get();
        $data['materials'] = DB::connection($user_db_conn_name)->table('materials')->get();
        $data['units'] = DB::connection($user_db_conn_name)->table('units')->get();
        $data['sites'] = getallActivesites();
        $data['materialentry'] = DB::connection($user_db_conn_name)->table('material_entry')->where('id', $id)->first();
        $data['conversion_format'] = DB::connection($user_db_conn_name)->table('material_conversion_rules')->join('units', 'units.id', '=', 'material_conversion_rules.to_unit')->select('material_conversion_rules.*', 'units.name as to_unit_name')->get();

        $site_id = session()->get("site_id");
        $role_details = getRoleDetailsById(session()->get('role'));
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        if (!isSuperAdmin() && $entry_at_site == "current" && $site_id != $data['materialentry']->site_id) {
            return redirect('/pending_material')->with('error', "You don't have permission to edit entries at site - " . getSiteDetailsById($data['materialentry']->site_id)->name . "!");
        }
        if ($data['materialentry']->date < $min_date) {
            return redirect('/pending_material')
                ->with('error', "You don't have permission to edit entries before " . $min_date . " !");
        }
        return  view('layouts.material.editmaterialentry')->with('data', json_encode($data));
    }
    public function approve_material_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $this->approve_material_entry($id, $user_db_conn_name);

        return redirect('/verified_material')
            ->with('success', 'Material Entries Approved Successfully!');
    }

    public function approve_material_entry($id, $user_db_conn_name)
    {
        $material_entry = DB::connection($user_db_conn_name)
            ->table('material_entry')
            ->join('materials', 'materials.id', '=', 'material_entry.material_id')
            ->join('units', 'units.id', '=', 'material_entry.unit')
            ->select('material_entry.*', 'materials.name as material', 'units.name as unitname')
            ->where('material_entry.id', $id)
            ->first();

        if (!$material_entry) {
            throw new \Exception("Invalid material or unit reference.");
        }

        $exists = DB::connection($user_db_conn_name)->table('material_stock_transactions')
            ->where('refrence', 'Purchase')
            ->where('refrence_id', $id)
            ->exists();

        if ($exists) {
            return;
        }

        DB::connection($user_db_conn_name)->table('material_entry')->where('id', '=', $id)->update(['status' => 'Approved']);
        $stock_data = ['site_id' => $material_entry->site_id, 'material_id' => $material_entry->material_id, 'qty' => $material_entry->qty, 'unit' => $material_entry->unit, 'type' => 'IN', 'refrence' => 'Purchase', 'refrence_id' => $material_entry->id];
        DB::connection($user_db_conn_name)->table('material_stock_transactions')->insert($stock_data);
        $check_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $material_entry->site_id)->where('material_id', '=', $material_entry->material_id)->where('unit', '=', $material_entry->unit)->get();
        if (count($check_current_stock) > 0) {
            $current_qty = $check_current_stock[0]->qty;
            $new_qty = $current_qty + $material_entry->qty;
            DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_current_stock[0]->id)->update(['qty' => $new_qty]);
        } else {
            $new_stock_data = ['material_id' => $material_entry->material_id, 'site_id' => $material_entry->site_id, 'qty' => $material_entry->qty, 'unit' => $material_entry->unit];
            DB::connection($user_db_conn_name)->table('material_stock_record')->insert($new_stock_data);
        }

        sendAlertNotification($material_entry->user_id, 'Your entry of ' . $material_entry->material . ' of ' . $material_entry->qty . ' ' . $material_entry->unitname . ' has been approved. Check Application For More Information.', 'Material Approved');
        addActivity($id, 'material_entry', "Material Entry Approved ", 3);
    }

    public function reject_material_entry($id, $user_db_conn_name)
    {
        $material_entry = DB::connection($user_db_conn_name)
            ->table('material_entry')
            ->join('materials', 'materials.id', '=', 'material_entry.material_id')
            ->join('units', 'units.id', '=', 'material_entry.unit')
            ->select('material_entry.*', 'materials.name as material', 'units.name as unitname')
            ->where('material_entry.id', $id)
            ->first();

        if (!$material_entry || $material_entry->status === 'Rejected') {
            return;
        }

        DB::connection($user_db_conn_name)->table('material_entry')->where('id', '=', $id)->update(['status' => 'Rejected']);
        $check_entry_approved = DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('refrence_id', '=', $material_entry->id)->where('refrence', '=', 'Purchase')->get();
        if(count($check_entry_approved) == 1){
            DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('refrence_id', '=', $material_entry->id)->where('refrence', '=', 'Purchase')->delete();
            $check_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $material_entry->site_id)->where('material_id', '=', $material_entry->material_id)->where('unit', '=', $material_entry->unit)->get();
            if (count($check_current_stock) > 0) {
                $current_qty = $check_current_stock[0]->qty;
                $new_qty = $current_qty - $material_entry->qty;
                DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_current_stock[0]->id)->update(['qty' => $new_qty]);
            } 
        }
       
        sendAlertNotification($material_entry->user_id, 'Your entry of ' . $material_entry->material . ' of ' . $material_entry->qty . ' ' . $material_entry->unitname . ' has been approved. Check Application For More Information.', 'Material Approved');
        addActivity($id, 'material_entry', "Material Entry Rejected ", 3);
    }

    public function update_material(Request $request)
    {
        $ids = $request->input('check_list');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        if ($ids != null) {
            if ($request->input('approve_material')) {
                foreach ($ids as $id) {
                    $this->approve_material_entry($id, $user_db_conn_name);
                }
                return redirect('/pending_material')
                    ->with('success', 'Material Approved successfully!');
            } else if ($request->input('reject_material')) {
                foreach ($ids as $id) {
                    $this->reject_material_entry($id, $user_db_conn_name);
                }
                return redirect('/pending_material')
                    ->with('success', 'Material Rejected successfully!');
            }
        } else {
            return redirect('/pending_material')
                ->with('error', 'Please Choose Atleast One Material Entry!');
        }
    }

    public function add_material_bill_info(Request $request)
    {
        $result = array();
        $ids = $request->input('check_list');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        if ($ids != null) {
            foreach ($ids as $id) {
                $rawd = DB::connection($user_db_conn_name)->table('material_entry')->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')->leftjoin('units', 'units.id', '=', 'material_entry.unit')->leftjoin('users', 'users.id', '=', 'material_entry.user_id')->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')->where('material_entry.id', '=', $id)->get();
                array_push($result, $rawd);
            }
            $data['material_entries'] = $result;

            return view('layouts.material.materialbillinfo')->with('data', json_encode($data));
        } else {
            return redirect('/verified_material')
                ->with('error', 'Please Choose Atleast One Material Entry!');
        }
    }
    public function update_material_bill_info(Request $request)
    {

        $data = $request->input();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $length = count($data['ids']);
        $bill_info = $data['bill_no'];
        for ($i = 0; $i < $length; $i++) {
            $id = $data['ids'][$i];
            $rate = $data['rates'][$i];
            $tax = $data['tax'][$i];
            $material_entry = DB::connection($user_db_conn_name)->table('material_entry')->where('id', $id)->get()[0];
            $taxamunt = ($tax * $rate) / 100;
            $finalamount = $taxamunt + $rate;
            $amount = $material_entry->qty * $finalamount;
            DB::connection($user_db_conn_name)->table('material_entry')->where('id', '=', $id)->update(['amount' => $amount, 'rate' => $rate, 'tax' => $tax, 'bill_no' => $bill_info]);
            $debit_data = ['supplier_id' => $material_entry->supplier, 'type' => 'Debit', 'entry_id' => $id];
            DB::connection($user_db_conn_name)->table('material_supplier_statement')->where('entry_id', $id)->delete();
            DB::connection($user_db_conn_name)->table('material_supplier_statement')->insert($debit_data);
            addActivity($id, 'material_entry', "Material Bill information Updated ", 3);
        }

        return redirect('/verified_material')
            ->with('success', 'Material Bills Updated successfully!');
    }

    public function bulk_edit_pending_material(Request $request)
    {
        $ids = $request->input('check_list');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        if ($ids != null) {
            $data['material_entries'] = DB::connection($user_db_conn_name)->table('material_entry')
                ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                ->select('material_entry.*', 'materials.name as material', 'units.name as unit_name', 'sites.name as site', 'material_supplier.name as supplier')
                ->whereIn('material_entry.id', $ids)
                ->get();

            return view('layouts.material.bulk_edit_pending')->with('data', json_encode($data));
        } else {
            return redirect('/pending_material')
                ->with('error', 'Please Choose Atleast One Material Entry!');
        }
    }

    public function update_bulk_pending_material(Request $request)
    {
        $ids = $request->input('ids');
        $qtys = $request->input('qtys');
        $vehicals = $request->input('vehicals');
        $remarks = $request->input('remarks');
        $dates = $request->input('dates');
        $user_db_conn_name = session()->get('comp_db_conn_name');

        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = $duration['max'];

        if ($ids != null) {
            DB::connection($user_db_conn_name)->beginTransaction();
            try {
                foreach ($ids as $key => $id) {
                    if (strtotime($dates[$key]) < strtotime($min_date) || strtotime($dates[$key]) > strtotime($max_date)) {
                        DB::connection($user_db_conn_name)->rollBack();
                        return redirect()->back()->with('error', "You don't have permission to update entry for date: " . $dates[$key]);
                    }
                    DB::connection($user_db_conn_name)->table('material_entry')->where('id', $id)->update([
                        'qty' => $qtys[$key],
                        'vehical' => $vehicals[$key],
                        'remark' => $remarks[$key],
                        'date' => $dates[$key],
                    ]);
                    addActivity($id, 'material_entry', "Material Entry Data Updated via Bulk Edit", 3);
                }
                DB::connection($user_db_conn_name)->commit();
                return redirect('/pending_material')
                    ->with('success', 'Material Entries Updated successfully!');
            } catch (\Exception $e) {
                DB::connection($user_db_conn_name)->rollBack();
                return redirect('/pending_material')
                    ->with('error', 'Error While Updating Material Entries. ' . $e->getMessage());
            }
        } else {
            return redirect('/pending_material')
                ->with('error', 'No entries to update!');
        }
    }
}
