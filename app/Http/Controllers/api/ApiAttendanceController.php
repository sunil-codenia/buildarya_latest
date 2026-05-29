<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

            $attendance_id = DB::connection($conn)->table('attendance')->insertGetId([
                'user_id' => $uid,
                'site_id' => $site_id,
                'date' => $date,
                'in_time' => $in_time,
                'status' => 'Present',
                'in_location' => $in_location,
                'remarks' => $remarks,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            addActivity($attendance_id, 'attendance', "User clocked in for " . $date, 13, $uid, $conn);

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

            $updatedRemarks = $attendance->remarks;
            if ($remarks) {
                $updatedRemarks = $updatedRemarks ? $updatedRemarks . ' | Out: ' . $remarks : $remarks;
            }

            DB::connection($conn)->table('attendance')
                ->where('id', $attendance->id)
                ->update([
                    'out_time' => $out_time,
                    'out_location' => $out_location,
                    'remarks' => $updatedRemarks,
                    'updated_at' => now()
                ]);

            addActivity($attendance->id, 'attendance', "User clocked out for " . $date, 13, $uid, $conn);

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
                ->select(
                    'attendance.*', 
                    'users.name as user_name', 
                    'users.username as user_username',
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

            $targetUserId = $request->input('user_id');
            $date = $request->input('date');
            $status = $request->input('status'); // Present, Absent, Half Day, Leave, Holiday
            $site_id = $request->input('site_id');
            $in_time = $request->input('in_time');
            $out_time = $request->input('out_time');
            $remarks = $request->input('remarks');

            if (!$targetUserId || !$date || !$status) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => '300',
                    'message' => 'User ID, Date, and Status are required fields!'
                ]);
            }

            // Check if record already exists for this date & user
            $existing = DB::connection($conn)->table('attendance')
                ->where('user_id', $targetUserId)
                ->where('date', $date)
                ->first();

            $data = [
                'user_id' => $targetUserId,
                'site_id' => $site_id,
                'date' => $date,
                'status' => $status,
                'in_time' => $in_time,
                'out_time' => $out_time,
                'remarks' => $remarks ? 'Manual Override: ' . $remarks : 'Manual Override',
                'updated_at' => now()
            ];

            if ($existing) {
                DB::connection($conn)->table('attendance')
                    ->where('id', $existing->id)
                    ->update($data);
                
                $id = $existing->id;
                addActivity($id, 'attendance', "Manual override updated for user $targetUserId on $date", 13, $uid, $conn);
            } else {
                $data['created_at'] = now();
                $id = DB::connection($conn)->table('attendance')->insertGetId($data);
                addActivity($id, 'attendance', "Manual attendance logged for user $targetUserId on $date", 13, $uid, $conn);
            }

            $record = DB::connection($conn)->table('attendance')->where('id', $id)->first();

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

            $canManage = $isSuperAdmin || ($perm && ($perm->can_add == 1 || $perm->can_edit == 1 || $perm->can_view == 1));
            
            if (!$canManage && $record->user_id != $uid) {
                return response()->json(['status' => 'Failed', 'status_code' => '403', 'message' => 'Unauthorized access.']);
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
            if ($request->has('site_id')) $updateData['site_id'] = $request->input('site_id');
            if ($request->has('status')) $updateData['status'] = $request->input('status');
            if ($request->has('in_time')) $updateData['in_time'] = $request->input('in_time');
            if ($request->has('out_time')) $updateData['out_time'] = $request->input('out_time');
            if ($request->has('date')) $updateData['date'] = $request->input('date');
            if ($request->has('user_id') && $canManage) $updateData['user_id'] = $request->input('user_id');
            if ($request->has('remarks')) $updateData['remarks'] = $request->input('remarks');

            if (!empty($updateData)) {
                $updateData['updated_at'] = now();
                DB::connection($conn)->table('attendance')->where('id', $id)->update($updateData);
                addActivity($id, 'attendance', "Attendance updated via API.", 13, $uid, $conn);
            }

            $updatedRecord = DB::connection($conn)->table('attendance')->where('id', $id)->first();
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

            DB::connection($conn)->table('attendance')->where('id', $id)->delete();
            addActivity(0, 'attendance', "Attendance record deleted via API.", 13, $uid, $conn);

            return response()->json(['status' => 'Ok', 'status_code' => '200', 'message' => 'Attendance deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Failed', 'status_code' => '500', 'message' => $e->getMessage()]);
        }
    }
}
