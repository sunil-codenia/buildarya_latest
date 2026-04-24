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
            
            $role_id = $user->role_id;
            $role_details = DB::table('roles')->where('id', $role_id)->first();
            $visiblity_at_site = $role_details->visiblity_at_site;
            
            $query = DB::table('material_entry')
                ->leftJoin('materials', 'materials.id', '=', 'material_entry.material_id')
                ->leftJoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                ->leftJoin('units', 'units.id', '=', 'material_entry.unit')
                ->leftJoin('sites', 'sites.id', '=', 'material_entry.site_id')
                ->leftJoin('users', 'users.id', '=', 'material_entry.user_id')
                ->select('material_entry.*', 'sites.name as site_name', 'users.name as user_name', 'materials.name as material_name', 'material_supplier.name as supplier_name', 'units.name as unit_name')
                ->orderBy('material_entry.create_datetime', 'desc');

            if ($visiblity_at_site == 'current' && $site_id && $site_id != 'all') {
                $query->where('material_entry.site_id', $site_id);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('material_entry.remark', 'like', "%$search%")
                      ->orWhere('materials.name', 'like', "%$search%")
                      ->orWhere('material_supplier.name', 'like', "%$search%");
                });
            }

            $entries = $query->paginate(20);

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

            $updateData = $request->only(['site_id', 'supplier', 'material_id', 'unit', 'qty', 'vehical', 'remark', 'location', 'date']);
            
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
}
