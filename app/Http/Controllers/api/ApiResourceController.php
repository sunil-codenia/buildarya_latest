<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiResourceController extends Controller
{
    /**
     * List all active Sites
     */
    public function sites(Request $request)
    {
        try {
            $user = $request->user();
            $role_details = DB::table('roles')->where('id', $user->role_id)->first();
            
            $query = DB::table('sites')->where('status', 'Active');

            // Apply visibility logic if needed
            if ($role_details->visiblity_at_site == 'current' && $user->site_id && $user->site_id != 'all') {
                $query->where('id', $user->site_id);
            }

            $sites = $query->get();
            return response()->json(['status' => 'Ok', 'data' => $sites]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List all Users (Staff)
     */
    public function users(Request $request)
    {
        try {
            $users = DB::table('users')
                ->where('status', 'Active')
                ->select('id', 'name', 'username', 'role_id', 'site_id', 'image')
                ->get();
            return response()->json(['status' => 'Ok', 'data' => $users]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List all Roles
     */
    public function roles(Request $request)
    {
        try {
            $roles = DB::table('roles')->get();
            return response()->json(['status' => 'Ok', 'data' => $roles]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Sales & Payment Resources
     */
    public function salesCompanies(Request $request)
    {
        return response()->json(['status' => 'Ok', 'data' => DB::table('sales_company')->get()]);
    }

    public function salesProjects(Request $request)
    {
        return response()->json(['status' => 'Ok', 'data' => DB::table('sales_project')->get()]);
    }

    public function salesParties(Request $request)
    {
        return response()->json(['status' => 'Ok', 'data' => DB::table('sales_party')->get()]);
    }

    public function otherParties(Request $request)
    {
        return response()->json(['status' => 'Ok', 'data' => DB::table('other_parties')->get()]);
    }

    public function adjustmentTypes(Request $request)
    {
        return response()->json(['status' => 'Ok', 'data' => DB::table('sales_dedadd')->get()]);
    }
}
