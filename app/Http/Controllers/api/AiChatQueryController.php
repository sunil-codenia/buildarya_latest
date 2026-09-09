<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class AiChatQueryController extends Controller
{
    /**
     * Resolve active Tenant database connection, site context, and user details
     */
    private function resolveTenant(Request $request)
    {
        $conn = $request->get('conn') ?? $request->post('conn');
        $user_id = $request->get('uid') ?? $request->post('uid');
        $site_id = $request->get('site_id') ?? $request->post('site_id');

        // Web session fallback
        if (!$conn && session()->has('comp_db_conn_name')) {
            $conn = session()->get('comp_db_conn_name');
        }
        if (!$user_id && session()->has('uid')) {
            $user_id = session()->get('uid');
        }
        if (!$site_id && session()->has('site_id')) {
            $site_id = session()->get('site_id');
        }

        // Bearer token fallback for API consumers
        if ((!$conn || !$user_id) && $request->bearerToken()) {
            $tokenStr = $request->bearerToken();
            $tokenId = null;
            if (strpos($tokenStr, '|') !== false) {
                [$tokenId, $tokenStr] = explode('|', $tokenStr, 2);
            }
            try {
                $token = DB::connection('mysql')->table('personal_access_tokens')->where('id', $tokenId)->first();
                if ($token) {
                    $conn = $conn ?? $token->name;
                    $user_id = $user_id ?? $token->tokenable_id;
                }
            } catch (\Exception $e) {}
        }

        if (!$conn) {
            $conn = config('database.default');
        }

        $user_name = session()->get('name', 'User');
        $user_username = session()->get('username', 'user');
        $is_superadmin = session()->get('is_superadmin') === 'yes' || session()->get('role') == 1;
        $assigned_ids = [];

        if ($user_id && $conn) {
            try {
                $uRecord = DB::connection($conn)->table('users')->where('id', $user_id)->first();
                if ($uRecord) {
                    $user_name = $uRecord->name ?? $user_name;
                    $user_username = $uRecord->username ?? $user_username;
                    if (isset($uRecord->role_id) && $uRecord->role_id == 1) {
                        $is_superadmin = true;
                    }
                    if (!empty($uRecord->site_id)) {
                        $assigned_ids = array_map('intval', array_filter(explode(',', (string)$uRecord->site_id)));
                    }
                }
            } catch (\Exception $e) {
                // Ignore fallback error
            }
        }

        if (empty($assigned_ids) && session()->has('assigned_site_ids')) {
            $sess_assigned = session()->get('assigned_site_ids');
            if (is_array($sess_assigned)) {
                $assigned_ids = array_map('intval', array_filter($sess_assigned));
            } else if (is_string($sess_assigned)) {
                $assigned_ids = array_map('intval', array_filter(explode(',', $sess_assigned)));
            }
        }

        // Resolve Site Name
        $active_site_name = "All Authorized Sites";
        if (!empty($site_id) && $site_id != 'all') {
            try {
                $sObj = DB::connection($conn)->table('sites')->where('id', $site_id)->first();
                if ($sObj && isset($sObj->name)) {
                    $active_site_name = $sObj->name;
                }
            } catch (\Exception $e) {}
        } else if (!empty($assigned_ids)) {
            try {
                $sites = DB::connection($conn)->table('sites')->whereIn('id', $assigned_ids)->get();
                if ($sites->count() > 0) {
                    $active_site_name = implode(', ', $sites->pluck('name')->toArray());
                }
            } catch (\Exception $e) {}
        }

        return [
            'conn' => $conn,
            'uid' => $user_id,
            'site_id' => $site_id,
            'site_name' => $active_site_name,
            'user_name' => $user_name,
            'user_username' => $user_username,
            'is_superadmin' => $is_superadmin,
            'assigned_site_ids' => $assigned_ids
        ];
    }

    /**
     * Build a compact live schema snapshot from the current tenant database so the AI knows the real tables and columns.
     */
    private function buildDatabaseSchemaContext($conn)
    {
        try {
            $tables = DB::connection($conn)->select("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY TABLE_NAME");
            if (empty($tables)) {
                return "No database schema metadata available.";
            }

            $schemaLines = [];
            foreach ($tables as $tableRow) {
                $tableName = $tableRow->TABLE_NAME ?? $tableRow->table_name ?? null;
                if (!$tableName) {
                    continue;
                }

                $columns = DB::connection($conn)->select("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ORDINAL_POSITION", [$tableName]);
                $columnNames = array_map(function ($column) {
                    return $column->COLUMN_NAME ?? $column->column_name;
                }, $columns);

                if (empty($columnNames)) {
                    $schemaLines[] = "- {$tableName} (no columns detected)";
                    continue;
                }

                $schemaLines[] = "- {$tableName}({" . implode(', ', $columnNames) . "})";
            }

            return implode("\n", $schemaLines);
        } catch (\Exception $e) {
            return "No database schema metadata available.";
        }
    }

    /**
     * LLM Engine Call to dynamically generate SQL query from free-form text input via external APIs
     */
    private function callLlmForSql($queryText, $tenant)
    {
        $openaiKey = env('OPENAI_API_KEY');
        $geminiKey = env('GEMINI_API_KEY');
        $groqKey = env('GROQ_API_KEY');
        $deepseekKey = env('DEEPSEEK_API_KEY');
        $conn = $tenant['conn'] ?? config('database.default');
        $schemaContext = $this->buildDatabaseSchemaContext($conn);

        if (!$openaiKey && !$geminiKey && !$groqKey && !$deepseekKey) {
            return null;
        }

        $systemPrompt = "You are an expert MySQL query generator for Buildarya Construction ERP.\n"
            . "Generate ONLY a single valid executable MySQL SELECT query based on user request.\n"
            . "CRITICAL RULES:\n"
            . "1. ONLY return the raw SQL string without any explanation, title, markdown formatting, or ```sql blocks.\n"
            . "2. ONLY generate SELECT queries. NEVER generate INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE.\n"
            . "3. If user input is a greeting (e.g. 'hi', 'hello', 'hey'), reply with 'NONE'.\n"
            . "4. Order records by 1 DESC unless counting records or specific limit requested.\n"
            . "5. Use the exact table and column names from the live schema below. Never invent missing columns or tables.\n"
            . "6. If the query is about people or users, use the users table and join roles if needed.\n"
            . "7. If query mentions a site, use site_id filters based on the tenant site context.\n"
            . "8. For task queries, tasks.assigned_to stores user IDs (or comma-separated IDs). To query tasks assigned to a specific user by name (e.g. 'sunil'), filter using EXISTS (SELECT 1 FROM users WHERE (users.name LIKE '%sunil%' OR users.username LIKE '%sunil%') AND (FIND_IN_SET(users.id, tasks.assigned_to) OR tasks.assigned_to = CAST(users.id AS CHAR) OR tasks.assigned_by = users.id)). Include assigned user names in SELECT via (SELECT GROUP_CONCAT(name SEPARATOR ', ') FROM users WHERE FIND_IN_SET(users.id, tasks.assigned_to) OR users.id = tasks.assigned_to) as assigned_to.\n\n"
            . "LIVE DATABASE SCHEMA (CURRENT TENANT):\n"
            . $schemaContext . "\n\n"
            . "Active Site Context: site_id = " . ($tenant['site_id'] ?? 'all') . " (" . ($tenant['site_name'] ?? 'Head Office') . "). Filter by site_id if applicable.\n";

        $rawSql = null;
        $providerUsed = null;

        try {
            if ($openaiKey) {
                $providerUsed = 'OpenAI (GPT-4o-mini)';
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $openaiKey,
                    'Content-Type' => 'application/json'
                ])->timeout(8)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $queryText]
                    ],
                    'temperature' => 0.1
                ]);
                if ($response->successful()) {
                    $rawSql = $response->json()['choices'][0]['message']['content'] ?? null;
                }
            } else if ($geminiKey) {
                $providerUsed = 'Google Gemini AI';
                $model = env('GEMINI_MODEL', 'gemini-1.5-flash');
                
                // 1. Try URL key parameter
                $response = Http::timeout(8)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $systemPrompt . "\nUser Query: " . $queryText]
                            ]
                        ]
                    ]
                ]);

                // 2. Fallback to header authorization if needed
                if (!$response->successful()) {
                    $response = Http::withHeaders(['x-goog-api-key' => $geminiKey])
                        ->timeout(8)
                        ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                            'contents' => [
                                [
                                    'parts' => [
                                        ['text' => $systemPrompt . "\nUser Query: " . $queryText]
                                    ]
                                ]
                            ]
                        ]);
                }

                if ($response->successful()) {
                    $rawSql = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
                }
            } else if ($groqKey) {
                $providerUsed = 'Groq Cloud AI';
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $groqKey,
                    'Content-Type' => 'application/json'
                ])->timeout(8)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $queryText]
                    ],
                    'temperature' => 0.1
                ]);
                if ($response->successful()) {
                    $rawSql = $response->json()['choices'][0]['message']['content'] ?? null;
                }
            } else if ($deepseekKey) {
                $providerUsed = 'DeepSeek AI';
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $deepseekKey,
                    'Content-Type' => 'application/json'
                ])->timeout(8)->post('https://api.deepseek.com/chat/completions', [
                    'model' => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $queryText]
                    ],
                    'temperature' => 0.1
                ]);
                if ($response->successful()) {
                    $rawSql = $response->json()['choices'][0]['message']['content'] ?? null;
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        if (!$rawSql || trim($rawSql) === 'NONE') {
            return null;
        }

        // Sanitize markdown code blocks
        $cleanSql = trim($rawSql);
        $cleanSql = preg_replace('/^```(?:sql)?/i', '', $cleanSql);
        $cleanSql = preg_replace('/```$/', '', $cleanSql);
        $cleanSql = trim($cleanSql);

        // Security check: ensure strictly SELECT query
        $upper = strtoupper($cleanSql);
        if (strpos($upper, 'SELECT') !== 0) {
            return null;
        }
        if (preg_match('/\b(DELETE|UPDATE|INSERT|DROP|ALTER|TRUNCATE|GRANT|REVOKE)\b/i', $upper)) {
            return null;
        }

        return [
            'sql' => $cleanSql,
            'provider' => $providerUsed
        ];
    }

    /**
     * Local Natural Language Text-to-SQL Generator Engine (Converts text prompt to dynamic SQL query string)
     */
    private function generateDynamicSqlFromText($queryText, $tenant)
    {
        $lower = strtolower(trim($queryText));
        $req_site_id = $tenant['site_id'] ?? null;
        $is_superadmin = $tenant['is_superadmin'] ?? false;
        $assigned_ids = $tenant['assigned_site_ids'] ?? [];

        // Dynamic Site Scope Resolver
        $getSiteFilter = function($columnName) use ($is_superadmin, $assigned_ids, $req_site_id) {
            // 1. Superadmin has access to ALL sites data across tenant database
            if ($is_superadmin) {
                return null;
            }
            // 2. Filter by site_id if passed for standard non-admin users
            if (!empty($req_site_id) && $req_site_id !== 'all') {
                return "{$columnName} = " . intval($req_site_id);
            }
            // 3. Filter by assigned site IDs for multi-site users
            if (!empty($assigned_ids)) {
                if (count($assigned_ids) === 1) {
                    return "{$columnName} = " . intval($assigned_ids[0]);
                } else {
                    return "{$columnName} IN (" . implode(',', array_map('intval', $assigned_ids)) . ")";
                }
            }
            return null;
        };

        $selectQuery = null;
        $whereClauses = [];

        // 1. Identify Database Target Entity and construct Base SELECT Query
        if (strpos($lower, 'sales report') !== false || strpos($lower, 'slaes report') !== false || strpos($lower, 'sales reprt') !== false || strpos($lower, 'report of sales') !== false || strpos($lower, 'invoice report') !== false) {
            $selectQuery = "SELECT sales_invoice.id, sales_invoice.invoice_no, sales_party.name as party_name, sales_invoice.taxable_value, sales_invoice.amount, sales_invoice.status, sales_invoice.date FROM sales_invoice LEFT JOIN sales_party ON sales_party.id=sales_invoice.party_id";
        } else if (strpos($lower, 'pending report') !== false || strpos($lower, 'pending reprt') !== false || strpos($lower, 'report of pending') !== false) {
            $selectQuery = "SELECT expenses.id, expenses.particular, expenses.amount, COALESCE(users.name, 'Staff') as recorded_by, expenses.date, expenses.status, expenses.remark FROM expenses LEFT JOIN users ON users.id=expenses.user_id";
            $whereClauses[] = "(expenses.status LIKE '%Pending%' OR expenses.status LIKE '%pending%')";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'voucher report') !== false || strpos($lower, 'vouchers report') !== false || strpos($lower, 'voucher reprt') !== false || strpos($lower, 'report of voucher') !== false || strpos($lower, 'report of vouchers') !== false) {
            $selectQuery = "SELECT id, voucher_no, party_type, amount, payment_details, status, date FROM payment_vouchers";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if ((strpos($lower, 'expense report') !== false || strpos($lower, 'expense reprt') !== false || strpos($lower, 'expence report') !== false || strpos($lower, 'report of expense') !== false || strpos($lower, 'report of expenses') !== false)
            && (strpos($lower, 'site') !== false || strpos($lower, 'sites') !== false || strpos($lower, 'according to') !== false || strpos($lower, 'by site') !== false || strpos($lower, 'sitewise') !== false || strpos($lower, 'site-wise') !== false)) {
            $selectQuery = "SELECT sites.name as site_name, SUM(expenses.amount) as total_amount, COUNT(expenses.id) as expense_count FROM expenses LEFT JOIN sites ON sites.id = expenses.site_id";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
            $whereClauses[] = "expenses.amount IS NOT NULL";
        } else if (strpos($lower, 'expense report') !== false || strpos($lower, 'expense reprt') !== false || strpos($lower, 'expence report') !== false || strpos($lower, 'report of expense') !== false || strpos($lower, 'report of expenses') !== false) {
            $selectQuery = "SELECT expenses.id, expenses.particular, expenses.amount, COALESCE(users.name, 'Staff') as recorded_by, expenses.date, expenses.status, expenses.remark FROM expenses LEFT JOIN users ON users.id=expenses.user_id";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'material report') !== false || strpos($lower, 'material reprt') !== false || strpos($lower, 'matrial report') !== false || strpos($lower, 'stock report') !== false) {
            $selectQuery = "SELECT material_entry.id, materials.name as material_name, material_entry.qty, material_entry.vehical, material_entry.date, material_entry.status FROM material_entry LEFT JOIN materials ON materials.id=material_entry.material_id";
            $sf = $getSiteFilter('material_entry.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'attendance report') !== false || strpos($lower, 'attendace report') !== false || strpos($lower, 'report of attendance') !== false) {
            $selectQuery = "SELECT attendance.id, COALESCE(users.name, 'Labour') as person_name, attendance.date, attendance.in_time, attendance.out_time, attendance.status, attendance.remarks FROM attendance LEFT JOIN users ON users.id=attendance.user_id";
            $sf = $getSiteFilter('attendance.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'pending') !== false && (strpos($lower, 'voucher') !== false || strpos($lower, 'payment') !== false)) {
            $selectQuery = "SELECT id, voucher_no, party_type, amount, payment_details, status, date FROM payment_vouchers";
            $whereClauses[] = "(status LIKE '%Pending%' OR status LIKE '%pending%')";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'pending') !== false && (strpos($lower, 'bill') !== false || strpos($lower, 'bills') !== false || strpos($lower, 'party') !== false)) {
            $selectQuery = "SELECT id, name, bankname, bank_ac, ifsc, panno, status FROM bills_party";
            $whereClauses[] = "(status LIKE '%Pending%' OR status LIKE '%pending%')";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if ((strpos($lower, 'verified') !== false || strpos($lower, 'approved') !== false) && (strpos($lower, 'voucher') !== false || strpos($lower, 'payment') !== false)) {
            $selectQuery = "SELECT id, voucher_no, party_type, amount, payment_details, status, date FROM payment_vouchers";
            $whereClauses[] = "(status LIKE '%Verified%' OR status LIKE '%verified%' OR status LIKE '%Approved%' OR status LIKE '%approved%')";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if ((strpos($lower, 'verified') !== false || strpos($lower, 'approved') !== false) && (strpos($lower, 'bill') !== false || strpos($lower, 'bills') !== false || strpos($lower, 'party') !== false)) {
            $selectQuery = "SELECT id, name, bankname, bank_ac, ifsc, panno, status FROM bills_party";
            $whereClauses[] = "(status LIKE '%Verified%' OR status LIKE '%verified%' OR status LIKE '%Approved%' OR status LIKE '%approved%')";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'paid') !== false && (strpos($lower, 'voucher') !== false || strpos($lower, 'payment') !== false)) {
            $selectQuery = "SELECT id, voucher_no, party_type, amount, payment_details, status, date FROM payment_vouchers";
            $whereClauses[] = "(status LIKE '%Paid%' OR status LIKE '%paid%')";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'paid') !== false && (strpos($lower, 'bill') !== false || strpos($lower, 'bills') !== false || strpos($lower, 'party') !== false)) {
            $selectQuery = "SELECT id, name, bankname, bank_ac, ifsc, panno, status FROM bills_party";
            $whereClauses[] = "(status LIKE '%Paid%' OR status LIKE '%paid%')";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'rejected') !== false && (strpos($lower, 'voucher') !== false || strpos($lower, 'payment') !== false)) {
            $selectQuery = "SELECT id, voucher_no, party_type, amount, payment_details, status, date FROM payment_vouchers";
            $whereClauses[] = "(status LIKE '%Rejected%' OR status LIKE '%rejected%')";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'rejected') !== false && (strpos($lower, 'bill') !== false || strpos($lower, 'bills') !== false || strpos($lower, 'party') !== false)) {
            $selectQuery = "SELECT id, name, bankname, bank_ac, ifsc, panno, status FROM bills_party";
            $whereClauses[] = "(status LIKE '%Rejected%' OR status LIKE '%rejected%')";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'sales party') !== false || strpos($lower, 'sales parties') !== false) {
            $selectQuery = "SELECT id, name, address, phone, gst, status FROM sales_party";
        } else if (strpos($lower, 'sales project') !== false || strpos($lower, 'sales projects') !== false) {
            $selectQuery = "SELECT id, name, details, status, create_datetime FROM sales_project";
        } else if (strpos($lower, 'sales company') !== false || strpos($lower, 'sales companies') !== false) {
            $selectQuery = "SELECT id, name, address, phone, gst, status FROM sales_company";
        } else if (strpos($lower, 'sale') !== false || strpos($lower, 'invoice') !== false || strpos($lower, 'billing') !== false) {
            $selectQuery = "SELECT sales_invoice.id, sales_invoice.invoice_no, sales_party.name as party_name, sales_invoice.taxable_value, sales_invoice.amount, sales_invoice.status, sales_invoice.date FROM sales_invoice LEFT JOIN sales_party ON sales_party.id=sales_invoice.party_id";
        } else if (strpos($lower, 'expense party') !== false || strpos($lower, 'expence party') !== false || strpos($lower, 'expense_party') !== false) {
            $selectQuery = "SELECT id, name, address, pan_no, status, create_datetime FROM expense_party";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'bill party') !== false || strpos($lower, 'bills party') !== false || strpos($lower, 'bill_party') !== false) {
            $selectQuery = "SELECT id, name, bankname, bank_ac, ifsc, panno, status FROM bills_party";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'payment voucher') !== false || strpos($lower, 'payment_voucher') !== false) {
            $selectQuery = "SELECT id, voucher_no, party_type, amount, payment_details, status, date FROM payment_vouchers";
            $sf = $getSiteFilter('site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'other party') !== false || strpos($lower, 'other parties') !== false) {
            $selectQuery = "SELECT id, name, mobile_no, status FROM other_parties";
        } else if (strpos($lower, 'document') !== false || strpos($lower, 'doc') !== false) {
            $selectQuery = "SELECT id, name, particular, date, remark, status FROM doc_upload";
        } else if (strpos($lower, 'contact') !== false) {
            $selectQuery = "SELECT id, name, phone, email, position FROM contact";
        } else if (strpos($lower, 'activity') !== false || strpos($lower, 'log') !== false) {
            $selectQuery = "SELECT activity.id, users.name as user_name, activity.ref_table, activity.action, activity.date, activity.time FROM activity LEFT JOIN users ON users.id=activity.uid";
        } else if (strpos($lower, 'role') !== false || strpos($lower, 'permission') !== false) {
            $selectQuery = "SELECT id, name, is_superadmin, created_at FROM roles";
        } else if (strpos($lower, 'site') !== false || strpos($lower, 'location') !== false || strpos($lower, 'branch') !== false || strpos($lower, 'project') !== false) {
            $selectQuery = "SELECT id, name, address, status, sites_type FROM sites";
            $sf = $getSiteFilter('id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'user') !== false || strpos($lower, 'usr') !== false || strpos($lower, 'staff') !== false || strpos($lower, 'team') !== false || strpos($lower, 'employee') !== false || strpos($lower, 'member') !== false || strpos($lower, 'developer') !== false || strpos($lower, 'engineer') !== false || strpos($lower, 'manager') !== false || strpos($lower, 'admin') !== false || strpos($lower, 'who is') !== false || strpos($lower, 'who') !== false || strpos($lower, 'find') !== false) {
            $selectQuery = "SELECT users.id, users.name, COALESCE(roles.name, 'Staff') as role_name, users.username, users.contact_no, users.status FROM users LEFT JOIN roles ON roles.id=users.role_id";
            if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $queryText, $m)) {
                $v = addslashes(trim($m[1]));
                $whereClauses[] = "(users.username LIKE '%{$v}%' OR users.name LIKE '%{$v}%')";
            } else {
                preg_match_all('/\b([a-zA-Z0-9._-]+)\b/', $lower, $words);
                $ignoreWords = ['show', 'me', 'the', 'all', 'who', 'is', 'a', 'an', 'are', 'find', 'get', 'list', 'details', 'info', 'record', 'records', 'user', 'users', 'staff', 'team', 'member', 'members'];
                $keywordsFound = [];
                if (!empty($words[1])) {
                    foreach ($words[1] as $w) {
                        if (strlen($w) > 2 && !in_array($w, $ignoreWords)) {
                            $keywordsFound[] = addslashes($w);
                        }
                    }
                }
                if (!empty($keywordsFound)) {
                    $subOrs = [];
                    foreach ($keywordsFound as $kw) {
                        $subOrs[] = "users.name LIKE '%{$kw}%' OR users.username LIKE '%{$kw}%' OR roles.name LIKE '%{$kw}%'";
                    }
                    $whereClauses[] = "(" . implode(' OR ', $subOrs) . ")";
                }
            }
        } else if (strpos($lower, 'supplier') !== false || strpos($lower, 'suplier') !== false || strpos($lower, 'vendor') !== false || strpos($lower, 'dealer') !== false || strpos($lower, 'supply') !== false) {
            $selectQuery = "SELECT id, name, address, gstin, bank_name, bank_ac, status FROM material_supplier";
        } else if (strpos($lower, 'attendance') !== false || strpos($lower, 'attendace') !== false || strpos($lower, 'attendac') !== false || strpos($lower, 'atteance') !== false || strpos($lower, 'attandance') !== false || strpos($lower, 'attandace') !== false || strpos($lower, 'atendance') !== false || strpos($lower, 'attendence') !== false || strpos($lower, 'attndance') !== false || strpos($lower, 'headcount') !== false || strpos($lower, 'present') !== false || strpos($lower, 'checkin') !== false) {
            $selectQuery = "SELECT attendance.id, COALESCE(users.name, 'Labour') as person_name, attendance.date, attendance.in_time, attendance.out_time, attendance.status, attendance.remarks FROM attendance LEFT JOIN users ON users.id=attendance.user_id";
            $sf = $getSiteFilter('attendance.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'pending') !== false && (strpos($lower, 'expense') !== false || strpos($lower, 'expence') !== false)) {
            $selectQuery = "SELECT expenses.id, expenses.particular, expenses.amount, COALESCE(users.name, 'Staff') as recorded_by, expenses.date, expenses.status, expenses.remark FROM expenses LEFT JOIN users ON users.id=expenses.user_id";
            $whereClauses[] = "(expenses.status LIKE '%Pending%' OR expenses.status LIKE '%pending%')";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'expense') !== false || strpos($lower, 'expence') !== false || strpos($lower, 'petty') !== false || strpos($lower, 'cost') !== false || strpos($lower, 'audit') !== false) {
            $selectQuery = "SELECT expenses.id, expenses.particular, expenses.amount, COALESCE(users.name, 'Staff') as recorded_by, expenses.date, expenses.status, expenses.remark FROM expenses LEFT JOIN users ON users.id=expenses.user_id";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'stock') !== false || strpos($lower, 'material') !== false || strpos($lower, 'matrial') !== false || strpos($lower, 'steel') !== false || strpos($lower, 'cement') !== false || strpos($lower, 'entry') !== false) {
            $selectQuery = "SELECT material_entry.id, materials.name as material_name, material_entry.qty, material_entry.vehical, material_entry.date, material_entry.status FROM material_entry LEFT JOIN materials ON materials.id=material_entry.material_id";
            $sf = $getSiteFilter('material_entry.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'task') !== false || strpos($lower, 'taks') !== false || strpos($lower, 'todo') !== false || strpos($lower, 'assignment') !== false || strpos($lower, 'work') !== false) {
            $selectQuery = "SELECT tasks.id, tasks.title, sites.name as site_name, (SELECT GROUP_CONCAT(name SEPARATOR ', ') FROM users WHERE FIND_IN_SET(users.id, tasks.assigned_to) OR users.id = tasks.assigned_to) as assigned_to, tasks.priority, tasks.status, tasks.due_date FROM tasks LEFT JOIN sites ON sites.id=tasks.site_id";
            $sf = $getSiteFilter('tasks.site_id');
            if ($sf) $whereClauses[] = $sf;

            if (strpos($lower, 'pending') !== false) {
                $whereClauses[] = "(tasks.status LIKE '%Pending%' OR tasks.status LIKE '%pending%')";
            } else if (strpos($lower, 'progress') !== false || strpos($lower, 'in progress') !== false) {
                $whereClauses[] = "(tasks.status LIKE '%Progress%' OR tasks.status LIKE '%progress%')";
            } else if (strpos($lower, 'completed') !== false) {
                $whereClauses[] = "(tasks.status LIKE '%Completed%' OR tasks.status LIKE '%completed%')";
            } else if (strpos($lower, 'hold') !== false || strpos($lower, 'on hold') !== false) {
                $whereClauses[] = "(tasks.status LIKE '%Hold%' OR tasks.status LIKE '%hold%')";
            }
        } else if (strpos($lower, 'asset') !== false || strpos($lower, 'machinery') !== false || strpos($lower, 'machine') !== false || strpos($lower, 'tool') !== false) {
            $selectQuery = "SELECT id, name, cost_price, status, create_datetime FROM assets";
        } else if (strpos($lower, 'labour') !== false || strpos($lower, 'worker') !== false) {
            $selectQuery = "SELECT id, name, mobile_no, status FROM labours";
        }

        if (!$selectQuery) {
            return null;
        }

        // 2. Parse Date Conditions from User Prompt (Today, Yesterday, This Month, Specific Date or Date Range)
        $dateColumn = null;
        if (strpos($selectQuery, 'attendance') !== false) {
            $dateColumn = 'attendance.date';
        } else if (strpos($selectQuery, 'expenses') !== false) {
            $dateColumn = 'expenses.date';
        } else if (strpos($selectQuery, 'material_entry') !== false) {
            $dateColumn = 'material_entry.date';
        } else if (strpos($selectQuery, 'tasks') !== false) {
            $dateColumn = 'tasks.created_at';
        } else if (strpos($selectQuery, 'assets') !== false) {
            $dateColumn = 'assets.create_datetime';
        }

        $parseDateStringToYmd = function ($value) {
            $text = trim((string) $value);
            if ($text === '') {
                return null;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
                return $text;
            }

            if (preg_match('/^(\d{1,2})(?:st|nd|rd|th)?\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)(?:\s+(\d{4}))?$/i', $text, $mDate)) {
                $day = intval($mDate[1]);
                $monthStr = $mDate[2];
                $year = !empty($mDate[3]) ? intval($mDate[3]) : date('Y');
                $parsedTs = strtotime("{$day} {$monthStr} {$year}");
                return $parsedTs ? date('Y-m-d', $parsedTs) : null;
            }

            if (preg_match('/^(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s+(\d{4}))?$/i', $text, $mDate2)) {
                $monthStr = $mDate2[1];
                $day = intval($mDate2[2]);
                $year = !empty($mDate2[3]) ? intval($mDate2[3]) : date('Y');
                $parsedTs = strtotime("{$day} {$monthStr} {$year}");
                return $parsedTs ? date('Y-m-d', $parsedTs) : null;
            }

            if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $text, $mSlash)) {
                $d = intval($mSlash[1]);
                $m = intval($mSlash[2]);
                $y = intval($mSlash[3]);
                if ($y < 100) $y += 2000;
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }

            return null;
        };

        if ($dateColumn) {
            if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)(?:\s+(\d{4}))?\s+(?:to|and)\s+(\d{1,2})(?:st|nd|rd|th)?\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)(?:\s+(\d{4}))?\b/i', $queryText, $rangeMatchDayMonth)) {
                $startDate = $parseDateStringToYmd($rangeMatchDayMonth[1] . ' ' . $rangeMatchDayMonth[2] . (!empty($rangeMatchDayMonth[3]) ? ' ' . $rangeMatchDayMonth[3] : ''));
                $endDate = $parseDateStringToYmd($rangeMatchDayMonth[4] . ' ' . $rangeMatchDayMonth[5] . (!empty($rangeMatchDayMonth[6]) ? ' ' . $rangeMatchDayMonth[6] : ''));
                if ($startDate && $endDate) {
                    $whereClauses[] = "DATE({$dateColumn}) BETWEEN '{$startDate}' AND '{$endDate}'";
                }
            } else if (preg_match('/\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s+(\d{4}))?\s+(?:to|and)\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s+(\d{4}))?\b/i', $queryText, $rangeMatchMonthFirst)) {
                $startDate = $parseDateStringToYmd($rangeMatchMonthFirst[1] . ' ' . $rangeMatchMonthFirst[2] . (!empty($rangeMatchMonthFirst[3]) ? ' ' . $rangeMatchMonthFirst[3] : ''));
                $endDate = $parseDateStringToYmd($rangeMatchMonthFirst[4] . ' ' . $rangeMatchMonthFirst[5] . (!empty($rangeMatchMonthFirst[6]) ? ' ' . $rangeMatchMonthFirst[6] : ''));
                if ($startDate && $endDate) {
                    $whereClauses[] = "DATE({$dateColumn}) BETWEEN '{$startDate}' AND '{$endDate}'";
                }
            } else if (preg_match('/\b(?:from|between)\s+([A-Za-z0-9,\-\/ ]+?)\s+(?:to|and)\s+([A-Za-z0-9,\-\/ ]+?)(?:\s*(?:for|in|on|$))\b/i', $queryText, $rangeMatch)) {
                $startDate = $parseDateStringToYmd($rangeMatch[1]);
                $endDate = $parseDateStringToYmd($rangeMatch[2]);
                if ($startDate && $endDate) {
                    $whereClauses[] = "DATE({$dateColumn}) BETWEEN '{$startDate}' AND '{$endDate}'";
                }
            } else if (preg_match('/\b(today|todays|today\'s|todays\s+only)\b/i', $lower)) {
                $whereClauses[] = "DATE({$dateColumn}) = CURDATE()";
            } else if (preg_match('/\b(yesterday|yesterdays|yesterday\'s)\b/i', $lower)) {
                $whereClauses[] = "DATE({$dateColumn}) = SUBDATE(CURDATE(), 1)";
            } else if (preg_match('/\b(this\s+month|current\s+month)\b/i', $lower)) {
                $whereClauses[] = "MONTH({$dateColumn}) = MONTH(CURDATE()) AND YEAR({$dateColumn}) = YEAR(CURDATE())";
            } else if (preg_match('/\b(this\s+week|current\s+week)\b/i', $lower)) {
                $whereClauses[] = "YEARWEEK({$dateColumn}, 1) = YEARWEEK(CURDATE(), 1)";
            } else if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $queryText, $dMatch)) {
                $whereClauses[] = "DATE({$dateColumn}) = '" . addslashes($dMatch[1]) . "'";
            } else if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)(?:\s+(\d{4}))?\b/i', $queryText, $mDate)) {
                $day = intval($mDate[1]);
                $monthStr = $mDate[2];
                $year = !empty($mDate[3]) ? intval($mDate[3]) : date('Y');
                $parsedTs = strtotime("{$day} {$monthStr} {$year}");
                if ($parsedTs) {
                    $whereClauses[] = "DATE({$dateColumn}) = '" . date('Y-m-d', $parsedTs) . "'";
                }
            } else if (preg_match('/\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{1,2})(?:st|nd|rd|th)?(?:\s+(\d{4}))?\b/i', $queryText, $mDate2)) {
                $monthStr = $mDate2[1];
                $day = intval($mDate2[2]);
                $year = !empty($mDate2[3]) ? intval($mDate2[3]) : date('Y');
                $parsedTs = strtotime("{$day} {$monthStr} {$year}");
                if ($parsedTs) {
                    $whereClauses[] = "DATE({$dateColumn}) = '" . date('Y-m-d', $parsedTs) . "'";
                }
            } else if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})\b/', $queryText, $mSlash)) {
                $d = intval($mSlash[1]);
                $m = intval($mSlash[2]);
                $y = intval($mSlash[3]);
                if ($y < 100) $y += 2000;
                $formatted = sprintf('%04d-%02d-%02d', $y, $m, $d);
                $whereClauses[] = "DATE({$dateColumn}) = '{$formatted}'";
            }
        }

        // 3. Parse Filter Value Conditions from User Prompt
        if (preg_match('/(?:equals|equal|named|called|with|whose\s+\w+\s+is)\s+[\'"]?([a-zA-Z0-9._%+-@\s]+)[\'"]?/i', $queryText, $filterMatch)) {
            $val = addslashes(trim($filterMatch[1]));
            $reservedWords = ['head office', 'all site', 'today', 'todays', 'yesterday', 'this month', 'month', 'week', 'year', 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december', 'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
            if (!empty($val) && !in_array(strtolower($val), $reservedWords)) {
                if (strpos($selectQuery, 'users') !== false) {
                    $whereClauses[] = "(users.name LIKE '%{$val}%' OR users.username LIKE '%{$val}%' OR roles.name LIKE '%{$val}%')";
                } else if (strpos($selectQuery, 'material_supplier') !== false) {
                    $whereClauses[] = "name LIKE '%{$val}%'";
                } else if (strpos($selectQuery, 'tasks') !== false) {
                    $whereClauses[] = "title LIKE '%{$val}%'";
                } else if (strpos($selectQuery, 'expenses') !== false) {
                    $whereClauses[] = "particular LIKE '%{$val}%'";
                }
            }
        }

        // 4. Parse User Entity Match for Task queries (e.g. "sunil pending tasks", "tasks for sunil", "admin tasks")
        if (strpos($selectQuery, 'tasks') !== false) {
            preg_match_all('/\b([a-zA-Z0-9._-]+)\b/', $lower, $taskWords);
            $systemWords = ['show', 'give', 'me', 'list', 'the', 'all', 'a', 'an', 'for', 'of', 'to', 'in', 'task', 'tasks', 'taks', 'todo', 'assignment', 'work', 'pending', 'progress', 'completed', 'hold', 'site', 'sites', 'report', 'reports', 'today', 'yesterday', 'month', 'week', 'year', 'due', 'status', 'priority', 'high', 'medium', 'low', 'critical', 'record', 'records', 'details', 'detail'];
            $candidateUserNames = [];
            if (!empty($taskWords[1])) {
                foreach ($taskWords[1] as $tw) {
                    if (strlen($tw) >= 3 && !in_array($tw, $systemWords)) {
                        $candidateUserNames[] = addslashes($tw);
                    }
                }
            }
            if (!empty($candidateUserNames)) {
                $userSubClauses = [];
                foreach ($candidateUserNames as $cName) {
                    $userSubClauses[] = "EXISTS (SELECT 1 FROM users WHERE (users.name LIKE '%{$cName}%' OR users.username LIKE '%{$cName}%') AND (FIND_IN_SET(users.id, tasks.assigned_to) OR tasks.assigned_to = CAST(users.id AS CHAR) OR tasks.assigned_by = users.id))";
                }
                $whereClauses[] = "(" . implode(' OR ', $userSubClauses) . ")";
            }
        }

        $groupByClause = '';
        if (strpos($selectQuery, 'SUM(expenses.amount)') !== false) {
            $groupByClause = ' GROUP BY expenses.site_id, sites.name';
        }

        $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';
        if ($groupByClause !== '') {
            return "{$selectQuery}{$whereSql}{$groupByClause} ORDER BY total_amount DESC";
        }
        return "{$selectQuery}{$whereSql} ORDER BY 1 DESC";
    }

    /**
     * AI Text-to-Query API Processor
     */
    public function processQuery(Request $request)
    {
        try {
            $queryText = trim($request->input('query') ?? $request->input('prompt') ?? $request->input('message') ?? '');
            if (empty($queryText)) {
                return response()->json([
                    'status' => 'Failed',
                    'status_code' => 400,
                    'message' => 'Please provide a query text.'
                ], 400);
            }

            $tenant = $this->resolveTenant($request);
            $conn = $tenant['conn'];
            $site_name = $tenant['site_name'];
            $user_name = $tenant['user_name'];
            $user_username = $tenant['user_username'];

            $lower = strtolower($queryText);

            // Check if input is a simple greeting or introductory text
            $isGreeting = preg_match('/^(hi|hello|hey|hiya|hlo|greetings|good morning|good afternoon|good evening|who are you|what can you do|help)$/i', trim($lower));

            if ($isGreeting) {
                $html = $this->buildGreetingHtml($user_name, $site_name);
                return response()->json([
                    'status' => 'Ok',
                    'status_code' => 200,
                    'message' => 'Buildarya AI Assistant Greeting',
                    'data' => [
                        'query' => $queryText,
                        'intent' => 'greeting',
                        'ai_provider' => 'Buildarya AI Assistant',
                        'sql_generated' => '',
                        'active_site' => $site_name,
                        'records_count' => 0,
                        'records' => [],
                        'summary' => "Buildarya AI Assistant greeted user {$user_name}.",
                        'html' => $html,
                        'is_pdf_requested' => false,
                        'pdf_url' => url('/attendance/export?type=pdf')
                    ]
                ]);
            }

            $createFormIntent = $this->detectCreateFormIntent($queryText);
            if ($createFormIntent) {
                $html = $this->renderCreateFormHtml($createFormIntent, $tenant);
                return response()->json([
                    'status' => 'Ok',
                    'status_code' => 200,
                    'message' => 'Create form opened for ' . ucfirst($createFormIntent) . '.',
                    'data' => [
                        'query' => $queryText,
                        'intent' => 'create_form',
                        'entity' => $createFormIntent,
                        'ai_provider' => 'Buildarya AI Assistant',
                        'sql_generated' => '',
                        'active_site' => $site_name,
                        'records_count' => 0,
                        'records' => [],
                        'summary' => 'AI detected a create action and opened the add form.',
                        'html' => $html,
                        'is_pdf_requested' => false,
                        'pdf_url' => url('/attendance/export?type=pdf')
                    ]
                ]);
            }

            $isPdfRequest = (strpos($lower, 'pdf') !== false || strpos($lower, 'download') !== false || strpos($lower, 'export') !== false);
            $isOtherSiteRequest = (strpos($lower, 'other site') !== false || strpos($lower, 'all site') !== false);

            $sqlToExec = null;
            $provider = 'Buildarya Text-to-SQL AI Engine';

            // 1. Attempt LLM Text-to-SQL generation if external API key (OpenAI/Gemini/Groq) is configured
            $llmResult = $this->callLlmForSql($queryText, $tenant);
            if ($llmResult && !empty($llmResult['sql'])) {
                $sqlToExec = $llmResult['sql'];
                $provider = $llmResult['provider'];
            } else {
                // 2. Dynamically convert user text to SQL using local Text-to-SQL engine
                $sqlToExec = $this->generateDynamicSqlFromText($queryText, $tenant);
            }

            // Execute AI-generated SQL query directly against the tenant database
            if ($sqlToExec) {
                try {
                    $fetchedRows = DB::connection($conn)->select($sqlToExec);
                    $html = $this->buildDynamicSqlHtml($fetchedRows, $sqlToExec, $provider, $queryText, $tenant, $isOtherSiteRequest, $isPdfRequest);
                    
                    return response()->json([
                        'status' => 'Ok',
                        'status_code' => 200,
                        'message' => "Query dynamically converted to SQL and executed via {$provider}",
                        'data' => [
                            'query' => $queryText,
                            'intent' => 'ai_text_to_sql',
                            'ai_provider' => $provider,
                            'sql_generated' => $sqlToExec,
                            'active_site' => $site_name,
                            'records_count' => count($fetchedRows),
                            'records' => $fetchedRows,
                            'summary' => "AI Engine dynamically converted text into SQL: [{$sqlToExec}]. Executed on database and returned " . count($fetchedRows) . " records.",
                            'html' => $html,
                            'is_pdf_requested' => $isPdfRequest,
                            'pdf_url' => url('/attendance/export?type=pdf')
                        ]
                    ]);
                } catch (\Exception $e) {
                    // Fallback to metric summary on execution exception
                }
            }

            // General Metrics Fallback
            $sqlGenerated = "SELECT COUNT(*) FROM attendance; SELECT COUNT(*) FROM expenses; SELECT COUNT(*) FROM material_entry; SELECT COUNT(*) FROM tasks;";

            $attCount = 0; $expCount = 0; $matCount = 0; $taskCount = 0; $userCount = 0; $supCount = 0;
            try {
                $attCount = DB::connection($conn)->table('attendance')->count();
                $expCount = DB::connection($conn)->table('expenses')->count();
                $matCount = DB::connection($conn)->table('material_entry')->count();
                $taskCount = DB::connection($conn)->table('tasks')->count();
                $userCount = DB::connection($conn)->table('users')->count();
                $supCount = DB::connection($conn)->table('material_supplier')->count();
            } catch (\Exception $e) {}

            $records = [
                'suppliers' => $supCount,
                'attendance' => $attCount,
                'expenses' => $expCount,
                'materials' => $matCount,
                'tasks' => $taskCount,
                'users' => $userCount
            ];

            $summaryText = "Buildarya AI Engine processed input: '{$queryText}'. Analyzed tenant database metrics for site '{$site_name}'.";
            $html = $this->buildGeneralHtml($records, $summaryText, $sqlGenerated, $queryText, $site_name, $user_name, $user_username, $isOtherSiteRequest, $tenant['is_superadmin']);

            return response()->json([
                'status' => 'Ok',
                'status_code' => 200,
                'message' => 'Query processed successfully by Buildarya AI',
                'data' => [
                    'query' => $queryText,
                    'intent' => 'general',
                    'ai_provider' => 'Buildarya Text-to-SQL AI Engine',
                    'sql_generated' => $sqlGenerated,
                    'active_site' => $site_name,
                    'records_count' => is_array($records) ? count($records) : 0,
                    'records' => $records,
                    'summary' => $summaryText,
                    'html' => $html,
                    'is_pdf_requested' => $isPdfRequest,
                    'pdf_url' => url('/attendance/export?type=pdf')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'status_code' => 500,
                'message' => 'Failed to process AI query: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build friendly greeting response for conversational inputs like "hi", "hello", "who are you"
     */
    private function buildGreetingHtml($user_name, $site_name)
    {
        return '
            <div style="background: linear-gradient(135deg, rgba(16, 163, 127, 0.15), rgba(13, 138, 106, 0.25)); border: 1px solid rgba(16, 163, 127, 0.4); border-radius: 12px; padding: 18px 22px; margin-bottom: 12px; color: #ffffff;">
                <div style="font-weight: 700; font-size: 16px; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 22px;">👋</span> Hello ' . e($user_name) . '!
                </div>
                <div style="font-size: 13.5px; line-height: 1.6; color: #e5e7eb;">
                    I am your <strong>Buildarya AI Assistant</strong>, connected directly to your company database for <strong>' . e($site_name) . '</strong>.
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
        ';
    }

    private function detectCreateFormIntent($queryText)
    {
        $lower = strtolower(trim($queryText));

        $patterns = [
            'user' => [
                'add user', 'add users', 'new user', 'create user', 'create users', 'i want to add user',
                'user form', 'show user form', 'open user form', 'new member', 'add staff', 'add employee',
                'add new user', 'i want to add new user', 'i want to create user'
            ],
            'site' => [
                'add site', 'new site', 'create site', 'site form', 'show site form', 'open site form',
                'add new site', 'new sites', 'add new sites', 'i want to add new site'
            ],
            'role' => [
                'add role', 'new role', 'create role', 'role form', 'show role form', 'open role form',
                'add new role', 'new roles', 'add new roles', 'i want to add new role', 'i want to add new roles'
            ],
            'expense' => [
                'add expense', 'new expense', 'create expense', 'expense form', 'show expense form', 'open expense form',
                'add new expense', 'add expense entry', 'new expense entry', 'add new expense entry'
            ],
            'material' => [
                'add material', 'new material', 'create material', 'material form', 'show material form', 'open material form',
                'add new material', 'material entry', 'add material entry', 'add new material entry'
            ],
            'task' => [
                'add task', 'new task', 'create task', 'task form', 'show task form', 'open task form',
                'add new task', 'add task form', 'create task form'
            ],
            'attendance' => [
                'add manual attendance', 'new manual attendance', 'create manual attendance', 'manual attendance',
                'add attendance', 'new attendance', 'attendance form', 'manual attendance form'
            ],
            'ticket' => [
                'add support ticket', 'new support ticket', 'create support ticket', 'support ticket',
                'add ticket', 'new ticket', 'ticket form', 'support ticket form'
            ],
            'machinery' => [
                'add machinery', 'new machinery', 'create machinery', 'machinery form', 'add new machinery',
                'add machinery head', 'new machinery head', 'machinery head form', 'machinery head',
                'add machinery expense head', 'new machinery expense head', 'machinery expense head',
                'machinery expense head form', 'add new machinery head'
            ],
            'asset' => [
                'add asset', 'new asset', 'create asset', 'asset form', 'add new assets', 'new assets',
                'add asset head', 'new asset head', 'asset head form', 'asset expense head',
                'add new asset head', 'new asset expense head', 'add asset expense head'
            ],
            'bill_party' => [
                'add bill party', 'new bill party', 'create bill party', 'bill party form', 'add new bill party'
            ],
            'bill' => [
                'add bill', 'new bill', 'create bill', 'bill form', 'add new bill', 'add bill works',
                'new bill works', 'add new bill works', 'bill works', 'add bill rate', 'new bill rate',
                'add new bill rate', 'bill rate'
            ],
            'sales_party' => [
                'add sales party', 'new sales party', 'create sales party', 'sales party form', 'add new sales party'
            ],
            'sales_project' => [
                'add sales project', 'new sales project', 'create sales project', 'sales project form', 'add new sales project'
            ],
            'invoice_head' => [
                'add invoice head', 'new invoice head', 'create invoice head', 'invoice head form', 'add new invoice head'
            ],
            'contact_category' => [
                'add contact category', 'new contact category', 'create contact category', 'contact category form',
                'add contact categories', 'new contact categories', 'add contacts category', 'add contacts categories'
            ],
            'contact_company' => [
                'add new company in contacts', 'new company in contacts', 'add company in contacts', 'company form in contacts',
                'add new company', 'new company', 'company contact form'
            ],
            'material_supplier' => [
                'add material supplier', 'new material supplier', 'create material supplier', 'material supplier form',
                'add new material supplier', 'add new supplier', 'new supplier'
            ],
            'material_unit' => [
                'add material unit', 'new material unit', 'create material unit', 'material unit form',
                'add new material unit', 'add unit', 'new unit', 'add new unit'
            ],
            'material_entry' => [
                'add material entry', 'new material entry', 'create material entry', 'material entry form',
                'add new material entry', 'add material consumption', 'new material consumption', 'add wastage', 'new wastage'
            ],
            'cost_category' => [
                'add cost category', 'new cost category', 'create cost category', 'cost category form',
                'add new cost category', 'add new cost categories', 'add cost categories'
            ],
            'expense_party' => [
                'add expense party', 'new expense party', 'create expense party', 'expense party form',
                'add expense parties', 'new expense parties', 'add new expense party', 'add new expense parties'
            ],
            'payment_voucher' => [
                'generate voucher', 'generate payment voucher', 'add payment voucher', 'new payment voucher',
                'create payment voucher', 'voucher form', 'add new payment voucher'
            ],
            'machinery_head' => [
                'add new machinery head', 'new machinery head', 'add machinery head', 'machinery head form'
            ],
            'machinery_expense_head' => [
                'add machinery expense head', 'new machinery expense head', 'add new machinery expense head',
                'machinery expense head form', 'machinery expense head'
            ],
            'asset_head' => [
                'add asset head', 'new asset head', 'add new asset head', 'asset head form', 'asset expense head'
            ]
        ];

        foreach ($patterns as $entity => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($lower, $phrase) !== false) {
                    return $entity;
                }
            }
        }

        $createWords = ['add', 'create', 'new', 'insert', 'make', 'generate', 'open'];
        $hasCreateSignal = false;
        foreach ($createWords as $word) {
            if (strpos($lower, $word) !== false) {
                $hasCreateSignal = true;
                break;
            }
        }

        if (!$hasCreateSignal && (strpos($lower, 'form') !== false || strpos($lower, 'screen') !== false)) {
            $hasCreateSignal = true;
        }

        if (!$hasCreateSignal) {
            return null;
        }

        $entityPriority = [
            'user' => ['user', 'users', 'member', 'staff', 'employee'],
            'site' => ['site', 'sites'],
            'role' => ['role', 'roles'],
            'expense' => ['expense', 'expenses', 'petty', 'payment voucher', 'voucher'],
            'material' => ['material', 'materials', 'stock', 'supplier', 'unit'],
            'task' => ['task', 'todo', 'assignment'],
            'attendance' => ['manual attendance', 'attendance'],
            'ticket' => ['support ticket', 'ticket'],
            'machinery' => ['machinery', 'machine'],
            'asset' => ['asset', 'assets'],
            'bill_party' => ['bill party'],
            'bill' => ['bill works', 'bill rate', 'bill'],
            'sales_party' => ['sales party'],
            'sales_project' => ['sales project'],
            'invoice_head' => ['invoice head'],
            'contact_category' => ['contact category', 'contact categories'],
            'contact_company' => ['company in contacts', 'company'],
            'material_supplier' => ['material supplier', 'supplier'],
            'material_unit' => ['material unit', 'unit'],
            'material_entry' => ['material entry', 'wastage', 'consumption'],
            'cost_category' => ['cost category'],
            'expense_party' => ['expense party', 'expense parties'],
            'payment_voucher' => ['payment voucher', 'voucher'],
            'machinery_head' => ['machinery head'],
            'machinery_expense_head' => ['machinery expense head'],
            'asset_head' => ['asset head', 'asset expense head']
        ];

        foreach ($entityPriority as $entity => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($lower, $keyword) !== false) {
                    return $entity;
                }
            }
        }

        return null;
    }

    private function renderCreateFormHtml($entity, $tenant)
    {
        $conn = $tenant['conn'] ?? config('database.default');
        $siteOptions = '';
        $roleOptions = '';
        $sites = DB::connection($conn)->table('sites')->select('id', 'name')->orderBy('name')->get();
        $roles = DB::connection($conn)->table('roles')->select('id', 'name')->orderBy('name')->get();
        $companyName = session()->get('comp_name') ?? ($tenant['comp_name'] ?? '');
        $companyId = session()->get('comp_db_id') ?? ($tenant['comp_db_id'] ?? '');
        $today = date('Y-m-d');
        $minDate = date('Y-m-d', strtotime('-30 days'));
        $maxDate = date('Y-m-d', strtotime('+30 days'));

        foreach ($sites as $site) {
            $siteOptions .= '<option value="' . e($site->id) . '">' . e($site->name) . '</option>';
        }

        foreach ($roles as $role) {
            $roleOptions .= '<option value="' . e($role->id) . '">' . e($role->name) . '</option>';
        }

        if ($entity === 'user') {
            $companyInput = !empty($companyId) ? '<div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Company</label><input type="text" value="' . e($companyName) . '" readonly style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><input type="hidden" name="company_id" value="' . e($companyId) . '"></div>' : '<div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Company</label><input type="text" value="' . e($companyName) . '" readonly style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><input type="hidden" name="company_id" value=""></div>';

            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">Add New User</div>
                        </div>
                        <a href="' . url('/users') . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . url('/addnewuser') . '" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                            <div style="grid-column: span 2; display:flex; justify-content:center; align-items:center; flex-direction:column; gap:10px;">
                                <img height="150" width="150" src="' . asset('/images/noprofile.jpg') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="User image preview">
                                <input type="file" accept="Image/*" name="image" style="width:100%; padding:8px 10px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">
                            </div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Full Name"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Phone Number</label><input type="number" name="contact_no" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="10 Digit Mobile"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Username</label><input type="text" name="username" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Login Username"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Password</label><input type="password" name="password" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Login Password"></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Site</label><select name="site_id[]" required multiple style="width:100%; min-height:100px; padding:8px 10px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $siteOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Role</label><select name="role_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="" selected disabled>--Select Role--</option>' . $roleOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Pan No.</label><input type="text" name="pan_no" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="PAN Card No"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Login Platform</label><select name="mobile_only" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="no">Web & Mobile Both</option><option value="yes">Only Mobile App</option></select></div>
                            ' . $companyInput . '
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">View Duration (Days)</label><input type="number" min="0" name="view_duration" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Enter number of days (e.g. 5)"><small style="display:block; color:#9ca3af; margin-top:4px;">Optional: Defaults to Role setting if empty.</small></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Creation Duration (Days)</label><input type="number" min="0" name="add_duration" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Enter number of days (e.g. 5)"><small style="display:block; color:#9ca3af; margin-top:4px;">Optional: Defaults to Role setting if empty.</small></div>
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="button" style="background:#374151; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Close</button>
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Save User</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'site') {
            $projects = DB::connection($conn)->table('projects')->select('id', 'name')->orderBy('name')->get();
            $projectOptions = '<option value="" selected disabled>--Select Project--</option>';
            foreach ($projects as $project) {
                $projectOptions .= '<option value="' . e($project->id) . '">' . e($project->name) . '</option>';
            }
            $projectOptions .= '<option value="0">No Project</option>';

            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">Add New Site</div>
                        </div>
                        <a href="' . url('/sites') . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . url('/addsites') . '" method="POST" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Site Name"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Address</label><input type="text" name="address" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Site Address"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Opening Balance</label><input type="text" name="open_balance" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="0.00"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Sites Type</label><select name="sitestype" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="" selected disabled>--Select Sites Type--</option><option value="Official Site">Official Site</option><option value="Working Site">Working Site</option></select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Project</label><select name="project_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $projectOptions . '</select></div>
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Save Site</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'role') {
            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">Add New Role</div>
                        </div>
                        <a href="' . url('/user_roles') . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . url('/addnewrole') . '" method="POST" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                            <div style="grid-column: span 1;">
                                <label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Role Name</label>
                                <div style="display:flex; align-items:center; width:100%; border:1px solid #475569; border-radius:8px; background:#0f172a; overflow:hidden;">
                                    <span style="padding:10px 12px; color:#94a3b8; background:rgba(148,163,184,0.08);"><i class="zmdi zmdi-user"></i></span>
                                    <input type="text" name="name" required style="width:100%; padding:10px 12px; border:none; outline:none; background:transparent; color:#fff;" placeholder="Role Name">
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Save Role</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'expense') {
            $siteSelect = '<select name="site_id[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select>';
            $partyPlaceholder = '<option value="" selected disabled>--Expense Parties--</option>';
            $headSelect = '<select name="head_id[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="" selected disabled>--Select Head--</option>';

            $headRows = DB::connection($conn)->table('expense_head')->select('id', 'name')->orderBy('name')->get();
            foreach ($headRows as $head) {
                $headSelect .= '<option value="' . e($head->id) . '">' . e($head->name) . '</option>';
            }
            $headSelect .= '</select>';

            $partyRows = DB::connection($conn)->table('expense_party')->select('id', 'name', 'status', 'cost_category_id')->orderBy('name')->get();
            $billRows = DB::connection($conn)->table('bills_party')->select('id', 'name', 'status', 'cost_category_id')->orderBy('name')->get();

            $partySelect = '<select name="party_id[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">';
            $partySelect .= '<option disabled>--Expense Parties--</option>';
            foreach ($partyRows as $party) {
                $disabled = ($party->status == 'Pending') ? 'disabled' : '';
                $partySelect .= '<option value="' . e($party->id) . '||expense" ' . $disabled . ' data-cost-category="' . e($party->cost_category_id) . '">' . e($party->name) . ($party->status == 'Pending' ? ' (Pending Activation)' : '') . '</option>';
            }
            $partySelect .= '<option disabled>--Bill Parties--</option>';
            foreach ($billRows as $party) {
                $disabled = ($party->status == 'Pending') ? 'disabled' : '';
                $partySelect .= '<option value="' . e($party->id) . '||bill" ' . $disabled . ' data-cost-category="' . e($party->cost_category_id) . '">' . e($party->name) . ($party->status == 'Pending' ? ' (Pending Activation)' : '') . '</option>';
            }
            $partySelect .= '</select>';

            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">Add New Expense</div>
                        </div>
                        <a href="' . url('/new_expense') . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . url('/addnewExpenses') . '" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                            <div style="grid-column: span 2; display:flex; justify-content:center; align-items:center; flex-direction:column; gap:10px;">
                                <img height="150" width="150" src="' . asset('/images/expense.png') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="Expense image preview">
                                <input type="file" accept="Image/*" name="image[]" style="width:100%; padding:8px 10px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">
                            </div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Site</label>' . $siteSelect . '</div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Expense Party</label>' . $partySelect . '</div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Cost Category</label>' . $headSelect . '</div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Particular</label><input type="text" name="particular[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Enter The Particular Item"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Amount</label><input type="number" min="0" step="0.01" name="amount[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="0.00"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Remark</label><input type="text" name="remark[]" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Enter The Remark (If Any)"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Date</label><input type="date" name="date[]" required min="' . e($minDate) . '" max="' . e($maxDate) . '" value="' . e($today) . '" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="button" style="background:#374151; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Add Row</button>
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Save Expense</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'material') {
            $materialRows = DB::connection($conn)->table('materials')->select('id', 'name')->orderBy('name')->get();
            $unitRows = DB::connection($conn)->table('units')->select('id', 'name')->orderBy('name')->get();
            $supplierRows = DB::connection($conn)->table('material_supplier')->select('id', 'name', 'status')->orderBy('name')->get();

            $materialOptions = '<option value="" selected disabled>--Select Material--</option>';
            foreach ($materialRows as $material) {
                $materialOptions .= '<option value="' . e($material->id) . '">' . e($material->name) . '</option>';
            }

            $unitOptions = '<option value="" selected disabled>--Select Unit--</option>';
            foreach ($unitRows as $unit) {
                $unitOptions .= '<option value="' . e($unit->id) . '">' . e($unit->name) . '</option>';
            }

            $supplierOptions = '<option value="" selected disabled>--Select Supplier--</option>';
            foreach ($supplierRows as $supplier) {
                $disabled = ($supplier->status == 'Pending') ? 'disabled' : '';
                $supplierOptions .= '<option value="' . e($supplier->id) . '" ' . $disabled . '>' . e($supplier->name) . ($supplier->status == 'Pending' ? ' (Pending Activation)' : '') . '</option>';
            }

            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">Add New Material Entry</div>
                        </div>
                        <a href="' . url('/new_material') . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . url('/addnewmaterial') . '" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                            <div style="grid-column: span 2; display:flex; flex-wrap:wrap; gap:10px; justify-content:center; align-items:center;">
                                <div style="text-align:center;">
                                    <img height="80" width="80" src="' . asset('/images/expense.png') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="Material image 1">
                                    <div style="font-size:11px; margin-top:5px; color:#d1d5db;">Image 1</div>
                                    <input type="file" accept="Image/*" name="image[]" style="width:110px; font-size:10px; padding:6px 8px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">
                                </div>
                                <div style="text-align:center;">
                                    <img height="80" width="80" src="' . asset('/images/expense.png') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="Material image 2">
                                    <div style="font-size:11px; margin-top:5px; color:#d1d5db;">Image 2</div>
                                    <input type="file" accept="Image/*" name="image2[]" style="width:110px; font-size:10px; padding:6px 8px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">
                                </div>
                                <div style="text-align:center;">
                                    <img height="80" width="80" src="' . asset('/images/expense.png') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="Material image 3">
                                    <div style="font-size:11px; margin-top:5px; color:#d1d5db;">Image 3</div>
                                    <input type="file" accept="Image/*" name="image3[]" style="width:110px; font-size:10px; padding:6px 8px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">
                                </div>
                                <div style="text-align:center;">
                                    <img height="80" width="80" src="' . asset('/images/expense.png') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="Material image 4">
                                    <div style="font-size:11px; margin-top:5px; color:#d1d5db;">Image 4</div>
                                    <input type="file" accept="Image/*" name="image4[]" style="width:110px; font-size:10px; padding:6px 8px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">
                                </div>
                                <div style="text-align:center;">
                                    <img height="80" width="80" src="' . asset('/images/expense.png') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="Material image 5">
                                    <div style="font-size:11px; margin-top:5px; color:#d1d5db;">Image 5</div>
                                    <input type="file" accept="Image/*" name="image5[]" style="width:110px; font-size:10px; padding:6px 8px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">
                                </div>
                            </div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Site</label><select name="site_id[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $siteOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Supplier</label><select name="supplier[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $supplierOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Material</label><select name="material_id[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $materialOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Unit</label><select name="unit[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $unitOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Quantity</label><input type="number" name="qty[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="0.00"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Converted Qty (Cubic M)</label><input type="number" name="converted_qty[]" step="0.01" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="0.00"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Vehicle</label><input type="text" name="vehical[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Vehicle No"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Remark</label><input type="text" name="remark[]" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Remark (if any)"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Date</label><input type="date" name="date[]" required min="' . e($minDate) . '" max="' . e($maxDate) . '" value="' . e($today) . '" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Save Material Entry</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'task') {
            $userRows = DB::connection($conn)->table('users')->select('id', 'name')->orderBy('name')->get();
            $siteRows = DB::connection($conn)->table('sites')->select('id', 'name')->orderBy('name')->get();
            $categoryRows = DB::connection($conn)->table('task_category')->select('id', 'name')->orderBy('name')->get();

            $assignedOptions = '';
            foreach ($userRows as $user) {
                $assignedOptions .= '<option value="' . e($user->id) . '">' . e($user->name) . '</option>';
            }

            $siteTaskOptions = '<option value="" disabled selected>-- Choose Site --</option>';
            foreach ($siteRows as $site) {
                $siteTaskOptions .= '<option value="' . e($site->id) . '">' . e($site->name) . '</option>';
            }

            $categoryOptions = '<option value="" selected>-- Choose Category (Optional) --</option>';
            foreach ($categoryRows as $category) {
                $categoryOptions .= '<option value="' . e($category->id) . '">' . e($category->name) . '</option>';
            }

            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">Create New Task</div>
                        </div>
                        <a href="' . url('/tasks') . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . url('/tasks') . '" method="POST" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Task Category</label><select name="category_id" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $categoryOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Assigned Site</label><select name="site_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $siteTaskOptions . '</select></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Task Title</label><input type="text" name="title" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="What needs to be done?"></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Description</label><textarea name="description" rows="3" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Add details..."></textarea></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Assign To</label><select name="assigned_to[]" multiple required style="width:100%; min-height:110px; padding:8px 10px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $assignedOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Priority</label><select name="priority" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="Low">Low</option><option value="Medium" selected>Medium</option><option value="High">High</option></select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Due Date</label><input type="date" name="due_date" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Completed Date</label><input type="date" name="completed_at" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Create Task</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'attendance') {
            $userRows = DB::connection($conn)->table('users')->select('id', 'name')->orderBy('name')->get();
            $userOptions = '<option value="" selected disabled>-- Select User --</option>';
            foreach ($userRows as $user) {
                $userOptions .= '<option value="' . e($user->id) . '">' . e($user->name) . '</option>';
            }

            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">Add Manual Attendance</div>
                        </div>
                        <a href="' . url('/attendance') . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . url('/attendance') . '" method="POST" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Site</label><select name="site_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $siteOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">User / Labour</label><select name="user_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $userOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Date</label><input type="date" name="date" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">In Time</label><input type="time" name="in_time" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Out Time</label><input type="time" name="out_time" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Status</label><select name="status" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="Present">Present</option><option value="Absent">Absent</option><option value="Late">Late</option></select></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Remarks</label><textarea name="remarks" rows="3" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Attendance remarks"></textarea></div>
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Save Attendance</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'ticket') {
            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">Create Support Ticket</div>
                        </div>
                        <a href="' . url('/tickets') . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . url('/tickets') . '" method="POST" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Subject</label><input type="text" name="subject" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Ticket subject"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Priority</label><select name="priority" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="Low">Low</option><option value="Medium" selected>Medium</option><option value="High">High</option></select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Department</label><select name="department" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="Support">Support</option><option value="Technical">Technical</option><option value="Operations">Operations</option></select></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Message / Description</label><textarea name="message" rows="4" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Describe the issue"></textarea></div>
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Create Ticket</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'machinery' || $entity === 'machinery_head' || $entity === 'machinery_expense_head') {
            $machineHeadRows = DB::connection($conn)->table('machinery_head')->select('id', 'name')->orderBy('name')->get();
            $expenseHeadRows = DB::connection($conn)->table('machinery_expense_head')->select('id', 'name')->orderBy('name')->get();
            $machineryHeadOptions = '<option value="" selected disabled>--Select Machinery Head--</option>';
            foreach ($machineHeadRows as $row) {
                $machineryHeadOptions .= '<option value="' . e($row->id) . '">' . e($row->name) . '</option>';
            }
            $expenseHeadOptions = '<option value="" selected disabled>--Select Expense Head--</option>';
            foreach ($expenseHeadRows as $row) {
                $expenseHeadOptions .= '<option value="' . e($row->id) . '">' . e($row->name) . '</option>';
            }

            $title = ($entity === 'machinery_expense_head') ? 'Add Machinery Expense Head' : (($entity === 'machinery_head') ? 'Add Machinery Head' : 'Add New Machinery');
            $action = ($entity === 'machinery_expense_head') ? url('/addmachineryExpensehead') : (($entity === 'machinery_head') ? url('/addmachineryhead') : url('/add_newmechinery'));

            $headFields = ($entity === 'machinery') ? '
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Machinery Head</label><select name="head_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $machineryHeadOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Expense Head</label><select name="expense_head_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $expenseHeadOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Site</label><select name="site_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $siteOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Machinery Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Machine name"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Purchase Date</label><input type="date" name="purchase_date" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Amount</label><input type="number" step="0.01" name="amount" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="0.00"></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Remarks</label><textarea name="remark" rows="3" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Entry remarks"></textarea></div>' : (
                ($entity === 'machinery_head') ? '
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Head Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Machinery head name"></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Description</label><textarea name="description" rows="3" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Description"></textarea></div>' : '
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Expense Head Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Machinery expense head name"></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Description</label><textarea name="description" rows="3" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Expense head description"></textarea></div>'
            );

            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">' . e($title) . '</div>
                        </div>
                        <a href="' . $action . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . $action . '" method="POST" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                            ' . $headFields . '
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Save</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'asset' || $entity === 'asset_head') {
            $assetHeadRows = DB::connection($conn)->table('asset_head')->select('id', 'name')->orderBy('name')->get();
            $assetHeadOptions = '<option value="" selected disabled>--Select Asset Head--</option>';
            foreach ($assetHeadRows as $row) {
                $assetHeadOptions .= '<option value="' . e($row->id) . '">' . e($row->name) . '</option>';
            }

            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">' . ($entity === 'asset_head' ? 'Add Asset Head' : 'Add New Asset') . '</div>
                        </div>
                        <a href="' . ($entity === 'asset_head' ? url('/assets_head') : url('/new_asset')) . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . ($entity === 'asset_head' ? url('/add_asset_head') : url('/addnew_asset')) . '" method="POST" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                            ' . ($entity === 'asset_head' ? '
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Asset Head Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Asset head name"></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Description</label><textarea name="description" rows="3" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Description"></textarea></div>' : '
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Asset Head</label><select name="asset_head_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $assetHeadOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Site</label><select name="site_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $siteOptions . '</select></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Asset Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Asset name"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Purchase Date</label><input type="date" name="purchase_date" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Cost</label><input type="number" min="0" step="0.01" name="cost" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="0.00"></div>
                            <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Status</label><select name="status" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="Active">Active</option><option value="Sold">Sold</option><option value="Maintenance">Maintenance</option></select></div>
                            <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Remarks</label><textarea name="remark" rows="3" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Remarks"></textarea></div>') . '
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Save</button>
                        </div>
                    </form>
                </div>
            ';
        }

        if ($entity === 'sales_party' || $entity === 'sales_project' || $entity === 'invoice_head' || $entity === 'contact_category' || $entity === 'contact_company' || $entity === 'cost_category' || $entity === 'expense_party' || $entity === 'material_supplier' || $entity === 'material_unit' || $entity === 'material_entry' || $entity === 'bill_party' || $entity === 'payment_voucher') {
            $actionUrl = [
                'sales_party' => url('/sales_parties'),
                'sales_project' => url('/sales_project'),
                'invoice_head' => url('/sales_inv_head'),
                'contact_category' => url('/contact_category'),
                'contact_company' => url('/contacts'),
                'cost_category' => url('/cost_categories'),
                'expense_party' => url('/expense_parties'),
                'material_supplier' => url('/materialsupplier'),
                'material_unit' => url('/material_unit'),
                'material_entry' => url('/new_material'),
                'bill_party' => url('/billparty'),
                'payment_voucher' => url('/new_paymentvoucher')
            ][$entity] ?? url('/dashboard');

            $title = [
                'sales_party' => 'Add Sales Party',
                'sales_project' => 'Add Sales Project',
                'invoice_head' => 'Add Invoice Head',
                'contact_category' => 'Add Contact Category',
                'contact_company' => 'Add Company in Contacts',
                'cost_category' => 'Add Cost Category',
                'expense_party' => 'Add Expense Party',
                'material_supplier' => 'Add Material Supplier',
                'material_unit' => 'Add Material Unit',
                'material_entry' => 'Add Material Entry',
                'bill_party' => 'Add New Bill Party',
                'payment_voucher' => 'Generate Payment Voucher'
            ][$entity] ?? 'Add Entry';

            $content = '';
            if ($entity === 'sales_party') {
                $content = '<div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Party Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Sales party name"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Address</label><input type="text" name="address" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Address"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Phone</label><input type="text" name="phone" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Phone"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">GST</label><input type="text" name="gst" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="GST number"></div>';
            } elseif ($entity === 'sales_project') {
                $content = '<div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Project Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Project name"></div><div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Details</label><textarea name="details" rows="3" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Project description"></textarea></div>';
            } elseif ($entity === 'invoice_head') {
                $content = '<div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Head Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Invoice head name"></div>';
            } elseif ($entity === 'contact_category') {
                $content = '<div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Category Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Contact category"></div>';
            } elseif ($entity === 'contact_company') {
                $content = '<div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Company Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Company name"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Phone</label><input type="text" name="phone" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Phone"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Email</label><input type="email" name="email" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Email"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Address</label><input type="text" name="address" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Address"></div>';
            } elseif ($entity === 'cost_category') {
                $content = '<div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Cost Category Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Cost category name"></div>';
            } elseif ($entity === 'expense_party') {
                $content = '<div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Party Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Expense party name"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Address</label><input type="text" name="address" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Address"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">PAN</label><input type="text" name="pan_no" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="PAN number"></div>';
            } elseif ($entity === 'material_supplier') {
                $content = '<div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Supplier Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Supplier name"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Address</label><input type="text" name="address" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Supplier address"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">GSTIN</label><input type="text" name="gstin" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="GSTIN"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Bank A/c</label><input type="text" name="bank_ac" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Account number"></div>';
            } elseif ($entity === 'material_unit') {
                $content = '<div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Unit Name</label><input type="text" name="name" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Unit name"></div>';
            } elseif ($entity === 'material_entry') {
                $content = '<div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Site</label><select name="site_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $siteOptions . '</select></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Material</label><select name="material_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $siteOptions . '</select></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Quantity</label><input type="number" step="0.01" name="qty" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="0.00"></div><div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Date</label><input type="date" name="date" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>';
            } elseif ($entity === 'bill_party') {
                $costCategoryRows = DB::connection($conn)->table('expense_head')->select('id', 'name')->orderBy('name')->get();
                $costCategoryOptions = '<option value="" selected disabled>-- Select Cost Category --</option>';
                foreach ($costCategoryRows as $category) {
                    $costCategoryOptions .= '<option value="' . e($category->id) . '">' . e($category->name) . '</option>';
                }

                $content = '
                    <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Name</label><input type="text" id="Name" required name="name" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Bill party name"></div>
                    <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Address</label><input type="text" id="Address" required name="address" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Address"></div>
                    <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Pan No.</label><input type="text" id="panno" required name="panno" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Pan No."></div>
                    <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Bank A/C</label><input type="text" id="bank_ac" required name="bank_ac" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Account number"></div>
                    <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Bank Ifsc</label><input type="text" id="ifsc" required name="ifsc" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="IFSC"></div>
                    <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Bank Name</label><input type="text" id="bankname" required name="bankname" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Bank name"></div>
                    <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Bank A/C Holder</label><input type="text" id="ac_holder_name" required name="ac_holder_name" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Bank account holder name"></div>
                    <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Cost Category</label><select name="cost_category_id" id="cost_category_id" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $costCategoryOptions . '</select></div>
                    <div style="grid-column: span 2;"><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">QR Code Image</label><input type="file" id="qr_code" class="form-control" name="qr_code" accept="image/*" style="width:100%; padding:8px 10px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                ';
            } elseif ($entity === 'payment_voucher') {
                $selectedCompanyId = $tenant['comp_db_id'] ?? session()->get('comp_db_id') ?? '';
                $selectedCompanyName = $tenant['comp_name'] ?? session()->get('comp_name') ?? 'Company';
                $companyField = !empty($selectedCompanyId)
                    ? '<div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Company</label><input type="hidden" name="company_id[]" value="' . e($selectedCompanyId) . '"><select class="form-control" disabled style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="' . e($selectedCompanyId) . '" selected>' . e($selectedCompanyName) . '</option></select></div>'
                    : '<div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Company</label><input type="hidden" name="company_id[]" value=""><select class="form-control" disabled style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"><option value="" selected>--Select Company--</option></select></div>';

                $siteOptionsForVoucher = '<option value="" selected disabled>--Select Voucher Party First--</option>';
                foreach ($sites as $site) {
                    $siteOptionsForVoucher .= '<option value="' . e($site->id) . '">' . e($site->name) . '</option>';
                }

                $partyOptionsForVoucher = '<option value="" selected disabled>--Select Voucher Party--</option>';
                $partyRows = DB::connection($conn)->table('material_supplier')->select('id', 'name')->orderBy('name')->get();
                foreach ($partyRows as $party) {
                    $partyOptionsForVoucher .= '<option value="' . e($party->id) . '||material">' . e($party->name) . '</option>';
                }
                $billRows = DB::connection($conn)->table('bills_party')->select('id', 'name')->orderBy('name')->get();
                foreach ($billRows as $party) {
                    $partyOptionsForVoucher .= '<option value="' . e($party->id) . '||bill">' . e($party->name) . '</option>';
                }
                $otherRows = DB::connection($conn)->table('other_parties')->select('id', 'name')->orderBy('name')->get();
                foreach ($otherRows as $party) {
                    $partyOptionsForVoucher .= '<option value="' . e($party->id) . '||other">' . e($party->name) . '</option>';
                }
                foreach ($sites as $site) {
                    $partyOptionsForVoucher .= '<option value="' . e($site->id) . '||site||W">' . e($site->name) . '</option>';
                }

                $content = '
                    <div style="grid-column: span 2; display:flex; justify-content:flex-start; gap:16px; flex-wrap:wrap; align-items:flex-start;">
                        <div style="min-width:160px;">
                            <label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Voucher Image</label>
                            <img height="120" width="120" src="' . asset('/images/expense.png') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="Voucher image preview">
                            <input type="file" accept="Image/*" name="image[]" style="margin-top:8px; width:100%; padding:8px 10px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">
                        </div>
                        <div style="min-width:160px;">
                            <label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">QR Code Image</label>
                            <img height="120" width="120" src="' . asset('/images/expense.png') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="QR image preview">
                            <input type="file" accept="Image/*" name="qr_code[]" style="margin-top:8px; width:100%; padding:8px 10px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">
                        </div>
                    </div>
                    ' . $companyField . '
                    <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Voucher Party</label><select name="party_id[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $partyOptionsForVoucher . '</select></div>
                    <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Site</label><select name="site_id[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;">' . $siteOptionsForVoucher . '</select></div>
                    <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Voucher No.</label><input type="text" name="voucher_no[]" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Enter The Voucher No."></div>
                    <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Amount</label><input type="number" name="amount[]" min="0" step="0.01" required style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="0.00"></div>
                    <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Date</label><input type="date" name="date[]" required value="' . e($today) . '" min="' . e($minDate) . '" max="' . e($maxDate) . '" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;"></div>
                    <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Payment Details</label><input type="text" name="payment_details[]" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Enter The Payment Details"></div>
                    <div><label style="display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;">Remark</label><input type="text" name="remark[]" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff;" placeholder="Enter The Remark (If Any)"></div>';
            }

            return '
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                        <div>
                            <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                            <div style="font-size:20px; font-weight:700; margin-top:4px;">' . e($title) . '</div>
                        </div>
                        <a href="' . $actionUrl . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                    </div>
                    <form action="' . $actionUrl . '" method="POST" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                        ' . csrf_field() . '
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                            ' . $content . '
                        </div>
                        <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                            <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700;">Save</button>
                        </div>
                    </form>
                </div>
            ';
        }

        return '
            <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                <div style="font-size:18px; font-weight:700; margin-bottom:8px;">Add ' . e(ucfirst($entity)) . '</div>
                <p style="margin:0; color:#d1d5db;">The prompt is recognized as a create action, but the exact form is not yet mapped for this module.</p>
                <div style="margin-top:12px;"><a href="' . url('/users') . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-weight:700;">Open main list page</a></div>
            </div>
        ';
    }

    private function renderApiKeyNotice()
    {
        $hasKey = !empty(env('OPENAI_API_KEY')) || !empty(env('GEMINI_API_KEY')) || !empty(env('GROQ_API_KEY')) || !empty(env('DEEPSEEK_API_KEY'));
        if ($hasKey) {
            return '';
        }

        return '
            <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 12px; color: #fbbf24;">
                <div style="font-weight: 700; color: #f59e0b; font-size: 11px; margin-bottom: 3px; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                    <i class="zmdi zmdi-alert-triangle"></i> AI Provider API Key Not Configured (.env)
                </div>
                <div>
                    To enable full free-form text-to-SQL generation using OpenAI or Gemini, set <code>OPENAI_API_KEY=your_key</code> in <code>.env</code>.
                    <br><span style="color:#d1d5db; font-size:11px;">Query converted to SQL via Buildarya Text-to-SQL Engine:</span>
                </div>
            </div>
        ';
    }

    private function renderSqlBadge($sql, $provider = 'Buildarya Text-to-SQL AI Engine')
    {
        if (empty($sql)) return '';
        $apiNotice = (strpos($provider, 'Buildarya') !== false) ? $this->renderApiKeyNotice() : '';
        return $apiNotice . '
            <div style="background: rgba(16, 163, 127, 0.1); border: 1px solid rgba(16, 163, 127, 0.3); border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-family: monospace; font-size: 12px; color: #34d399; overflow-x: auto;">
                <div style="font-weight: 700; color: #10a37f; font-size: 11px; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">
                    ⚡ AI Generated SQL Query (' . e($provider) . ')
                </div>
                <code>' . e($sql) . '</code>
            </div>
        ';
    }

    private function renderRestrictionNotice($user_name, $user_username, $site_name, $isOtherSiteRequest, $is_superadmin)
    {
        if ($isOtherSiteRequest && !$is_superadmin) {
            return '
                <div style="background: rgba(239, 68, 68, 0.12); border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;">
                    <div style="font-weight: 700; color: #fca5a5; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                        <i class="zmdi zmdi-lock"></i> Role Access Restriction Applied
                    </div>
                    <div style="font-size: 12px; color: #d1d5db; margin-top: 4px;">
                        User <strong>' . e($user_name) . '</strong> (' . e($user_username) . ') is scoped strictly to <strong>' . e($site_name) . '</strong>. 
                        Access to unassigned site data is restricted. Showing authorized records below:
                    </div>
                </div>
            ';
        }
        return '';
    }

    /**
     * Render dynamic HTML table for arbitrary AI Text-to-SQL Query results
     */
    private function buildDynamicSqlHtml($rows, $sql, $provider, $queryText, $tenant, $isOtherSiteRequest, $isPdfRequest)
    {
        $sqlBadge = $this->renderSqlBadge($sql, $provider);
        $restriction = $this->renderRestrictionNotice($tenant['user_name'], $tenant['user_username'], $tenant['site_name'], $isOtherSiteRequest, $tenant['is_superadmin']);

        $pdfBannerHtml = '';
        if ($isPdfRequest) {
            $pdfBannerHtml = '
                <div style="background: linear-gradient(135deg, rgba(16, 163, 127, 0.15), rgba(13, 138, 106, 0.25)); border: 1px solid rgba(16, 163, 127, 0.4); border-radius: 12px; padding: 16px 20px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;">
                    <div>
                        <div style="font-weight: 700; font-size: 15px; color: #ffffff; display: flex; align-items: center; gap: 8px;">
                            <i class="zmdi zmdi-file-text" style="color: #10a37f; font-size: 20px;"></i>
                            <span>Attendance PDF Report — ' . e($tenant['site_name']) . '</span>
                        </div>
                        <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                            Official site attendance logs formatted as PDF document matching your request.
                        </div>
                    </div>
                    <a href="' . url('/attendance/export?type=pdf') . '" target="_blank" style="background: #10a37f; color: #ffffff; font-weight: 700; padding: 10px 18px; border-radius: 8px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3); transition: background 0.2s ease;">
                        <i class="zmdi zmdi-download"></i> Download Attendance Report (CSV/PDF)
                    </a>
                </div>
            ';
        }

        if (empty($rows)) {
            return "{$restriction}{$sqlBadge}{$pdfBannerHtml}<p><strong>🤖 AI Text-to-SQL Query Results — " . e($tenant['site_name']) . ":</strong></p><p>No records found in database matching query: <em>\"" . e($queryText) . "\"</em>.</p>";
        }

        $firstRow = (array)$rows[0];
        $columns = array_keys($firstRow);

        $thHtml = '';
        foreach ($columns as $col) {
            $formattedHeader = ucwords(str_replace('_', ' ', $col));
            $thHtml .= '<th>' . e($formattedHeader) . '</th>';
        }

        $trHtml = '';
        foreach ($rows as $row) {
            $rowArr = (array)$row;
            $trHtml .= '<tr>';
            foreach ($columns as $col) {
                $val = $rowArr[$col] ?? 'N/A';
                if (is_array($val) || is_object($val)) {
                    $val = json_encode($val);
                }
                $trHtml .= '<td>' . e((string)$val) . '</td>';
            }
            $trHtml .= '</tr>';
        }

        return "
            {$restriction}
            {$sqlBadge}
            {$pdfBannerHtml}
            <p><strong>🤖 AI Text-to-SQL Dynamic Results — " . e($tenant['site_name']) . " (Generated via {$provider}):</strong></p>
            <table>
                <thead>
                    <tr>{$thHtml}</tr>
                </thead>
                <tbody>
                    {$trHtml}
                </tbody>
            </table>
            <p>Fetched <strong>" . count($rows) . " matching records</strong> dynamically from tenant database.</p>
        ";
    }

    private function buildGeneralHtml($records, $summaryText, $sqlGenerated, $queryText, $site_name, $user_name, $user_username, $isOtherSiteRequest, $is_superadmin)
    {
        $sqlBadge = $this->renderSqlBadge($sqlGenerated);
        $restriction = $this->renderRestrictionNotice($user_name, $user_username, $site_name, $isOtherSiteRequest, $is_superadmin);

        return "
            {$restriction}
            {$sqlBadge}
            <p><strong>Buildarya AI Text-to-Query Database Report — " . e($site_name) . ":</strong></p>
            <p>Processed text query: <em>\"" . e($queryText) . "\"</em> for user <strong>" . e($user_name) . "</strong> (" . e($user_username) . ").</p>
            <p>Available Module Summary for <strong>" . e($site_name) . "</strong>:</p>
            <ul>
                <li><strong>Material Suppliers:</strong> " . ($records['suppliers'] ?? 0) . " records available</li>
                <li><strong>Attendance Check-ins:</strong> " . ($records['attendance'] ?? 0) . " records available</li>
                <li><strong>Expense Vouchers:</strong> " . ($records['expenses'] ?? 0) . " records available</li>
                <li><strong>Material Entry Logs:</strong> " . ($records['materials'] ?? 0) . " records available</li>
                <li><strong>Task Assignments:</strong> " . ($records['tasks'] ?? 0) . " records available</li>
                <li><strong>Team Members:</strong> " . ($records['users'] ?? 0) . " registered users</li>
            </ul>
        ";
    }
}
