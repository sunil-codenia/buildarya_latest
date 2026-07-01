<?php

namespace App\Http\Controllers\api;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MachineryController extends Controller
{
    //
    public function get_machineries(Request $request){
        $conn = $request->post('conn');
        $site_id = $request->post('site_id');
        $data = DB::connection($conn)->table('machinery_details')->where('site_id', $site_id)->get();
        return json_encode($data);
    }
    public function get_machinery_documents(Request $request){
        $conn = $request->post('conn');
        $site_id = $request->post('site_id');
    
        $query = "SELECT md.id,md.machinery_id,md.name,md.issue_date,md.end_date,md.create_date,md.attachment,md.remark FROM `machinery_documents` as md INNER JOIN machinery_details ON machinery_details.id = md.machinery_id WHERE machinery_details.site_id = '$site_id'";
        $data = DB::connection($conn)->select($query);
        return json_encode($data);
    }
    public function get_machinery_service(Request $request){
        $conn = $request->post('conn');
        $site_id = $request->post('site_id');
        $query = "SELECT ms.id,ms.machinery_id,ms.next_service_on,ms.user_id,ms.maintainence_item,ms.create_date,ms.image1,ms.image2,ms.image3,ms.image4,ms.image4,ms.image5,ms.remark FROM `machinery_services` as ms INNER JOIN machinery_details as md ON ms.machinery_id = md.id WHERE md.site_id = '$site_id'";
        $data = DB::connection($conn)->select($query);
        return json_encode($data);
    }

     public function addmachinerydocument(Request $request)
    {
       
        $user_db_conn_name = $request->conn;
        $id =  $request->machinery_id;
        $name = $request->name;
        $issue_date = $request->issue_date;
        $end_date = $request->end_date;
        $remark = $request->remark;

        $uid = $request->uid ?? $request->user_id;
        if ($uid) {
            $role_id = getAppRoleByUId($uid, $user_db_conn_name);
            $role_details = getAppRoleDetailsById($role_id, $user_db_conn_name);
            $user_details = DB::connection($user_db_conn_name)->table('users')->where('id', $uid)->first();
            $add_duration = getUserAddDuration($user_details, $user_db_conn_name);
            $duration = getdurationdates($add_duration);
            $min_date = $duration['min'];
            $max_date = substr($duration['today'], 0, 10);
            if (strtotime($issue_date) < strtotime($min_date) || strtotime($issue_date) > strtotime($max_date)) {
                $result = array();
                $result['status'] = 'Failed';
                $result['message'] = "You don't have permission to add document for issue date: " . $issue_date;
                $response = array();
                array_push($response, $result);
                return json_encode($response);
            }
        }

        if (isset($request->attachment)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->attachment->extension();
                $request->attachment->move(public_path('images/app_images/'.$user_db_conn_name.'/machinery_doc'), $imageName);
                $imagePath = "images/app_images/".$user_db_conn_name."/machinery_doc/" . $imageName;
                $rawd = [
                    'machinery_id' => $id,
                    'name' => $name,
                    'issue_date' => $issue_date,
                    'end_date' => $end_date,
                    'remark' => $remark,
                    'attachment' => $imagePath
                ];


                try {
                    $id =  DB::connection($user_db_conn_name)->table('machinery_documents')->insertGetId($rawd);
                    addActivity($id, 'machinery_documents', "New Machinery Document Added", 6,0,$user_db_conn_name);

                    $result['status'] = 'Ok';
                    $result['message'] = 'Document Updated Successfully!';
                    $result['inserted_id'] = $id;
                    $result['image'] = $imagePath;

                } catch (\Exception $e) {

                    $result['status'] = 'Failed';
                    $result['message'] = 'Insertion Error While Updating Document!';
                }
            } catch (\Exception $e) {
                $result['status'] = 'Failed';
                $result['message'] = 'Image Error While Updating Document!';
            }
        } else {
            $result['status'] = 'Failed';
            $result['message'] = 'Image is missing!';
        }

        $response = array();
        array_push($response, $result);
        return json_encode($response);
    }


     public function addmachineryservice(Request $request)
    {
        
      
        $user_db_conn_name = $request->conn;
        $id =  $request->machinery_id;
        $next_service_on = $request->next_service_on;
        $remark = $request->remark;
        $maintainence_item = $request->maintainence_item;
        $user_id = $request->uid;
        $image1 = "";
        $image2 = "";
        $image3 = "";
        $image4 = "";
        $image5 = "";
        if (isset($request->image1)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image1->extension();
                $request->image1->move(public_path('images/app_images/'.$user_db_conn_name.'/machinery_service'), $imageName);
                $image1 = "images/app_images/".$user_db_conn_name."/machinery_service/" . $imageName;
            }catch(\Exception $e) {

            }
        }

        if (isset($request->image2)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image2->extension();
                $request->image2->move(public_path('images/app_images/'.$user_db_conn_name.'/machinery_service'), $imageName);
                $image2 = "images/app_images/".$user_db_conn_name."/machinery_service/" . $imageName;
            }catch(\Exception $e) {
                
            } 
        } 

        if (isset($request->image3)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image3->extension();
                $request->image3->move(public_path('images/app_images/'.$user_db_conn_name.'/machinery_service'), $imageName);
                $image3 = "images/app_images/".$user_db_conn_name."/machinery_service/" . $imageName;
            }catch(\Exception $e) {
                
            } 
        }

        if (isset($request->image4)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image4->extension();
                $request->image4->move(public_path('images/app_images/'.$user_db_conn_name.'/machinery_service'), $imageName);
                $image4 = "images/app_images/".$user_db_conn_name."/machinery_service/" . $imageName;
            }catch(\Exception $e) {
                
            } 
        }

        if (isset($request->image5)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image5->extension();
                $request->image5->move(public_path('images/app_images/'.$user_db_conn_name.'/machinery_service'), $imageName);
                $image5 = "images/app_images/".$user_db_conn_name."/machinery_service/" . $imageName;
            } catch(\Exception $e) {
                
            }
        }
        
        $rawd = [
            'machinery_id' => $id,
            'next_service_on' => $next_service_on,
            'maintainence_item' => $maintainence_item,
            'remark' => $remark,
            'image1' => $image1,
            'image2' => $image2,
            'image3' => $image3,
            'image4' => $image4,
            'image5' => $image5,
            'user_id' => $user_id,
        ];
         try {
           $id =  DB::connection($user_db_conn_name)->table('machinery_services')->insertGetId($rawd);
           addActivity($id, 'machinery_services', "New Machinery Service Added", 6,$user_id,$user_db_conn_name);

            $result['status'] = 'Ok';
            $result['message'] = 'Machinery service Updated Successfully!';
            $result['inserted_id'] = $id;
            $result['image1'] = $image1;
            $result['image2'] = $image2;
            $result['image3'] = $image3;
            $result['image4'] = $image4;
            $result['image5'] = $image5;
        } 

         catch (\Exception $e) {
            $result['status'] = 'Failed';
                $result['message'] = 'Error While Updating Machinery Service!';
            
        }

          

      $response = array();
        array_push($response, $result);
        return json_encode($response);
    }





     public function soldmachinery(Request $request)
    {
        $id = $request->get('id');
        $conn = $request->post('conn');
        $user_db_conn_name = $conn;
        $head_id = $request->get('head_id');
        $from_site = $request->get('from_site');
        $sold_value = $request->get('sold_value');
        $remark = $request->get('remark');


        try {
            $id=DB::connection($user_db_conn_name)->table('machinery_details')->where('id', $id)->update(['status' => 'Sold', 'sale_price' => $sold_value]);
            addActivity($id, 'machinery_details', "Machinery Sold For Amount - ".$sold_value, 6,0,$user_db_conn_name);
            $machinery_trans = [
                'machinery_id' => $id,
                'from_site' => $from_site,
                'transaction_type' => 'Sold',
                'remark' => $remark
            ];
           DB::connection($user_db_conn_name)->table('machinery_transaction')->insert($machinery_trans);
            $this->addsitesBalance($from_site, $sold_value, "machinery Sold - " . $remark, $user_db_conn_name);
           
        }catch(\Exception $e) {
                
        } 
      $response = array();
        array_push($response);
        return json_encode($response);


    }
   public function addsitesBalance($id, $amount, $remark, $user_db_conn_name)
    {

        $data = [
            'site_id' => $id,
            'amount' => $amount,
            'remark' => $remark
        ];
        $pay_id =   DB::connection($user_db_conn_name)->table('site_payments')->insertGetId($data);
        addActivity($pay_id, 'site_payments', "Site Payment Added By Selling Machinery Of Amount - .$amount ", 6,0,$user_db_conn_name);

        $tdata = [
            'site_id' => $id,
            'type' => 'Credit',
            'payment_id' => $pay_id
        ];
        DB::connection($user_db_conn_name)->table('sites_transaction')->where('payment_id', '=', $pay_id)->delete();

        DB::connection($user_db_conn_name)->table('sites_transaction')->insert($tdata);
    }
}
