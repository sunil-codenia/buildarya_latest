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
     * LLM Engine Call to dynamically generate SQL query from free-form text input via external APIs
     */
    private function callLlmForSql($queryText, $tenant)
    {
        $openaiKey = env('OPENAI_API_KEY');
        $geminiKey = env('GEMINI_API_KEY');
        $groqKey = env('GROQ_API_KEY');
        $deepseekKey = env('DEEPSEEK_API_KEY');

        if (!$openaiKey && !$geminiKey && !$groqKey && !$deepseekKey) {
            return null;
        }

        $systemPrompt = "You are an expert MySQL query generator for Buildarya Construction ERP.\n"
            . "Generate ONLY a single valid executable MySQL SELECT query based on user request.\n"
            . "CRITICAL RULES:\n"
            . "1. ONLY return the raw SQL string without any explanation, title, markdown formatting, or ```sql blocks.\n"
            . "2. ONLY generate SELECT queries. NEVER generate INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE.\n"
            . "3. If user input is a greeting (e.g. 'hi', 'hello', 'hey'), reply with 'NONE'.\n"
            . "4. Always add 'LIMIT 25' unless counting records.\n\n"
            . "DATABASE SCHEMA:\n"
            . "- attendance (id, user_id, bills_party_id, site_id, date, in_time, out_time, status, remarks)\n"
            . "- expenses (id, particular, amount, user_id, site_id, head_id, party_id, party_type, status, date, location, remark)\n"
            . "- tasks (id, title, description, site_id, priority, status, due_date)\n"
            . "- material_entry (id, material_id, site_id, qty, vehical, date, status, remark)\n"
            . "- material_supplier (id, name, address, gstin, bank_name, bank_ac, status)\n"
            . "- users (id, name, username, contact_no, site_id, status)\n"
            . "- sites (id, name, address)\n"
            . "- bills_party (id, name, mobile_no)\n"
            . "- expense_party (id, name)\n"
            . "- expense_head (id, name)\n\n"
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
        if (strpos($lower, 'role') !== false || strpos($lower, 'permission') !== false) {
            $selectQuery = "SELECT id, name, is_superadmin, created_at FROM roles";
        } else if (strpos($lower, 'site') !== false || strpos($lower, 'location') !== false || strpos($lower, 'branch') !== false || strpos($lower, 'project') !== false) {
            $selectQuery = "SELECT id, name, address, status, sites_type FROM sites";
            $sf = $getSiteFilter('id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'user') !== false || strpos($lower, 'usr') !== false || strpos($lower, 'staff') !== false || strpos($lower, 'team') !== false || strpos($lower, 'employee') !== false || strpos($lower, 'member') !== false) {
            $selectQuery = "SELECT id, name, username, contact_no, status FROM users";
            if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $queryText, $m)) {
                $v = addslashes(trim($m[1]));
                $whereClauses[] = "(username LIKE '%{$v}%' OR name LIKE '%{$v}%')";
            }
        } else if (strpos($lower, 'supplier') !== false || strpos($lower, 'suplier') !== false || strpos($lower, 'vendor') !== false || strpos($lower, 'dealer') !== false || strpos($lower, 'supply') !== false) {
            $selectQuery = "SELECT id, name, address, gstin, bank_name, bank_ac, status FROM material_supplier";
        } else if (strpos($lower, 'attendance') !== false || strpos($lower, 'attendace') !== false || strpos($lower, 'attendac') !== false || strpos($lower, 'atteance') !== false || strpos($lower, 'attandance') !== false || strpos($lower, 'attandace') !== false || strpos($lower, 'atendance') !== false || strpos($lower, 'attendence') !== false || strpos($lower, 'attndance') !== false || strpos($lower, 'labour') !== false || strpos($lower, 'headcount') !== false || strpos($lower, 'present') !== false || strpos($lower, 'checkin') !== false) {
            $selectQuery = "SELECT attendance.id, COALESCE(users.name, 'Labour') as person_name, attendance.date, attendance.in_time, attendance.out_time, attendance.status, attendance.remarks FROM attendance LEFT JOIN users ON users.id=attendance.user_id";
            $sf = $getSiteFilter('attendance.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'expense') !== false || strpos($lower, 'expence') !== false || strpos($lower, 'petty') !== false || strpos($lower, 'voucher') !== false || strpos($lower, 'cost') !== false || strpos($lower, 'audit') !== false) {
            $selectQuery = "SELECT expenses.id, expenses.particular, expenses.amount, COALESCE(users.name, 'Staff') as recorded_by, expenses.date, expenses.status, expenses.remark FROM expenses LEFT JOIN users ON users.id=expenses.user_id";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'stock') !== false || strpos($lower, 'material') !== false || strpos($lower, 'matrial') !== false || strpos($lower, 'steel') !== false || strpos($lower, 'cement') !== false || strpos($lower, 'entry') !== false) {
            $selectQuery = "SELECT material_entry.id, materials.name as material_name, material_entry.qty, material_entry.vehical, material_entry.date, material_entry.status FROM material_entry LEFT JOIN materials ON materials.id=material_entry.material_id";
            $sf = $getSiteFilter('material_entry.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'task') !== false || strpos($lower, 'taks') !== false || strpos($lower, 'todo') !== false || strpos($lower, 'assignment') !== false || strpos($lower, 'work') !== false) {
            $selectQuery = "SELECT tasks.id, tasks.title, sites.name as site_name, COALESCE(users.name, 'Admin') as assigned_to, tasks.priority, tasks.status, tasks.created_at as due_date FROM tasks LEFT JOIN sites ON sites.id=tasks.site_id LEFT JOIN users ON users.id=tasks.created_by";
            $sf = $getSiteFilter('tasks.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'asset') !== false || strpos($lower, 'machinery') !== false || strpos($lower, 'machine') !== false || strpos($lower, 'tool') !== false) {
            $selectQuery = "SELECT id, name, cost_price, status, create_datetime FROM assets";
        }

        if (!$selectQuery) {
            return null;
        }

        // 2. Parse Date Conditions from User Prompt (Today, Yesterday, This Month, Specific Date)
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

        if ($dateColumn) {
            if (preg_match('/\b(today|todays|today\'s|todays\s+only)\b/i', $lower)) {
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
        // Check for explicit user pattern like "assign to <name>", "task for <name>", "for user <name>"
        if (preg_match('/(?:assign(?:ed)?\s+to|for\s+user|to\s+user|for)\s+([a-zA-Z0-9._%+-@]+)/i', $queryText, $uMatch)) {
            $userVal = addslashes(trim($uMatch[1]));
            $reservedWords = ['head office', 'all site', 'today', 'todays', 'yesterday', 'this month', 'month', 'week', 'year', 'task', 'tasks'];
            if (!empty($userVal) && !in_array(strtolower($userVal), $reservedWords)) {
                if (strpos($selectQuery, 'tasks') !== false) {
                    $whereClauses[] = "(users.name LIKE '%{$userVal}%' OR users.username LIKE '%{$userVal}%')";
                } else if (strpos($selectQuery, 'users') !== false) {
                    $whereClauses[] = "(name LIKE '%{$userVal}%' OR username LIKE '%{$userVal}%')";
                }
            }
        } else if (preg_match('/(?:is|equals|equal|named|called|with|whose\s+\w+\s+is)\s+[\'"]?([a-zA-Z0-9._%+-@\s]+)[\'"]?/i', $queryText, $filterMatch)) {
            $val = addslashes(trim($filterMatch[1]));
            $reservedWords = ['head office', 'all site', 'today', 'todays', 'yesterday', 'this month', 'month', 'week', 'year', 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december', 'jan', 'feb', 'mar', 'apr', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
            if (!empty($val) && !in_array(strtolower($val), $reservedWords)) {
                if (strpos($selectQuery, 'users') !== false) {
                    $whereClauses[] = "(name LIKE '%{$val}%' OR username LIKE '%{$val}%' OR contact_no LIKE '%{$val}%')";
                } else if (strpos($selectQuery, 'material_supplier') !== false) {
                    $whereClauses[] = "name LIKE '%{$val}%'";
                } else if (strpos($selectQuery, 'tasks') !== false) {
                    $whereClauses[] = "(tasks.title LIKE '%{$val}%' OR users.name LIKE '%{$val}%' OR users.username LIKE '%{$val}%')";
                } else if (strpos($selectQuery, 'expenses') !== false) {
                    $whereClauses[] = "particular LIKE '%{$val}%'";
                }
            }
        }

        $whereSql = !empty($whereClauses) ? ' WHERE ' . implode(' AND ', $whereClauses) : '';
        return "{$selectQuery}{$whereSql} ORDER BY 1 DESC LIMIT 25";
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

            // If query could not be translated into a valid DB query (off-topic / unnecessary user request)
            $html = $this->buildTrainingNoticeHtml($queryText, $user_name, $site_name);
            return response()->json([
                'status' => 'Ok',
                'status_code' => 200,
                'message' => 'Query is currently under AI model training',
                'data' => [
                    'query' => $queryText,
                    'intent' => 'training_notice',
                    'ai_provider' => 'Buildarya Text-to-SQL AI Engine',
                    'sql_generated' => '',
                    'active_site' => $site_name,
                    'records_count' => 0,
                    'records' => [],
                    'summary' => "We are currently training our AI model for this type of query.",
                    'html' => $html,
                    'is_pdf_requested' => false,
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

    /**
     * Build training notice HTML for off-topic or unnecessary user queries
     */
    private function buildTrainingNoticeHtml($queryText, $user_name, $site_name)
    {
        return '
            <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 12px; padding: 18px 22px; margin-bottom: 14px; color: #ffffff;">
                <div style="font-weight: 700; font-size: 15px; color: #fbbf24; margin-bottom: 6px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">🤖</span> Buildarya AI Training Notice
                </div>
                <div style="font-size: 13.5px; line-height: 1.6; color: #fef3c7;">
                    We are currently training our AI model for this type of query: <em>"' . e($queryText) . '"</em>
                </div>
                <div style="margin-top: 12px; font-size: 13px; color: #d1d5db; line-height: 1.6;">
                    Please ask queries related to your <strong>' . e($site_name) . '</strong> database such as:
                    <ul style="margin-top: 6px; margin-bottom: 0; padding-left: 18px; color: #e5e7eb; line-height: 1.8;">
                        <li>👷 <em>"Show attendance records for today"</em></li>
                        <li>📋 <em>"Show task list assigned to Sunil"</em></li>
                        <li>💰 <em>"Show expense vouchers"</em></li>
                        <li>📦 <em>"Check material stock entries"</em></li>
                        <li>🏬 <em>"Show material suppliers list"</em></li>
                        <li>👥 <em>"Show site users list"</em></li>
                    </ul>
                </div>
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
        foreach (array_slice($rows, 0, 30) as $row) {
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
