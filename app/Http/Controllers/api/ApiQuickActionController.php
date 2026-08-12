<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\QuickAction;

class ApiQuickActionController extends Controller
{
    /**
     * Get quick action list or single record
     */
    public function index(Request $request)
    {
        try {
            $conn = config('database.default');
            $query = DB::connection($conn)->table('quick_action');

            if ($request->has('user_id') && !empty($request->user_id)) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('search') && !empty($request->search)) {
                $query->where('quick_action_text', 'LIKE', '%' . $request->search . '%');
            }

            $query->orderBy('id', 'desc');

            $perPage = intval($request->get('per_page', 20));
            $data = $query->paginate($perPage);

            return response()->json([
                'status' => 'Ok',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get single quick action by ID
     */
    public function show(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $item = DB::connection($conn)->table('quick_action')->where('id', $id)->first();

            if (!$item) {
                return response()->json(['status' => 'Error', 'message' => 'Quick action not found'], 404);
            }

            return response()->json([
                'status' => 'Ok',
                'data' => $item
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new quick action record
     */
    public function store(Request $request)
    {
        $request->validate([
            'quick_action_text' => 'required|string',
            'user_id' => 'nullable|integer'
        ]);

        try {
            $conn = config('database.default');
            $userId = $request->user_id ?? ($request->user() ? $request->user()->id : null);

            $id = DB::connection($conn)->table('quick_action')->insertGetId([
                'user_id' => $userId,
                'quick_action_text' => $request->quick_action_text,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $data = DB::connection($conn)->table('quick_action')->where('id', $id)->first();

            return response()->json([
                'status' => 'Ok',
                'message' => 'Quick action created successfully',
                'data' => $data
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a quick action record
     */
    public function destroy(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $targetId = $id ?? $request->id ?? $request->quick_action_id;

            if (!$targetId) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required for deletion'], 400);
            }

            $item = DB::connection($conn)->table('quick_action')->where('id', $targetId)->first();
            if (!$item) {
                return response()->json(['status' => 'Error', 'message' => 'Quick action not found'], 404);
            }

            DB::connection($conn)->table('quick_action')->where('id', $targetId)->delete();

            return response()->json([
                'status' => 'Ok',
                'message' => 'Quick action deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
