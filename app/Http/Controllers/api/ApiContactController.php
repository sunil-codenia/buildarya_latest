<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApiContactController extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->get('search');
            $query = DB::table('contacts')->orderBy('id', 'desc');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%$search%")
                      ->orWhere('company_name', 'like', "%$search%")
                      ->orWhere('mobile', 'like', "%$search%")
                      ->orWhere('email', 'like', "%$search%");
                });
            }

            $contacts = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $contacts]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required', 'mobile' => 'required']);

        try {
            $id = DB::table('contacts')->insertGetId([
                'name' => $request->name,
                'company_name' => $request->company_name,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'address' => $request->address,
                'remark' => $request->remark,
                'create_datetime' => Carbon::now()
            ]);

            return response()->json(['status' => 'Ok', 'message' => 'Contact created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::table('contacts')->where('id', $id)->update($request->only(['name', 'company_name', 'mobile', 'email', 'address', 'remark']));
            return response()->json(['status' => 'Ok', 'message' => 'Contact updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            DB::table('contacts')->where('id', $id)->delete();
            return response()->json(['status' => 'Ok', 'message' => 'Contact deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
