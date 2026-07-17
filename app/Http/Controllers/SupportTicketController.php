<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class SupportTicketController extends Controller
{
    private function getShaarvikUrl()
    {
        return rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');
    }

    public function index()
    {
        if (!isSuperAdmin() && checkmodulepermission(17, 'can_report') != 1) {
            abort(403, 'Unauthorized access.');
        }

        $companyUid = session()->get('comp_id');
        $tickets = [];
        $error = null;
        $shaarvikUrl = $this->getShaarvikUrl();

        if ($companyUid) {
            try {
                $response = Http::timeout(10)->get("{$shaarvikUrl}/api/mysql/tickets", [
                    'companyUid' => $companyUid,
                ]);

                if ($response->successful()) {
                    $tickets = $response->json();
                } else {
                    $error = 'Failed to fetch tickets. Error: ' . $response->body();
                }
            } catch (\Exception $e) {
                $error = 'Could not connect to billing server.';
                Log::error("Shaarvik ticket fetch failed: " . $e->getMessage());
            }
        } else {
            $error = 'Company session not found.';
        }

        return view('layouts.tickets.index', compact('tickets', 'error'));
    }

    public function store(Request $request)
    {
        if (!isSuperAdmin() && checkmodulepermission(17, 'can_report') != 1) {
            return back()->with('error', 'Unauthorized.');
        }

        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string'
        ]);

        $companyUid = session()->get('comp_id');
        if (!$companyUid) {
            return back()->with('error', 'Company session not found.');
        }

        $shaarvikUrl = $this->getShaarvikUrl();
        $user = auth()->user();

        try {
            $response = Http::timeout(10)->post("{$shaarvikUrl}/api/mysql/tickets", [
                'companyUid' => $companyUid,
                'subject' => $request->subject,
                'description' => $request->description,
                'createdByName' => $user->name ?? '',
                'createdByEmail' => $user->email ?? ''
            ]);

            if ($response->successful()) {
                return redirect()->route('tickets.index')->with('success', 'Ticket created successfully.');
            } else {
                return back()->with('error', 'Failed to create ticket: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Shaarvik ticket create failed: " . $e->getMessage());
            return back()->with('error', 'Could not connect to billing server.');
        }
    }

    public function show($id)
    {
        if (!isSuperAdmin() && checkmodulepermission(17, 'can_report') != 1) {
            abort(403, 'Unauthorized access.');
        }

        $shaarvikUrl = $this->getShaarvikUrl();
        $ticket = null;
        $error = null;

        try {
            $response = Http::timeout(10)->get("{$shaarvikUrl}/api/mysql/tickets/{$id}");

            if ($response->successful()) {
                $ticket = $response->json();
            } else {
                $error = 'Failed to load ticket details.';
            }
        } catch (\Exception $e) {
            $error = 'Could not connect to billing server.';
            Log::error("Shaarvik ticket detail fetch failed: " . $e->getMessage());
        }

        if (!$ticket && !$error) {
            abort(404);
        }

        return view('layouts.tickets.show', compact('ticket', 'error'));
    }

    public function reply(Request $request, $id)
    {
        if (!isSuperAdmin() && checkmodulepermission(17, 'can_report') != 1) {
            return back()->with('error', 'Unauthorized.');
        }

        $request->validate([
            'reply_text' => 'required|string'
        ]);

        $shaarvikUrl = $this->getShaarvikUrl();
        $user = auth()->user();

        try {
            $response = Http::timeout(10)->post("{$shaarvikUrl}/api/mysql/tickets/{$id}", [
                'replyText' => $request->reply_text,
                'isAdminReply' => false,
                'repliedByName' => $user->name ?? '',
                'repliedByEmail' => $user->email ?? ''
            ]);

            if ($response->successful()) {
                return redirect()->route('tickets.show', $id)->with('success', 'Reply posted successfully.');
            } else {
                return back()->with('error', 'Failed to post reply.');
            }
        } catch (\Exception $e) {
            Log::error("Shaarvik ticket reply failed: " . $e->getMessage());
            return back()->with('error', 'Could not connect to billing server.');
        }
    }
}
