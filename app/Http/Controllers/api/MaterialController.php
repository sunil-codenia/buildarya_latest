<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

class MaterialController extends Controller
{
    //

    public function get_materials(Request $request)
    {
        $conn = $request->conn;
        $data = DB::connection($conn)->table('materials')->get();
        return json_encode($data);
    }
    public function get_material_supplier(Request $request)
    {
        $conn = $request->conn;
        $data = DB::connection($conn)->table('material_supplier')->select('id', 'name')->get();
        return json_encode($data);
    }
    public function get_material_unit(Request $request)
    {
        $conn = $request->conn;
        $data = DB::connection($conn)->table('units')->get();
        return json_encode($data);
    }

    public function get_material_entry(Request $request)
    {
        $conn = $request->conn;
        $site_id = $request->site_id;
        $uid = $request->uid;
      

        $role_id = getAppRoleByUId($uid, $conn);
        $role_details = getAppRoleDetailsById($role_id, $conn);
        $user_details = DB::connection($conn)->table('users')->where('id', $uid)->first();
        $view_duration = getUserViewDuration($user_details, $conn);
        $visiblity_at_site = $role_details ? $role_details->visiblity_at_site : 'all';

        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];
        $filters = array();
        if ($visiblity_at_site == 'current') {
            $filters = [['material_entry.site_id', '=', $site_id]];
        } 
        $data = DB::connection($conn)->table('material_entry')->select('material_entry.*')->where($filters)->whereBetween('material_entry.create_datetime', [$min_date, $max_date])->limit(200)->orderBy('material_entry.create_datetime', 'desc')->get();      
         return json_encode($data);
    }

    public function addmaterialentry(Request $request)
    {

       
        $conn = $request->conn;
        $supplier = $request->supplier;
        $material_id = $request->material_id;
        $unit = $request->unit;
        $qty = $request->qty;
        $vehical = $request->vehical;
        $remark = $request->remark;
        $location = $request->location;
        $site_id = $request->site_id;
        $uid = $request->user_id;
        $date = $request->date;

        $role_id = getAppRoleByUId($uid, $conn);
        $role_details = getAppRoleDetailsById($role_id, $conn);
        $user_details = DB::connection($conn)->table('users')->where('id', $uid)->first();
        $add_duration = getUserAddDuration($user_details, $conn);
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = substr($duration['today'], 0, 10);
        if (strtotime($date) < strtotime($min_date) || strtotime($date) > strtotime($max_date)) {
            $result = array();
            $result['status'] = 'Failed';
            $result['message'] = "You don't have permission to add entry for date: " . $date;
            $response = array();
            array_push($response, $result);
            return json_encode($response);
        }
        
        if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('material_entry', 'image3')) {
            try {
                \Illuminate\Support\Facades\Schema::connection($conn)->table('material_entry', function ($table) {
                    $table->string('image3', 2000)->nullable();
                    $table->string('image4', 2000)->nullable();
                    $table->string('image5', 2000)->nullable();
                });
            } catch (\Exception $e) {
                \Log::error("Failed adding columns: " . $e->getMessage());
            }
        }

        $uploadedImages = [];
        for ($k = 1; $k <= 5; $k++) {
            $key = 'image' . ($k === 1 ? '' : $k);
            try {
                if ($request->hasFile($key)) {
                    $file = $request->file($key);
                    $imgFile = null;
                    if (is_array($file) && isset($file[0])) {
                        $imgFile = $file[0];
                    } elseif ($file instanceof \Illuminate\Http\UploadedFile) {
                        $imgFile = $file;
                    }
                    if ($imgFile && $imgFile->isValid()) {
                        $imageName = time() . rand(10000, 1000000) . '.' . $imgFile->getClientOriginalExtension();
                        $imgFile->move(public_path('images/app_images/' . $conn . '/material'), $imageName);
                        $uploadedImages[] = "images/app_images/" . $conn . "/material/" . $imageName;
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed saving image $k: " . $e->getMessage());
            }
        }

        $imagePath = count($uploadedImages) > 0 ? implode(',', $uploadedImages) : "images/expense.png";

        $role_id = getAppRoleByUId($uid, $conn);
        $status = getAppInitialEntryStatusByRole($role_id, $conn);
        $data = [
            'supplier' => $supplier,
            'material_id' => $material_id,
            'unit' => $unit,
            'qty' => $qty,
            'vehical' => $vehical,
            'image' => $imagePath,
            'status' => $status,
            'remark' => $remark,
            'location' => $location,
            'site_id' => $site_id,
            'user_id' => $uid,
            'date' => $date,        
        ];
        try {
            $id =  DB::connection($conn)->table('material_entry')->insertGetId($data);
            addActivity($id,'material_entry',"New Material Entry Created",3,$uid,$conn);
            $result['status'] = 'Ok';
            $result['message'] = 'Material Entry Created Successfully!';
            $result['inserted_id'] = $id;
            $result['image'] = $imagePath;

            if ($status == 'Approved') {
                $party_status = DB::connection($conn)->table('material_supplier')->where('id', '=', $supplier)->get()[0];
                if ($party_status->status == 'Active') {
                    $this->approve_material_entry($id, $conn);
                }
            }
        } catch (\Exception $e) {
            $result['status'] = 'Failed';
            $result['message'] = 'Error While Creating Material Entry !';
            // $result['message'] = $e->getMessage();
        }
        $response = array();
        array_push($response, $result);
        return json_encode($response);
    }
    public function addmaterialentryImage(Request $request)
    {
        $conn = $request->conn;
        $material_entry_id = $request->material_entry_id;
        $imagePath = "images/expense.png";
        try {
            if (isset($request->image)) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
                $request->image->move(public_path('images/app_images/material'), $imageName);
                $imagePath = "images/app_images/material/" . $imageName;
            } else {
                $imagePath = "images/expense.png";
            }
        } catch (\Exception $e) {
            $imagePath = "images/expense.png";
        }
        try {
            DB::connection($conn)->table('material_entry')->where('id', $material_entry_id)->update(array('image2' => $imagePath));
            addActivity($material_entry_id,'material_entry',"Material Image Updated",3,0,$conn);

            $result['status'] = 'Ok';
            $result['message'] = 'Material Entry Image Uploaded Successfully!';
            $result['image'] = $imagePath;
          
        } catch (\Exception $e) {
            $result['status'] = 'Failed';
            $result['message'] = $e->getMessage();
        }
        $response = array();
        array_push($response, $result);
        return json_encode($response);
    }
    public function approve_material_entry($id, $user_db_conn_name)
    {
        DB::connection($user_db_conn_name)->table('material_entry')->where('id', '=', $id)->update(['status' => 'Approved']);
        addActivity($id,'material_entry',"Material Entry Approved",3,0,$user_db_conn_name);
    }
}
