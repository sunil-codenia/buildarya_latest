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
        $hasAllSites = empty($assignedSites) || in_array('all', $assignedSites);

        // Fetch all sites and users
        $sitesQuery = DB::connection($conn)->table('sites');
        if (!$hasAllSites) {
            $sitesQuery->whereIn('id', $assignedSites);
        }
        $sites = $sitesQuery->get();

        $usersQuery = DB::connection($conn)->table('users');
        if (!$hasAllSites) {
            $usersQuery->where(function($q) use ($assignedSites) {
                foreach ($assignedSites as $sid) {
                    $q->orWhereRaw("FIND_IN_SET(?, site_id)", [$sid]);
                }
            });
        }
        $users = $usersQuery->get();

        $billParties = DB::connection($conn)->table('bills_party')
            ->where(function($q) {
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['Deactive', 'deactive', 'Disabled']);
            })
            ->orderBy('name', 'asc')
            ->get();

        // Fetch attendance logs for the selected date
        $attendanceLogsQuery = DB::connection($conn)->table('attendance')
            ->leftJoin('users', 'users.id', '=', 'attendance.user_id')
            ->leftJoin('bills_party', 'bills_party.id', '=', 'attendance.bills_party_id')
            ->leftJoin('sites', 'sites.id', '=', 'attendance.site_id')
            ->where('attendance.date', $date);

        if (!$hasAllSites) {
            $attendanceLogsQuery->where(function($q) use ($assignedSites) {
                $q->whereIn('attendance.site_id', $assignedSites)
                  ->orWhere(function($sub) use ($assignedSites) {
                      $sub->where(function($sub2) {
                          $sub2->whereNull('attendance.site_id')
                               ->orWhere('attendance.site_id', '=', 0);
                      });
                      $sub->where(function($sub3) use ($assignedSites) {
                          foreach ($assignedSites as $sid) {
                              $sub3->orWhereRaw("FIND_IN_SET(?, users.site_id)", [$sid]);
                          }
                      });
                  });
            });
        }

        $attendanceLogs = $attendanceLogsQuery->select(
                'attendance.*', 
                DB::raw('COALESCE(users.name, bills_party.name) as user_name'),
                DB::raw('COALESCE(users.username, "Labour Contractor") as user_username'),
                'sites.name as site_name',
                'users.site_id as user_site_id'
            )
            ->get();

        // Fallback site_name resolving in PHP for logs where site_id is empty/0
        $siteNamesMap = $sites->pluck('name', 'id')->toArray();
        foreach ($attendanceLogs as $log) {
            if (!empty($log->bills_party_id)) {
                $log->labour_count = DB::connection($conn)->table('contractor_labour_attendance')
                    ->where('attendance_id', $log->id)
                    ->count();
            } else {
                $log->labour_count = 0;
            }

            if (empty($log->site_name) && !empty($log->user_site_id)) {
                $userSites = explode(',', $log->user_site_id);
                $firstUserSiteId = $userSites[0] ?? null;
                if ($firstUserSiteId && isset($siteNamesMap[$firstUserSiteId])) {
                    $log->site_name = $siteNamesMap[$firstUserSiteId];
                }
            }
            if (empty($log->site_name)) {
                $log->site_name = 'Head Office';
            }
        }

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
        $hasAllSites = empty($assignedSites) || in_array('all', $assignedSites);
        if (!$hasAllSites && !in_array($request->site_id, $assignedSites)) {
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
            $attendanceId = $existing->id;
            if (!$imagePath) {
                unset($data['image']);
            }
            DB::connection($conn)->table('attendance')->where('id', $existing->id)->update($data);
        } else {
            $data['date'] = $dateString;
            $data['created_at'] = Carbon::now()->toDateTimeString();
            $attendanceId = DB::connection($conn)->table('attendance')->insertGetId($data);
        }

        // Save dynamic individual labours for Labour Contractor if provided
        if ($billsPartyId && $request->has('labour_name') && is_array($request->labour_name)) {
            $labourNames = $request->labour_name;
            $labourMobiles = $request->labour_mobile ?? [];
            $labourAddresses = $request->labour_address ?? [];
            $labourCheckins = $request->labour_checkin ?? [];
            $labourCheckouts = $request->labour_checkout ?? [];
            $labourPhotos = $request->file('labour_photo') ?? [];

            foreach ($labourNames as $idx => $lName) {
                if (empty(trim($lName))) continue;

                $lMobile = trim($labourMobiles[$idx] ?? '');
                $lAddress = trim($labourAddresses[$idx] ?? '');
                $lCheckin = !empty($labourCheckins[$idx]) ? Carbon::parse($dateString . ' ' . $labourCheckins[$idx])->toDateTimeString() : ($request->clock_in ? Carbon::parse($dateString . ' ' . $request->clock_in)->toDateTimeString() : Carbon::now()->toDateTimeString());
                $lCheckout = !empty($labourCheckouts[$idx]) ? Carbon::parse($dateString . ' ' . $labourCheckouts[$idx])->toDateTimeString() : ($request->clock_out ? Carbon::parse($dateString . ' ' . $request->clock_out)->toDateTimeString() : null);

                $lPhotoPath = null;
                if (isset($labourPhotos[$idx]) && $labourPhotos[$idx]->isValid()) {
                    $pFile = $labourPhotos[$idx];
                    $pDir = public_path("images/app_images/{$comp_id}/labour_photos");
                    if (!File::exists($pDir)) {
                        File::makeDirectory($pDir, 0755, true);
                    }
                    $pName = time() . '_labour_' . $idx . '_' . uniqid() . '.' . $pFile->getClientOriginalExtension();
                    $pFile->move($pDir, $pName);
                    $lPhotoPath = "images/app_images/{$comp_id}/labour_photos/{$pName}";
                }

                $labourId = null;
                if (!empty($lMobile)) {
                    $existingLabour = DB::connection($conn)->table('labours')->where('mobile_number', $lMobile)->first();
                    if ($existingLabour) {
                        $labourId = $existingLabour->id;
                        $updateData = ['name' => $lName];
                        if (!empty($lAddress)) $updateData['address'] = $lAddress;
                        if (!empty($lPhotoPath)) $updateData['photo'] = $lPhotoPath;
                        DB::connection($conn)->table('labours')->where('id', $labourId)->update($updateData);
                    }
                }

                if (!$labourId) {
                    $labourId = DB::connection($conn)->table('labours')->insertGetId([
                        'name' => $lName,
                        'mobile_number' => $lMobile ?: null,
                        'address' => $lAddress ?: null,
                        'photo' => $lPhotoPath,
                        'created_at' => Carbon::now()->toDateTimeString(),
                        'updated_at' => Carbon::now()->toDateTimeString()
                    ]);
                }

                DB::connection($conn)->table('contractor_labour_attendance')->insert([
                    'attendance_id' => $attendanceId,
                    'contractor_id' => $billsPartyId,
                    'labour_id' => $labourId,
                    'site_id' => $request->site_id,
                    'date' => $dateString,
                    'name' => $lName,
                    'mobile_number' => $lMobile ?: null,
                    'address' => $lAddress ?: null,
                    'photo' => $lPhotoPath,
                    'checkin_datetime' => $lCheckin,
                    'checkout_datetime' => $lCheckout,
                    'status' => $request->status == 'Leave' ? 'Absent' : $request->status,
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString()
                ]);
            }
        }

        return redirect()->back()->with('success', 'Attendance record saved successfully!');
    }

    public function searchLabourByMobile(Request $request)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $mobile = trim($request->input('mobile_number'));
        if (empty($mobile)) {
            return response()->json(['status' => 'not_found']);
        }

        $labour = DB::connection($conn)->table('labours')->where('mobile_number', $mobile)->first();
        if ($labour) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $labour->id,
                    'name' => $labour->name,
                    'mobile_number' => $labour->mobile_number,
                    'address' => $labour->address,
                    'photo' => $labour->photo ? asset($labour->photo) : null
                ]
            ]);
        }

        return response()->json(['status' => 'not_found']);
    }

    public function getContractorLabours($attendanceId)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $labours = DB::connection($conn)->table('contractor_labour_attendance')
            ->where('attendance_id', $attendanceId)
            ->get();

        foreach ($labours as $l) {
            $l->photo_url = $l->photo ? asset($l->photo) : null;
            $l->checkin_formatted = $l->checkin_datetime ? date('d M Y, h:i A', strtotime($l->checkin_datetime)) : '--';
            $l->checkout_formatted = $l->checkout_datetime ? date('d M Y, h:i A', strtotime($l->checkout_datetime)) : '--';
        }

        return response()->json(['status' => 'success', 'data' => $labours]);
    }

    public function clockOutLabour(Request $request)
    {
        $conn = session()->get('comp_db_conn_name');
        if (!$conn) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'id' => 'required',
            'checkout_datetime' => 'required'
        ]);

        $checkout = Carbon::parse($request->checkout_datetime)->toDateTimeString();

        DB::connection($conn)->table('contractor_labour_attendance')
            ->where('id', $request->id)
            ->update([
                'checkout_datetime' => $checkout,
                'updated_at' => Carbon::now()->toDateTimeString()
            ]);

        return response()->json(['status' => 'success', 'message' => 'Labour clocked out successfully!']);
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
        $hasAllSites = empty($assignedSites) || in_array('all', $assignedSites);
        if (!$hasAllSites && !in_array($request->site_id, $assignedSites)) {
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
            $cleanAssignedSites = array_filter($assignedSites, 'is_numeric');
            $siteId = !empty($cleanAssignedSites) ? reset($cleanAssignedSites) : null;
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

        sendAttendanceMarkedWhatsAppNotification($uid, Carbon::today()->toDateString(), Carbon::now()->toDateTimeString(), $siteId, $conn);

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

        sendAttendanceClockOutWhatsAppNotification($uid, Carbon::today()->toDateString(), Carbon::now()->toDateTimeString(), $attendance->site_id, $conn);

        return redirect()->back()->with('success', 'Clocked out successfully!');
    }
}
