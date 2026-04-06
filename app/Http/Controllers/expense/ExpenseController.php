<?php

namespace App\Http\Controllers\expense;

use App\Exports\ExpenseExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Response;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;


class ExpenseController extends Controller
{
    //
    public function verified_expense(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');

        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;

        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];

        $filters = [['expenses.status', '!=', 'Pending']];
        if ($visiblity_at_site == 'current') {
            $filters[] = ['expenses.site_id', '=', $site_id];
        }

        $query = DB::connection($user_db_conn_name)->table('expenses')
            ->leftJoin('expense_party', function ($join) {
                $join->on('expense_party.id', '=', 'expenses.party_id')
                    ->where('expenses.party_type', '=', 'expense');
            })
            ->leftJoin('bills_party', function ($join) {
                $join->on('bills_party.id', '=', 'expenses.party_id')
                    ->where('expenses.party_type', '=', 'bill');
            })
            ->leftJoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
            ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
            ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
            ->select(
                'expenses.*',
                'sites.name as site',
                'users.name as user',
                'expense_head.name as head',
                DB::raw('CASE WHEN expenses.party_type = "bill" THEN bills_party.name ELSE expense_party.name END as party_name')
            )
            ->where($filters)
            ->whereBetween('expenses.create_datetime', [$min_date, $max_date]);

        if ($request->get('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('expenses.particular', 'like', "%$search%")
                    ->orWhere('expenses.amount', 'like', "%$search%")
                    ->orWhere('sites.name', 'like', "%$search%")
                    ->orWhere('users.name', 'like', "%$search%")
                    ->orWhere('expense_head.name', 'like', "%$search%")
                    ->orWhere('expense_party.name', 'like', "%$search%")
                    ->orWhere('bills_party.name', 'like', "%$search%");
            });
        }

        $data = $query->orderBy('expenses.create_datetime', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Pre-fetch mappings to avoid N+1 queries in the view
        $asset_expense_heads = DB::connection($user_db_conn_name)->table('assets_expense_head')->pluck('head_id')->toArray();
        $machinery_expense_heads = DB::connection($user_db_conn_name)->table('machinery_expense_head')->pluck('head_id')->toArray();
        $asset_heads = DB::connection($user_db_conn_name)->table('asset_head')->pluck('name', 'id')->toArray();
        $machinery_heads = DB::connection($user_db_conn_name)->table('machinery_head')->pluck('name', 'id')->toArray();

        return view('layouts.expense.verified', compact('data', 'asset_expense_heads', 'machinery_expense_heads', 'asset_heads', 'machinery_heads'));
    }

    public function verified_expense_export(Request $request, $type)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');

        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;

        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];

        $filters = [['expenses.status', '!=', 'Pending']];
        if ($visiblity_at_site == 'current') {
            $filters[] = ['expenses.site_id', '=', $site_id];
        }

        $query = DB::connection($user_db_conn_name)->table('expenses')
            ->leftJoin('expense_party', function ($join) {
                $join->on('expense_party.id', '=', 'expenses.party_id')
                    ->where('expenses.party_type', '=', 'expense');
            })
            ->leftJoin('bills_party', function ($join) {
                $join->on('bills_party.id', '=', 'expenses.party_id')
                    ->where('expenses.party_type', '=', 'bill');
            })
            ->leftJoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
            ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
            ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
            ->select(
                'expenses.*',
                'sites.name as site',
                'users.name as user',
                'expense_head.name as head',
                DB::raw('CASE WHEN expenses.party_type = "bill" THEN bills_party.name ELSE expense_party.name END as party_name')
            )
            ->where($filters)
            ->whereBetween('expenses.create_datetime', [$min_date, $max_date]);

        if ($request->get('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('expenses.particular', 'like', "%$search%")
                    ->orWhere('expenses.amount', 'like', "%$search%")
                    ->orWhere('sites.name', 'like', "%$search%")
                    ->orWhere('users.name', 'like', "%$search%")
                    ->orWhere('expense_head.name', 'like', "%$search%")
                    ->orWhere('expense_party.name', 'like', "%$search%")
                    ->orWhere('bills_party.name', 'like', "%$search%");
            });
        }

        $expenses = $query->orderBy('expenses.create_datetime', 'desc')->get();
        $file_name = "Verified Expenses (" . date('d-m-Y') . ")";

        if ($type == 'pdf') {
            $pdf = Pdf::loadView('layouts.expense.exports.verified_export', compact('expenses'))->setPaper('a4', 'landscape');
            return $pdf->download($file_name . ".pdf");
        }

        return Excel::download(new \App\Exports\VerifiedExpenseExport($expenses), $file_name . "." . $type);
    }

    public function bulk_approve_verified(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $ids = $request->input('check_list');

        if (empty($ids)) {
            return redirect('/verified_expense')->with('error', 'Please select at least one expense!');
        }

        try {
            DB::connection($user_db_conn_name)->table('expenses')
                ->whereIn('id', $ids)
                ->update(['status' => 'Approved']);
            
            foreach ($ids as $id) {
                addActivity($id, 'expenses', "Expense Approved via Bulk Action", 2);
            }

            return redirect('/verified_expense')->with('success', 'Selected Expenses Approved Successfully!');
        } catch (\Exception $e) {
            return redirect('/verified_expense')->with('error', 'Error while approving bulk expenses!');
        }
    }

    public function bulk_reject_verified(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $ids = $request->input('check_list');

        if (empty($ids)) {
            return redirect('/verified_expense')->with('error', 'Please select at least one expense!');
        }

        try {
            DB::connection($user_db_conn_name)->table('expenses')
                ->whereIn('id', $ids)
                ->update(['status' => 'Rejected']);
            
            foreach ($ids as $id) {
                addActivity($id, 'expenses', "Expense Rejected via Bulk Action", 2);
            }

            return redirect('/verified_expense')->with('success', 'Selected Expenses Rejected Successfully!');
        } catch (\Exception $e) {
            return redirect('/verified_expense')->with('error', 'Error while rejecting bulk expenses!');
        }
    }


//     public function verified_expense(Request $request)
// {
//     $data = array();
//     $user_db_conn_name = $request->session()->get('comp_db_conn_name');
//     $role_id = $request->session()->get('role');
//     $site_id = $request->session()->get('site_id');

//     $role_details = getRoleDetailsById($role_id);
//     $view_duration = $role_details->view_duration;
//     $visiblity_at_site = $role_details->visiblity_at_site;

//     $dates = getdurationdates($view_duration);
//     $min_date = $dates['min'];
//     $max_date = $dates['max'];

//     // Build base query with only required columns and proper indexing hints
//     $query = DB::connection($user_db_conn_name)
//         ->table('expenses')
//         ->select([
//             'expenses.id',
//             'expenses.date',
//             'expenses.amount',
//             'expenses.particular',
//             'expenses.status',
//             'expenses.image',
//             'expenses.party_type',
//             'expenses.party_id',
//             'expenses.location',
//             'expenses.remark',
//             'expenses.head_id',
//             'expense_head.name as head',
//             'sites.name as site',
//             'users.name as user'
//         ])
//         ->leftJoin('expense_party', function($join) {
//             $join->on('expenses.party_id', '=', 'expense_party.id')
//                  ->where('expenses.party_type', '=', 'expense');
//         })
//         ->leftJoin('bills_party', function($join) {
//             $join->on('expenses.party_id', '=', 'bills_party.id')
//                  ->where('expenses.party_type', '=', 'bill');
//         })
//         ->leftJoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
//         ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
//         ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
//         ->where('expenses.status', '!=', 'Pending')
//         ->whereBetween('expenses.create_datetime', [$min_date, $max_date]);

//     // Add site visibility filter if needed
//     if ($visiblity_at_site == 'current') {
//         $query->where('expenses.site_id', $site_id);
//     }

//     // Apply final sorting and get results
//     $data = $query->orderBy('expenses.create_datetime', 'desc')
//                   ->get();

//     return view('layouts.expense.verified')->with('data', json_encode($data));
// }
    public function pending_expense(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $view_duration = $request->session()->get('view_duration');
        $role_details = DB::connection($user_db_conn_name)->table('roles')->where('id', $role_id)->first();
        $visiblity_at_site = $role_details->visiblity_at_site;

        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        if ($from_date && $to_date) {
            $min_date = $from_date;
            $max_date = $to_date;
        } else {
            $dates = getdurationdates($view_duration);
            $min_date = $dates['min'];
            $max_date = $dates['max'];
        }

        $req_site_id = $request->get('site_id');
        if ($visiblity_at_site == 'current') {
            $filters = [['expenses.status', '=', 'Pending'], ['expenses.site_id', '=', $site_id]];
        } else {
            if ($req_site_id && $req_site_id != 'all') {
                $filters = [['expenses.status', '=', 'Pending'], ['expenses.site_id', '=', $req_site_id]];
            } else {
                $filters = [['expenses.status', '=', 'Pending']];
            }
        }

        $data = [];

        return  view('layouts.expense.pending')->with('data', json_encode($data));
    }

    public function get_pending_expense_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $view_duration = $request->session()->get('view_duration');
        $role_details = DB::connection($user_db_conn_name)->table('roles')->where('id', $role_id)->first();
        $visiblity_at_site = $role_details->visiblity_at_site;

        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        if ($from_date && $to_date) {
            $min_date = $from_date;
            $max_date = $to_date;
        } else {
            $dates = getdurationdates($view_duration);
            $min_date = $dates['min'];
            $max_date = $dates['max'];
        }

        $req_site_id = $request->get('site_id');
        if ($visiblity_at_site == 'current') {
            $filters = [['expenses.status', '=', 'Pending'], ['expenses.site_id', '=', $site_id]];
        } else {
            if ($req_site_id && $req_site_id != 'all') {
                $filters = [['expenses.status', '=', 'Pending'], ['expenses.site_id', '=', $req_site_id]];
            } else {
                $filters = [['expenses.status', '=', 'Pending']];
            }
        }

        $query = DB::connection($user_db_conn_name)->table('expenses')
            ->leftjoin('expense_party', 'expense_party.id', '=', 'expenses.party_id')
            ->leftjoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
            ->leftjoin('sites', 'sites.id', '=', 'expenses.site_id')
            ->leftjoin('users', 'users.id', '=', 'expenses.user_id')
            ->select('expenses.*', 'sites.name as site', 'users.name as user', 'expense_party.name as party', 'expense_head.name as head')
            ->where($filters)
            ->whereBetween('expenses.date', [date('Y-m-d', strtotime($min_date)), date('Y-m-d', strtotime($max_date))]);

        $totalRecords = $query->count();

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                // We must search on alias or base names
                $q->where('expense_head.name', 'LIKE', "%{$search}%")
                  ->orWhere('expenses.particular', 'LIKE', "%{$search}%")
                  ->orWhere('expenses.amount', 'LIKE', "%{$search}%")
                  ->orWhere('sites.name', 'LIKE', "%{$search}%")
                  ->orWhere('users.name', 'LIKE', "%{$search}%")
                  ->orWhere('expenses.location', 'LIKE', "%{$search}%")
                  ->orWhere('expenses.remark', 'LIKE', "%{$search}%");
            });
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc');
        
        // 0:Check, 1: #, 2: Party, 3: Head, 4: Particular, 5: Amount, 6: Site, 7: User, 8: Location, 9: Status, 10: Remark, 11: Date, 12: Image, 13: Action
        $columns = [
            3 => 'expense_head.name',
            4 => 'expenses.particular',
            5 => 'expenses.amount',
            6 => 'sites.name',
            7 => 'users.name',
            8 => 'expenses.location',
            10 => 'expenses.remark',
            11 => 'expenses.date'
        ];
        
        if (isset($columns[$orderColumnIndex])) {
            $query->orderBy($columns[$orderColumnIndex], $orderDir);
        } else {
            $query->orderBy('expenses.create_datetime', 'desc');
        }

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        
        if ($length != -1) {
            $query->skip($start)->take($length);
        }

        $data = $query->get();

        $formattedData = [];
        $i = $start + 1;
        
        $can_certify = checkmodulepermission(2, 'can_certify') == 1;
        $can_edit = checkmodulepermission(2, 'can_edit') == 1;

        foreach ($data as $row) {
            $ddid = $row->id;
            
            $checkbox = '<input type="checkbox" name="check_list[]" class="check_item" value="'.$ddid.'" onclick="event.stopPropagation()">';
            
            $partyName = htmlspecialchars(getExpensePartyNameByPartyType($row->party_id, $row->party_type));
            $headName = htmlspecialchars((string) $row->head);
            $particular = htmlspecialchars((string) $row->particular);
            $amount = htmlspecialchars((string) $row->amount);
            $site = htmlspecialchars((string) $row->site);
            $user = htmlspecialchars((string) $row->user);
            $location = htmlspecialchars((string) $row->location);
            $status = htmlspecialchars((string) $row->status);
            $remark = htmlspecialchars((string) $row->remark);
            $date = htmlspecialchars((string) $row->date);
            
            $imageLink = $row->image;
            $image = '<img class="lazy" data-src="'.$imageLink.'" src="'.$imageLink.'" onclick="enlargeImage(\''.$imageLink.'\')" height="50px" width="50px" />';
            
            $actionHtml = '';
            if (is_asset_head($row->head_id) || is_machinery_head($row->head_id)) {
                if (is_asset_head($row->head_id)) {
                    if (!empty($row->asset_head)) {
                        $assetVal = getAssetHeadsById($row->asset_head);
                        $assetName = $assetVal ? $assetVal->name : '';
                        $actionHtml .= 'Asset Category - ' . htmlspecialchars((string)$assetName) . '<br>';
                    }
                    if ($can_certify) {
                        $actionHtml .= '<button type="button" onclick="openassignassetheadmodel(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-wrench"></i></button>';
                    }
                } elseif (is_machinery_head($row->head_id)) {
                    if (!empty($row->machinery_head)) {
                        $machineVal = getMachineryHeadsById($row->machinery_head);
                        $machineName = $machineVal ? $machineVal->name : '';
                        $actionHtml .= 'Machinery Category - ' . htmlspecialchars((string)$machineName) . '<br>';
                    }
                    if ($can_certify) {
                        $actionHtml .= '<button type="button" onclick="openassignmachineryheadmodel(\''.$ddid.'\')" style="all:unset"><img src="'.asset('/images/gears.png').'" style="width:20px" /></button>';
                    }
                }
            }
            $actionHtml .= '&nbsp;';
            if ($can_edit) {
                $actionHtml .= '<button title="Edit" type="button" onclick="editexpense(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-edit"></i></button>';
            }

            $formattedData[] = [
                $checkbox,
                $i++,
                $partyName,
                $headName,
                $particular,
                $amount,
                $site,
                $user,
                $location,
                $status,
                $remark,
                $date,
                $image,
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
    public function new_expense(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['expense_head'] = DB::connection($user_db_conn_name)->table('expense_head')->get();
        $data['expense_party'] = DB::connection($user_db_conn_name)->table('expense_party')->where('status', '=', 'Active')->get();
        $data['bill_party'] = DB::connection($user_db_conn_name)->table('bills_party')->where('status', '=', 'Active')->get();
        $data['sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        return  view('layouts.expense.new')->with('data', json_encode($data));
    }
    public function edit_expense(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['expense'] = DB::connection($user_db_conn_name)->table('expenses')->where('id', $request->get('id'))->get()[0];
        $data['expense_head'] = DB::connection($user_db_conn_name)->table('expense_head')->get();
        $data['expense_party'] = DB::connection($user_db_conn_name)->table('expense_party')->where('status', '=', 'Active')->get();
        $data['bill_party'] = DB::connection($user_db_conn_name)->table('bills_party')->where('status', '=', 'Active')->get();
        $data['sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();

        $site_id = session()->get("site_id");
        $role_details = getRoleDetailsById(session()->get('role'));
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        if ($entry_at_site == "current" && $site_id != $data['expense']->site_id) {
            return redirect('/pending_expense')
                ->with('error', "You don't have permission to edit entries at site - " . getSiteDetailsById($data['expense']->site_id)->name . "!");
        }
        if ($data['expense']->date < $min_date) {
            return redirect('/pending_expense')
                ->with('error', "You don't have permission to edit entries before " . $min_date . " !");
        }

        return  view('layouts.expense.edit')->with('data', json_encode($data));
    }
    public function bulk_edit_expense(Request $request)
    {
        $ids = $request->input('check_list');
        if (empty($ids)) {
            return redirect('/pending_expense')->with('error', 'Please select at least one expense to edit!');
        }

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = array();
        $data['expenses'] = DB::connection($user_db_conn_name)->table('expenses')->whereIn('id', $ids)->get();
        $data['expense_head'] = DB::connection($user_db_conn_name)->table('expense_head')->get();
        $data['expense_party'] = DB::connection($user_db_conn_name)->table('expense_party')->where('status', '=', 'Active')->get();
        $data['bill_party'] = DB::connection($user_db_conn_name)->table('bills_party')->where('status', '=', 'Active')->get();
        $data['sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();

        return view('layouts.expense.bulk_edit')->with('data', json_encode($data));
    }

    public function updateBulkExpenses(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $add_duration = session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = $duration['max'];

        $ids = $request->input('id');
        $site_ids = $request->input('site_id');
        $party_ids = $request->input('party_id');
        $head_ids = $request->input('head_id');
        $particulars = $request->input('particular');
        $amounts = $request->input('amount');
        $remarks = $request->input('remark');
        $dates = $request->input('date');

        try {
            foreach ($ids as $key => $id) {
                if ($dates[$key] < $min_date || $dates[$key] > $max_date) {
                    return redirect()->back()->with('error', "You don't have permission to update entry for date: " . $dates[$key]);
                }
                $head_id = $head_ids[$key];
                $current_status = $status;
                if (is_machinery_head($head_id) || is_asset_head($head_id)) {
                    $current_status = 'Pending';
                }

                $party = explode("||", $party_ids[$key]);

                $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', $id)->first();
                $imagePath = $expense->image;

                $imageKey = 'image_' . $key;
                if ($request->hasFile($imageKey)) {
                    if (File::exists($expense->image) && $expense->image != 'images/expense.png') {
                        File::delete($expense->image);
                    }
                    $file = $request->file($imageKey);
                    $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                    $file->move(public_path('images/app_images/' . $user_db_conn_name . '/expense'), $imageName);
                    $imagePath = "images/app_images/" . $user_db_conn_name . "/expense/" . $imageName;
                }

                $updateData = [
                    'site_id' => $site_ids[$key],
                    'user_id' => $user_id,
                    'party_id' => $party[0],
                    'party_type' => $party[1],
                    'head_id' => $head_id,
                    'particular' => $particulars[$key],
                    'amount' => $amounts[$key],
                    'remark' => $remarks[$key],
                    'image' => $imagePath,
                    'status' => $current_status,
                    'date' => $dates[$key],
                ];

                DB::connection($user_db_conn_name)->table('expenses')->where('id', $id)->update($updateData);
                addActivity($id, 'expenses', "Expense Data Updated via Bulk Edit", 2);

                if ($current_status == 'Approved') {
                    if ($party[1] == 'bill') {
                        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $party[0])->get()[0];
                    } else {
                        $party_status = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $party[0])->get()[0];
                    }
                    if ($party_status->status == 'Active') {
                        $this->approve_expense($id, $user_db_conn_name);
                    }
                }
            }
            return redirect('/pending_expense')->with('success', 'Expenses Updated successfully!');
        } catch (\Exception $e) {
            return redirect('/pending_expense')->with('error', 'Error While Updating Expenses: ' . $e->getMessage());
        }
    }

    public function addnewExpenses(Request $request)
    {
        // print_r($request);

        $res = false;
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = $request->input();
        // print_r($data);
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $add_duration = session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = $duration['max'];

        $status = getInitialEntryStatusByRole($role_id);
        if (is_machinery_head($data['head_id']) || is_asset_head($data['head_id'])) {
            $status = 'Pending';
        }
        $length = count($data['site_id']);
        for ($i = 0; $i < $length; $i++) {
            if ($data['date'][$i] < $min_date || $data['date'][$i] > $max_date) {
                return redirect()->back()->with('error', "You don't have permission to add entry for date: " . $data['date'][$i]);
            }
            if (isset($request->image[$i])) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image[$i]->extension();
                $request->image[$i]->move(public_path('images/app_images/'.$user_db_conn_name.'/expense'), $imageName);
                $imagePath = "images/app_images/".$user_db_conn_name."/expense/" . $imageName;
            } else {
                $imagePath = "images/expense.png";
            }
            $party = explode("||", $data['party_id'][$i]);

            $rawd = [
                'site_id' => $data['site_id'][$i],
                'user_id' => $user_id,
                'party_id' => $party[0],
                'party_type' => $party[1],
                'head_id' => $data['head_id'][$i],
                'particular' => $data['particular'][$i],
                'amount' => $data['amount'][$i],
                'remark' => $data['remark'][$i],
                'image' => $imagePath,
                'status' => $status,
                'date' => $data['date'][$i],
            ];
            try {
                $id = DB::connection($user_db_conn_name)->table('expenses')->insertGetId($rawd);
                addActivity($id,'expenses',"New Expense Created",2);
                if ($status == 'Approved') {
                    if ($party[1] == 'bill') {
                        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $party[0])->get()[0];
                    } else {
                        $party_status = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $party[0])->get()[0];
                    }
                    if ($party_status->status == 'Active') {
                        $this->approve_expense($id, $user_db_conn_name);
                    }
                }
                $res = true;
            } catch (\Exception $e) {
                $res = false;
            }
        }
        if ($res) {
            return redirect('/verified_expense')
                ->with('success', 'Expenses Created successfully!');
        } else {
            return redirect('/verified_expense')
                ->with('error', $e . 'Error While Creating Expense. Please Try Again After Reconciling The Statement.!');
        }
    }
    public function updateExpenses(Request $request)
    {
        $ids = $request->input('check_list');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        if ($ids != null) {
            if ($request->input('approve_expense') !== null) {
                foreach ($ids as $id) {

                    $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->get()[0];                  
                    if ($expense->party_type == 'bill') {
                        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $expense->party_id)->get()[0];
                    } else {
                        $party_status = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $expense->party_id)->get()[0];
                    }
                    if ($party_status->status == 'Active') {
                        $this->approve_expense($id, $user_db_conn_name);
                    } else {
                        return redirect('/pending_expense')
                            ->with('error', $party_status->name . ' Party Is Not Active!');
                    }
                }
                return redirect('/pending_expense')
                    ->with('success', 'Expense Approved successfully!');
            } else if ($request->input('reject_expense') !== null) {
                foreach ($ids as $id) {
                    $this->reject_expense($id, $user_db_conn_name);
                }
                return redirect('/pending_expense')
                    ->with('success', 'Expense Rejected successfully!');
            }
        } else {
            return redirect('/pending_expense')
                ->with('error', 'Please Choose Atleast One Expense!');
        }
    }
    public function  updateexpenseAssetHead(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $asset_head = $request->get('asset_head');
        $id = $request->get('asset_head_expense_id');
        try {
            DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->update(['asset_head' => $asset_head]);
            addActivity($id,'expenses',"Asset Allocate To Expense",2);
        } catch (\Exception $e) {
            return redirect('/pending_expense')
                ->with('error', 'Error While Assigning Expense Asset Head!');
        }
        return redirect('/pending_expense')
            ->with('success', 'Assigning Expense Asset Head Succeed!');
    }
    public function  updateexpenseMachineryHead(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $machinery_head = $request->get('machinery_head');
        $id = $request->get('machinery_head_expense_id');
        try {
            DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->update(['machinery_head' => $machinery_head]);
            addActivity($id,'expenses',"Machinery Allocate To Expense",2);
        } catch (\Exception $e) {
            return redirect('/pending_expense')
                ->with('error', 'Error While Assigning Expense Machinery Head!');
        }
        return redirect('/pending_expense')
            ->with('success', 'Assigning Expense Machinery Head Succeed!');
    }

    public function updateEditExpenses(Request $request)
    {

        $data = $request->input();
        $user_id = session()->get('uid');
        $status = getInitialEntryStatusByRole($role_id);
        $add_duration = session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = $duration['max'];

        if ($data['date'] < $min_date || $data['date'] > $max_date) {
            return redirect()->back()->with('error', "You don't have permission to update entry for date: " . $data['date']);
        }

        if (is_machinery_head($data['head_id']) || is_asset_head($data['head_id'])) {
            $status = 'Pending';
        }
        $id = $data['id'];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', $id)->get()[0];

        if (isset($request->image)) {

            if(File::exists($expense->image)  && $expense->image != 'images/expense.png') {
                File::delete($expense->image);
            }

            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/'.$user_db_conn_name.'/expense'), $imageName);
            $imagePath = "images/app_images/".$user_db_conn_name."/expense/" . $imageName;
        } else {
            $imagePath = $expense->image;
        }
        $party = explode("||", $data['party_id']);

        $rawd = [
            'id' => $id,
            'site_id' => $data['site_id'],
            'user_id' => $user_id,
            'party_id' => $party[0],
            'party_type' => $party[1],
            'head_id' => $data['head_id'],
            'particular' => $data['particular'],
            'amount' => $data['amount'],
            'remark' => $data['remark'],
            'image' => $imagePath,
            'status' => $status,
            'date' => $data['date'],
        ];


        try {
            DB::connection($user_db_conn_name)->table('expenses')->upsert($rawd, 'id');
            addActivity($id,'expenses',"Expense Data Updated",2);
            if ($status == 'Approved') {
                if ($party[1] == 'bill') {
                    $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $party[0])->get()[0];
                } else {
                    $party_status = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $party[0])->get()[0];
                }
                if ($party_status->status == 'Active') {
                    $this->approve_expense($id, $user_db_conn_name);
                }
                return redirect('/verified_expense')
                    ->with('success', 'Expense Updated successfully!');
            }
            return redirect('/pending_expense')
                ->with('success', 'Expense Updated successfully!');
        } catch (\Exception $e) {
            return redirect('/pending_expense')
                ->with('error', $e . 'Error While Updating Expense. Please Try Again After Reconciling The Statement.!');
        }
    }
    public function approve_expense_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->get()[0];
        if ($expense->party_type == 'bill') {
            $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $expense->party_id)->get()[0];
        } else {
            $party_status = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $expense->party_id)->get()[0];
        }
        if ($party_status->status == 'Active') {

            $this->approve_expense($id, $user_db_conn_name);
            return redirect('/verified_expense')
                ->with('success', 'Expense Approved successfully!');
        } else {
            return redirect('/verified_expense')
                ->with('error', 'Party Is Not Active!');
        }
    }
    public function reject_expense_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $this->reject_expense($id, $user_db_conn_name);
        return redirect('/verified_expense')
            ->with('success', 'Expense Rejected successfully!');
    }
    public function approve_expense($id, $user_db_conn_name)
    {

        $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->get()[0];
        DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->update(['status' => 'Approved']);
        sendAlertNotification($expense->user_id,'Your expense of amount '.$expense->amount.' has been approved. Check Application For More Information.','Expense Approved');
        addActivity($id,'expenses',"Expense Approved",2);
        $site_trans = [
            'site_id' => $expense->site_id,
            'type' => 'Debit',
            'expense_id' => $id,
            'create_datetime' => $expense->create_datetime
        ];
        DB::connection($user_db_conn_name)->table('sites_transaction')->where('expense_id', $id)->delete();
        DB::connection($user_db_conn_name)->table('sites_transaction')->insert($site_trans);

        if ($expense->party_type == 'bill') {
            $site_trans = [
                'party_id' => $expense->party_id,
                'type' => 'Credit',
                'particular' => $expense->particular,
                'expense_id' => $id,
                'create_datetime' => $expense->create_datetime
            ];
            DB::connection($user_db_conn_name)->table('bill_party_statement')->where('expense_id', $id)->delete();
            DB::connection($user_db_conn_name)->table('bill_party_statement')->insert($site_trans);
        }
        $head_id = $expense->head_id;

        if (is_asset_head($head_id)) {
            $asset = [
                'cost_price' => $expense->amount,
                'name' => $expense->particular,
                'head_id' => $expense->asset_head,
                'expense_id' => $id,
                'site_id' => $expense->site_id,
                'create_datetime' => $expense->create_datetime
            ];
            $asset_id =  DB::connection($user_db_conn_name)->table('assets')->insertGetId($asset);
            addActivity($asset_id,'assets',"New Assets Created Via Expense",5);
            $asset_trans = [
                'asset_id' => $asset_id,
                'to_site' => $expense->site_id,
                'transaction_type' => 'Purchase',
                'remark' => $expense->remark
            ];
            DB::connection($user_db_conn_name)->table('asset_transaction')->insert($asset_trans);
        }
        if (is_machinery_head($head_id)) {
            $machine = [
                'name' => $expense->particular,
                'head_id' => $expense->machinery_head,
                'expense_id' => $id,
                'site_id' => $expense->site_id,
                'cost_price' => $expense->amount,
                'create_datetime' => $expense->create_datetime
            ];
            $machinery_id =  DB::connection($user_db_conn_name)->table('machinery_details')->insertGetId($machine);
            addActivity($machinery_id,'machinery_details',"New Machinery Created Via Expense",6);
            $machinery_trans = [
                'machinery_id' => $machinery_id,
                'to_site' => $expense->site_id,
                'transaction_type' => 'Purchase',
                'remark' => $expense->remark
            ];
            DB::connection($user_db_conn_name)->table('machinery_transaction')->insert($machinery_trans);
        }
    }


    public function reject_expense($id, $user_db_conn_name)
    {
        $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->get()[0];
        DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->update(['status' => 'Rejected']);
        sendAlertNotification($expense->user_id,'Your expense of amount '.$expense->amount.' has been rejected. Check Application For More Information.','Expense Rejected');

        DB::connection($user_db_conn_name)->table('sites_transaction')->where('expense_id', '=', $id)->delete();
        addActivity($id,'expenses',"Expense Rejected",2);

        if ($expense->party_type == 'bill') {
            DB::connection($user_db_conn_name)->table('bill_party_statement')->where('expense_id', '=', $id)->delete();
        }
        $head_id = $expense->head_id;

        if (is_asset_head($head_id, $user_db_conn_name)) {
            $asset_id = DB::connection($user_db_conn_name)->table('assets')->select('id')->where('expense_id', '=', $id)->get()[0]->id;
            DB::connection($user_db_conn_name)->table('assets')->where('expense_id', '=', $id)->delete();
            DB::connection($user_db_conn_name)->table('asset_transaction')->where('asset_id', '=', $asset_id)->delete();
        }
        if (is_machinery_head($head_id, $user_db_conn_name)) {
            $machinery_id = DB::connection($user_db_conn_name)->table('machinery_details')->select('id')->where('expense_id', '=', $id)->get()[0]->id;
            DB::connection($user_db_conn_name)->table('machinery_details')->where('expense_id', '=', $id)->delete();
            DB::connection($user_db_conn_name)->table('machinery_transaction')->where('machinery_id', '=', $machinery_id)->delete();
        }
    }



    // require


        public function expense_reports(Request $request)
    {        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $expense_party = DB::connection($user_db_conn_name)->table('expense_party')->get();
        $bill_party = DB::connection($user_db_conn_name)->table('bills_party')->get();
        return view('layouts.expense.expense_reports',compact(['bill_party','expense_party']));
    }
    public function expensereports(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $type = $request->get('Report_Type');
        $report_code = $request->get('type');
        if($report_code == 3 || $report_code==4){
            $partydetail = explode('||',$request->get('party_id'));
            $partyname = $partydetail[0];
            $partytype = $partydetail[1];   
        }
        

        $start_date = $request->get('start_date');
        $sitename = $request->get('site_id');
         $headname = $request->get('head_id');
        $end_date = $request->get('end_date');
        addActivity(0,'expenses',"Expense Report Generated Of Data (".$start_date." - ".$end_date.")",2);
        if ($report_code == 1) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code);
            } else {
                $file_name = "Expense Date Report (" . $start_date . " To " . $end_date . ").pdf"; 
                    $expenses = DB::connection($user_db_conn_name)
                    ->table('expenses')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'bills_party.id')
                            ->where('expenses.party_type', '=', 'bill');
                    })
                    ->leftJoin('expense_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'expense_party.id')
                            ->where('expenses.party_type', '=', 'expense');
                    })
                    ->leftjoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                    ->leftjoin('sites', 'sites.id', '=', 'expenses.site_id')
                    ->leftjoin('users', 'users.id', '=', 'expenses.user_id')
                    ->selectRaw('expenses.*, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name, sites.name as site_name, users.name as user_name,expense_head.name as head_name')
                    ->whereBetween('expenses.create_datetime', [$start_date, $end_date])
                    ->orderBy('expenses.create_datetime', 'desc')->get();            
                $pdf = Pdf::loadView('layouts.expense.pdfs.accToDate', compact('expenses','start_date','end_date'));
                return $pdf->download($file_name);
            }
        } elseif ($report_code == 2) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,$sitename,"",  "","");
            } else {
                $file_name = "Expense Site Report (" . $start_date . " To " . $end_date . ").pdf"; 
                    $expenses =  DB::connection($user_db_conn_name)
                    ->table('expenses')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'bills_party.id')
                            ->where('expenses.party_type', '=', 'bill');
                    })
                    ->leftJoin('expense_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'expense_party.id')
                            ->where('expenses.party_type', '=', 'expense');
                    })
                    ->leftjoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                    ->leftjoin('sites', 'sites.id', '=', 'expenses.site_id')
                    ->leftjoin('users', 'users.id', '=', 'expenses.user_id')
                    ->selectRaw(
                        'expenses.*, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name, sites.name as site_name, users.name as user_name,expense_head.name as head_name'
                    )
                    ->where([['expenses.site_id', '=', $sitename]])
                    ->whereBetween('expenses.create_datetime', [$start_date, $end_date])
                    ->orderBy('expenses.create_datetime', 'desc')->get();
                    $start_date = $start_date;
                    $end_date = $end_date;
                    $sitename = getSiteDetailsById($sitename)->name;        
                $pdf = Pdf::loadView('layouts.expense.pdfs.accToSite', compact('expenses','start_date','end_date','sitename'));
                return $pdf->download($file_name);
            }
        } elseif ($report_code == 3) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $partyname, "",$partytype);
            } else {
                $file_name = "Expense Party Report (" . $start_date . " To " . $end_date . ").pdf"; 
                $expenses = DB::connection($user_db_conn_name)
                ->table('expenses')
                ->leftJoin('bills_party', function ($join) {
                    $join->on('expenses.party_id', '=', 'bills_party.id')
                        ->where('expenses.party_type', '=', 'bill');
                })
                ->leftJoin('expense_party', function ($join) {
                    $join->on('expenses.party_id', '=', 'expense_party.id')
                        ->where('expenses.party_type', '=', 'expense');
                })
                ->leftjoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftjoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftjoin('users', 'users.id', '=', 'expenses.user_id')
                ->selectRaw(
                    'expenses.*, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name, sites.name as site_name, users.name as user_name,expense_head.name as head_name'
                )
                ->whereBetween('expenses.create_datetime', [$start_date, $end_date])
                ->where('expenses.party_id', '=', $partyname)
                ->where('expenses.party_type', '=', $partytype)
                ->orderBy('expenses.create_datetime', 'desc')->get();
     
                $partyname = $partytype == 'expense' ? DB::connection($user_db_conn_name)->table('expense_party')->where('id',$partyname)->get()[0]->name : DB::connection($user_db_conn_name)->table('bills_party')->where('id',$partyname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.expense.pdfs.accToParty', compact('expenses','start_date','end_date','partyname'));
            return $pdf->download($file_name);
            }
        } elseif ($report_code == 4) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,$sitename,$partyname,"",$partytype);
            } else {
                $file_name = "Expense Party Report At Particular Site (" . $start_date . " To " . $end_date . ").pdf"; 
                    $expenses = DB::connection($user_db_conn_name)
                    ->table('expenses')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'bills_party.id')
                            ->where('expenses.party_type', '=', 'bill');
                    })
                    ->leftJoin('expense_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'expense_party.id')
                            ->where('expenses.party_type', '=', 'expense');
                    })
                    ->leftjoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                    ->leftjoin('sites', 'sites.id', '=', 'expenses.site_id')
                    ->leftjoin('users', 'users.id', '=', 'expenses.user_id')
                    ->selectRaw(
                        'expenses.*, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name, sites.name as site_name, users.name as user_name,expense_head.name as head_name'
                    )

                    ->whereBetween('expenses.create_datetime', [$start_date, $end_date])
                    ->where('expenses.party_id', '=', $partyname)->where('expenses.party_type', '=', $partytype)->where('expenses.site_id', '=', $sitename)
                    ->orderBy('expenses.create_datetime', 'desc')->get();

                    $partyname = $partytype == 'expense' ? DB::connection($user_db_conn_name)->table('expense_party')->where('id',$partyname)->get()[0]->name : DB::connection($user_db_conn_name)->table('bills_party')->where('id',$partyname)->get()[0]->name;               

                $sitename = getSiteDetailsById($sitename)->name;       
                $pdf = Pdf::loadView('layouts.expense.pdfs.accToPartyAtSite', compact('expenses','start_date','end_date','partyname','sitename'));
                return $pdf->download($file_name);
            }
        } elseif ($report_code == 5) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code , "","", $headname,"");
            } else {
                $file_name = "Expense Head Report (" . $start_date . " To " . $end_date . ").pdf"; 
                $expenses = DB::connection($user_db_conn_name)
                ->table('expenses')
                ->leftJoin('bills_party', function ($join) {
                    $join->on('expenses.party_id', '=', 'bills_party.id')
                        ->where('expenses.party_type', '=', 'bill');
                })
                ->leftJoin('expense_party', function ($join) {
                    $join->on('expenses.party_id', '=', 'expense_party.id')
                        ->where('expenses.party_type', '=', 'expense');
                })
                ->leftjoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftjoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftjoin('users', 'users.id', '=', 'expenses.user_id')
                ->selectRaw(
                    'expenses.*, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name, sites.name as site_name, users.name as user_name,expense_head.name as head_name'
                )

                ->whereBetween('expenses.create_datetime', [$start_date, $end_date])
                ->where('expenses.head_id', '=', $headname)
                ->orderBy('expenses.create_datetime', 'desc')->get();
          
            $headname = DB::connection($user_db_conn_name)->table('expense_head')->where('id',$headname)->get()[0]->name;
            $pdf = Pdf::loadView('layouts.expense.pdfs.accToHead', compact('expenses','start_date','end_date','headname'));
            return $pdf->download($file_name);
            }
        } else {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename, "", $headname,"");
            } else {
                $file_name = "Expense Head Report At Particular Site (" . $start_date . " To " . $end_date . ").pdf"; 
                $expenses = DB::connection($user_db_conn_name)
                ->table('expenses')
                ->leftJoin('bills_party', function ($join) {
                    $join->on('expenses.party_id', '=', 'bills_party.id')
                        ->where('expenses.party_type', '=', 'bill');
                })
                ->leftJoin('expense_party', function ($join) {
                    $join->on('expenses.party_id', '=', 'expense_party.id')
                        ->where('expenses.party_type', '=', 'expense');
                })
                ->leftjoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftjoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftjoin('users', 'users.id', '=', 'expenses.user_id')
                ->selectRaw(
                    'expenses.*, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name, sites.name as site_name, users.name as user_name,expense_head.name as head_name'
                )
                ->whereBetween('expenses.create_datetime', [$start_date, $end_date])
                ->where('expenses.head_id', '=', $headname)->where('expenses.site_id', '=', $sitename)
                ->orderBy('expenses.create_datetime', 'desc')->get();
            $headname = DB::connection($user_db_conn_name)->table('expense_head')->where('id',$headname)->get()[0]->name;
            $sitename = getSiteDetailsById($sitename)->name;           
            $pdf = Pdf::loadView('layouts.expense.pdfs.accToHeadAtSite', compact('expenses','start_date','end_date','headname','sitename'));
            return $pdf->download($file_name);
            }
        }
    }

    public function exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename = null, $partyname = null, $headname = null,$partytype=null)
    {
        $file_name = "Expense ";

        if($report_code == 1){
            $file_name .= "Date Report";
        }
        else if($report_code == 2){
            $file_name .= "Site Report " ;
        }
        else if($report_code == 3){

            $file_name .= "Party Report " ;
        }
        else if($report_code == 4){
            $file_name .= "Party Report At Particular Site " ;
        }
        else if($report_code == 5){
            $file_name .= "Head Report " ;
        }
        else if($report_code == 6){

            $file_name .= "Head Report At Particular Site " ;
        }

        $file_name .= "(". $start_date . " TO " . $end_date . ").xlsx";
        // $filename = str_replace(['(', ')', '&',' ','/','\''], ['_', '_', 'and' , '_','_','_'], $file_name);
        return Excel::download(new ExpenseExport($user_db_conn_name, $start_date, $end_date, $report_code, $sitename,$partyname, $headname,$partytype ), $file_name);
        
    }
}
