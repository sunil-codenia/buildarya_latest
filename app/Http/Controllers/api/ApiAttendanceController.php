<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class ApiAttendanceController extends Controller
{
    /**
     * Resolve active Database Connection and User ID from Request or Bearer Token fallback
     */
    private function resolveTenant(Request $request)
    {
        $conn = $request->get('conn') ?? $request->post('conn');
        $user_id = $request->get('uid') ?? $request->post('uid');

        // Fallback: Resolve from Bearer token
        if ((!$conn || !$user_id) && $request->bearerToken()) {
            $tokenStr = $request->bearerToken();
            $tokenId = null;
            if (strpos($tokenStr, '|') !== false) {
                [$tokenId, $tokenStr] = explode('|', $tokenStr, 2);
            }
            $token = DB::connection('mysql')->table('personal_access_tokens')->where('id', $tokenId)->first();
            if ($token) {
                $conn = $conn ?? $token->name;
                $user_id = $user_id ?? $token->tokenable_id;
            }
        }

        if (!$conn) {
            $conn = config('database.default');
        }

        return ['conn' => $conn, 'uid' => $user_id];
    }

    /**
     * Clock-In for a site/project
     */
    public function clockIn(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            if (!$uid) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Unauthorized or User ID is missing!'
                ]);
            }

            $date = $request->input('date') ?? Carbon::now()->format('Y-m-d');
            $in_time = $request->input('in_time') ?? Carbon::now()->format('Y-m-d H:i:s');
            $site_id = $request->input('site_id');
            if (empty($site_id) || $site_id === 'null' || $site_id === 'undefined' || $site_id == 0) {
                $userRecord = DB::connection($conn)->table('users')->where('id', $uid)->first();
                if ($userRecord && !empty($userRecord->site_id)) {
                    $userSites = explode(',', (string)$userRecord->site_id);
                    $firstUserSite = $userSites[0] ?? null;
                    if (is_numeric($firstUserSite)) {
                        $site_id = $firstUserSite;
                    }
                }
                if (empty($site_id) || $site_id === 'null' || $site_id === 'undefined' || $site_id == 0) {
                    $firstSite = DB::connection($conn)->table('sites')->first();
                    $site_id = $firstSite ? $firstSite->id : null;
                }
            }
            $in_location = $request->input('in_location');
            $remarks = $request->input('remarks');

            // Check if already clocked in for this date
            $exists = DB::connection($conn)->table('attendance')
                ->where('user_id', $uid)
                ->where('date', $date)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'You have already clocked in for ' . $date . '!'
                ]);
            }

            // Resolve company uid for file upload path
            $company = DB::connection('mysql')->table('companies')->where('db_conn_name', $conn)->first();
            $comp_id = $company ? $company->uid : $conn;

            $imagePath = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $dirPath = public_path("images/app_images/{$comp_id}/attendance");
                if (!File::exists($dirPath)) {
                    File::makeDirectory($dirPath, 0755, true);
                }
                $fileName = time() . '_in_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($dirPath, $fileName);
                $imagePath = "images/app_images/{$comp_id}/attendance/{$fileName}";
            }

            $attendance_id = DB::connection($conn)->table('attendance')->insertGetId([
                'user_id' => $uid,
                'site_id' => $site_id,
                'date' => $date,
                'in_time' => $in_time,
                'status' => 'Present',
                'in_location' => $in_location,
                'remarks' => $remarks,
                'image' => $imagePath,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            addActivity($attendance_id, 'attendance', "User clocked in for " . $date, 13, $uid, $conn);

            sendAttendanceMarkedWhatsAppNotification($uid, $date, $in_time, $site_id, $conn);

            $record = DB::connection($conn)->table('attendance')->where('id', $attendance_id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Clocked in successfully!',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to clock in: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Clock-Out for today/specified date
     */
    public function clockOut(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            if (!$uid) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'Unauthorized or User ID is missing!'
                ]);
            }

            $date = $request->input('date') ?? Carbon::now()->format('Y-m-d');
            $out_time = $request->input('out_time') ?? Carbon::now()->format('Y-m-d H:i:s');
            $out_location = $request->input('out_location');
            $remarks = $request->input('remarks');

            // Find clock-in entry
            $attendance = DB::connection($conn)->table('attendance')
                ->where('user_id', $uid)
                ->where('date', $date)
                ->first();

            if (!$attendance) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'You must clock in first before clocking out!'
                ]);
            }

            if ($attendance->out_time) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'You have already clocked out for today!'
                ]);
            }

            // Resolve company uid for file upload path
            $company = DB::connection('mysql')->table('companies')->where('db_conn_name', $conn)->first();
            $comp_id = $company ? $company->uid : $conn;

            $outImagePath = null;
            $fileInputKey = $request->hasFile('out_image') ? 'out_image' : 'image';
            if ($request->hasFile($fileInputKey)) {
                $file = $request->file($fileInputKey);
                $dirPath = public_path("images/app_images/{$comp_id}/attendance");
                if (!File::exists($dirPath)) {
                    File::makeDirectory($dirPath, 0755, true);
                }
                $fileName = time() . '_out_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($dirPath, $fileName);
                $outImagePath = "images/app_images/{$comp_id}/attendance/{$fileName}";
            }

            $updatedRemarks = $attendance->remarks;
            if ($remarks) {
                $updatedRemarks = $updatedRemarks ? $updatedRemarks . ' | Out: ' . $remarks : $remarks;
            }

            $updateData = [
                'out_time' => $out_time,
                'out_location' => $out_location,
                'remarks' => $updatedRemarks,
                'updated_at' => now()
            ];

            if ($outImagePath) {
                $updateData['out_image'] = $outImagePath;
            }

            DB::connection($conn)->table('attendance')
                ->where('id', $attendance->id)
                ->update($updateData);

            addActivity($attendance->id, 'attendance', "User clocked out for " . $date, 13, $uid, $conn);

            sendAttendanceClockOutWhatsAppNotification($uid, $date, $out_time, $attendance->site_id, $conn);

            $record = DB::connection($conn)->table('attendance')->where('id', $attendance->id)->first();

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Clocked out successfully!',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to clock out: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Retrieve Attendance History
     */
    public function history(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            
            $targetUserId = $request->input('user_id');

            $userRecord = DB::connection($conn)->table('users')->where('id', $tenant['uid'])->first();
            $isSuperAdmin = $userRecord && ($userRecord->role_id == 1);

            $perm = DB::connection($conn)->table('user_permission')
                ->where('user_id', $tenant['uid'])
                ->where('module_id', 13)
                ->first();

            $canManage = $isSuperAdmin || ($perm && ($perm->can_add == 1 || $perm->can_edit == 1));

            if (!$canManage) {
                $targetUserId = $tenant['uid'];
            }

            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $siteId = $request->input('site_id');
            $status = $request->input('status');

            $query = DB::connection($conn)->table('attendance')
                ->leftJoin('users', 'users.id', '=', 'attendance.user_id')
                ->leftJoin('sites', 'sites.id', '=', 'attendance.site_id')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'attendance.bills_party_id')
                ->select(
                    'attendance.*', 
                    DB::raw('COALESCE(users.name, bills_party.name) as user_name'), 
                    DB::raw('COALESCE(users.username, "Labour Contractor") as user_username'),
                    'users.name as real_user_name',
                    'bills_party.name as contractor_name',
                    'sites.name as site_name'
                );

            if ($targetUserId) {
                $query->where('attendance.user_id', $targetUserId);
            }
            if ($startDate) {
                $query->where('attendance.date', '>=', $startDate);
            }
            if ($endDate) {
                $query->where('attendance.date', '<=', $endDate);
            }
            if ($siteId) {
                $query->where('attendance.site_id', $siteId);
            }
            if ($status) {
                $query->where('attendance.status', $status);
            }

            $records = $query->orderBy('attendance.date', 'desc')->get();

            foreach ($records as $rec) {
                $rec->image_url = $rec->image ? asset($rec->image) : null;
                $rec->out_image_url = $rec->out_image ? asset($rec->out_image) : null;

                if ($rec->bills_party_id) {
                    $contractorLabours = DB::connection($conn)->table('contractor_labour_attendance')
                        ->where(function($q) use ($rec) {
                            $q->where('attendance_id', $rec->id)
                              ->orWhere(function($q2) use ($rec) {
                                  $q2->where('contractor_id', $rec->bills_party_id)
                                     ->where('date', $rec->date);
                              });
                        })
                        ->get();
                    foreach ($contractorLabours as $cl) {
                        $cl->photo_url = $cl->photo ? asset($cl->photo) : null;
                    }
                    $rec->labours = $contractorLabours;
                } else {
                    $rec->labours = [];
                }
            }

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $records
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to retrieve attendance history: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Retrieve Monthly Attendance Summary metrics
     */
    public function summary(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];

            $targetUserId = $request->input('user_id');

            $userRecord = DB::connection($conn)->table('users')->where('id', $tenant['uid'])->first();
            $isSuperAdmin = $userRecord && ($userRecord->role_id == 1);

            $perm = DB::connection($conn)->table('user_permission')
                ->where('user_id', $tenant['uid'])
                ->where('module_id', 13)
                ->first();

            $canManage = $isSuperAdmin || ($perm && ($perm->can_add == 1 || $perm->can_edit == 1));

            if (!$canManage) {
                $targetUserId = $tenant['uid'];
            } elseif (empty($targetUserId)) {
                $targetUserId = null; // Admin fetching global summary
            }

            $month = $request->input('month') ?? Carbon::now()->format('m');
            $year = $request->input('year') ?? Carbon::now()->format('Y');

            $query = DB::connection($conn)->table('attendance')
                ->whereMonth('date', $month)
                ->whereYear('date', $year);

            if ($targetUserId) {
                $query->where('user_id', $targetUserId);
            }

            $records = $query->get();

            $metrics = [
                'total_present' => $records->where('status', 'Present')->count(),
                'total_absent' => $records->where('status', 'Absent')->count(),
                'total_half_day' => $records->where('status', 'Half Day')->count(),
                'total_leave' => $records->where('status', 'Leave')->count(),
                'total_holiday' => $records->where('status', 'Holiday')->count(),
                'records' => $records
            ];

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to retrieve monthly summary: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Manual Override / Log by Supervisor or Admin
     */
    public function logManual(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            // Check permissions
            $userRecord = DB::connection($conn)->table('users')->where('id', $uid)->first();
            $isSuperAdmin = $userRecord && ($userRecord->role_id == 1);

            $perm = DB::connection($conn)->table('user_permission')
                ->where('user_id', $uid)
                ->where('module_id', 13)
                ->first();

            $canManage = $isSuperAdmin || ($perm && ($perm->can_add == 1 || $perm->can_edit == 1));

            if (!$canManage) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'You do not have permission to manually log attendance.'
                ]);
            }

            $targetUserId = $request->input('user_id');
            $billsPartyId = $request->input('bills_party_id');
            $date = $request->input('date');
            $status = $request->input('status'); // Present, Absent, Half Day, Leave
            $site_id = $request->input('site_id');
            if (empty($site_id) || $site_id === 'null' || $site_id === 'undefined' || $site_id == 0) {
                if ($targetUserId && $targetUserId !== 'labour_contractor' && $targetUserId !== 'labour contractor' && is_numeric($targetUserId)) {
                    $targetUser = DB::connection($conn)->table('users')->where('id', $targetUserId)->first();
                    if ($targetUser && !empty($targetUser->site_id)) {
                        $userSites = explode(',', (string)$targetUser->site_id);
                        $firstUserSite = $userSites[0] ?? null;
                        if (is_numeric($firstUserSite)) {
                            $site_id = $firstUserSite;
                        }
                    }
                }
                if (empty($site_id) || $site_id === 'null' || $site_id === 'undefined' || $site_id == 0) {
                    $firstSite = DB::connection($conn)->table('sites')->first();
                    $site_id = $firstSite ? $firstSite->id : null;
                }
            }
            $in_time = $request->input('in_time') ?? $request->input('clock_in');
            $out_time = $request->input('out_time') ?? $request->input('clock_out');
            $remarks = $request->input('remarks');

            if (!$targetUserId || !$date || !$status) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'User ID, Date, and Status are required fields!'
                ]);
            }

            if ($targetUserId === 'labour_contractor' || $targetUserId === 'labour contractor' || !is_numeric($targetUserId)) {
                $targetUserId = null;
                if (!$billsPartyId) {
                    return response()->json([
                        'status' => 'Failed',
                        'status_code' => '300',
                        'message' => 'Labour Contractor (bills_party_id) is required when user_id is labour contractor!'
                    ]);
                }
            } else {
                $billsPartyId = null;
            }

            // Resolve company uid for file upload path
            $company = DB::connection('mysql')->table('companies')->where('db_conn_name', $conn)->first();
            $comp_id = $company ? $company->uid : $conn;

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

            $inTimeFormatted = null;
            if ($in_time) {
                if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $in_time)) {
                    $inTimeFormatted = Carbon::parse($date . ' ' . $in_time)->toDateTimeString();
                } else {
                    $inTimeFormatted = Carbon::parse($in_time)->toDateTimeString();
                }
            }

            $outTimeFormatted = null;
            if ($out_time) {
                if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $out_time)) {
                    $outTimeFormatted = Carbon::parse($date . ' ' . $out_time)->toDateTimeString();
                } else {
                    $outTimeFormatted = Carbon::parse($out_time)->toDateTimeString();
                }
            }

            $in_location = $request->input('in_location') ?? $request->input('gps_lat') ?? $request->input('latitude') ?? 'Manual';
            $out_location = $request->input('out_location') ?? $request->input('gps_lng') ?? $request->input('longitude') ?? 'Manual';

            // Check if record already exists for this date & (user or contractor)
            $query = DB::connection($conn)->table('attendance')->where('date', $date);
            if ($targetUserId) {
                $query->where('user_id', $targetUserId);
            } else {
                $query->where('bills_party_id', $billsPartyId);
            }
            $existing = $query->first();

            $data = [
                'user_id' => $targetUserId,
                'bills_party_id' => $billsPartyId,
                'site_id' => $site_id,
                'date' => $date,
                'status' => $status,
                'in_time' => $inTimeFormatted,
                'out_time' => $outTimeFormatted,
                'in_location' => $in_location,
                'out_location' => $out_location,
                'remarks' => $remarks ? 'Manual Override: ' . $remarks : 'Manual Override',
                'updated_at' => now()
            ];

            if ($imagePath) {
                $data['image'] = $imagePath;
            }

            if ($existing) {
                if ($imagePath && $existing->image && File::exists(public_path($existing->image))) {
                    File::delete(public_path($existing->image));
                }
                DB::connection($conn)->table('attendance')
                    ->where('id', $existing->id)
                    ->update($data);
                
                $id = $existing->id;
                addActivity($id, 'attendance', "Manual override updated for " . ($targetUserId ? "user $targetUserId" : "contractor $billsPartyId") . " on $date", 13, $uid, $conn);
            } else {
                $data['created_at'] = now();
                $id = DB::connection($conn)->table('attendance')->insertGetId($data);
                addActivity($id, 'attendance', "Manual attendance logged for " . ($targetUserId ? "user $targetUserId" : "contractor $billsPartyId") . " on $date", 13, $uid, $conn);
            }

            // Save/Update individual contractor labours if billsPartyId is present
            if ($billsPartyId) {
                $laboursList = [];

                // 1. Form-data array inputs matching website submission (labour_name[], labour_mobile[], etc.)
                if ($request->has('labour_name') && is_array($request->labour_name)) {
                    $names = $request->input('labour_name');
                    $mobiles = $request->input('labour_mobile') ?? $request->input('labour_mobile_number') ?? [];
                    $addresses = $request->input('labour_address') ?? [];
                    $checkins = $request->input('labour_checkin') ?? $request->input('labour_checkin_time') ?? [];
                    $checkouts = $request->input('labour_checkout') ?? $request->input('labour_checkout_time') ?? [];

                    foreach ($names as $idx => $lName) {
                        if (empty(trim($lName))) continue;
                        $laboursList[] = [
                            'name' => trim($lName),
                            'mobile_number' => trim($mobiles[$idx] ?? ''),
                            'address' => trim($addresses[$idx] ?? ''),
                            'checkin_time' => $checkins[$idx] ?? null,
                            'checkout_time' => $checkouts[$idx] ?? null,
                            'photo_index' => $idx
                        ];
                    }
                }

                // 2. Fallback: JSON array parameter 'labours'
                if (empty($laboursList) && $request->has('labours')) {
                    $rawLabours = $request->input('labours');
                    if (is_string($rawLabours)) {
                        $rawLabours = json_decode($rawLabours, true);
                    }
                    if (is_array($rawLabours)) {
                        $laboursList = $rawLabours;
                    }
                }

                if (!empty($laboursList)) {
                    $labourFiles = $request->file('labour_photo') ?? $request->file('labour_photos') ?? [];

                    foreach ($laboursList as $index => $lData) {
                        if (!is_array($lData)) continue;
                        $lName = trim($lData['name'] ?? $lData['labour_name'] ?? '');
                        if (empty($lName)) continue;

                        $lMobile = trim($lData['mobile_number'] ?? $lData['mobile'] ?? $lData['labour_mobile'] ?? '');
                        $lAddress = trim($lData['address'] ?? $lData['labour_address'] ?? '');

                        $lInTimeStr = $lData['checkin_time'] ?? $lData['checkin'] ?? $lData['checkin_datetime'] ?? null;
                        $lOutTimeStr = $lData['checkout_time'] ?? $lData['checkout'] ?? $lData['checkout_datetime'] ?? null;

                        $lCheckin = $lInTimeStr 
                            ? (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $lInTimeStr) ? Carbon::parse($date . ' ' . $lInTimeStr)->toDateTimeString() : Carbon::parse($lInTimeStr)->toDateTimeString()) 
                            : ($inTimeFormatted ?? Carbon::now()->toDateTimeString());

                        $lCheckout = $lOutTimeStr 
                            ? (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $lOutTimeStr) ? Carbon::parse($date . ' ' . $lOutTimeStr)->toDateTimeString() : Carbon::parse($lOutTimeStr)->toDateTimeString()) 
                            : $outTimeFormatted;

                        // File upload check
                        $lPhotoPath = null;
                        $photoFileKey = isset($lData['photo_index']) ? $lData['photo_index'] : $index;

                        if (isset($labourFiles[$photoFileKey]) && $labourFiles[$photoFileKey]->isValid()) {
                            $pFile = $labourFiles[$photoFileKey];
                            $pDir = public_path("images/app_images/{$comp_id}/labour_photos");
                            if (!File::exists($pDir)) {
                                File::makeDirectory($pDir, 0755, true);
                            }
                            $pName = time() . '_labour_' . $photoFileKey . '_' . uniqid() . '.' . $pFile->getClientOriginalExtension();
                            $pFile->move($pDir, $pName);
                            $lPhotoPath = "images/app_images/{$comp_id}/labour_photos/{$pName}";
                        } elseif ($request->hasFile("labours.{$index}.photo")) {
                            $pFile = $request->file("labours.{$index}.photo");
                            if ($pFile->isValid()) {
                                $pDir = public_path("images/app_images/{$comp_id}/labour_photos");
                                if (!File::exists($pDir)) {
                                    File::makeDirectory($pDir, 0755, true);
                                }
                                $pName = time() . '_labour_' . $index . '_' . uniqid() . '.' . $pFile->getClientOriginalExtension();
                                $pFile->move($pDir, $pName);
                                $lPhotoPath = "images/app_images/{$comp_id}/labour_photos/{$pName}";
                            }
                        }

                        // Master labours record insert/update
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
                            'attendance_id' => $id,
                            'contractor_id' => $billsPartyId,
                            'labour_id' => $labourId,
                            'site_id' => $site_id,
                            'date' => $date,
                            'name' => $lName,
                            'mobile_number' => $lMobile ?: null,
                            'address' => $lAddress ?: null,
                            'photo' => $lPhotoPath,
                            'checkin_datetime' => $lCheckin,
                            'checkout_datetime' => $lCheckout,
                            'status' => $status == 'Leave' ? 'Absent' : $status,
                            'created_at' => Carbon::now()->toDateTimeString(),
                            'updated_at' => Carbon::now()->toDateTimeString()
                        ]);
                    }
                }
            }

            $record = DB::connection($conn)->table('attendance')
                ->leftJoin('users', 'users.id', '=', 'attendance.user_id')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'attendance.bills_party_id')
                ->select(
                    'attendance.*', 
                    DB::raw('COALESCE(users.name, bills_party.name) as user_name'),
                    DB::raw('COALESCE(users.username, "Labour Contractor") as user_username')
                )
                ->where('attendance.id', $id)
                ->first();

            if ($record && $record->bills_party_id) {
                $contractorLabours = DB::connection($conn)->table('contractor_labour_attendance')
                    ->where('attendance_id', $id)
                    ->get();
                foreach ($contractorLabours as $cl) {
                    $cl->photo_url = $cl->photo ? asset($cl->photo) : null;
                }
                $record->labours = $contractorLabours;
            }

            return response()->json([
                'status' => 'Ok',
                'status_code' => '200',
                'message' => 'Attendance logged successfully!',
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '300',
                'message' => 'Failed to manually log attendance: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get details of a single attendance record by ID
     */
    public function show(Request $request, $id)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            $record = DB::connection($conn)->table('attendance')
                ->leftJoin('users', 'users.id', '=', 'attendance.user_id')
                ->leftJoin('sites', 'sites.id', '=', 'attendance.site_id')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'attendance.bills_party_id')
                ->select(
                    'attendance.*', 
                    DB::raw('COALESCE(users.name, bills_party.name) as user_name'),
                    DB::raw('COALESCE(users.username, "Labour Contractor") as user_username'),
                    'users.name as real_user_name',
                    'bills_party.name as contractor_name',
                    'sites.name as site_name'
                )
                ->where('attendance.id', $id)
                ->first();

            if (!$record) {
                return response()->json(['status' => 'Failed', 'status_code' => '404', 'message' => 'Record not found.']);
            }

            $userRecord = DB::connection($conn)->table('users')->where('id', $tenant['uid'])->first();
            $isSuperAdmin = $userRecord && ($userRecord->role_id == 1);

            $perm = DB::connection($conn)->table('user_permission')
                ->where('user_id', $uid)
                ->where('module_id', 13)
                ->first();

            $canManage = $isSuperAdmin || ($perm && ($perm->can_add == 1 || $perm->can_edit == 1 || $perm->can_view == 1));
            
            if (!$canManage && $record->user_id != $uid) {
                return response()->json(['status' => 'Failed', 'status_code' => '403', 'message' => 'Unauthorized access.']);
            }

            $record->image_url = $record->image ? asset($record->image) : null;
            $record->out_image_url = $record->out_image ? asset($record->out_image) : null;

            if ($record->bills_party_id) {
                $contractorLabours = DB::connection($conn)->table('contractor_labour_attendance')
                    ->where(function($q) use ($id, $record) {
                        $q->where('attendance_id', $id)
                          ->orWhere(function($q2) use ($record) {
                              $q2->where('contractor_id', $record->bills_party_id)
                                 ->where('date', $record->date);
                          });
                    })
                    ->get();
                foreach ($contractorLabours as $cl) {
                    $cl->photo_url = $cl->photo ? asset($cl->photo) : null;
                }
                $record->labours = $contractorLabours;
            } else {
                $record->labours = [];
            }

            return response()->json(['status' => 'Ok', 'status_code' => '200', 'data' => $record]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '500', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update an attendance record by ID
     */
    public function update(Request $request, $id)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            $record = DB::connection($conn)->table('attendance')->where('id', $id)->first();

            if (!$record) {
                return response()->json(['status' => 'Failed', 'status_code' => '404', 'message' => 'Record not found.']);
            }

            $userRecord = DB::connection($conn)->table('users')->where('id', $tenant['uid'])->first();
            $isSuperAdmin = $userRecord && ($userRecord->role_id == 1);

            $perm = DB::connection($conn)->table('user_permission')
                ->where('user_id', $uid)
                ->where('module_id', 13)
                ->first();

            $canManage = $isSuperAdmin || ($perm && ($perm->can_edit == 1 || $perm->can_add == 1));
            
            if (!$canManage && $record->user_id != $uid) {
                return response()->json(['status' => 'Failed', 'status_code' => '403', 'message' => 'Unauthorized access.']);
            }

            $updateData = [];
            
            // Resolve company uid for file upload path
            $company = DB::connection('mysql')->table('companies')->where('db_conn_name', $conn)->first();
            $comp_id = $company ? $company->uid : $conn;

            if ($request->hasFile('image')) {
                // Delete old image
                if ($record->image && File::exists(public_path($record->image))) {
                    File::delete(public_path($record->image));
                }
                $file = $request->file('image');
                $dirPath = public_path("images/app_images/{$comp_id}/attendance");
                if (!File::exists($dirPath)) {
                    File::makeDirectory($dirPath, 0755, true);
                }
                $fileName = time() . '_manual_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($dirPath, $fileName);
                $updateData['image'] = "images/app_images/{$comp_id}/attendance/{$fileName}";
            }

            if ($request->has('site_id')) {
                $site_id = $request->input('site_id');
                if (empty($site_id) || $site_id === 'null' || $site_id === 'undefined' || $site_id == 0) {
                    $site_id = null;
                    if ($record->user_id) {
                        $userRecord = DB::connection($conn)->table('users')->where('id', $record->user_id)->first();
                        if ($userRecord && !empty($userRecord->site_id)) {
                            $userSites = explode(',', (string)$userRecord->site_id);
                            $firstUserSite = $userSites[0] ?? null;
                            if (is_numeric($firstUserSite)) {
                                $site_id = $firstUserSite;
                            }
                        }
                    }
                    if (empty($site_id) || $site_id === 'null' || $site_id === 'undefined' || $site_id == 0) {
                        $firstSite = DB::connection($conn)->table('sites')->first();
                        $site_id = $firstSite ? $firstSite->id : null;
                    }
                }
                $updateData['site_id'] = $site_id;
            }
            if ($request->has('status')) $updateData['status'] = $request->input('status');
            if ($request->has('date')) $updateData['date'] = $request->input('date');
            
            $dateVal = $request->input('date') ?? $record->date;

            $in_time = $request->input('in_time') ?? $request->input('clock_in');
            if ($in_time !== null) {
                if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $in_time)) {
                    $updateData['in_time'] = Carbon::parse($dateVal . ' ' . $in_time)->toDateTimeString();
                } else {
                    $updateData['in_time'] = Carbon::parse($in_time)->toDateTimeString();
                }
            }

            $out_time = $request->input('out_time') ?? $request->input('clock_out');
            if ($out_time !== null) {
                if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $out_time)) {
                    $updateData['out_time'] = Carbon::parse($dateVal . ' ' . $out_time)->toDateTimeString();
                } else {
                    $updateData['out_time'] = Carbon::parse($out_time)->toDateTimeString();
                }
            }

            $in_location = $request->input('in_location') ?? $request->input('gps_lat') ?? $request->input('latitude');
            if ($in_location !== null) {
                $updateData['in_location'] = $in_location;
            }

            $out_location = $request->input('out_location') ?? $request->input('gps_lng') ?? $request->input('longitude');
            if ($out_location !== null) {
                $updateData['out_location'] = $out_location;
            }

            if ($request->has('user_id') && $canManage) {
                $targetUserId = $request->input('user_id');
                if ($targetUserId === 'labour_contractor' || $targetUserId === 'labour contractor' || !is_numeric($targetUserId)) {
                    $updateData['user_id'] = null;
                    if ($request->has('bills_party_id')) {
                        $updateData['bills_party_id'] = $request->input('bills_party_id');
                    }
                } else {
                    $updateData['user_id'] = $targetUserId;
                    $updateData['bills_party_id'] = null;
                }
            } elseif ($request->has('bills_party_id') && $canManage) {
                $updateData['bills_party_id'] = $request->input('bills_party_id');
            }

            if ($request->has('remarks')) $updateData['remarks'] = $request->input('remarks');

            if (!empty($updateData)) {
                $updateData['updated_at'] = now();
                DB::connection($conn)->table('attendance')->where('id', $id)->update($updateData);
                addActivity($id, 'attendance', "Attendance updated via API.", 13, $uid, $conn);
            }

            // Handle deleted labour ids if passed during update
            if ($request->has('deleted_labour_ids') && !empty($request->deleted_labour_ids)) {
                $delIds = is_array($request->deleted_labour_ids) ? $request->deleted_labour_ids : explode(',', $request->deleted_labour_ids);
                $delIds = array_filter($delIds, 'is_numeric');
                if (!empty($delIds)) {
                    DB::connection($conn)->table('contractor_labour_attendance')->whereIn('id', $delIds)->delete();
                }
            }

            // Handle contractor labours update or insertion if labours or labour_name passed
            $targetPartyId = $updateData['bills_party_id'] ?? $record->bills_party_id;
            if ($targetPartyId) {
                $laboursList = [];
                if ($request->has('labours')) {
                    $rawLabours = $request->input('labours');
                    if (is_string($rawLabours)) $rawLabours = json_decode($rawLabours, true);
                    if (is_array($rawLabours)) $laboursList = $rawLabours;
                }
                if (empty($laboursList) && $request->has('labour_name') && is_array($request->labour_name)) {
                    $lIds = $request->input('labour_id') ?? [];
                    $names = $request->input('labour_name');
                    $mobiles = $request->input('labour_mobile') ?? $request->input('labour_mobile_number') ?? [];
                    $addresses = $request->input('labour_address') ?? [];
                    $checkins = $request->input('labour_checkin') ?? $request->input('labour_checkin_time') ?? [];
                    $checkouts = $request->input('labour_checkout') ?? $request->input('labour_checkout_time') ?? [];

                    foreach ($names as $idx => $lName) {
                        if (empty(trim($lName))) continue;
                        $laboursList[] = [
                            'id' => $lIds[$idx] ?? null,
                            'name' => trim($lName),
                            'mobile_number' => trim($mobiles[$idx] ?? ''),
                            'address' => trim($addresses[$idx] ?? ''),
                            'checkin_time' => $checkins[$idx] ?? null,
                            'checkout_time' => $checkouts[$idx] ?? null,
                            'photo_index' => $idx
                        ];
                    }
                }

                if (!empty($laboursList)) {
                    $targetDate = $updateData['date'] ?? $record->date;
                    $targetSite = $updateData['site_id'] ?? $record->site_id;
                    $targetStatus = $updateData['status'] ?? $record->status;

                    foreach ($laboursList as $index => $lData) {
                        if (!is_array($lData)) continue;
                        $lName = trim($lData['name'] ?? $lData['labour_name'] ?? '');
                        if (empty($lName)) continue;

                        $lId = $lData['id'] ?? null;
                        $lMobile = trim($lData['mobile_number'] ?? $lData['mobile'] ?? '');
                        $lAddress = trim($lData['address'] ?? '');

                        $lInTimeStr = $lData['checkin_time'] ?? $lData['checkin'] ?? null;
                        $lOutTimeStr = $lData['checkout_time'] ?? $lData['checkout'] ?? null;

                        $lCheckin = $lInTimeStr 
                            ? (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $lInTimeStr) ? Carbon::parse($targetDate . ' ' . $lInTimeStr)->toDateTimeString() : Carbon::parse($lInTimeStr)->toDateTimeString()) 
                            : null;
                        $lCheckout = $lOutTimeStr 
                            ? (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $lOutTimeStr) ? Carbon::parse($targetDate . ' ' . $lOutTimeStr)->toDateTimeString() : Carbon::parse($lOutTimeStr)->toDateTimeString()) 
                            : null;

                        $lPhotoPath = null;
                        $idx = $lData['photo_index'] ?? $index;
                        if ($request->hasFile('labour_photo') && isset($request->file('labour_photo')[$idx])) {
                            $lFile = $request->file('labour_photo')[$idx];
                            if ($lFile && $lFile->isValid()) {
                                $dirPath = public_path("images/app_images/{$comp_id}/labours");
                                if (!File::exists($dirPath)) {
                                    File::makeDirectory($dirPath, 0755, true);
                                }
                                $fileName = time() . '_labour_' . uniqid() . '.' . $lFile->getClientOriginalExtension();
                                $lFile->move($dirPath, $fileName);
                                $lPhotoPath = "images/app_images/{$comp_id}/labours/{$fileName}";
                            }
                        }

                        if ($lId) {
                            $existingCL = DB::connection($conn)->table('contractor_labour_attendance')->where('id', $lId)->first();
                            if ($existingCL) {
                                $clUpdate = [
                                    'name' => $lName,
                                    'mobile_number' => $lMobile ?: null,
                                    'address' => $lAddress ?: null,
                                    'updated_at' => now()
                                ];
                                if ($lCheckin) $clUpdate['checkin_datetime'] = $lCheckin;
                                if ($lCheckout) $clUpdate['checkout_datetime'] = $lCheckout;
                                if ($lPhotoPath) $clUpdate['photo'] = $lPhotoPath;
                                DB::connection($conn)->table('contractor_labour_attendance')->where('id', $lId)->update($clUpdate);
                                continue;
                            }
                        }

                        // Create new labour entry
                        DB::connection($conn)->table('contractor_labour_attendance')->insert([
                            'attendance_id' => $id,
                            'contractor_id' => $targetPartyId,
                            'site_id' => $targetSite,
                            'date' => $targetDate,
                            'name' => $lName,
                            'mobile_number' => $lMobile ?: null,
                            'address' => $lAddress ?: null,
                            'photo' => $lPhotoPath,
                            'checkin_datetime' => $lCheckin ?? Carbon::now()->toDateTimeString(),
                            'checkout_datetime' => $lCheckout,
                            'status' => $targetStatus == 'Leave' ? 'Absent' : $targetStatus,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }

            $updatedRecord = DB::connection($conn)->table('attendance')
                ->leftJoin('users', 'users.id', '=', 'attendance.user_id')
                ->leftJoin('sites', 'sites.id', '=', 'attendance.site_id')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'attendance.bills_party_id')
                ->select(
                    'attendance.*', 
                    DB::raw('COALESCE(users.name, bills_party.name) as user_name'),
                    DB::raw('COALESCE(users.username, "Labour Contractor") as user_username'),
                    'users.name as real_user_name',
                    'bills_party.name as contractor_name',
                    'sites.name as site_name'
                )
                ->where('attendance.id', $id)
                ->first();

            if ($updatedRecord) {
                $updatedRecord->image_url = $updatedRecord->image ? asset($updatedRecord->image) : null;
                $updatedRecord->out_image_url = $updatedRecord->out_image ? asset($updatedRecord->out_image) : null;

                if ($updatedRecord->bills_party_id) {
                    $contractorLabours = DB::connection($conn)->table('contractor_labour_attendance')
                        ->where(function($q) use ($id, $updatedRecord) {
                            $q->where('attendance_id', $id)
                              ->orWhere(function($q2) use ($updatedRecord) {
                                  $q2->where('contractor_id', $updatedRecord->bills_party_id)
                                     ->where('date', $updatedRecord->date);
                              });
                        })
                        ->get();
                    foreach ($contractorLabours as $cl) {
                        $cl->photo_url = $cl->photo ? asset($cl->photo) : null;
                    }
                    $updatedRecord->labours = $contractorLabours;
                } else {
                    $updatedRecord->labours = [];
                }
            }

            return response()->json(['status' => 'Ok', 'status_code' => '200', 'data' => $updatedRecord, 'message' => 'Attendance updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '500', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Delete an attendance record by ID
     */
    public function destroy(Request $request, $id)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $uid = $tenant['uid'];

            $record = DB::connection($conn)->table('attendance')->where('id', $id)->first();

            if (!$record) {
                return response()->json(['status' => 'Failed', 'status_code' => '404', 'message' => 'Record not found.']);
            }

            $userRecord = DB::connection($conn)->table('users')->where('id', $tenant['uid'])->first();
            $isSuperAdmin = $userRecord && ($userRecord->role_id == 1);

            $perm = DB::connection($conn)->table('user_permission')
                ->where('user_id', $uid)
                ->where('module_id', 13)
                ->first();

            $canDelete = $isSuperAdmin || ($perm && $perm->can_delete == 1);
            
            if (!$canDelete && $record->user_id != $uid) {
                return response()->json(['status' => 'Failed', 'status_code' => '403', 'message' => 'Unauthorized to delete this record.']);
            }

            DB::connection($conn)->table('contractor_labour_attendance')->where('attendance_id', $id)->delete();
            DB::connection($conn)->table('attendance')->where('id', $id)->delete();
            addActivity(0, 'attendance', "Attendance record deleted via API.", 13, $uid, $conn);

            return response()->json(['status' => 'Ok', 'status_code' => '200', 'message' => 'Attendance deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '500', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Search labour details by mobile number
     */
    public function searchLabour(Request $request)
    {
        try {
            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];

            $mobile = trim($request->input('mobile_number') ?? $request->input('mobile') ?? '');
            if (empty($mobile)) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '400',
                    'message' => 'Mobile number is required.'
                ]);
            }

            $labour = DB::connection($conn)->table('labours')
                ->where('mobile_number', $mobile)
                ->first();

            if ($labour) {
                return response()->json([
                    'status' => 'Ok',
                    'status_code' => '200',
                    'message' => 'Labour found successfully.',
                    'data' => [
                        'id' => $labour->id,
                        'name' => $labour->name,
                        'mobile_number' => $labour->mobile_number,
                        'address' => $labour->address,
                        'photo' => $labour->photo,
                        'photo_url' => $labour->photo ? asset($labour->photo) : null,
                        'created_at' => $labour->created_at,
                        'updated_at' => $labour->updated_at
                    ]
                ]);
            }

            return response()->json([
                'status' => 'Failed',
                'status_code' => '404',
                'message' => 'Labour record not found for this mobile number.',
                'data' => null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => '500',
                'message' => $e->getMessage()
            ]);
        }
    }
}
