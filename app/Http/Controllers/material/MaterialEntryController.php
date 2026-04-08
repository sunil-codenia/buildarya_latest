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
                    ->orWhere('material_entry.remark', 'LIKE', "%{$search}%");
            });
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc');

        $columns = [
            2 => 'material_supplier.name',
            3 => 'materials.name',
            5 => 'qty',
            15 => 'date'
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
            if ($row->image) {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image).'" onclick="enlargeImage(\''.asset($row->image).'\')" height="50px" width="50px" />&nbsp;';
            }
            if ($row->image2) {
                $imageHtml .= '<img class="lazy" src="'.asset($row->image2).'" onclick="enlargeImage(\''.asset($row->image2).'\')" height="50px" width="50px" />';
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
                    ->orWhere('material_entry.remark', 'LIKE', "%{$search}%");
            });
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc');
        
        $columns = [
            1 => 'material_supplier.name',
            2 => 'materials.name',
            3 => 'units.name',
            4 => 'qty',
            11 => 'date'
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
            $vehical = $row->vehical;
            $status = $row->status;
            $remark = $row->remark;
            $site = $row->site;
            $user = $row->user;
            $location = $row->location;
            $date = $row->date;
            
            $imageHtml = '<img class="lazy" src="'.asset($row->image).'" onclick="enlargeImage(\''.asset($row->image).'\')" height="50px" width="50px" />';
            if ($row->image2) {
                $imageHtml .= ' <img class="lazy" src="'.asset($row->image2).'" onclick="enlargeImage(\''.asset($row->image2).'\')" height="50px" width="50px" />';
            }

            $actionHtml = '';
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
    public function new_material(Request $request)
    {

        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['material_supplier'] = DB::connection($user_db_conn_name)->table('material_supplier')->where('status', '=', 'Active')->get();
        $data['materials'] = DB::connection($user_db_conn_name)->table('materials')->get();
        $data['units'] = DB::connection($user_db_conn_name)->table('units')->get();
        $data['sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        return  view('layouts.material.newmaterial')->with('data', json_encode($data));
    }
    public function addnewmaterial(Request $request)
    {
        $result = false;
        $data = $request->input();
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $length = count($data['site_id']);
        for ($i = 0; $i < $length; $i++) {
            if (isset($request->image[$i])) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image[$i]->extension();
                $request->image[$i]->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName);
                $imagePath = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName;
            } else {
                $imagePath = "images/expense.png";
            }
            $rawd = [
                'supplier' => $data['supplier'][$i],
                'material_id' => $data['material_id'][$i],
                'unit' => $data['unit'][$i],
                'qty' => $data['qty'][$i],
                'vehical' => $data['vehical'][$i],
                'image' => $imagePath,
                'remark' => $data['remark'][$i],
                'site_id' => $data['site_id'][$i],
                'status' => $status,
                'user_id' => $user_id,
                'date' => $data['date'][$i],
            ];
            try {
                $id =  DB::connection($user_db_conn_name)->table('material_entry')->insertGetId($rawd);

                if ($status == 'Approved') {
                    $this->approve_material_entry($id, $user_db_conn_name);
                }
                $result = true;
            } catch (\Exception $e) {
                $result = false;
            }
        }

        if ($result) {
            addActivity($id, 'material_entry', "New Material Entries Created ", 3);
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
        $id = $data['id'];
        $material_entry = DB::connection($user_db_conn_name)->table('material_entry')->where('id', $id)->get()[0];

        if (isset($request->image)) {
            if (File::exists($material_entry->image) && $material_entry->image != 'images/expense.png') {
                File::delete($material_entry->image);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/' . $user_db_conn_name . '/material'), $imageName);
            $imagePath = "images/app_images/" . $user_db_conn_name . "/material/" . $imageName;
        } else {
            $imagePath = $material_entry->image;
        }
        $rawd = [
            'id' => $id,
            'supplier' => $data['supplier'],
            'material_id' => $data['material_id'],
            'unit' => $data['unit'],
            'qty' => $data['qty'],
            'vehical' => $data['vehical'],
            'image' => $imagePath,
            'remark' => $data['remark'],
            'site_id' => $data['site_id'],
            'status' => $status,
            'user_id' => $user_id,
            'date' => $data['date'],
        ];
        try {
            DB::connection($user_db_conn_name)->table('material_entry')->upsert($rawd, 'id');
            addActivity($id, 'material_entry', "Material Entry Data Updated ", 3);
            if ($status == 'Approved') {
                $this->approve_material_entry($id, $user_db_conn_name);
            }
            return redirect('/verified_material')
                ->with('success', 'Material Entries Updated successfully!');
        } catch (\Exception $e) {
            return redirect('/verified_material')
                ->with('error', 'Error While Updating Material Entries. Please Try Again After Reconciling The Statement.!');
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
        $data['material_supplier'] = DB::connection($user_db_conn_name)->table('material_supplier')->where('status', '=', 'Active')->get();
        $data['materials'] = DB::connection($user_db_conn_name)->table('materials')->get();
        $data['units'] = DB::connection($user_db_conn_name)->table('units')->get();
        $data['sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        $data['materialentry'] = DB::connection($user_db_conn_name)->table('material_entry')->where('id', $id)->get()[0];

        $site_id = session()->get("site_id");
        $role_details = getRoleDetailsById(session()->get('role'));
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        if ($entry_at_site == "current" && $site_id != $data['materialentry']->site_id) {
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
        $material_entry = DB::connection($user_db_conn_name)->table('material_entry')->join('materials', 'materials.id', '=', 'material_entry.material_id')->join('units', 'units.id', '=', 'material_entry.unit')->select('material_entry.*', 'materials.name as material', 'units.name as unitname')->where('material_entry.id', $id)->get()[0];
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
        $material_entry = DB::connection($user_db_conn_name)->table('material_entry')->join('materials', 'materials.id', '=', 'material_entry.material_id')->join('units', 'units.id', '=', 'material_entry.unit')->select('material_entry.*', 'materials.name as material', 'units.name as unitname')->where('material_entry.id', $id)->get()[0];

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

        if ($ids != null) {
            DB::connection($user_db_conn_name)->beginTransaction();
            try {
                foreach ($ids as $key => $id) {
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
