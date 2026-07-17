<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiSupportTicketController extends Controller
{
    private function getShaarvikUrl()
    {
        return rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
    }

    private function getCompanyUid()
    {
        $connName = config('database.default');
        $company = DB::connection('mysql')->table('companies')
            ->where('db_conn_name', $connName)
            ->orWhere('db_name', $connName)
            ->first();

        return $company ? $company->uid : null;
    }

    /**
     * List all support tickets for the current company
     */
    public function index(Request $request)
    {
        $companyUid = $this->getCompanyUid();
        
        if (!$companyUid) {
            return response()->json(['status' => 'Failed', 'message' => 'Company not found for the current tenant.'], 404);
        }

        $shaarvikUrl = $this->getShaarvikUrl();

        try {
            $response = Http::timeout(10)->get("{$shaarvikUrl}/api/mysql/tickets", [
                'companyUid' => $companyUid,
            ]);

            if ($response->successful()) {
                return response()->json(['status' => 'Ok', 'data' => $response->json()]);
            }

            return response()->json(['status' => 'Failed', 'message' => 'Failed to fetch tickets: ' . $response->body()], 400);
        } catch (\Exception $e) {
            Log::error("Shaarvik ticket fetch failed: " . $e->getMessage());
            return response()->json(['status' => 'Error', 'message' => 'Could not connect to billing server.'], 500);
        }
    }

    /**
     * Create a new support ticket
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string'
        ]);

        $companyUid = $this->getCompanyUid();
        if (!$companyUid) {
            return response()->json(['status' => 'Failed', 'message' => 'Company not found for the current tenant.'], 404);
        }

        $user = $request->user();
        $shaarvikUrl = $this->getShaarvikUrl();

        try {
            $response = Http::timeout(10)->post("{$shaarvikUrl}/api/mysql/tickets", [
                'companyUid' => $companyUid,
                'subject' => $request->subject,
                'description' => $request->description,
                'createdByName' => $user->name ?? '',
                'createdByEmail' => $user->email ?? ''
            ]);

            if ($response->successful()) {
                return response()->json(['status' => 'Ok', 'message' => 'Ticket created successfully.', 'data' => $response->json()]);
            }

            return response()->json(['status' => 'Failed', 'message' => 'Failed to create ticket: ' . $response->body()], 400);
        } catch (\Exception $e) {
            Log::error("Shaarvik ticket create failed: " . $e->getMessage());
            return response()->json(['status' => 'Error', 'message' => 'Could not connect to billing server.'], 500);
        }
    }

    /**
     * Get ticket details with replies
     */
    public function show(Request $request, $id)
    {
        $shaarvikUrl = $this->getShaarvikUrl();

        try {
            $response = Http::timeout(10)->get("{$shaarvikUrl}/api/mysql/tickets/{$id}");

            if ($response->successful()) {
                return response()->json(['status' => 'Ok', 'data' => $response->json()]);
            }

            return response()->json(['status' => 'Failed', 'message' => 'Failed to load ticket details.'], 404);
        } catch (\Exception $e) {
            Log::error("Shaarvik ticket detail fetch failed: " . $e->getMessage());
            return response()->json(['status' => 'Error', 'message' => 'Could not connect to billing server.'], 500);
        }
    }

    /**
     * Reply to a ticket
     */
    public function reply(Request $request, $id)
    {
        $request->validate([
            'reply_text' => 'required|string'
        ]);

        $user = $request->user();
        $shaarvikUrl = $this->getShaarvikUrl();

        try {
            $response = Http::timeout(10)->post("{$shaarvikUrl}/api/mysql/tickets/{$id}", [
                'replyText' => $request->reply_text,
                'isAdminReply' => false,
                'repliedByName' => $user->name ?? '',
                'repliedByEmail' => $user->email ?? ''
            ]);

            if ($response->successful()) {
                return response()->json(['status' => 'Ok', 'message' => 'Reply posted successfully.', 'data' => $response->json()]);
            }

            return response()->json(['status' => 'Failed', 'message' => 'Failed to post reply.'], 400);
        } catch (\Exception $e) {
            Log::error("Shaarvik ticket reply failed: " . $e->getMessage());
            return response()->json(['status' => 'Error', 'message' => 'Could not connect to billing server.'], 500);
        }
    }
}
