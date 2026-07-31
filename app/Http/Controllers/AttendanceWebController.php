<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class AttendanceWebController extends Controller
{
    public function index(Request $request)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return redirect('/login')->with('error', 'Please log in again.');
        }

        // Selected Date filter
        $date = $request->get('date', Carbon::today()->toDateString());

        $assignedSites = session()->get('assigned_site_ids', []);

        // Fetch all sites and users
        $sitesQuery = DB::connection($conn)->table('sites');
        if (!empty($assignedSites)) {
            $sitesQuery->whereIn('id', $assignedSites);
        }
        $sites = $sitesQuery->get();

        $usersQuery = DB::connection($conn)->table('users');
        if (!empty($assignedSites)) {
            $usersQuery->where(function($q) use ($assignedSites) {
                foreach ($assignedSites as $sid) {
                    $q->orWhereRaw("FIND_IN_SET(?, site_id)", [$sid]);
                }
            });
        }
        $users = $usersQuery->get();

        $billParties = DB::connection($conn)->table('bills_party')->where('status', 'Active')->get();

        // Fetch attendance logs for the selected date
        $attendanceLogsQuery = DB::connection($conn)->table('attendance')
            ->leftJoin('users', 'users.id', '=', 'attendance.user_id')
            ->leftJoin('bills_party', 'bills_party.id', '=', 'attendance.bills_party_id')
            ->leftJoin('sites', 'sites.id', '=', 'attendance.site_id')
            ->where('attendance.date', $date);

        if (!empty($assignedSites)) {
            $attendanceLogsQuery->whereIn('attendance.site_id', $assignedSites);
        }

        $attendanceLogs = $attendanceLogsQuery->select(
                'attendance.*', 
                DB::raw('COALESCE(users.name, bills_party.name) as user_name'),
                DB::raw('COALESCE(users.username, "Labour Contractor") as user_username'),
                'sites.name as site_name'
            )
            ->get();

        // Calculate Stats
        $totalUsers = $users->count();
        $present = $attendanceLogs->where('status', 'Present')->count();
        $absent = $attendanceLogs->where('status', 'Absent')->count();
        $halfDay = $attendanceLogs->where('status', 'Half Day')->count();
        $onLeave = $attendanceLogs->where('status', 'Leave')->count();

        // Logged-in user's log for today (for self-attendance clock-in/out widget)
        $userTodayLog = DB::connection($conn)->table('attendance')
            ->where('user_id', session()->get('uid'))
            ->where('date', Carbon::today()->toDateString())
            ->first();

        return view('layouts.attendance.index', compact(
            'attendanceLogs', 
            'sites', 
            'users', 
            'billParties',
            'date', 
            'totalUsers', 
            'present', 
            'absent', 
            'halfDay', 
            'onLeave',
            'userTodayLog'
        ));
    }

    public function storeManual(Request $request)
    {
        // Enforce can_add module permission
        if (checkmodulepermission(13, 'can_add') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to manually log attendance.');
        }

        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return response()->json(['status' => 'Error', 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'user_id' => 'required',
            'bills_party_id' => 'required_if:user_id,labour_contractor',
            'site_id' => 'required',
            'status' => 'required|in:Present,Absent,Half Day,Leave',
            'clock_in' => 'nullable',
            'clock_out' => 'nullable',
            'date' => 'required|date',
            'image' => 'nullable|image|max:5120'
        ]);

        $assignedSites = session()->get('assigned_site_ids', []);
        if (!empty($assignedSites) && !in_array($request->site_id, $assignedSites)) {
            return redirect()->back()->with('errorcode', 'You do not have permission for this site.');
        }

        $dateString = Carbon::parse($request->date)->toDateString();

        $userId = $request->user_id;
        $billsPartyId = null;
        if ($userId === 'labour_contractor') {
            $userId = null;
            $billsPartyId = $request->bills_party_id;
        }

        $comp_id = session()->get('comp_id');
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $dirPath = public_path("images/app_images/{$comp_id}/attendance");
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
            }
            $fileName = time() . '_manual_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($dirPath, $fileName);
            $imagePath = "images/app_images/{$comp_id}/attendance/{$fileName}";
        }

        // Check if attendance already exists
        $query = DB::connection($conn)->table('attendance')
            ->where('date', $dateString);
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('bills_party_id', $billsPartyId);
        }
        $existing = $query->first();

        $data = [
            'user_id' => $userId,
            'bills_party_id' => $billsPartyId,
            'site_id' => $request->site_id,
            'status' => $request->status,
            'image' => $imagePath,
            'in_time' => $request->clock_in ? Carbon::parse($dateString . ' ' . $request->clock_in)->toDateTimeString() : null,
            'out_time' => $request->clock_out ? Carbon::parse($dateString . ' ' . $request->clock_out)->toDateTimeString() : null,
            'in_location' => $request->gps_lat ?? 'Manual',
            'out_location' => $request->gps_lng ?? 'Manual',
            'remarks' => 'Manual Override',
            'updated_at' => Carbon::now()->toDateTimeString()
        ];

        if ($existing) {
            if (!$imagePath) {
                unset($data['image']);
            }
            DB::connection($conn)->table('attendance')->where('id', $existing->id)->update($data);
        } else {
            $data['date'] = $dateString;
            $data['created_at'] = Carbon::now()->toDateTimeString();
            DB::connection($conn)->table('attendance')->insert($data);
        }

        return redirect()->back()->with('success', 'Attendance record saved successfully!');
    }

    public function updateManual(Request $request, $id)
    {
        // Enforce can_edit module permission
        if (checkmodulepermission(13, 'can_edit') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to edit attendance logs.');
        }

        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return redirect('/login');
        }

        $request->validate([
            'user_id' => 'required',
            'bills_party_id' => 'required_if:user_id,labour_contractor',
            'site_id' => 'required',
            'status' => 'required|in:Present,Absent,Half Day,Leave',
            'clock_in' => 'nullable',
            'clock_out' => 'nullable',
            'date' => 'required|date',
            'image' => 'nullable|image|max:5120'
        ]);

        $assignedSites = session()->get('assigned_site_ids', []);
        if (!empty($assignedSites) && !in_array($request->site_id, $assignedSites)) {
            return redirect()->back()->with('errorcode', 'You do not have permission for this site.');
        }

        $dateString = Carbon::parse($request->date)->toDateString();

        $userId = $request->user_id;
        $billsPartyId = null;
        if ($userId === 'labour_contractor') {
            $userId = null;
            $billsPartyId = $request->bills_party_id;
        }

        $comp_id = session()->get('comp_id');
        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $dirPath = public_path("images/app_images/{$comp_id}/attendance");
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
            }
            $fileName = time() . '_manual_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($dirPath, $fileName);
            $imagePath = "images/app_images/{$comp_id}/attendance/{$fileName}";
        }

        $data = [
            'user_id' => $userId,
            'bills_party_id' => $billsPartyId,
            'site_id' => $request->site_id,
            'status' => $request->status,
            'date' => $dateString,
            'in_time' => $request->clock_in ? Carbon::parse($dateString . ' ' . $request->clock_in)->toDateTimeString() : null,
            'out_time' => $request->clock_out ? Carbon::parse($dateString . ' ' . $request->clock_out)->toDateTimeString() : null,
            'remarks' => 'Manual Update',
            'updated_at' => Carbon::now()->toDateTimeString()
        ];

        if ($imagePath) {
            $data['image'] = $imagePath;
        }

        DB::connection($conn)->table('attendance')->where('id', $id)->update($data);

        return redirect()->back()->with('success', 'Attendance record updated successfully!');
    }

    public function delete($id)
    {
        // Enforce can_delete module permission
        if (checkmodulepermission(13, 'can_delete') != 1) {
            return redirect()->back()->with('errorcode', 'You do not have permission to delete attendance logs.');
        }

        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return redirect('/login');
        }

        DB::connection($conn)->table('attendance')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Attendance record deleted successfully!');
    }

    public function webClockIn(Request $request)
    {
        $conn = session()->get('comp_db_conn_name');
        $uid = session()->get('uid');
        $comp_id = session()->get('comp_id'); // e.g. new_buildarya

        if (!$conn || !$uid) {
            return redirect('/login')->with('error', 'Please log in again.');
        }

        // Validate Clock In Today
        $exists = DB::connection($conn)->table('attendance')
            ->where('user_id', $uid)
            ->where('date', Carbon::today()->toDateString())
            ->exists();

        if ($exists) {
            return redirect()->back()->with('errorcode', 'You have already clocked in today.');
        }

        $latitude = $request->input('latitude', '0.0000');
        $longitude = $request->input('longitude', '0.0000');
        $imageBase64 = $request->input('image_base64');
        $siteId = $request->input('site_id');

        // Resolve site ID fallback
        if (!$siteId) {
            $assignedSites = session()->get('assigned_site_ids', []);
            $siteId = (!empty($assignedSites) && is_numeric($assignedSites[0])) ? $assignedSites[0] : null;
            if (!$siteId) {
                $firstSite = DB::connection($conn)->table('sites')->first();
                $siteId = $firstSite ? $firstSite->id : null;
            }
        }

        $imagePath = null;
        if ($imageBase64) {
            try {
                // Decode base64
                $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64));
                
                // Ensure target directory exists
                $dirPath = public_path("images/app_images/{$comp_id}/attendance");
                if (!File::exists($dirPath)) {
                    File::makeDirectory($dirPath, 0755, true);
                }

                // Write file
                $fileName = time() . '_in.png';
                $filePath = $dirPath . '/' . $fileName;
                File::put($filePath, $imgData);
                $imagePath = "images/app_images/{$comp_id}/attendance/{$fileName}";
            } catch (\Exception $e) {
                \Log::error("Failed to save checkin photo: " . $e->getMessage());
            }
        }

        DB::connection($conn)->table('attendance')->insert([
            'user_id' => $uid,
            'site_id' => $siteId,
            'date' => Carbon::today()->toDateString(),
            'in_time' => Carbon::now()->toDateTimeString(),
            'status' => 'Present',
            'in_location' => $latitude . ',' . $longitude,
            'image' => $imagePath,
            'remarks' => 'Web Check-In',
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString()
        ]);

        return redirect()->back()->with('success', 'Clocked in successfully!');
    }

    public function webClockOut(Request $request)
    {
        $conn = session()->get('comp_db_conn_name');
        $uid = session()->get('uid');
        $comp_id = session()->get('comp_id');

        if (!$conn || !$uid) {
            return redirect('/login')->with('error', 'Please log in again.');
        }

        $attendance = DB::connection($conn)->table('attendance')
            ->where('user_id', $uid)
            ->where('date', Carbon::today()->toDateString())
            ->first();

        if (!$attendance) {
            return redirect()->back()->with('errorcode', 'You have not clocked in today yet.');
        }

        if ($attendance->out_time) {
            return redirect()->back()->with('errorcode', 'You have already clocked out today.');
        }

        $latitude = $request->input('latitude', '0.0000');
        $longitude = $request->input('longitude', '0.0000');
        $imageBase64 = $request->input('image_base64');

        $outImagePath = null;
        if ($imageBase64) {
            try {
                $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageBase64));
                $dirPath = public_path("images/app_images/{$comp_id}/attendance");
                if (!File::exists($dirPath)) {
                    File::makeDirectory($dirPath, 0755, true);
                }

                $fileName = time() . '_out.png';
                $filePath = $dirPath . '/' . $fileName;
                File::put($filePath, $imgData);
                $outImagePath = "images/app_images/{$comp_id}/attendance/{$fileName}";
            } catch (\Exception $e) {
                \Log::error("Failed to save checkout photo: " . $e->getMessage());
            }
        }

        DB::connection($conn)->table('attendance')
            ->where('id', $attendance->id)
            ->update([
                'out_time' => Carbon::now()->toDateTimeString(),
                'out_location' => $latitude . ',' . $longitude,
                'out_image' => $outImagePath,
                'remarks' => $attendance->remarks . ' | Web Check-Out',
                'updated_at' => Carbon::now()->toDateTimeString()
            ]);

        return redirect()->back()->with('success', 'Clocked out successfully!');
    }
}
