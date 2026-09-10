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
            ->select('username', 'question', 'response', 'response_payload', 'response_time_ms', 'searched_at');

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
