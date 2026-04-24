<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyPlanController extends Controller
{
    public function add_company_plan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_name' => 'required|string|max:255',
            'company_id' => 'required|integer|exists:companies,id',
            'platform_name' => 'sometimes|string|max:255',
            'plan_amount' => 'sometimes|numeric',
            'expiry_date' => 'sometimes|string',
            'modules' => 'sometimes|array',
            'modules.*' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $platformName = $request->input('platform_name', 'Default');
            $expiryDate = $request->has('expiry_date') ? \Carbon\Carbon::parse($request->expiry_date)->format('Y-m-d') : null;

            $planId = DB::table('subscription_plans')->insertGetId([
                'company_id' => $request->company_id,
                'platform_name' => $platformName,
                'plan_name' => $request->plan_name,
                'plan_amount' => $request->input('plan_amount', 0),
                'expiry_date' => $expiryDate,
                'modules' => json_encode($request->input('modules', [])),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Subscription plan created successfully',
                'plan_id' => $planId
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
