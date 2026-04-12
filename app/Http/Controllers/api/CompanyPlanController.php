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

            $planId = DB::table('company_plan')->insertGetId([
                'plan_name' => $request->plan_name,
                'company_id' => $request->company_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($request->has('modules')) {
                foreach ($request->modules as $moduleId) {
                    DB::table('company_modules')->insert([
                        'company_id' => $request->company_id,
                        'company_plan_id' => $planId,
                        'module_id' => $moduleId,
                        'created_at' => now()
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Company plan created successfully',
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
