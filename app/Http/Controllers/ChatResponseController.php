<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatResponseController extends Controller
{
    /**
     * Show AI search responses saved for the signed-in company.
     */
    public function index(Request $request)
    {
        $tenantConnection = $request->session()->get('comp_db_conn_name') ?? config('database.default');
        $auditConnection = $this->findAuditConnection($tenantConnection);

        if (!$auditConnection) {
            return view('chat_response.index', [
                'responses' => collect(),
                'auditUnavailable' => true,
            ]);
        }

        $companyConnection = $request->session()->get('comp_db_conn_name');
        $userId = $request->session()->get('uid');
        $username = $request->session()->get('username');

        $query = DB::connection($auditConnection)
            ->table('user_search_audit')
            ->select('id', 'username', 'question', 'response', 'response_payload', 'is_checked', 'response_time_ms', 'searched_at');

        if ($companyConnection) {
            $query->where(function ($auditQuery) use ($companyConnection, $userId, $username) {
                $auditQuery->where('company_connection', $companyConnection);

                // Older rows were recorded before company_connection was added.
                // Keep them visible only to the user who created them.
                if ($userId || $username) {
                    $auditQuery->orWhere(function ($legacyQuery) use ($userId, $username) {
                        $legacyQuery->whereNull('company_connection');
                        if ($userId) {
                            $legacyQuery->where('user_id', $userId);
                        }
                        if ($username) {
                            $legacyQuery->where('username', $username);
                        }
                    });
                }
            });
        } elseif ($userId || $username) {
            if ($userId) {
                $query->where('user_id', $userId);
            }
            if ($username) {
                $query->where('username', $username);
            }
        } else {
            $query->whereRaw('1 = 0');
        }

        $responses = $query->orderByDesc('searched_at')->paginate(25)->appends($request->query());

        return view('chat_response.index', compact('responses') + ['auditUnavailable' => false]);
    }

    /**
     * Update checkbox status for a chat response record.
     */
    public function updateCheck(Request $request)
    {
        $id = $request->input('id');
        $isChecked = $request->input('is_checked') ? 1 : 0;

        if (!$id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Record ID is required.'
            ], 400);
        }

        $tenantConnection = $request->session()->get('comp_db_conn_name') ?? config('database.default');
        $auditConnection = $this->findAuditConnection($tenantConnection);

        if (!$auditConnection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Audit connection unavailable.'
            ], 500);
        }

        DB::connection($auditConnection)
            ->table('user_search_audit')
            ->where('id', $id)
            ->update([
                'is_checked' => $isChecked,
                'updated_at' => now('Asia/Kolkata')
            ]);

        return response()->json([
            'status' => 'success',
            'message' => $isChecked ? 'Marked as checked.' : 'Marked as unchecked.',
            'is_checked' => (bool)$isChecked,
            'id' => $id
        ]);
    }

    /**
     * Bulk update checkbox status for multiple chat response records.
     */
    public function bulkUpdateCheck(Request $request)
    {
        $ids = $request->input('ids');
        $isChecked = $request->input('is_checked') ? 1 : 0;

        if (empty($ids) || !is_array($ids)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Record IDs are required.'
            ], 400);
        }

        $tenantConnection = $request->session()->get('comp_db_conn_name') ?? config('database.default');
        $auditConnection = $this->findAuditConnection($tenantConnection);

        if (!$auditConnection) {
            return response()->json([
                'status' => 'error',
                'message' => 'Audit connection unavailable.'
            ], 500);
        }

        DB::connection($auditConnection)
            ->table('user_search_audit')
            ->whereIn('id', $ids)
            ->update([
                'is_checked' => $isChecked,
                'updated_at' => now('Asia/Kolkata')
            ]);

        return response()->json([
            'status' => 'success',
            'message' => count($ids) . ' record(s) updated successfully.',
            'is_checked' => (bool)$isChecked,
            'count' => count($ids)
        ]);
    }

    private function findAuditConnection($tenantConnection)
    {
        foreach (array_unique(array_filter([$tenantConnection, 'mysql', config('database.default')])) as $connection) {
            try {
                if (Schema::connection($connection)->hasTable('user_search_audit')) {
                    return $connection;
                }
            } catch (\Throwable $e) {
                // Try the next configured connection.
            }
        }

        return null;
    }
}
