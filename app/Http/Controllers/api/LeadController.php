<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{

    
    /**
     * POST /api/leads
     * Save lead data into the leads table.
     */
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name'    => 'required|string|max:150',
            'company_name' => 'nullable|string|max:150',
            'email'        => 'required|email|max:150',
            'phone'        => 'nullable|string|max:20',
            'message'      => 'nullable|string|max:2000',
        ]);

        $id = DB::table('leads')->insertGetId([
            'full_name'    => $validated['full_name'],
            'company_name' => $validated['company_name'] ?? null,
            'email'        => $validated['email'],
            'phone'        => $validated['phone'] ?? null,
            'message'      => $validated['message'] ?? null,
            'status'       => 'new',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        //test
        $lead = DB::table('leads')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Lead saved successfully.',
            'data'    => $lead,
        ], 201);
    }
}
