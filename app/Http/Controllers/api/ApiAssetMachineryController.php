<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ApiAssetMachineryController extends Controller
{
    // ==========================================
    // ASSETS MODULE
    // ==========================================

    public function assetSummary(Request $request)
    {
        try {
            $data = [
                'asset_heads' => DB::table('asset_head')->get(),
                'sites' => DB::table('sites')->where('status', 'Active')->get(),
            ];
            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function listAssets(Request $request)
    {
        try {
            $user = $request->user();
            $head_id = $request->get('head_id');
            $site_id = $request->get('site_id', $user->site_id);
            $status = $request->get('status', 'Working');

            $query = DB::table('assets')
                ->leftJoin('sites', 'sites.id', '=', 'assets.site_id')
                ->leftJoin('asset_head', 'asset_head.id', '=', 'assets.head_id')
                ->select('assets.*', 'sites.name as site_name', 'asset_head.name as head_name')
                ->orderBy('assets.id', 'desc');

            if ($head_id) $query->where('assets.head_id', $head_id);
            if ($site_id && $site_id != 'all') $query->where('assets.site_id', $site_id);
            if ($status) $query->where('assets.status', $status);

            $assets = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $assets]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeAsset(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'head_id' => 'required',
            'site_id' => 'required',
            'cost_price' => 'required|numeric'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            
            return DB::transaction(function () use ($request, $user, $conn) {
                $data = [
                    'name' => $request->name,
                    'head_id' => $request->head_id,
                    'site_id' => $request->site_id,
                    'cost_price' => $request->cost_price,
                    'status' => 'Working'
                ];
                $id = DB::table('assets')->insertGetId($data);
                addActivity($id, 'assets', "New Asset Purchased - " . $request->name, 5, $user->id, $conn);

                DB::table('asset_transaction')->insert([
                    'asset_id' => $id,
                    'to_site' => $request->site_id,
                    'transaction_type' => 'Purchase',
                    'remark' => 'Asset added manually via API'
                ]);

                return response()->json(['status' => 'Ok', 'message' => 'Asset created successfully', 'id' => $id]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function transferAsset(Request $request, $id)
    {
        $request->validate([
            'to_site' => 'required',
            'remark' => 'nullable'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $asset = DB::table('assets')->where('id', $id)->first();

            if (!$asset) return response()->json(['status' => 'Failed', 'message' => 'Asset not found'], 404);
            if ($asset->site_id == $request->to_site) return response()->json(['status' => 'Failed', 'message' => 'Asset already at this site'], 400);

            return DB::transaction(function () use ($id, $asset, $request, $user, $conn) {
                DB::table('assets')->where('id', $id)->update(['site_id' => $request->to_site]);
                
                DB::table('asset_transaction')->insert([
                    'asset_id' => $id,
                    'from_site' => $asset->site_id,
                    'to_site' => $request->to_site,
                    'transaction_type' => 'Transfer',
                    'remark' => $request->remark
                ]);

                addActivity($id, 'assets', "Asset Transferred to site: " . $request->to_site, 5, $user->id, $conn);
                return response()->json(['status' => 'Ok', 'message' => 'Asset transferred successfully']);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // MACHINERY MODULE
    // ==========================================

    public function machinerySummary(Request $request)
    {
        try {
            $data = [
                'machinery_heads' => DB::table('machinery_head')->get(),
                'sites' => DB::table('sites')->where('status', 'Active')->get(),
            ];
            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function listMachinery(Request $request)
    {
        try {
            $user = $request->user();
            $head_id = $request->get('head_id');
            $site_id = $request->get('site_id', $user->site_id);

            $query = DB::table('machinery_details')
                ->leftJoin('sites', 'sites.id', '=', 'machinery_details.site_id')
                ->leftJoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
                ->select('machinery_details.*', 'sites.name as site_name', 'machinery_head.name as head_name')
                ->orderBy('machinery_details.id', 'desc');

            if ($head_id) $query->where('machinery_details.head_id', $head_id);
            if ($site_id && $site_id != 'all') $query->where('machinery_details.site_id', $site_id);

            $machinery = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $machinery]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMachinery(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'head_id' => 'required',
            'site_id' => 'required',
            'cost_price' => 'required|numeric'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            return DB::transaction(function () use ($request, $user, $conn) {
                $data = [
                    'name' => $request->name,
                    'head_id' => $request->head_id,
                    'site_id' => $request->site_id,
                    'cost_price' => $request->cost_price,
                    'status' => 'Working'
                ];
                $id = DB::table('machinery_details')->insertGetId($data);
                addActivity($id, 'machinery_details', "New Machinery Purchased - " . $request->name, 6, $user->id, $conn);

                DB::table('machinery_transaction')->insert([
                    'machinery_id' => $id,
                    'to_site' => $request->site_id,
                    'transaction_type' => 'Purchase',
                    'remark' => 'Machinery added manually via API'
                ]);

                return response()->json(['status' => 'Ok', 'message' => 'Machinery created successfully', 'id' => $id]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // MACHINERY DOCUMENTS & SERVICES
    // ==========================================

    public function machineryDocuments($id)
    {
        try {
            $docs = DB::table('machinery_documents')->where('machinery_id', $id)->get();
            return response()->json(['status' => 'Ok', 'data' => $docs]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMachineryDocument(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'issue_date' => 'required|date',
            'attachment' => 'required|file'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $file = $request->file('attachment');
            $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
            $file->move(public_path('images/app_images/' . $conn . '/machinery_doc'), $imageName);
            $imagePath = "images/app_images/" . $conn . "/machinery_doc/" . $imageName;

            $docId = DB::table('machinery_documents')->insertGetId([
                'machinery_id' => $id,
                'name' => $request->name,
                'issue_date' => $request->issue_date,
                'end_date' => $request->end_date,
                'remark' => $request->remark,
                'attachment' => $imagePath
            ]);

            addActivity($docId, 'machinery_documents', "New Doc Uploaded via API: " . $request->name, 6, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Document uploaded successfully', 'id' => $docId]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function machineryServices($id)
    {
        try {
            $services = DB::table('machinery_services')->where('machinery_id', $id)->orderBy('create_date', 'desc')->get();
            return response()->json(['status' => 'Ok', 'data' => $services]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMachineryService(Request $request, $id)
    {
        $request->validate([
            'create_date' => 'required|date',
            'maintainence_item' => 'required',
            'image1' => 'required|file'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $images = [];

            for ($i = 1; $i <= 5; $i++) {
                if ($request->hasFile('image'.$i)) {
                    $file = $request->file('image'.$i);
                    $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                    $file->move(public_path('images/app_images/' . $conn . '/machinery_service'), $imageName);
                    $images['image'.$i] = "images/app_images/" . $conn . "/machinery_service/" . $imageName;
                }
            }

            $serviceData = array_merge([
                'machinery_id' => $id,
                'create_date' => $request->create_date,
                'next_service_on' => $request->next_service_on,
                'maintainence_item' => $request->maintainence_item,
                'remark' => $request->remark,
                'user_id' => $user->id
            ], $images);

            $serviceId = DB::table('machinery_services')->insertGetId($serviceData);
            addActivity($serviceId, 'machinery_services', "New Service Record via API", 6, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Service record added successfully', 'id' => $serviceId]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
