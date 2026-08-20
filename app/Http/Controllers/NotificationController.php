<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function sendNotification(Request $request)
    {
        $user_db_conn_name = session()->get('comp_db_conn_name');
        
        $users = DB::connection($user_db_conn_name)->table('users')->where('status', 'Active')->get();
        $sites = DB::connection($user_db_conn_name)->table('sites')->where('status', 'Active')->get();

        return view('layouts.notification.send', compact('users', 'sites'));
    }

    public function postSendNotification(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'target_type' => 'required|string|in:all,site,user',
        ]);

        $user_db_conn_name = session()->get('comp_db_conn_name');
        $targetType = $request->input('target_type');
        $title = $request->input('title');
        $msg = $request->input('message');
        $url = $request->input('url', '/dashboard');

        if ($targetType === 'all') {
            saveWebNotification('all', $title, $msg, $url, $user_db_conn_name);
            return redirect()->back()->with('success', 'Push notification sent to ALL app users successfully!');
        } elseif ($targetType === 'site') {
            $siteId = $request->input('site_id');
            if (!$siteId) {
                return redirect()->back()->with('error', 'Please select a site to target.');
            }
            $userIds = DB::connection($user_db_conn_name)->table('users')
                ->where('status', 'Active')
                ->where(function ($q) use ($siteId) {
                    $q->where('site_id', '=', $siteId)
                      ->orWhereRaw("FIND_IN_SET(?, site_id)", [$siteId]);
                })
                ->pluck('id')
                ->toArray();

            if (empty($userIds)) {
                return redirect()->back()->with('error', 'No active users found assigned to the selected site.');
            }

            saveWebNotification($userIds, $title, $msg, $url, $user_db_conn_name);
            return redirect()->back()->with('success', 'Push notification sent to users of the selected site successfully!');
        } elseif ($targetType === 'user') {
            $userId = $request->input('user_id');
            if (!$userId) {
                return redirect()->back()->with('error', 'Please select a user to target.');
            }

            saveWebNotification($userId, $title, $msg, $url, $user_db_conn_name);
            return redirect()->back()->with('success', 'Push notification sent to the selected user successfully!');
        }

        return redirect()->back()->with('error', 'Invalid target type selected.');
    }
}
