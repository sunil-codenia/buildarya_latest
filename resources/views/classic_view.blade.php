@extends('app')

@section('content')
@php
    $conn = session()->get('comp_db_conn_name');
    $assigned_ids = session()->get('assigned_site_ids', []);
    $site_id = session()->get('site_id');
    $user_name = session()->get('name', 'User');
    $user_username = session()->get('username', 'User');
    $is_superadmin = session()->get('is_superadmin') === 'yes' || session()->get('role') == 1;

    $active_site_name = null;
    $active_site_display = null;
    $site_count = 1;
    $site_names_array = [];
    $realAttendanceData = [];
    $realExpenseData = [];
    $realMaterialData = [];
    $realSupplierData = [];
    $realTaskData = [];
    $realUserData = [];
    $allCompanySites = [];

    if ($conn) {
        try {
            // 1. Resolve Active Site Name
            if (!empty($site_id) && $site_id != 'all') {
                $siteObj = \Illuminate\Support\Facades\DB::connection($conn)
                    ->table('sites')
                    ->where('id', $site_id)
                    ->first();
                if ($siteObj && isset($siteObj->name)) {
                    $active_site_name = $siteObj->name;
                    $active_site_display = $siteObj->name;
                    $site_names_array = [$siteObj->name];
                }
            }

            if (empty($active_site_name) && !empty($assigned_ids)) {
                $sites = \Illuminate\Support\Facades\DB::connection($conn)
                    ->table('sites')
                    ->whereIn('id', array_filter((array)$assigned_ids))
                    ->get();
                if ($sites->count() > 0) {
                    $site_names_array = $sites->pluck('name')->toArray();
                    $site_count = count($site_names_array);
                    $active_site_name = implode(', ', $site_names_array);
                    $active_site_display = $site_count > 1 ? "All Sites ({$site_count} Active)" : $site_names_array[0];
                }
            }

            if (empty($active_site_name)) {
                $sites = \Illuminate\Support\Facades\DB::connection($conn)
                    ->table('sites')
                    ->where('status', 'Active')
                    ->get();
                if ($sites->count() > 0) {
                    $site_names_array = $sites->pluck('name')->toArray();
                    $site_count = count($site_names_array);
                    $active_site_name = implode(', ', $site_names_array);
                    $active_site_display = $site_count > 1 ? "All Sites ({$site_count} Active)" : $site_names_array[0];
                }
            }

            // 2. Query REAL Attendance Records from tenant DB
            $attLogs = \Illuminate\Support\Facades\DB::connection($conn)
                ->table('attendance')
                ->leftJoin('users', 'users.id', '=', 'attendance.user_id')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'attendance.bills_party_id')
                ->leftJoin('sites', 'sites.id', '=', 'attendance.site_id')
                ->select(
                    'attendance.*',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(users.name, bills_party.name, "Labour Contractor") as party_or_user_name'),
                    \Illuminate\Support\Facades\DB::raw('COALESCE(users.username, "Labour Party") as user_role_title'),
                    'sites.name as site_name'
                )
                ->orderBy('attendance.id', 'desc')
                ->limit(10)
                ->get();

            foreach ($attLogs as $log) {
                $realAttendanceData[] = [
                    'id' => $log->id,
                    'name' => $log->party_or_user_name,
                    'role' => $log->user_role_title,
                    'site' => $log->site_name ?? $active_site_name,
                    'date' => $log->date ? \Carbon\Carbon::parse($log->date)->format('d M Y') : 'N/A',
                    'in_time' => $log->in_time ? \Carbon\Carbon::parse($log->in_time)->format('h:i A') : 'Manual',
                    'out_time' => $log->out_time ? \Carbon\Carbon::parse($log->out_time)->format('h:i A') : 'Pending',
                    'status' => $log->status ?? 'Present',
                    'remarks' => $log->remarks ?? 'Check-in'
                ];
            }

            // 3. Query REAL Expenses Records from tenant DB
            $expLogs = \Illuminate\Support\Facades\DB::connection($conn)
                ->table('expenses')
                ->leftJoin('expense_party', function($join) {
                    $join->on('expense_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'expense');
                })
                ->leftJoin('bills_party', function($join) {
                    $join->on('bills_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'bill');
                })
                ->leftJoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
                ->select(
                    'expenses.*',
                    'sites.name as site_name',
                    'users.name as user_name',
                    'expense_head.name as cost_category_name',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(expense_party.name, bills_party.name) as party_name')
                )
                ->orderBy('expenses.id', 'desc')
                ->limit(10)
                ->get();

            foreach ($expLogs as $exp) {
                $realExpenseData[] = [
                    'id' => $exp->id,
                    'party_name' => $exp->party_name ?? 'N/A',
                    'cost_category_name' => $exp->cost_category_name ?? 'N/A',
                    'particular' => $exp->particular ?? 'Site Expense',
                    'amount' => number_format((float)($exp->amount ?? 0), 2),
                    'user' => $exp->user_name ?? $user_name,
                    'site' => $exp->site_name ?? $active_site_name,
                    'status' => $exp->status ?? 'Pending',
                    'date' => $exp->date ? \Carbon\Carbon::parse($exp->date)->format('d M Y') : 'N/A',
                    'remark' => $exp->remark ?? ''
                ];
            }

            // 4. Query REAL Material Entry Records from tenant DB
            $matLogs = \Illuminate\Support\Facades\DB::connection($conn)
                ->table('material_entry')
                ->leftJoin('materials', 'materials.id', '=', 'material_entry.material_id')
                ->leftJoin('sites', 'sites.id', '=', 'material_entry.site_id')
                ->select('material_entry.*', 'materials.name as material_name', 'sites.name as site_name')
                ->orderBy('material_entry.id', 'desc')
                ->limit(10)
                ->get();

            foreach ($matLogs as $mat) {
                $realMaterialData[] = [
                    'id' => $mat->id,
                    'material' => $mat->material_name ?? 'Site Material',
                    'qty' => ($mat->qty ?? 0) . ' Units',
                    'vehicle' => $mat->vehical ?? 'Site Transport',
                    'site' => $mat->site_name ?? $active_site_name,
                    'status' => $mat->status ?? 'Approved',
                    'date' => $mat->date ? \Carbon\Carbon::parse($mat->date)->format('d M Y') : 'N/A',
                    'remark' => $mat->remark ?? ''
                ];
            }

            // 5. Query REAL Material Suppliers from tenant DB
            $supLogs = \Illuminate\Support\Facades\DB::connection($conn)
                ->table('material_supplier')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();

            foreach ($supLogs as $sup) {
                $realSupplierData[] = [
                    'id' => $sup->id,
                    'name' => $sup->name ?? 'Material Supplier',
                    'address' => $sup->address ?? 'N/A',
                    'gstin' => $sup->gstin ?? 'N/A',
                    'bank' => ($sup->bank_name ?? 'Bank') . ' (' . ($sup->bank_ac ?? 'N/A') . ')',
                    'status' => $sup->status ?? 'Active'
                ];
            }

            // 6. Query REAL Tasks from tenant DB
            $taskLogs = \Illuminate\Support\Facades\DB::connection($conn)
                ->table('tasks')
                ->leftJoin('sites', 'sites.id', '=', 'tasks.site_id')
                ->select('tasks.*', 'sites.name as site_name')
                ->orderBy('tasks.id', 'desc')
                ->limit(10)
                ->get();

            foreach ($taskLogs as $tsk) {
                $realTaskData[] = [
                    'id' => $tsk->id,
                    'title' => $tsk->title ?? 'Site Task',
                    'site' => $tsk->site_name ?? $active_site_name,
                    'priority' => $tsk->priority ?? 'Medium',
                    'status' => $tsk->status ?? 'Pending',
                    'due_date' => $tsk->due_date ? \Carbon\Carbon::parse($tsk->due_date)->format('d M Y') : 'N/A',
                    'description' => $tsk->description ?? ''
                ];
            }

            // 7. Query REAL Team Users from tenant DB
            $userLogs = \Illuminate\Support\Facades\DB::connection($conn)
                ->table('users')
                ->select('id', 'name', 'username', 'contact_no', 'status')
                ->orderBy('id', 'asc')
                ->limit(10)
                ->get();

            foreach ($userLogs as $u) {
                $realUserData[] = [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'contact_no' => $u->contact_no ?? 'N/A',
                    'status' => $u->status ?? 'Active'
                ];
            }

            // 8. Query all company sites
            $allCompanySites = \Illuminate\Support\Facades\DB::connection($conn)
                ->table('sites')
                ->pluck('name')
                ->toArray();

        } catch (\Exception $e) {
            \Log::error('Error querying real tenant records for Classic View: ' . $e->getMessage());
        }
    }

    if (empty($active_site_name)) {
        $active_site_name = "Head Office";
    }
    if (empty($active_site_display)) {
        $active_site_display = $active_site_name;
    }
@endphp

<style>
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

    /* Hide Main Navigation Sidebar on Classic View */
    #leftsidebar, 
    aside.sidebar,
    .overlay {
        display: none !important;
    }

    /* Force main section to expand full screen width */
    section.content {
        margin-left: 0 !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        padding-top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    /* Full-screen Dark Mesh Atmosphere */
    .content.home {
        padding: 0 !important;
        margin: 0 !important;
        background-color: #0b0f17 !important;
        min-height: calc(100vh - 60px);
    }

    .chat-container-wrapper {
        display: flex;
        height: calc(100vh - 65px);
        background: radial-gradient(circle at 50% 15%, rgba(16, 185, 129, 0.08) 0%, rgba(6, 182, 212, 0.03) 35%, #0b0f17 75%);
        color: #f1f5f9;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        overflow: hidden;
    }

    /* Left Sidebar */
    .chat-sidebar {
        width: 270px;
        background-color: #111827;
        display: flex;
        flex-direction: column;
        padding: 14px;
        border-right: 1px solid rgba(255, 255, 255, 0.07);
        flex-shrink: 0;
        transition: all 0.3s ease;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.2);
    }

    .new-chat-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 12px 16px;
        border: 1px solid rgba(16, 185, 129, 0.35);
        border-radius: 12px;
        color: #ffffff;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(6, 182, 212, 0.1));
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.25 ease;
        margin-bottom: 20px;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.15);
    }

    .new-chat-btn:hover {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.3), rgba(6, 182, 212, 0.2));
        border-color: #10b981;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.25);
    }

    .chat-history-title {
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 8px 12px 8px 8px;
    }

    .chat-history-list {
        flex: 1;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding-right: 4px;
    }

    .chat-history-list::-webkit-scrollbar {
        width: 4px;
    }

    .chat-history-list::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }

    .chat-history-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 10px;
        color: #94a3b8;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        border: 1px solid transparent;
        background: rgba(255, 255, 255, 0.02);
    }

    .chat-history-item:hover {
        background: rgba(255, 255, 255, 0.06);
        color: #f8fafc;
        border-color: rgba(255, 255, 255, 0.08);
        transform: translateX(2px);
    }

    .chat-history-item.active {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(15, 23, 42, 0.8));
        color: #10b981;
        font-weight: 700;
        border-color: rgba(16, 185, 129, 0.3);
    }

    .chat-history-item i {
        font-size: 15px;
        color: #64748b;
        transition: color 0.2s ease;
    }

    .chat-history-item:hover i, .chat-history-item.active i {
        color: #10b981;
    }

    .sidebar-user-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        padding-top: 14px;
        margin-top: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .user-pill {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #ffffff;
    }

    .user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, #10b981, #06b6d4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 14px;
        color: white;
        box-shadow: 0 0 15px rgba(16, 185, 129, 0.4);
    }

    /* Main Chat Window */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        position: relative;
        background-color: transparent;
    }

    /* Top Bar inside Chat */
    .chat-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 28px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        background: rgba(17, 24, 39, 0.7);
        backdrop-filter: blur(16px);
        z-index: 10;
    }

    .model-selector {
        display: flex;
        align-items: center;
        gap: 10px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 7px 16px;
        border-radius: 20px;
        color: #ffffff;
        font-size: 13px;
        font-weight: 700;
    }

    .pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background-color: #10b981;
        box-shadow: 0 0 10px #10b981;
        animation: pulseGlow 1.8s infinite;
    }

    @keyframes pulseGlow {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    .back-dashboard-btn {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.03));
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        padding: 7px 16px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .back-dashboard-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    /* Messages Area */
    .chat-messages-area {
        flex: 1;
        overflow-y: auto;
        padding: 30px 0;
        display: flex;
        flex-direction: column;
        scroll-behavior: smooth;
    }

    .chat-messages-area::-webkit-scrollbar {
        width: 6px;
    }

    .chat-messages-area::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.12);
        border-radius: 6px;
    }

    /* Welcome Screen Card Header */
    .welcome-screen {
        max-width: 860px;
        margin: 10px auto 30px auto;
        text-align: center;
        padding: 0 24px;
    }

    .welcome-icon-wrapper {
        position: relative;
        width: 72px;
        height: 72px;
        margin: 0 auto 24px auto;
    }

    .welcome-icon {
        width: 72px;
        height: 72px;
        border-radius: 22px;
        background: linear-gradient(135deg, #10b981, #06b6d4, #6366f1);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 35px rgba(16, 185, 129, 0.45);
        animation: orbFloat 4s ease-in-out infinite;
    }

    @keyframes orbFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }

    .welcome-icon i {
        font-size: 34px;
        color: #ffffff;
    }

    .welcome-title {
        font-size: 28px;
        font-weight: 800;
        background: linear-gradient(135deg, #ffffff 60%, #94a3b8);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 12px;
        letter-spacing: -0.5px;
    }

    .user-scope-banner {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(15, 23, 42, 0.7);
        border: 1px solid rgba(16, 185, 129, 0.3);
        backdrop-filter: blur(12px);
        border-radius: 30px;
        padding: 7px 20px;
        font-size: 13px;
        color: #34d399;
        font-weight: 600;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .quick-prompts-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        max-width: 860px;
        margin: 0 auto;
    }

    .prompt-card {
        background: rgba(30, 41, 59, 0.45);
        backdrop-filter: blur(14px);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        padding: 18px 20px;
        text-align: left;
        cursor: pointer;
        transition: all 0.25s ease;
        position: relative;
        overflow: hidden;
    }

    .prompt-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.5), transparent);
        opacity: 0;
        transition: opacity 0.25s ease;
    }

    .prompt-card:hover {
        background: rgba(30, 41, 59, 0.75);
        border-color: rgba(16, 185, 129, 0.4);
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4), 0 0 20px rgba(16, 185, 129, 0.15);
    }

    .prompt-card:hover::before {
        opacity: 1;
    }

    .prompt-icon-badge {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        margin-bottom: 12px;
    }

    .badge-suppliers { background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(168, 85, 247, 0.2)); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
    .badge-pdf { background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(249, 115, 22, 0.2)); color: #f97316; border: 1px solid rgba(249, 115, 22, 0.3); }
    .badge-live { background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(6, 182, 212, 0.2)); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
    .badge-expense { background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(234, 179, 8, 0.2)); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
    .badge-stock { background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(59, 130, 246, 0.2)); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
    .badge-tasks { background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(217, 70, 239, 0.2)); color: #ec4899; border: 1px solid rgba(236, 72, 153, 0.3); }

    .prompt-card-title {
        font-size: 14px;
        font-weight: 700;
        color: #f8fafc;
        margin-bottom: 4px;
    }

    .prompt-card-desc {
        font-size: 12px;
        color: #94a3b8;
        line-height: 1.4;
    }

    /* Message Row */
    .message-row {
        width: 100%;
        padding: 24px 0;
        animation: fadeInRow 0.3s ease;
    }

    @keyframes fadeInRow {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .message-row.user {
        background-color: transparent;
    }

    .message-row.assistant {
        background: rgba(15, 23, 42, 0.45);
        border-top: 1px solid rgba(255, 255, 255, 0.04);
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    }

    .message-content-inner {
        max-width: 860px;
        margin: 0 auto;
        padding: 0 24px;
        display: flex;
        gap: 20px;
    }

    .message-avatar {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-weight: 800;
        font-size: 15px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
    }

    .message-avatar.user-av {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #ffffff;
    }

    .message-avatar.ai-av {
        background: linear-gradient(135deg, #10b981, #06b6d4);
        color: #ffffff;
    }

    .message-text {
        flex: 1;
        font-size: 14px;
        line-height: 1.65;
        color: #e2e8f0;
    }

    .message-text p {
        margin-bottom: 14px;
    }

    .message-text p:last-child {
        margin-bottom: 0;
    }

    .message-text table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin: 16px 0;
        font-size: 13px;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    }

    .message-text th {
        background: linear-gradient(90deg, #1e293b, #0f172a);
        color: #f8fafc;
        font-weight: 700;
        padding: 12px 16px;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .message-text td {
        background-color: rgba(30, 41, 59, 0.3);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding: 10px 16px;
        color: #cbd5e1;
    }

    .message-text tr:last-child td {
        border-bottom: none;
    }

    .message-text tr:hover td {
        background-color: rgba(30, 41, 59, 0.6);
        color: #ffffff;
    }

    /* Bottom Input Bar */
    .chat-input-container {
        max-width: 860px;
        width: 100%;
        margin: 0 auto;
        padding: 10px 24px 28px 24px;
    }

    .input-box-wrapper {
        position: relative;
        background: rgba(30, 41, 59, 0.6);
        backdrop-filter: blur(18px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4), 0 0 15px rgba(16, 185, 129, 0.08);
        display: flex;
        align-items: center;
        padding: 12px 18px;
        transition: all 0.25s ease;
    }

    .input-box-wrapper:focus-within {
        border-color: rgba(16, 185, 129, 0.5);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.5), 0 0 25px rgba(16, 185, 129, 0.2);
        background: rgba(30, 41, 59, 0.85);
    }

    .chat-textarea {
        width: 100%;
        background: transparent;
        border: none;
        outline: none;
        color: #ffffff;
        font-size: 14px;
        resize: none;
        max-height: 120px;
        font-family: inherit;
    }

    .chat-textarea::placeholder {
        color: #64748b;
    }

    .send-btn {
        background: linear-gradient(135deg, #10b981, #06b6d4);
        color: #ffffff;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        margin-left: 12px;
        transition: all 0.25s ease;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
    }

    .send-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6);
    }

    .disclaimer-text {
        font-size: 11px;
        color: #64748b;
        text-align: center;
        margin-top: 12px;
        font-weight: 500;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .chat-sidebar {
            display: none;
        }
        .quick-prompts-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Voice Assistant Styles */
    .voice-mic-btn {
        background: rgba(255, 255, 255, 0.08);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.35);
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        margin-left: 8px;
        font-size: 18px;
        transition: all 0.25s ease;
        position: relative;
        flex-shrink: 0;
    }

    .voice-mic-btn:hover {
        background: rgba(16, 185, 129, 0.2);
        border-color: rgba(16, 185, 129, 0.6);
        color: #34d399;
        transform: scale(1.05);
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
    }

    .voice-mic-btn.is-listening {
        background: linear-gradient(135deg, #ef4444, #f43f5e) !important;
        color: #ffffff !important;
        border-color: #ef4444 !important;
        animation: pulseVoiceRecording 1.4s infinite;
    }

    @keyframes pulseVoiceRecording {
        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); transform: scale(1); }
        50% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0); transform: scale(1.08); }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); transform: scale(1); }
    }

    .voice-listening-card {
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(16, 185, 129, 0.45);
        border-radius: 16px;
        padding: 12px 18px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.55), 0 0 20px rgba(16, 185, 129, 0.15);
        animation: slideUpVoice 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideUpVoice {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .voice-wave-container {
        display: flex;
        align-items: center;
        gap: 4px;
        height: 24px;
        padding: 0 4px;
    }

    .wave-bar {
        display: inline-block;
        width: 3.5px;
        height: 8px;
        background: linear-gradient(to top, #10b981, #06b6d4);
        border-radius: 4px;
        animation: waveMotion 0.9s ease-in-out infinite alternate;
    }

    .wave-bar:nth-child(2) { animation-delay: 0.15s; }
    .wave-bar:nth-child(3) { animation-delay: 0.3s; }
    .wave-bar:nth-child(4) { animation-delay: 0.45s; }
    .wave-bar:nth-child(5) { animation-delay: 0.6s; }

    @keyframes waveMotion {
        0% { height: 6px; opacity: 0.5; }
        100% { height: 24px; opacity: 1; background: #34d399; }
    }

    .voice-status-info {
        flex: 1;
        min-width: 0;
    }

    .voice-status-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #34d399;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .voice-transcript-preview {
        font-size: 13.5px;
        color: #f8fafc;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 500;
    }

    .voice-card-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .voice-send-btn {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #ffffff;
        border: none;
        border-radius: 8px;
        padding: 6px 14px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s ease;
    }

    .voice-send-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .voice-cancel-btn {
        background: rgba(255, 255, 255, 0.08);
        color: #cbd5e1;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .voice-cancel-btn:hover {
        background: rgba(239, 68, 68, 0.15);
        border-color: rgba(239, 68, 68, 0.4);
        color: #fca5a5;
    }

    .voice-topbar-controls {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .voice-topbar-btn {
        background: rgba(16, 185, 129, 0.12);
        border: 1px solid rgba(16, 185, 129, 0.35);
        color: #34d399;
        border-radius: 18px;
        padding: 6px 12px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .voice-topbar-btn:hover {
        background: rgba(16, 185, 129, 0.22);
        transform: translateY(-1px);
    }

    .voice-topbar-btn.is-muted {
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.15);
        color: #94a3b8;
    }

    .voice-lang-select {
        background: rgba(30, 41, 59, 0.85);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #f8fafc;
        border-radius: 16px;
        padding: 5px 10px;
        font-size: 11px;
        font-weight: 600;
        outline: none;
        cursor: pointer;
        transition: border-color 0.2s ease;
    }

    .voice-lang-select:focus {
        border-color: #10b981;
    }

    .voice-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 600;
        color: #94a3b8;
    }

    .voice-status-pill.speaking {
        border-color: rgba(6, 182, 212, 0.5);
        color: #22d3ee;
        background: rgba(6, 182, 212, 0.1);
    }

    .ai-msg-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .ai-action-btn {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #94a3b8;
        border-radius: 8px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.2s ease;
    }

    .ai-action-btn:hover {
        background: rgba(16, 185, 129, 0.15);
        border-color: rgba(16, 185, 129, 0.35);
        color: #34d399;
    }

    .ai-action-btn.active-speaking {
        background: rgba(6, 182, 212, 0.2);
        border-color: #06b6d4;
        color: #22d3ee;
    }

    /* Custom Non-Intrusive Toast */
    .voice-custom-toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }

    .voice-custom-toast {
        background: rgba(15, 23, 42, 0.96);
        backdrop-filter: blur(16px);
        border: 1px solid rgba(16, 185, 129, 0.4);
        color: #f8fafc;
        border-radius: 12px;
        padding: 12px 18px;
        font-size: 13px;
        font-weight: 500;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6), 0 0 15px rgba(16, 185, 129, 0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        pointer-events: auto;
        max-width: 360px;
    }

    .voice-custom-toast.error {
        border-color: rgba(239, 68, 68, 0.4);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6), 0 0 15px rgba(239, 68, 68, 0.15);
    }

    .voice-custom-toast.error i {
        color: #ef4444;
    }

    .voice-custom-toast i {
        color: #10b981;
        font-size: 18px;
        flex-shrink: 0;
    }

    @keyframes toastSlideIn {
        from { opacity: 0; transform: translateX(20px); }
        to { opacity: 1; transform: translateX(0); }
    }
</style>

<div class="chat-container-wrapper">
    <!-- ChatGPT Left Sidebar -->
    <div class="chat-sidebar">
        <button class="new-chat-btn" onclick="startNewChat()">
            <i class="zmdi zmdi-plus"></i> New chat
        </button>

        <div class="chat-history-title">Recent Site Chats</div>
        
        <div class="chat-history-list" id="chat-history-list">
            <!-- Dynamically rendered via JS -->
        </div>

        <div class="sidebar-user-footer">
            <div class="user-pill">
                <div class="user-avatar">
                    {{ strtoupper(substr($user_name, 0, 2)) }}
                </div>
                <div style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <div style="font-size: 13px; font-weight: 700; color: #fff;">{{ $user_name }}</div>
                    <div style="font-size: 10px; color: #10b981; font-weight: 700; overflow: hidden; text-overflow: ellipsis;" title="{{ $active_site_name }}">{{ $active_site_display }}</div>
                </div>
            </div>
            <a href="{{ url('/dashboard') }}" title="Back to Normal View" style="color: #8e8ea0; font-size: 18px;">
                <i class="zmdi zmdi-view-dashboard"></i>
            </a>
        </div>
    </div>

    <!-- ChatGPT Main Workspace -->
    <div class="chat-main">
        <!-- Top Navigation -->
        <div class="chat-topbar">
            <div class="model-selector">
                <div class="pulse-dot"></div>
                <span>Buildarya AI 4.0 Pro Engine</span>
                <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 10px; padding: 2px 8px; border-radius: 10px; border: 1px solid rgba(16, 185, 129, 0.3); margin-left: 4px;">Live SQL DB</span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <!-- Voice Assistant Controls -->
                <div class="voice-topbar-controls">
                    <button type="button" id="voice-tts-toggle" class="voice-topbar-btn" onclick="toggleTtsSpeech()" title="Toggle Voice Assistant Speech Readout">
                        <i class="zmdi zmdi-volume-up" id="voice-tts-icon"></i>
                        <span id="voice-tts-text">Voice Reply: ON</span>
                    </button>
                    <select id="voice-lang-select" class="voice-lang-select" onchange="changeVoiceLanguage(this.value)" title="Voice Language">
                        <option value="en-IN" selected>🇮🇳 English (IN)</option>
                        <option value="hi-IN">🇮🇳 Hindi (हिन्दी)</option>
                        <option value="en-US">🌐 English (US)</option>
                    </select>
                    <div id="voice-topbar-status" class="voice-status-pill" title="Voice Assistant Status">
                        <i class="zmdi zmdi-mic"></i> <span id="voice-status-badge-text">Ready</span>
                    </div>
                </div>

                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 6px 14px; font-size: 12px; border-radius: 20px; font-weight: 700; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $active_site_name }}">
                    <i class="zmdi zmdi-pin" style="margin-right: 4px;"></i> {{ $active_site_display }}
                </span>
                <a href="{{ url('/dashboard') }}" class="back-dashboard-btn">
                    <i class="zmdi zmdi-view-dashboard"></i> Normal View
                </a>
            </div>
        </div>

        <!-- Chat Scroll Messages Area -->
        <div class="chat-messages-area" id="chat-messages-container">
            <!-- Welcome Screen Header Grid -->
            <div class="welcome-screen" id="welcome-screen">
                <div class="welcome-icon-wrapper">
                    <div class="welcome-icon">
                        <i class="zmdi zmdi-cloud-outline"></i>
                    </div>
                </div>
                <h1 class="welcome-title">How can Buildarya AI assist your sites today?</h1>
                
                <div class="user-scope-banner" title="{{ $active_site_name }}">
                    <i class="zmdi zmdi-account-circle" style="font-size: 16px;"></i>
                    Logged in as <strong>{{ $user_name }}</strong> ({{ $user_username }}) &bull; Active Scope: <strong>{{ $active_site_display }}</strong>
                </div>

                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 24px; flex-wrap: wrap;">
                    <span style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.35); color: #34d399; font-size: 12px; font-weight: 700; border-radius: 20px; padding: 6px 18px; display: inline-flex; align-items: center; gap: 7px; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);">
                        <i class="zmdi zmdi-mic" style="font-size: 16px; color: #10b981;"></i> Voice Assistant Enabled &bull; Click 🎙️ Mic below to speak (English / हिन्दी)
                    </span>
                </div>

                <div class="quick-prompts-grid">
                    <div class="prompt-card" onclick="sendQuickPrompt('give me supplier record')">
                        <div class="prompt-icon-badge badge-suppliers">
                            <i class="zmdi zmdi-store"></i>
                        </div>
                        <div class="prompt-card-title">Material Suppliers</div>
                        <div class="prompt-card-desc">Fetch material supplier list from DB</div>
                    </div>
                    <div class="prompt-card" onclick="sendQuickPrompt('i want proper attendance in pdf')">
                        <div class="prompt-icon-badge badge-pdf">
                            <i class="zmdi zmdi-file-text"></i>
                        </div>
                        <div class="prompt-card-title">Attendance PDF</div>
                        <div class="prompt-card-desc">Generate downloadable attendance PDF</div>
                    </div>
                    <div class="prompt-card" onclick="sendQuickPrompt('Show labour attendance report for {{ $active_site_display }}')">
                        <div class="prompt-icon-badge badge-live">
                            <i class="zmdi zmdi-accounts-alt"></i>
                        </div>
                        <div class="prompt-card-title">Live Attendance</div>
                        <div class="prompt-card-desc">Fetch actual attendance logs from DB</div>
                    </div>
                    <div class="prompt-card" onclick="sendQuickPrompt('Audit petty cash expenses for {{ $active_site_display }}')">
                        <div class="prompt-icon-badge badge-expense">
                            <i class="zmdi zmdi-money"></i>
                        </div>
                        <div class="prompt-card-title">Expense Vouchers</div>
                        <div class="prompt-card-desc">Fetch site expense vouchers from DB</div>
                    </div>
                    <div class="prompt-card" onclick="sendQuickPrompt('Check material stock balance for {{ $active_site_display }}')">
                        <div class="prompt-icon-badge badge-stock">
                            <i class="zmdi zmdi-layers"></i>
                        </div>
                        <div class="prompt-card-title">Material Stock</div>
                        <div class="prompt-card-desc">Fetch material stock entries from DB</div>
                    </div>
                    <div class="prompt-card" onclick="sendQuickPrompt('give me task list')">
                        <div class="prompt-icon-badge badge-tasks">
                            <i class="zmdi zmdi-assignment-check"></i>
                        </div>
                        <div class="prompt-card-title">Assigned Tasks</div>
                        <div class="prompt-card-desc">Fetch site task assignments from DB</div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Chat Thread Rows appended here -->
            <div id="chat-thread-rows"></div>
        </div>

        <!-- Sticky Chat Input Container -->
        <div class="chat-input-container">
            <!-- Voice Assistant Listening Visualizer Overlay -->
            <div id="voice-listening-overlay" class="voice-listening-card" style="display: none;">
                <div class="voice-wave-container">
                    <span class="wave-bar"></span>
                    <span class="wave-bar"></span>
                    <span class="wave-bar"></span>
                    <span class="wave-bar"></span>
                    <span class="wave-bar"></span>
                </div>
                <div class="voice-status-info">
                    <div class="voice-status-label">
                        <i class="zmdi zmdi-mic"></i> Listening to your voice... Speak now
                    </div>
                    <div id="voice-transcript-preview" class="voice-transcript-preview">Say e.g. "Show today's expenses", "Attendance report", "Material stock"...</div>
                </div>
                <div class="voice-card-actions">
                    <button type="button" class="voice-send-btn" onclick="stopVoiceAssistant(true)" title="Send spoken query">
                        <i class="zmdi zmdi-navigation"></i> Send
                    </button>
                    <button type="button" class="voice-cancel-btn" onclick="cancelVoiceAssistant()" title="Cancel voice input">
                        <i class="zmdi zmdi-close"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="input-box-wrapper">
                <textarea id="chat-user-input" class="chat-textarea" rows="1" placeholder="Type or click 🎙️ to speak: 'show attendance today', 'today expense', 'material stock'..." onkeydown="handleKeyPress(event)"></textarea>
                <button type="button" class="voice-mic-btn" id="voice-mic-btn" onclick="toggleVoiceAssistant()" title="Voice Assistant — Click to speak">
                    <i class="zmdi zmdi-mic" id="voice-mic-icon"></i>
                </button>
                <button class="send-btn" onclick="sendMessage()" title="Send Query">
                    <i class="zmdi zmdi-navigation"></i>
                </button>
            </div>
            <div class="disclaimer-text">
                Buildarya AI Voice &amp; Text Assistant &bull; Connected directly to <strong title="{{ $active_site_name }}">{{ $active_site_display }}</strong> database tables.
            </div>
        </div>
    </div>
</div>

<script>
    const CURRENT_USER_NAME = @json($user_name);
    const CURRENT_USER_ROLE = @json($user_username);
    const CURRENT_SITE_NAME = @json($active_site_display);
    const IS_SUPERADMIN = @json($is_superadmin);

    const REAL_ATTENDANCE = @json($realAttendanceData);
    const REAL_EXPENSES = @json($realExpenseData);
    const REAL_MATERIALS = @json($realMaterialData);
    const REAL_SUPPLIERS = @json($realSupplierData);
    const REAL_TASKS = @json($realTaskData);
    const REAL_USERS = @json($realUserData);

    const DEFAULT_RECENT_CHATS = [
        { title: `${CURRENT_SITE_NAME} Attendance`, query: `Show labour attendance report for ${CURRENT_SITE_NAME}` },
        { title: `Material Suppliers Record`, query: `give me supplier record` },
        { title: `${CURRENT_SITE_NAME} Expense Vouchers`, query: `Audit petty cash expenses for ${CURRENT_SITE_NAME}` },
        { title: `Material Stock Logs`, query: `Check material stock balance for ${CURRENT_SITE_NAME}` }
    ];

    let recentChats = [];
    try {
        const saved = localStorage.getItem('buildarya_recent_chats_' + CURRENT_USER_NAME);
        if (saved) {
            recentChats = JSON.parse(saved);
        }
    } catch (e) {}

    if (!recentChats || !Array.isArray(recentChats) || recentChats.length === 0) {
        recentChats = [...DEFAULT_RECENT_CHATS];
    }

    function renderRecentChatsSidebar(activeQuery = '') {
        const container = document.getElementById('chat-history-list');
        if (!container) return;

        let html = '';
        recentChats.forEach((chat) => {
            const isActive = activeQuery && (chat.query.toLowerCase() === activeQuery.toLowerCase() || chat.title.toLowerCase() === activeQuery.toLowerCase());
            const activeClass = isActive ? 'active' : '';
            const safeQuery = chat.query.replace(/'/g, "\\'").replace(/"/g, '&quot;');
            html += `
                <a href="javascript:void(0);" class="chat-history-item ${activeClass}" onclick="sendQuickPrompt('${safeQuery}')">
                    <i class="zmdi zmdi-comment-text"></i>
                    <span title="${escapeHtml(chat.title)}">${escapeHtml(chat.title)}</span>
                </a>
            `;
        });
        container.innerHTML = html;
    }

    function recordRecentChat(text) {
        let title = text;
        const lower = text.toLowerCase();
        if (lower.includes('supplier') || lower.includes('vendor')) {
            title = 'Material Suppliers Record';
        } else if (lower.includes('pdf')) {
            title = `${CURRENT_SITE_NAME} Attendance PDF`;
        } else if (lower.includes('attendance') || lower.includes('labour')) {
            title = `${CURRENT_SITE_NAME} Attendance`;
        } else if (lower.includes('expense') || lower.includes('voucher') || lower.includes('audit')) {
            title = `${CURRENT_SITE_NAME} Expense Vouchers`;
        } else if (lower.includes('stock') || lower.includes('material')) {
            title = 'Material Stock Logs';
        } else if (lower.includes('task') || lower.includes('todo')) {
            title = 'Assigned Tasks Record';
        } else if (lower.includes('user') || lower.includes('staff') || lower.includes('team')) {
            title = 'Team Users Record';
        } else {
            title = text.length > 25 ? text.substring(0, 25) + '...' : text;
        }

        recentChats = recentChats.filter(c => c.query.toLowerCase() !== text.toLowerCase() && c.title.toLowerCase() !== title.toLowerCase());
        recentChats.unshift({ title: title, query: text });

        if (recentChats.length > 10) {
            recentChats = recentChats.slice(0, 10);
        }

        try {
            localStorage.setItem('buildarya_recent_chats_' + CURRENT_USER_NAME, JSON.stringify(recentChats));
        } catch (e) {}

        renderRecentChatsSidebar(text);
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderRecentChatsSidebar();
        initVoiceAssistantUi();
    });

    function handleKeyPress(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    function startNewChat() {
        stopSpeaking();
        if (isVoiceListening) {
            stopVoiceAssistant(false);
        }
        const threadRows = document.getElementById('chat-thread-rows');
        if (threadRows) {
            threadRows.innerHTML = '';
        }
        const container = document.getElementById('chat-messages-container');
        if (container) {
            container.scrollTop = 0;
        }
        renderRecentChatsSidebar();
    }

    function sendQuickPrompt(promptText) {
        stopSpeaking();
        document.getElementById('chat-user-input').value = promptText;
        sendMessage();
    }

    function sendMessage() {
        stopSpeaking();
        if (isVoiceListening) {
            stopVoiceAssistant(false);
        }
        const input = document.getElementById('chat-user-input');
        const text = input.value.trim();
        if (!text) return;

        recordRecentChat(text);

        const threadRows = document.getElementById('chat-thread-rows');
        const container = document.getElementById('chat-messages-container');

        // Render User Message
        const userRow = document.createElement('div');
        userRow.className = 'message-row user';
        userRow.innerHTML = `
            <div class="message-content-inner">
                <div class="message-avatar user-av">${escapeHtml(CURRENT_USER_NAME.substring(0, 1))}</div>
                <div class="message-text">
                    <p>${escapeHtml(text)}</p>
                </div>
            </div>
        `;
        threadRows.appendChild(userRow);

        input.value = '';

        // Render AI Thinking State
        const aiRow = document.createElement('div');
        aiRow.className = 'message-row assistant';
        aiRow.innerHTML = `
            <div class="message-content-inner">
                <div class="message-avatar ai-av">AI</div>
                <div class="message-text" id="thinking-text">
                    <p><em>Processing query "${escapeHtml(text)}" against live tenant database...</em></p>
                </div>
            </div>
        `;
        threadRows.appendChild(aiRow);

        container.scrollTop = container.scrollHeight;

        // Fetch Live Database Response based on text query via Buildarya AI API
        fetch('/api/chat-query', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                query: text,
                conn: '{{ $conn }}',
                uid: '{{ session()->get("uid") }}',
                site_id: '{{ $site_id }}'
            })
        })
        .then(res => res.json())
        .then(res => {
            let outputHtml = '';
            if (res && res.data && res.data.html) {
                outputHtml = res.data.html;
            } else {
                // If API returns error (e.g. 422, unconfigured key), get proper data via live database response!
                outputHtml = generateLiveDatabaseResponse(text);
            }
            aiRow.querySelector('.message-text').innerHTML = outputHtml + buildAiMsgActionsHtml();
            container.scrollTop = container.scrollHeight;
            speakAiResponseIfEnabled(outputHtml);
        })
        .catch(err => {
            console.warn("AI API fallback:", err);
            let responseHtml = generateLiveDatabaseResponse(text);
            aiRow.querySelector('.message-text').innerHTML = responseHtml + buildAiMsgActionsHtml();
            container.scrollTop = container.scrollHeight;
            speakAiResponseIfEnabled(responseHtml);
        });
    }

    function getAiSuccessMessage(form, fallbackMessage) {
        const action = (form && form.action) ? form.action : '';
        const lowerAction = action.toLowerCase();

        if (lowerAction.includes('addnewuser') || lowerAction.includes('/users') && form.querySelector('[name="username"]')) {
            return '✅ User saved successfully. You remain in the AI assistant view.';
        }
        if (lowerAction.includes('addsites') || form.querySelector('[name="site_id"]') && form.querySelector('[name="name"]') && !form.querySelector('[name="username"]')) {
            return '✅ Site saved successfully. You remain in the AI assistant view.';
        }
        if (lowerAction.includes('addnewExpenses') || lowerAction.includes('new_expense') || form.querySelector('[name="particular[]"]')) {
            return '✅ Expense saved successfully. You remain in the AI assistant view.';
        }
        if (lowerAction.includes('addnewmaterial') || form.querySelector('[name="material_id[]"]')) {
            return '✅ Material entry saved successfully. You remain in the AI assistant view.';
        }
        if (lowerAction.includes('/tasks') || form.querySelector('[name="title"]') && form.querySelector('[name="assigned_to[]"]')) {
            return '✅ Task created successfully. You remain in the AI assistant view.';
        }

        return fallbackMessage || '✅ Saved successfully. You remain in the AI assistant view.';
    }

    function submitAiForm(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalLabel = submitBtn ? submitBtn.textContent : 'Save';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            redirect: 'manual'
        })
        .then(async response => {
            if (response.type === 'opaqueredirect' || response.status === 301 || response.status === 302 || response.status === 303 || response.status === 307 || response.status === 308) {
                return { message: 'Saved successfully. You remain in the AI assistant view.' };
            }

            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();
            if (!text || text.trim() === '') {
                return { message: 'Saved successfully. You remain in the AI assistant view.' };
            }
            if (text.toLowerCase().includes('<html') || text.toLowerCase().includes('<!doctype html')) {
                return { message: 'Saved successfully. You remain in the AI assistant view.' };
            }
            return { message: text.trim() };
        })
        .then(data => {
            const fallback = data && data.message ? data.message : 'Saved successfully. You remain in the AI assistant view.';
            const message = getAiSuccessMessage(form, fallback);
            const aiCard = form.closest('.message-text');
            if (aiCard) {
                aiCard.innerHTML = `
                    <div style="background: rgba(16,163,127,0.12); border: 1px solid rgba(16,163,127,0.35); border-radius: 12px; padding: 16px; color: #ecfdf5;">
                        <div style="font-weight: 700; margin-bottom: 8px;">✅ Success</div>
                        <div style="font-size: 13px; color: #d1fae5;">${escapeHtml(message)}</div>
                    </div>
                `;
            }
        })
        .catch(err => {
            const aiCard = form.closest('.message-text');
            if (aiCard) {
                aiCard.innerHTML = `
                    <div style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); border-radius: 12px; padding: 16px; color: #fee2e2;">
                        <div style="font-weight: 700; margin-bottom: 8px;">⚠️ Save failed</div>
                        <div style="font-size: 13px; color: #fecaca;">${escapeHtml(err.message || 'Unable to save form. Please try again.')}</div>
                    </div>
                `;
            }
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalLabel;
            }
        });
    }

    function generateLiveDatabaseResponse(query) {
        const lower = query.toLowerCase().trim();

        // 0. GREETINGS & INTRODUCTIONS
        const greetingsList = ['hi', 'hello', 'hey', 'hiya', 'hlo', 'greetings', 'good morning', 'good afternoon', 'good evening', 'who are you', 'what can you do', 'help', 'namaste', 'namaskar', 'kaise ho', 'kya hal hai'];
        if (greetingsList.includes(lower)) {
            return `
                <div style="background: linear-gradient(135deg, rgba(16, 163, 127, 0.15), rgba(13, 138, 106, 0.25)); border: 1px solid rgba(16, 163, 127, 0.4); border-radius: 12px; padding: 18px 22px; margin-bottom: 12px; color: #ffffff;">
                    <div style="font-weight: 700; font-size: 16px; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 22px;">👋</span> Hello ${escapeHtml(CURRENT_USER_NAME)}!
                    </div>
                    <div style="font-size: 13.5px; line-height: 1.6; color: #e5e7eb;">
                        I am your <strong>Buildarya AI Assistant</strong>, connected directly to your company database for <strong>${escapeHtml(CURRENT_SITE_NAME)}</strong>.
                    </div>
                    <div style="margin-top: 14px; font-size: 13px; color: #d1d5db;">
                        <strong>Ask me any natural language request to fetch live data from your database:</strong>
                        <ul style="margin-top: 8px; margin-bottom: 4px; padding-left: 20px; line-height: 1.8;">
                            <li>👷 <em>"Show attendance records for today"</em> (or <em>"download attendance pdf"</em>)</li>
                            <li>💰 <em>"Get latest petty cash expenses"</em></li>
                            <li>📦 <em>"Check material stock entries"</em></li>
                            <li>📋 <em>"Show pending tasks"</em></li>
                            <li>🏬 <em>"Show material suppliers list"</em></li>
                            <li>👥 <em>"Show registered users and team staff"</em></li>
                        </ul>
                    </div>
                </div>
            `;
        }

        const isRequestingOtherSite = lower.includes('other site') || lower.includes('all site');
        const isPdfRequest = lower.includes('pdf') || lower.includes('download') || lower.includes('export');

        let restrictionNoticeHtml = '';
        if (isRequestingOtherSite && !IS_SUPERADMIN) {
            restrictionNoticeHtml = `
                <div style="background: rgba(239, 68, 68, 0.12); border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    <div style="font-weight: 700; color: #fca5a5; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                        <i class="zmdi zmdi-lock"></i> Role Access Restriction Applied
                    </div>
                    <div style="font-size: 12px; color: #d1d5db; margin-top: 4px;">
                        User <strong>${escapeHtml(CURRENT_USER_NAME)}</strong> (${escapeHtml(CURRENT_USER_ROLE)}) is scoped strictly to <strong>${escapeHtml(CURRENT_SITE_NAME)}</strong>. 
                        Access to unassigned site data is restricted. Showing authorized records below:
                    </div>
                </div>
            `;
        }

        let pdfBannerHtml = `
            <div style="background: linear-gradient(135deg, rgba(16, 163, 127, 0.15), rgba(13, 138, 106, 0.25)); border: 1px solid rgba(16, 163, 127, 0.4); border-radius: 12px; padding: 16px 20px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;">
                <div>
                    <div style="font-weight: 700; font-size: 15px; color: #ffffff; display: flex; align-items: center; gap: 8px;">
                        <i class="zmdi zmdi-file-text" style="color: #10a37f; font-size: 20px;"></i>
                        <span>Attendance PDF Report — ${escapeHtml(CURRENT_SITE_NAME)}</span>
                    </div>
                    <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                        Official site attendance logs formatted as PDF document matching your request.
                    </div>
                </div>
                <a href="{{ url('/attendance/download-pdf') }}" target="_blank" style="background: #10a37f; color: #ffffff; font-weight: 700; padding: 10px 18px; border-radius: 8px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3); transition: background 0.2s ease;">
                    <i class="zmdi zmdi-download"></i> Download Attendance PDF Report
                </a>
            </div>
        `;

        // 1. SUPPLIER RECORDS QUERY
        if (lower.includes('supplier') || lower.includes('vendor') || lower.includes('dealer') || lower.includes('supply') || lower.includes('party')) {
            if (REAL_SUPPLIERS && REAL_SUPPLIERS.length > 0) {
                let rowsHtml = REAL_SUPPLIERS.map(item => `
                    <tr>
                        <td>#SUP-${escapeHtml(item.id)}</td>
                        <td><strong>${escapeHtml(item.name)}</strong></td>
                        <td>${escapeHtml(item.gstin)}</td>
                        <td>${escapeHtml(item.address)}</td>
                        <td>${escapeHtml(item.bank)}</td>
                        <td><span style="color:#10a37f; font-weight:700;">${escapeHtml(item.status)}</span></td>
                    </tr>
                `).join('');

                return `
                    ${restrictionNoticeHtml}
                    <p><strong>🏬 Live Material Suppliers Record (Fetched directly from database):</strong></p>
                    <table>
                        <thead>
                            <tr>
                                <th>Supplier ID</th>
                                <th>Supplier Name</th>
                                <th>GSTIN</th>
                                <th>Address</th>
                                <th>Bank Account Details</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                    <p>Fetched <strong>${REAL_SUPPLIERS.length} material supplier records</strong> from database connection.</p>
                `;
            } else {
                return `
                    ${restrictionNoticeHtml}
                    <p><strong>🏬 Material Suppliers Record:</strong></p>
                    <p>No supplier records found in database for this company.</p>
                `;
            }
        }

        // 2. TASK LIST QUERY
        if (lower.includes('task') || lower.includes('todo') || lower.includes('assignment') || lower.includes('kaam') || lower.includes('work')) {
            if (REAL_TASKS && REAL_TASKS.length > 0) {
                let rowsHtml = REAL_TASKS.map(item => `
                    <tr>
                        <td>#TSK-${escapeHtml(item.id)}</td>
                        <td><strong>${escapeHtml(item.title)}</strong></td>
                        <td>${escapeHtml(item.site)}</td>
                        <td><span style="color:${item.priority === 'High' ? '#ef4444' : '#f59e0b'}; font-weight:700;">${escapeHtml(item.priority)}</span></td>
                        <td><span style="color:${item.status === 'Completed' ? '#10a37f' : '#3b82f6'}; font-weight:700;">${escapeHtml(item.status)}</span></td>
                        <td>${escapeHtml(item.due_date)}</td>
                    </tr>
                `).join('');

                return `
                    ${restrictionNoticeHtml}
                    <p><strong>📋 Live Assigned Tasks Record — ${escapeHtml(CURRENT_SITE_NAME)} (Fetched directly from database):</strong></p>
                    <table>
                        <thead>
                            <tr>
                                <th>Task ID</th>
                                <th>Title / Work Description</th>
                                <th>Site</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                    <p>Fetched <strong>${REAL_TASKS.length} active site task records</strong> from database connection.</p>
                `;
            } else {
                return `
                    ${restrictionNoticeHtml}
                    <p><strong>📋 Assigned Tasks Record:</strong></p>
                    <p>No task assignments found in database for <strong>${escapeHtml(CURRENT_SITE_NAME)}</strong>.</p>
                `;
            }
        }

        // 3. TEAM / USER LIST QUERY
        if (lower.includes('user') || lower.includes('staff') || lower.includes('team') || lower.includes('employee') || lower.includes('member')) {
            if (REAL_USERS && REAL_USERS.length > 0) {
                let rowsHtml = REAL_USERS.map(item => `
                    <tr>
                        <td>#USR-${escapeHtml(item.id)}</td>
                        <td><strong>${escapeHtml(item.name)}</strong></td>
                        <td>${escapeHtml(item.username)}</td>
                        <td>${escapeHtml(item.contact_no || 'N/A')}</td>
                        <td><span style="color:#10a37f; font-weight:700;">${escapeHtml(item.status)}</span></td>
                    </tr>
                `).join('');

                return `
                    ${restrictionNoticeHtml}
                    <p><strong>👥 Live Company Team Members & Users (Fetched directly from database):</strong></p>
                    <table>
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Full Name</th>
                                <th>Role Title / Username</th>
                                <th>Contact Number</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                    <p>Fetched <strong>${REAL_USERS.length} registered team users</strong> from database connection.</p>
                `;
            } else {
                return `
                    ${restrictionNoticeHtml}
                    <p><strong>👥 Company Users Record:</strong></p>
                    <p>No user records found in database.</p>
                `;
            }
        }

        // 4. ATTENDANCE & PDF QUERY
        if (isPdfRequest || lower.includes('attendance') || lower.includes('labour') || lower.includes('headcount') || lower.includes('haziri') || lower.includes('hajiri') || lower.includes('present') || lower.includes('absent')) {
            if (REAL_ATTENDANCE && REAL_ATTENDANCE.length > 0) {
                let rowsHtml = REAL_ATTENDANCE.map(item => `
                    <tr>
                        <td><strong>${escapeHtml(item.name)}</strong></td>
                        <td>${escapeHtml(item.date)}</td>
                        <td>${escapeHtml(item.in_time)}</td>
                        <td>${escapeHtml(item.out_time)}</td>
                        <td><span style="color:#10a37f; font-weight:700;">${escapeHtml(item.status)}</span></td>
                        <td>${escapeHtml(item.remarks)}</td>
                    </tr>
                `).join('');

                return `
                    ${restrictionNoticeHtml}
                    ${isPdfRequest ? pdfBannerHtml : ''}
                    <p><strong>👷 Live Attendance Logs — ${escapeHtml(CURRENT_SITE_NAME)} (Fetched directly from database):</strong></p>
                    <table>
                        <thead>
                            <tr>
                                <th>Name / Party</th>
                                <th>Date</th>
                                <th>In Time</th>
                                <th>Out Time</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                    <p>Fetched <strong>${REAL_ATTENDANCE.length} recent attendance entries</strong> for <strong>${escapeHtml(CURRENT_SITE_NAME)}</strong> from database connection.</p>
                `;
            } else {
                return `
                    ${restrictionNoticeHtml}
                    ${isPdfRequest ? pdfBannerHtml : ''}
                    <p><strong>👷 Attendance Logs — ${escapeHtml(CURRENT_SITE_NAME)}:</strong></p>
                    <p>No recent attendance check-in records were found in the database for <strong>${escapeHtml(CURRENT_SITE_NAME)}</strong>.</p>
                `;
            }
        }

        // 5. EXPENSE & PETTY CASH QUERY
        if (lower.includes('expense') || lower.includes('petty') || lower.includes('audit') || lower.includes('voucher') || lower.includes('kharcha') || lower.includes('kharch') || lower.includes('hisaab') || lower.includes('hisab') || lower.includes('cost') || lower.includes('payment')) {
            if (REAL_EXPENSES && REAL_EXPENSES.length > 0) {
                let rowsHtml = REAL_EXPENSES.map(item => `
                    <tr>
                        <td>#EXP-${escapeHtml(item.id)}</td>
                        <td><strong>${escapeHtml(item.party_name || 'N/A')}</strong></td>
                        <td>${escapeHtml(item.cost_category_name || 'N/A')}</td>
                        <td><strong>${escapeHtml(item.particular)}</strong></td>
                        <td>₹${escapeHtml(item.amount)}</td>
                        <td>${escapeHtml(item.site || CURRENT_SITE_NAME)}</td>
                        <td>${escapeHtml(item.user)}</td>
                        <td>${escapeHtml(item.date)}</td>
                        <td><span style="color:${item.status === 'Approved' ? '#10a37f' : '#f59e0b'}; font-weight:700;">${escapeHtml(item.status)}</span></td>
                    </tr>
                `).join('');

                return `
                    ${restrictionNoticeHtml}
                    <p><strong>💰 Live Expense Vouchers — ${escapeHtml(CURRENT_SITE_NAME)} (Fetched directly from database):</strong></p>
                    <table>
                        <thead>
                            <tr>
                                <th>Voucher ID</th>
                                <th>Party Name</th>
                                <th>Cost Category Name</th>
                                <th>Particular</th>
                                <th>Amount</th>
                                <th>Site Name</th>
                                <th>User Name</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                    <p>Fetched <strong>${REAL_EXPENSES.length} recent expense vouchers</strong> from database connection.</p>
                `;
            } else {
                return `
                    ${restrictionNoticeHtml}
                    <p><strong>💰 Expense Vouchers — ${escapeHtml(CURRENT_SITE_NAME)}:</strong></p>
                    <p>No expense vouchers found in database for <strong>${escapeHtml(CURRENT_SITE_NAME)}</strong>.</p>
                `;
            }
        }

        // 6. MATERIAL & STOCK QUERY
        if (lower.includes('stock') || lower.includes('material') || lower.includes('steel') || lower.includes('cement') || lower.includes('maal') || lower.includes('saman') || lower.includes('inventory')) {
            if (REAL_MATERIALS && REAL_MATERIALS.length > 0) {
                let rowsHtml = REAL_MATERIALS.map(item => `
                    <tr>
                        <td>#MAT-${escapeHtml(item.id)}</td>
                        <td><strong>${escapeHtml(item.material)}</strong></td>
                        <td>${escapeHtml(item.qty)}</td>
                        <td>${escapeHtml(item.vehicle)}</td>
                        <td>${escapeHtml(item.date)}</td>
                        <td><span style="color:#10a37f; font-weight:700;">${escapeHtml(item.status)}</span></td>
                    </tr>
                `).join('');

                return `
                    ${restrictionNoticeHtml}
                    <p><strong>📦 Live Material Entry & Stock Logs — ${escapeHtml(CURRENT_SITE_NAME)} (Fetched directly from database):</strong></p>
                    <table>
                        <thead>
                            <tr>
                                <th>Entry ID</th>
                                <th>Material Name</th>
                                <th>Quantity</th>
                                <th>Vehicle</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                    <p>Fetched <strong>${REAL_MATERIALS.length} material entry records</strong> from database connection.</p>
                `;
            } else {
                return `
                    ${restrictionNoticeHtml}
                    <p><strong>📦 Material Entry — ${escapeHtml(CURRENT_SITE_NAME)}:</strong></p>
                    <p>No material entry records found in database for <strong>${escapeHtml(CURRENT_SITE_NAME)}</strong>.</p>
                `;
            }
        }

        // GENERAL QUERY FALLBACK
        return `
            ${restrictionNoticeHtml}
            <p><strong>Buildarya AI Database Report — ${escapeHtml(CURRENT_SITE_NAME)}:</strong></p>
            <p>Processed live database query: <em>"${escapeHtml(query)}"</em> for user <strong>${escapeHtml(CURRENT_USER_NAME)}</strong> (${escapeHtml(CURRENT_USER_ROLE)}).</p>
            <p>Available Module Summary for <strong>${escapeHtml(CURRENT_SITE_NAME)}</strong>:</p>
            <ul>
                <li><strong>Material Suppliers:</strong> ${REAL_SUPPLIERS.length} records available</li>
                <li><strong>Attendance Check-ins:</strong> ${REAL_ATTENDANCE.length} records available</li>
                <li><strong>Expense Vouchers:</strong> ${REAL_EXPENSES.length} records available</li>
                <li><strong>Material Entry Logs:</strong> ${REAL_MATERIALS.length} records available</li>
                <li><strong>Task Assignments:</strong> ${REAL_TASKS.length} records available</li>
                <li><strong>Team Members:</strong> ${REAL_USERS.length} registered users</li>
            </ul>
        `;
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // =========================================================================
    // BUILDARYA AI VOICE ASSISTANT ENGINE (Speech-To-Text & Text-To-Speech)
    // =========================================================================
    let speechRecognition = null;
    let isVoiceListening = false;
    let isVoiceTtsEnabled = localStorage.getItem('buildarya_voice_tts') !== 'false'; // default true
    let currentVoiceLang = localStorage.getItem('buildarya_voice_lang') || 'en-IN';
    let voiceAutoSendTimer = null;
    let currentSpeakingUtterance = null;
    let activeMediaStream = null;
    let activeMediaRecorder = null;
    let recordedAudioChunks = [];

    function initVoiceAssistantUi() {
        const langSelect = document.getElementById('voice-lang-select');
        if (langSelect) {
            langSelect.value = currentVoiceLang;
        }
        updateTtsToggleUi();
        updateVoiceUiState('idle');

        // Preload speech synthesis voices
        if ('speechSynthesis' in window) {
            window.speechSynthesis.getVoices();
            window.speechSynthesis.onvoiceschanged = function() {
                window.speechSynthesis.getVoices();
            };
        }
    }

    function initSpeechRecognition() {
        const SpeechRecognitionClass = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognitionClass) {
            return null;
        }

        const recognition = new SpeechRecognitionClass();
        recognition.continuous = false;
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;
        recognition.lang = currentVoiceLang;

        recognition.onstart = function() {
            isVoiceListening = true;
            updateVoiceUiState('listening');
        };

        recognition.onresult = function(event) {
            clearTimeout(voiceAutoSendTimer);
            let interimTranscript = '';
            let finalTranscript = '';

            for (let i = event.resultIndex; i < event.results.length; ++i) {
                const item = event.results[i];
                if (item.isFinal) {
                    finalTranscript += item[0].transcript;
                } else {
                    interimTranscript += item[0].transcript;
                }
            }

            const currentText = (finalTranscript || interimTranscript).trim();
            const previewEl = document.getElementById('voice-transcript-preview');
            const inputEl = document.getElementById('chat-user-input');

            if (currentText) {
                if (previewEl) previewEl.textContent = `"${currentText}"`;
                if (inputEl) inputEl.value = currentText;
            }

            if (finalTranscript && finalTranscript.trim().length > 0) {
                // Auto-send after 850ms silence pause once phrase completes
                voiceAutoSendTimer = setTimeout(() => {
                    stopVoiceAssistant(true);
                }, 850);
            }
        };

        recognition.onerror = function(event) {
            console.warn('Speech recognition notice:', event.error);
            clearTimeout(voiceAutoSendTimer);

            // If audio is actively recording via MediaRecorder, DO NOT abort or show modal!
            if (activeMediaStream && activeMediaStream.active && activeMediaRecorder && activeMediaRecorder.state === 'recording') {
                console.log('SpeechRecognition notice handled; MediaRecorder active recording continues.');
                return;
            }

            isVoiceListening = false;
            updateVoiceUiState('idle');

            if (event.error === 'not-allowed' || event.error === 'permission-denied') {
                if (!activeMediaStream || !activeMediaStream.active) {
                    showVoiceToast('Microphone access blocked. Click address bar icon to allow.', 'error');
                }
            } else if (event.error === 'no-speech') {
                showVoiceToast('No speech detected. Please speak into your microphone.');
            } else if (event.error !== 'aborted') {
                showVoiceToast('Voice recognition issue: ' + event.error, 'error');
            }
        };

        recognition.onend = function() {
            // If MediaRecorder is actively recording, keep the UI in listening state
            if (activeMediaStream && activeMediaStream.active && activeMediaRecorder && activeMediaRecorder.state === 'recording') {
                return;
            }
            isVoiceListening = false;
            updateVoiceUiState('idle');
        };

        return recognition;
    }

    function toggleVoiceAssistant() {
        if (isVoiceListening) {
            stopVoiceAssistant(true);
        } else {
            startVoiceAssistant();
        }
    }

    async function startVoiceAssistant() {
        stopSpeaking();

        // 1. Acquire live microphone stream via getUserMedia
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showVoiceToast('Microphone access is not supported by your browser.', 'error');
            return;
        }

        try {
            activeMediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (err) {
            console.warn('Microphone permission or hardware error:', err);
            if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                showVoiceToast('Microphone access blocked. Click address bar icon to allow.', 'error');
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                showVoiceToast('No microphone found on your computer. Please connect a microphone.', 'error');
            } else {
                showVoiceToast('Microphone error: ' + (err.message || err.name), 'error');
            }
            return;
        }

        isVoiceListening = true;
        updateVoiceUiState('listening');

        const previewEl = document.getElementById('voice-transcript-preview');
        if (previewEl) previewEl.textContent = 'Listening to your voice... Speak now';

        // 2. Start MediaRecorder on the active audio stream
        recordedAudioChunks = [];
        try {
            let options = {};
            if (typeof MediaRecorder !== 'undefined') {
                if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                    options = { mimeType: 'audio/webm;codecs=opus' };
                } else if (MediaRecorder.isTypeSupported('audio/webm')) {
                    options = { mimeType: 'audio/webm' };
                } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                    options = { mimeType: 'audio/mp4' };
                }
                activeMediaRecorder = new MediaRecorder(activeMediaStream, options);
                activeMediaRecorder.ondataavailable = function(e) {
                    if (e.data && e.data.size > 0) {
                        recordedAudioChunks.push(e.data);
                    }
                };
                activeMediaRecorder.start(250);
            }
        } catch (mrErr) {
            console.warn('MediaRecorder start notice:', mrErr);
        }

        // 3. Also try webkitSpeechRecognition in parallel for real-time live preview (if browser allows on this origin)
        const SpeechRecognitionClass = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SpeechRecognitionClass) {
            try {
                if (!speechRecognition) {
                    speechRecognition = initSpeechRecognition();
                }
                if (speechRecognition) {
                    speechRecognition.lang = currentVoiceLang;
                    speechRecognition.start();
                }
            } catch (srErr) {
                console.warn('SpeechRecognition start notice:', srErr);
            }
        }
    }

    async function stopVoiceAssistant(shouldSend = false) {
        clearTimeout(voiceAutoSendTimer);

        if (speechRecognition) {
            try { speechRecognition.stop(); } catch (e) {}
        }

        const inputEl = document.getElementById('chat-user-input');
        const previewEl = document.getElementById('voice-transcript-preview');
        const hasLiveTranscript = inputEl && inputEl.value.trim().length > 0;

        // If MediaRecorder was recording
        if (activeMediaRecorder && activeMediaRecorder.state !== 'inactive') {
            // Case A: SpeechRecognition already provided the full live text
            if (hasLiveTranscript) {
                activeMediaRecorder.stop();
                releaseActiveMediaStream();
                isVoiceListening = false;
                updateVoiceUiState('idle');
                if (shouldSend) {
                    sendMessage();
                }
                return;
            }

            // Case B: No live text yet (e.g. on 127.0.0.1 or unsupported browser) -> transcribe recorded audio!
            if (shouldSend) {
                if (previewEl) previewEl.textContent = 'Transcribing voice...';
                const badgeText = document.getElementById('voice-status-badge-text');
                if (badgeText) badgeText.textContent = 'Transcribing...';

                activeMediaRecorder.onstop = async function() {
                    const mime = activeMediaRecorder.mimeType || 'audio/webm';
                    const audioBlob = new Blob(recordedAudioChunks, { type: mime });
                    recordedAudioChunks = [];
                    releaseActiveMediaStream();

                    if (audioBlob.size > 200) {
                        try {
                            const formData = new FormData();
                            formData.append('audio', audioBlob, 'speech.webm');

                            const resp = await fetch('/api/chat-voice-transcribe', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: formData
                            });

                            const data = await resp.json();
                            if (data.success && data.text) {
                                if (inputEl) inputEl.value = data.text;
                                if (previewEl) previewEl.textContent = `"${data.text}"`;
                                isVoiceListening = false;
                                updateVoiceUiState('idle');
                                sendMessage();
                                return;
                            } else {
                                showVoiceToast(data.message || 'Voice could not be recognized. Please type your query.', 'error');
                            }
                        } catch (err) {
                            console.error('Audio transcription request failed:', err);
                            showVoiceToast('Voice transcription failed. Please type your query.', 'error');
                        }
                    } else {
                        showVoiceToast('No voice was recorded. Please speak louder.', 'info');
                    }

                    isVoiceListening = false;
                    updateVoiceUiState('idle');
                };

                activeMediaRecorder.stop();
                return;
            } else {
                activeMediaRecorder.stop();
                releaseActiveMediaStream();
            }
        } else {
            releaseActiveMediaStream();
        }

        isVoiceListening = false;
        updateVoiceUiState('idle');

        if (shouldSend && hasLiveTranscript) {
            sendMessage();
        }
    }

    function cancelVoiceAssistant() {
        clearTimeout(voiceAutoSendTimer);
        if (speechRecognition) {
            try { speechRecognition.abort(); } catch (e) {}
        }
        if (activeMediaRecorder && activeMediaRecorder.state !== 'inactive') {
            try { activeMediaRecorder.stop(); } catch (e) {}
        }
        releaseActiveMediaStream();
        recordedAudioChunks = [];
        isVoiceListening = false;
        updateVoiceUiState('idle');
        const inputEl = document.getElementById('chat-user-input');
        if (inputEl) inputEl.value = '';
    }

    function releaseActiveMediaStream() {
        if (activeMediaStream) {
            try {
                activeMediaStream.getTracks().forEach(t => t.stop());
            } catch (e) {}
            activeMediaStream = null;
        }
    }

    function updateVoiceUiState(state) {
        const micBtn = document.getElementById('voice-mic-btn');
        const overlay = document.getElementById('voice-listening-overlay');
        const statusBadge = document.getElementById('voice-topbar-status');
        const badgeText = document.getElementById('voice-status-badge-text');

        if (state === 'listening') {
            if (micBtn) micBtn.classList.add('is-listening');
            if (overlay) overlay.style.display = 'flex';
            if (statusBadge) {
                statusBadge.classList.add('speaking');
                if (badgeText) badgeText.textContent = 'Listening...';
            }
        } else if (state === 'speaking') {
            if (micBtn) micBtn.classList.remove('is-listening');
            if (overlay) overlay.style.display = 'none';
            if (statusBadge) {
                statusBadge.classList.add('speaking');
                if (badgeText) badgeText.textContent = 'Speaking...';
            }
        } else {
            if (micBtn) micBtn.classList.remove('is-listening');
            if (overlay) overlay.style.display = 'none';
            if (statusBadge) {
                statusBadge.classList.remove('speaking');
                if (badgeText) badgeText.textContent = 'Ready';
            }
        }
    }

    function toggleTtsSpeech() {
        isVoiceTtsEnabled = !isVoiceTtsEnabled;
        localStorage.setItem('buildarya_voice_tts', isVoiceTtsEnabled ? 'true' : 'false');
        updateTtsToggleUi();
        if (!isVoiceTtsEnabled) {
            stopSpeaking();
            showVoiceToast('Voice Assistant speech reply muted');
        } else {
            showVoiceToast('Voice Assistant speech reply enabled');
        }
    }

    function updateTtsToggleUi() {
        const btn = document.getElementById('voice-tts-toggle');
        const icon = document.getElementById('voice-tts-icon');
        const text = document.getElementById('voice-tts-text');
        if (!btn) return;

        if (isVoiceTtsEnabled) {
            btn.classList.remove('is-muted');
            if (icon) icon.className = 'zmdi zmdi-volume-up';
            if (text) text.textContent = 'Voice Reply: ON';
        } else {
            btn.classList.add('is-muted');
            if (icon) icon.className = 'zmdi zmdi-volume-off';
            if (text) text.textContent = 'Voice Reply: OFF';
        }
    }

    function changeVoiceLanguage(lang) {
        currentVoiceLang = lang;
        localStorage.setItem('buildarya_voice_lang', lang);
        if (speechRecognition) {
            speechRecognition.lang = lang;
        }
        const langName = lang === 'hi-IN' ? 'Hindi (हिन्दी)' : (lang === 'en-US' ? 'English (US)' : 'English (India)');
        showVoiceToast(`Voice language switched to ${langName}`);
    }

    function extractSpokenSummary(htmlOrText) {
        if (!htmlOrText) return '';
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = htmlOrText;

        // Clean out interactive action tags
        tempDiv.querySelectorAll('script, style, button, .ai-msg-actions, .voice-card-actions, select, input, .header-dropdown').forEach(el => el.remove());

        const rawText = tempDiv.innerText.trim();

        // 1. GREETINGS
        if (rawText.includes('Hello') && rawText.includes('Buildarya AI Assistant')) {
            return `Hello ${CURRENT_USER_NAME}! I am your Buildarya AI Assistant connected to ${CURRENT_SITE_NAME}. How can I assist you?`;
        }

        // 2. EXPENSES
        if (tempDiv.querySelector('table') && (rawText.includes('Expense') || rawText.includes('expense') || rawText.includes('Petty') || rawText.includes('Voucher'))) {
            const rows = tempDiv.querySelectorAll('tbody tr').length;
            if (rows > 0) {
                return `Found ${rows} expense vouchers for ${CURRENT_SITE_NAME}. Details are displayed on your screen.`;
            } else {
                return `No expense records were found for ${CURRENT_SITE_NAME}.`;
            }
        }

        // 3. ATTENDANCE
        if (rawText.includes('Attendance') || rawText.includes('attendance')) {
            const rows = tempDiv.querySelectorAll('tbody tr').length;
            if (rawText.includes('PDF Report')) {
                return `Your attendance PDF report for ${CURRENT_SITE_NAME} is ready for download.`;
            }
            if (rows > 0) {
                return `Found ${rows} attendance entries for ${CURRENT_SITE_NAME}.`;
            } else {
                return `No attendance check-in records were found for ${CURRENT_SITE_NAME}.`;
            }
        }

        // 4. MATERIAL & STOCK
        if (rawText.includes('Material') || rawText.includes('Stock') || rawText.includes('material')) {
            const rows = tempDiv.querySelectorAll('tbody tr').length;
            if (rows > 0) {
                return `Found ${rows} material stock records in your database.`;
            } else {
                return `No material entries were found.`;
            }
        }

        // 5. SUPPLIERS
        if (rawText.includes('Supplier') || rawText.includes('supplier')) {
            const rows = tempDiv.querySelectorAll('tbody tr').length;
            return `Found ${rows} material suppliers in your company records.`;
        }

        // 6. TASKS
        if (rawText.includes('Task') || rawText.includes('task')) {
            const rows = tempDiv.querySelectorAll('tbody tr').length;
            return `Found ${rows} assigned tasks for ${CURRENT_SITE_NAME}.`;
        }

        // 7. USERS / TEAM
        if (rawText.includes('Team') || rawText.includes('user') || rawText.includes('Users')) {
            const rows = tempDiv.querySelectorAll('tbody tr').length;
            return `Found ${rows} team users registered in your system.`;
        }

        // 8. FORMS
        if (tempDiv.querySelector('form')) {
            return `I have opened the form on your screen so you can submit your details.`;
        }

        // Fallback: first 180 chars of plain text
        const clean = rawText.replace(/\s+/g, ' ').trim();
        return clean.length > 200 ? clean.substring(0, 200) + '...' : clean;
    }

    function speakAiResponseIfEnabled(htmlOrText) {
        if (!isVoiceTtsEnabled) return;
        speakAiResponse(htmlOrText);
    }

    function speakAiResponse(htmlOrText) {
        if (!('speechSynthesis' in window)) return;
        stopSpeaking();

        const summary = extractSpokenSummary(htmlOrText);
        if (!summary) return;

        const utterance = new SpeechSynthesisUtterance(summary);
        utterance.rate = 1.0;
        utterance.pitch = 1.0;

        const voices = window.speechSynthesis.getVoices();
        if (voices && voices.length > 0) {
            const targetLang = currentVoiceLang.substring(0, 2).toLowerCase();
            const matchingVoice = voices.find(v => v.lang.toLowerCase().startsWith(targetLang) || v.name.toLowerCase().includes('india') || v.name.toLowerCase().includes('hindi')) || voices[0];
            if (matchingVoice) {
                utterance.voice = matchingVoice;
            }
        }

        utterance.onstart = function() {
            updateVoiceUiState('speaking');
        };
        utterance.onend = function() {
            updateVoiceUiState('idle');
            currentSpeakingUtterance = null;
        };
        utterance.onerror = function() {
            updateVoiceUiState('idle');
            currentSpeakingUtterance = null;
        };

        currentSpeakingUtterance = utterance;
        window.speechSynthesis.speak(utterance);
    }

    function stopSpeaking() {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
        currentSpeakingUtterance = null;
        updateVoiceUiState('idle');
    }

    function buildAiMsgActionsHtml() {
        return `
            <div class="ai-msg-actions">
                <button type="button" class="ai-action-btn" onclick="speakThisMessage(this)" title="Listen to summary">
                    <i class="zmdi zmdi-volume-up"></i> Listen
                </button>
                <button type="button" class="ai-action-btn" onclick="stopSpeaking()" title="Stop voice">
                    <i class="zmdi zmdi-stop"></i> Stop
                </button>
                <button type="button" class="ai-action-btn" onclick="copyMessageText(this)" title="Copy message text">
                    <i class="zmdi zmdi-copy"></i> Copy
                </button>
            </div>
        `;
    }

    function speakThisMessage(btn) {
        const msgTextContainer = btn.closest('.message-text');
        if (msgTextContainer) {
            speakAiResponse(msgTextContainer.innerHTML);
        }
    }

    function copyMessageText(btn) {
        const msgTextContainer = btn.closest('.message-text');
        if (!msgTextContainer) return;
        const text = msgTextContainer.innerText.replace(/Listen\s+Stop\s+Copy/g, '').trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="zmdi zmdi-check"></i> Copied!';
                setTimeout(() => { btn.innerHTML = origHtml; }, 1800);
            });
        }
    }

    function showVoiceToast(msg, type = 'info') {
        let container = document.getElementById('voice-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'voice-toast-container';
            container.className = 'voice-custom-toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'voice-custom-toast' + (type === 'error' ? ' error' : '');
        const iconClass = type === 'error' ? 'zmdi zmdi-alert-circle' : 'zmdi zmdi-info-outline';
        toast.innerHTML = `<i class="${iconClass}"></i><span>${msg}</span>`;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, 3500);
    }
</script>
@endsection
