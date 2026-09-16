<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\AttendanceWebController;
use App\Http\Controllers\api\AiChatQueryController;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class AttendancePdfExportTest extends TestCase
{
    public function test_attendance_report_blade_view_renders_proper_html_and_pdf(): void
    {
        $dummyLogs = [
            (object)[
                'id' => 1,
                'date' => '2026-09-16',
                'user_name' => 'Rajesh Sharma',
                'user_username' => 'rajesh',
                'site_name' => 'Head Office',
                'status' => 'Present',
                'in_time' => '2026-09-16 09:15:00',
                'out_time' => '2026-09-16 18:30:00',
                'labour_count' => 0,
                'remarks' => 'Check in verified'
            ],
            (object)[
                'id' => 2,
                'date' => '2026-09-16',
                'user_name' => 'Sharma Contractors',
                'user_username' => 'Contractor',
                'site_name' => 'Head Office',
                'status' => 'Present',
                'in_time' => '2026-09-16 08:30:00',
                'out_time' => '2026-09-16 17:00:00',
                'labour_count' => 14,
                'remarks' => '14 Labour count'
            ]
        ];

        $companyName = 'Modern New Company';
        $activeSiteName = 'Head Office';
        $reportPeriod = '16 Sep 2026';
        $generatedAt = '16 Sep 2026, 03:45 PM';
        $generatedBy = 'Admin User';
        $stats = [
            'total_entries' => 2,
            'present' => 2,
            'absent' => 0,
            'half_day' => 0,
            'labour_count' => 14
        ];

        $html = view('pdf.attendance_report', compact(
            'dummyLogs',
            'companyName',
            'activeSiteName',
            'reportPeriod',
            'generatedAt',
            'generatedBy',
            'stats'
        ))->with('attendanceLogs', $dummyLogs)->render();

        $this->assertStringContainsString('Modern New Company', $html);
        $this->assertStringContainsString('Official Attendance Report', $html);
        $this->assertStringContainsString('Rajesh Sharma', $html);
        $this->assertStringContainsString('Sharma Contractors', $html);
        $this->assertStringContainsString('14', $html);

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function test_ai_chat_controller_generates_proper_pdf_download_banner(): void
    {
        $controller = new AiChatQueryController();
        $refMethod = new \ReflectionMethod($controller, 'buildDynamicSqlHtml');
        $refMethod->setAccessible(true);

        $rows = [
            (object)[
                'id' => 1,
                'person_name' => 'Ramesh Kumar',
                'date' => '2026-09-16',
                'in_time' => '09:00:00',
                'out_time' => '18:00:00',
                'status' => 'Present',
                'remarks' => 'Normal duty'
            ]
        ];

        $tenant = [
            'conn' => config('database.default'),
            'user_name' => 'Test User',
            'user_username' => 'testuser',
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $pdfUrl = url('/attendance/download-pdf?today=1');

        $html = $refMethod->invoke(
            $controller,
            $rows,
            "SELECT * FROM attendance",
            "Buildarya AI SQL Engine",
            "i want proper attendance in pdf",
            $tenant,
            false,
            true,
            $pdfUrl
        );

        $this->assertStringContainsString('Attendance PDF Report — Head Office', $html);
        $this->assertStringContainsString('Download Attendance PDF Report', $html);
        $this->assertStringContainsString('/attendance/download-pdf?today=1', $html);
    }
}
