<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ApiMaterialController extends Controller
{
    /**
     * Get Material Summary (Suppliers, Materials, Units, Sites) for Dropdowns
     */
    public function summary(Request $request)
    {
        try {
            $conn = config('database.default');
            $data = [
                'suppliers' => DB::table('material_supplier')->select('id', 'name')->get(),
                'materials' => DB::table('materials')->get(),
                'units' => DB::table('units')->get(),
                'sites' => DB::table('sites')->where('status', 'Active')->get(),
            ];

            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Material Entries
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $site_id = $request->get('site_id', $user->site_id);
            $search = $request->get('search');
            $status = $request->get('status');
            $per_page = $request->get('per_page', 20);
            
            $role_id = $user->role_id;
            $role_details = DB::table('roles')->where('id', $role_id)->first();
            $visiblity_at_site = $role_details ? $role_details->visiblity_at_site : 'all';
            
            $view_duration = getUserViewDuration($user);
            $dates = getdurationdates($view_duration);
            $min_date = $dates['min'];
            $max_date = $dates['max'];
            
            $query = DB::table('material_entry')
                ->leftJoin('materials', 'materials.id', '=', 'material_entry.material_id')
                ->leftJoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                ->leftJoin('units', 'units.id', '=', 'material_entry.unit')
                ->leftJoin('sites', 'sites.id', '=', 'material_entry.site_id')
                ->leftJoin('users', 'users.id', '=', 'material_entry.user_id')
                ->select('material_entry.*', 'sites.name as site_name', 'users.name as user_name', 'materials.name as material_name', 'material_supplier.name as supplier_name', 'units.name as unit_name')
                ->whereBetween('material_entry.create_datetime', [$min_date, $max_date])
                ->orderBy('material_entry.create_datetime', 'desc');

            if ($visiblity_at_site == 'current' && $site_id && $site_id != 'all') {
                $query->where('material_entry.site_id', $site_id);
            }

            if ($status) {
                if (strpos($status, ',') !== false) {
                    $query->whereIn('material_entry.status', explode(',', $status));
                } else {
                    $query->where('material_entry.status', $status);
                }
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('material_entry.remark', 'like', "%$search%")
                      ->orWhere('material_entry.return_comment', 'like', "%$search%")
                      ->orWhere('materials.name', 'like', "%$search%")
                      ->orWhere('material_supplier.name', 'like', "%$search%")
                      ->orWhere('material_entry.vehical', 'like', "%$search%")
                      ->orWhere('sites.name', 'like', "%$search%");
                });
            }

            $entries = $query->paginate($per_page);

            return response()->json(['status' => 'Ok', 'data' => $entries]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store Material Entry
     */
    public function store(Request $request)
    {
        $request->validate([
            'site_id' => 'required',
            'supplier' => 'required',
            'material_id' => 'required',
            'qty' => 'required|numeric',
            'unit' => 'required',
            'date' => 'required|date'
        ]);

        try {
            $conn = config('database.default');
            $user = $request->user();

            $add_duration = getUserAddDuration($user);
            $duration = getdurationdates($add_duration);
            $min_date = $duration['min'];
            $max_date = substr($duration['today'], 0, 10);
            if (strtotime($request->date) < strtotime($min_date) || strtotime($request->date) > strtotime($max_date)) {
                return response()->json(['status' => 'Failed', 'message' => "You don't have permission to add entry for date: " . $request->date], 403);
            }
            
            $imagePath = "images/expense.png";
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                $file->move(public_path('images/app_images/' . $conn . '/material'), $imageName);
                $imagePath = "images/app_images/" . $conn . "/material/" . $imageName;
            }

            $status = getAppInitialEntryStatusByRole($user->role_id, $conn);

            $data = [
                'supplier' => $request->supplier,
                'material_id' => $request->material_id,
                'unit' => $request->unit,
                'qty' => $request->qty,
                'converted_qty' => $request->converted_qty,
                'vehical' => $request->vehical,
                'image' => $imagePath,
                'status' => $status,
                'remark' => $request->remark,
                'location' => $request->location,
                'site_id' => $request->site_id,
                'user_id' => $user->id,
                'date' => $request->date,
                'create_datetime' => Carbon::now()
            ];

            $id = DB::table('material_entry')->insertGetId($data);
            addActivity($id, 'material_entry', "New Material Entry Created via API", 3, $user->id, $conn);

            return response()->json([
                'status' => 'Ok', 
                'message' => 'Material entry created successfully', 
                'id' => $id, 
                'image' => $imagePath
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Material Entry
     */
    public function update(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $entry = DB::table('material_entry')->where('id', $id)->first();

            if (!$entry) {
                return response()->json(['status' => 'Failed', 'message' => 'Material entry not found'], 404);
            }

            if ($request->has('date')) {
                $add_duration = getUserAddDuration($user);
                $duration = getdurationdates($add_duration);
                $min_date = $duration['min'];
                $max_date = substr($duration['today'], 0, 10);
                if (strtotime($request->date) < strtotime($min_date) || strtotime($request->date) > strtotime($max_date)) {
                    return response()->json(['status' => 'Failed', 'message' => "You don't have permission to edit entry for date: " . $request->date], 403);
                }
            }

            $updateData = $request->only(['site_id', 'supplier', 'material_id', 'unit', 'qty', 'converted_qty', 'vehical', 'remark', 'location', 'date']);
            
            if ($request->hasFile('image')) {
                if (File::exists(public_path($entry->image)) && $entry->image != 'images/expense.png') {
                    File::delete(public_path($entry->image));
                }
                $file = $request->file('image');
                $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                $file->move(public_path('images/app_images/' . $conn . '/material'), $imageName);
                $updateData['image'] = "images/app_images/" . $conn . "/material/" . $imageName;
            }

            DB::table('material_entry')->where('id', $id)->update($updateData);
            addActivity($id, 'material_entry', "Material Entry Updated via API", 3, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Material entry updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Material Entry
     */
    public function destroy(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $entry = DB::table('material_entry')->where('id', $id)->first();

            if (!$entry) {
                return response()->json(['status' => 'Failed', 'message' => 'Material entry not found'], 404);
            }

            if ($entry->status == 'Approved') {
                return response()->json(['status' => 'Failed', 'message' => 'Cannot delete an approved material entry.'], 403);
            }

            if (File::exists(public_path($entry->image)) && $entry->image != 'images/expense.png') {
                File::delete(public_path($entry->image));
            }

            DB::table('material_entry')->where('id', $id)->delete();
            addActivity($id, 'material_entry', "Material Entry Deleted via API", 3, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Material entry deleted successfully']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Return Material Entry (Move to Returned status with return comment)
     */
    public function returnMaterial(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = isset($input['ids']) ? (array)$input['ids'] : (isset($input['id']) ? [$input['id']] : (array)($input['check_list'] ?? []));
            $return_comment = $input['return_comment'] ?? ($input['comment'] ?? ($input['remark'] ?? 'Returned for corrections'));

            if (empty($ids)) {
                return response()->json(['status' => 'Failed', 'message' => 'No material entry IDs provided'], 400);
            }

            if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('material_entry', 'return_comment')) {
                try {
                    \Illuminate\Support\Facades\Schema::connection($conn)->table('material_entry', function ($table) {
                        $table->text('return_comment')->nullable();
                    });
                } catch (\Exception $e) {
                    \Log::error("Failed adding return_comment column: " . $e->getMessage());
                }
            }

            $count = 0;
            foreach ($ids as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;

                $entry = DB::table('material_entry')->where('id', $singleId)->first();
                if (!$entry) continue;

                DB::table('material_entry')->where('id', $singleId)->update([
                    'status' => 'Returned',
                    'return_comment' => $return_comment
                ]);

                addActivity($singleId, 'material_entry', "Material Entry Returned via API with comment: " . $return_comment, 3, $user->id, $conn);
                
                if (function_exists('sendAlertNotification') && !empty($entry->user_id)) {
                    try {
                        sendAlertNotification($entry->user_id, 'Your material entry has been returned. Comment: ' . $return_comment, 'Material Returned');
                    } catch (\Exception $e) {
                        \Log::error("Failed to send notification: " . $e->getMessage());
                    }
                }

                $count++;
            }

            return response()->json(['status' => 'Ok', 'message' => "$count material entries returned successfully"]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Resubmit Returned Material Entry (Move back to Pending)
     */
    public function resubmitMaterial(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = isset($input['ids']) ? (array)$input['ids'] : (isset($input['id']) ? [$input['id']] : (array)($input['check_list'] ?? []));

            if (empty($ids)) {
                return response()->json(['status' => 'Failed', 'message' => 'No material entry IDs provided'], 400);
            }

            $count = 0;
            foreach ($ids as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;

                $entry = DB::table('material_entry')->where('id', $singleId)->first();
                if (!$entry) continue;

                DB::table('material_entry')->where('id', $singleId)->update([
                    'status' => 'Pending'
                ]);

                addActivity($singleId, 'material_entry', "Material Entry Resubmitted to Pending via API", 3, $user->id, $conn);
                $count++;
            }

            return response()->json(['status' => 'Ok', 'message' => "$count material entries resubmitted to Pending successfully"]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
