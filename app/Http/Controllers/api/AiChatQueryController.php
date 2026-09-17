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
     * Detect a suitable date column from the SELECT query by inspecting involved tables' schema.
     * Returns qualified column name like `table.column` or null when none found.
     */
    private function detectDateColumnFromSelect($selectQuery, $conn)
    {
        try {
            $tables = [];
            if (preg_match_all('/\bFROM\s+([a-zA-Z0-9_]+)\b/i', $selectQuery, $m)) {
                $tables = array_merge($tables, $m[1]);
            }
            if (preg_match_all('/\bJOIN\s+([a-zA-Z0-9_]+)\b/i', $selectQuery, $m2)) {
                $tables = array_merge($tables, $m2[1]);
            }
            $tables = array_values(array_unique($tables));

            $namePriority = ['date', 'created_at', 'created_on', 'created', 'start_date', 'end_date', 'entry_date'];
            $typePriority = ['date', 'datetime', 'timestamp', 'year'];

            foreach ($tables as $table) {
                $cols = DB::connection($conn)->select("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? ORDER BY ORDINAL_POSITION", [$table]);
                if (empty($cols)) continue;

                // 1) Prefer columns with canonical date-like names
                foreach ($namePriority as $preferred) {
                    foreach ($cols as $c) {
                        $colName = $c->COLUMN_NAME ?? $c->column_name;
                        if (strtolower($colName) === strtolower($preferred)) {
                            return "{$table}.{$colName}";
                        }
                    }
                }

                // 2) Prefer any column whose name contains 'date' or 'time' or 'at'
                foreach ($cols as $c) {
                    $colName = $c->COLUMN_NAME ?? $c->column_name;
                    if (preg_match('/date|time|at/i', $colName)) {
                        return "{$table}.{$colName}";
                    }
                }

                // 3) Prefer by data type
                foreach ($typePriority as $tType) {
                    foreach ($cols as $c) {
                        $colName = $c->COLUMN_NAME ?? $c->column_name;
                        $dataType = strtolower($c->DATA_TYPE ?? $c->data_type ?? '');
                        if ($dataType === $tType) {
                            return "{$table}.{$colName}";
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // ignore and return null
        }

        // Fallback: detect directly from query text if information_schema query was unavailable
        if (preg_match('/([a-zA-Z0-9_]+\.date)\b/i', $selectQuery, $mDateCol)) {
            return $mDateCol[1];
        }

        return null;
    }

    /**
     * LLM Engine Call to dynamically generate SQL query from free-form text input via external APIs
     */
    private function callLlmForSql($queryText, $tenant)
    {
        $openaiKey = getenv('OPENAI_API_KEY') !== false ? getenv('OPENAI_API_KEY') : env('OPENAI_API_KEY');
        $geminiKey = getenv('GEMINI_API_KEY') !== false ? getenv('GEMINI_API_KEY') : env('GEMINI_API_KEY');
        $groqKey = getenv('GROQ_API_KEY') !== false ? getenv('GROQ_API_KEY') : env('GROQ_API_KEY');
        $deepseekKey = getenv('DEEPSEEK_API_KEY') !== false ? getenv('DEEPSEEK_API_KEY') : env('DEEPSEEK_API_KEY');

        $openaiKey = !empty($openaiKey) ? $openaiKey : null;
        $geminiKey = !empty($geminiKey) ? $geminiKey : null;
        $groqKey = !empty($groqKey) ? $groqKey : null;
        $deepseekKey = !empty($deepseekKey) ? $deepseekKey : null;

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
            . "8. For task queries, tasks.assigned_to stores user IDs (or comma-separated IDs). To query tasks assigned to a specific user by name (e.g. 'sunil'), filter using EXISTS (SELECT 1 FROM users WHERE (users.name LIKE '%sunil%' OR users.username LIKE '%sunil%') AND (FIND_IN_SET(users.id, tasks.assigned_to) OR tasks.assigned_to = CAST(users.id AS CHAR) OR tasks.assigned_by = users.id)). Include assigned user names in SELECT via (SELECT GROUP_CONCAT(name SEPARATOR ', ') FROM users WHERE FIND_IN_SET(users.id, tasks.assigned_to) OR users.id = tasks.assigned_to) as assigned_to.\n"
            . "9. CRITICAL RULE FOR EXPENSE QUERIES:\n"
            . "When selecting or fetching records from the `expenses` table:\n"
            . "- NEVER execute a raw 'SELECT * FROM expenses' that returns raw numeric IDs.\n"
            . "- NEVER return raw IDs (party_id, head_id, site_id, user_id). Users require proper human-readable names in place of raw IDs:\n"
            . "  * In place of site_id, return the Site Name via: LEFT JOIN sites ON sites.id = expenses.site_id -> sites.name AS site_name\n"
            . "  * In place of user_id, return the User Name via: LEFT JOIN users ON users.id = expenses.user_id -> users.name AS user_name\n"
            . "  * In place of party_id, return the Party Name via: LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') -> COALESCE(expense_party.name, bills_party.name) AS party_name\n"
            . "  * In place of head_id, return the Cost Category Name via: LEFT JOIN expense_head ON expense_head.id = expenses.head_id -> expense_head.name AS cost_category_name\n"
            . "- Standard SELECT for expense queries MUST be:\n"
            . "SELECT expenses.id, COALESCE(expense_party.name, bills_party.name) AS party_name, expenses.party_type, expense_head.name AS cost_category_name, expenses.particular, expenses.amount, expenses.remark, expenses.image, sites.name AS site_name, users.name AS user_name, expenses.status, expenses.location, expenses.date FROM expenses LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') LEFT JOIN expense_head ON expense_head.id = expenses.head_id LEFT JOIN sites ON sites.id = expenses.site_id LEFT JOIN users ON users.id = expenses.user_id\n"
            . "- Always prefix WHERE/ORDER BY columns with table name 'expenses.' (e.g. expenses.site_id = 45, expenses.status = 'Approved') to avoid ambiguous column errors.\n\n"
            . "10. VOICE ASSISTANT & SPOKEN QUERIES:\n"
            . "The user input may be spoken or voice-transcribed via Speech-to-Text. Accurately resolve spoken terms and colloquial phrases:\n"
            . "- 'kharcha', 'kharch', 'hisaab', 'petty cash' refer to expenses (expenses table).\n"
            . "- 'haziri', 'hajiri', 'present', 'attendance' refer to attendance (attendance table).\n"
            . "- 'maal', 'saman', 'stock', 'inventory' refer to materials (material_entry or materials table).\n"
            . "- 'kaam', 'task', 'pending work' refer to tasks (tasks table).\n"
            . "- 'aaj', 'aaj ka', 'today' refers to current date CURDATE().\n"
            . "- Ignore verbal conversational filler phrases like 'please', 'can you show me', 'give me', 'bhai', 'tell me'.\n\n"
            . "11. STATUS FILTERING RULE:\n"
            . "When user asks for 'pending' records (e.g. 'pending expenses', 'pending materials', 'pending tasks', 'pending vouchers'):\n"
            . "- Must add status WHERE clause:\n"
            . "  * For expenses: expenses.status LIKE '%Pending%'\n"
            . "  * For attendance: attendance.status LIKE '%Pending%'\n"
            . "  * For material_entry: material_entry.status LIKE '%Pending%'\n"
            . "  * For tasks: tasks.status LIKE '%Pending%'\n"
            . "  * For payment_vouchers: payment_vouchers.status LIKE '%Pending%'\n"
            . "When user asks for 'verified' or 'approved' records (e.g. 'verified expenses', 'approved expenses', 'verified vouchers'):\n"
            . "- Must add status WHERE clause:\n"
            . "  * For expenses: (expenses.status LIKE '%Approved%' OR expenses.status LIKE '%Verified%')\n"
            . "  * For material_entry: (material_entry.status LIKE '%Approved%' OR material_entry.status LIKE '%Verified%')\n"
            . "  * For tasks: (tasks.status LIKE '%Completed%' OR tasks.status LIKE '%Approved%')\n"
            . "  * For payment_vouchers: (payment_vouchers.status LIKE '%Approved%' OR payment_vouchers.status LIKE '%Verified%')\n"
            . "When user asks for 'rejected' records:\n"
            . "- Filter with status LIKE '%Rejected%'.\n"
            . "When user asks for 'all' records or doesn't specify a status filter, do NOT restrict by status.\n\n"
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
                $model = env('GEMINI_MODEL', 'gemini-3.6-flash');
                if (empty($model) || $model === 'gemini-1.5-flash' || $model === 'gemini-2.0-flash') {
                    $model = 'gemini-3.6-flash';
                }
                
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
                    $candidates = $response->json()['candidates'] ?? [];
                    if (!empty($candidates[0]['content']['parts'])) {
                        foreach ($candidates[0]['content']['parts'] as $part) {
                            if (!empty($part['text'])) {
                                $candidateText = $part['text'];
                                if (stripos($candidateText, 'SELECT') !== false) {
                                    $rawSql = $candidateText;
                                    break;
                                }
                                if (!$rawSql) {
                                    $rawSql = $candidateText;
                                }
                            }
                        }
                    }
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

        // Sanitize markdown code blocks and extract SQL
        $cleanSql = trim($rawSql);
        if (preg_match('/```(?:sql)?\s*([\s\S]+?)```/i', $cleanSql, $m)) {
            $cleanSql = trim($m[1]);
        } else {
            $cleanSql = preg_replace('/^```(?:sql)?/i', '', $cleanSql);
            $cleanSql = preg_replace('/```$/', '', $cleanSql);
            $cleanSql = trim($cleanSql);
        }

        // If SELECT statement is preceded by conversational text or headers, isolate SELECT statement
        if (stripos($cleanSql, 'SELECT') !== false && strpos(strtoupper($cleanSql), 'SELECT') !== 0) {
            if (preg_match('/(SELECT[\s\S]+?)(?:;|$)/i', $cleanSql, $m)) {
                $cleanSql = trim($m[1]);
            }
        }

        // Remove trailing semicolons or whitespace
        $cleanSql = rtrim($cleanSql, "; \t\n\r\0\x0B");

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
            $selectQuery = "SELECT expenses.id, COALESCE(expense_party.name, bills_party.name) as party_name, expenses.party_type, expense_head.name as cost_category_name, expenses.particular, expenses.amount, expenses.remark, expenses.image, sites.name as site_name, COALESCE(users.name, 'Staff') as user_name, expenses.status, expenses.location, expenses.date FROM expenses LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') LEFT JOIN expense_head ON expense_head.id = expenses.head_id LEFT JOIN sites ON sites.id = expenses.site_id LEFT JOIN users ON users.id = expenses.user_id";
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
            $selectQuery = "SELECT expenses.id, COALESCE(expense_party.name, bills_party.name) as party_name, expenses.party_type, expense_head.name as cost_category_name, expenses.particular, expenses.amount, expenses.remark, expenses.image, sites.name as site_name, COALESCE(users.name, 'Staff') as user_name, expenses.status, expenses.location, expenses.date FROM expenses LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') LEFT JOIN expense_head ON expense_head.id = expenses.head_id LEFT JOIN sites ON sites.id = expenses.site_id LEFT JOIN users ON users.id = expenses.user_id";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
            if (strpos($lower, 'pending') !== false) {
                $whereClauses[] = "(expenses.status LIKE '%Pending%' OR expenses.status LIKE '%pending%')";
            } else if (strpos($lower, 'approved') !== false || strpos($lower, 'verified') !== false) {
                $whereClauses[] = "(expenses.status LIKE '%Approved%' OR expenses.status LIKE '%Verified%' OR expenses.status LIKE '%approved%' OR expenses.status LIKE '%verified%')";
            } else if (strpos($lower, 'rejected') !== false) {
                $whereClauses[] = "(expenses.status LIKE '%Rejected%' OR expenses.status LIKE '%rejected%')";
            }
        } else if (strpos($lower, 'material report') !== false || strpos($lower, 'material reprt') !== false || strpos($lower, 'matrial report') !== false || strpos($lower, 'stock report') !== false) {
            $selectQuery = "SELECT material_entry.id, materials.name as material_name, material_entry.qty, material_entry.vehical, material_entry.date, material_entry.status FROM material_entry LEFT JOIN materials ON materials.id=material_entry.material_id";
            $sf = $getSiteFilter('material_entry.site_id');
            if ($sf) $whereClauses[] = $sf;
            // Apply status filters when user explicitly asks for pending/approved/returned/rejected materials
            if (strpos($lower, 'pending') !== false) {
                $whereClauses[] = "(material_entry.status LIKE '%Pending%' OR material_entry.status LIKE '%pending%')";
            } else if (strpos($lower, 'approved') !== false || strpos($lower, 'verified') !== false) {
                $whereClauses[] = "(material_entry.status LIKE '%Approved%' OR material_entry.status LIKE '%approved%')";
            } else if (strpos($lower, 'returned') !== false) {
                $whereClauses[] = "(material_entry.status LIKE '%Returned%' OR material_entry.status LIKE '%returned%')";
            } else if (strpos($lower, 'rejected') !== false) {
                $whereClauses[] = "(material_entry.status LIKE '%Rejected%' OR material_entry.status LIKE '%rejected%')";
            }
        } else if (strpos($lower, 'attendance report') !== false || strpos($lower, 'attendace report') !== false || strpos($lower, 'report of attendance') !== false) {
            $selectQuery = "SELECT attendance.id, COALESCE(users.name, 'Labour') as person_name, attendance.date, attendance.in_time, attendance.out_time, attendance.status, attendance.remarks FROM attendance LEFT JOIN users ON users.id=attendance.user_id";
            $sf = $getSiteFilter('attendance.site_id');
            if ($sf) $whereClauses[] = $sf;
            if (strpos($lower, 'present') !== false) {
                $whereClauses[] = "(attendance.status LIKE '%Present%' OR attendance.status LIKE '%present%')";
            } else if (strpos($lower, 'absent') !== false) {
                $whereClauses[] = "(attendance.status LIKE '%Absent%' OR attendance.status LIKE '%absent%')";
            } else if (strpos($lower, 'half day') !== false || strpos($lower, 'halfday') !== false) {
                $whereClauses[] = "(attendance.status LIKE '%Half%' OR attendance.status LIKE '%half%')";
            } else if (strpos($lower, 'pending') !== false) {
                $whereClauses[] = "(attendance.status LIKE '%Pending%' OR attendance.status LIKE '%pending%')";
            }
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
                if (strpos($lower, 'inactive') !== false) {
                    $whereClauses[] = "(users.status LIKE '%Inactive%' OR users.status LIKE '%inactive%')";
                } else if (strpos($lower, 'active') !== false) {
                    $whereClauses[] = "(users.status LIKE '%Active%' OR users.status LIKE '%active%')";
                }

                preg_match_all('/\b([a-zA-Z0-9._-]+)\b/', $lower, $words);
                $ignoreWords = ['show', 'how', 'me', 'the', 'all', 'who', 'is', 'a', 'an', 'are', 'find', 'get', 'list', 'details', 'info', 'record', 'records', 'user', 'users', 'staff', 'team', 'member', 'members', 'give', 'view', 'fetch', 'display', 'tell', 'please', 'active', 'inactive'];
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
        } else if (strpos($lower, 'attendance') !== false || strpos($lower, 'attendace') !== false || strpos($lower, 'attendac') !== false || strpos($lower, 'atteance') !== false || strpos($lower, 'attandance') !== false || strpos($lower, 'attandace') !== false || strpos($lower, 'atendance') !== false || strpos($lower, 'attendence') !== false || strpos($lower, 'attndance') !== false || strpos($lower, 'headcount') !== false || strpos($lower, 'present') !== false || strpos($lower, 'checkin') !== false || strpos($lower, 'haziri') !== false || strpos($lower, 'hajiri') !== false) {
            $selectQuery = "SELECT attendance.id, COALESCE(users.name, 'Labour') as person_name, attendance.date, attendance.in_time, attendance.out_time, attendance.status, attendance.remarks FROM attendance LEFT JOIN users ON users.id=attendance.user_id";
            $sf = $getSiteFilter('attendance.site_id');
            if ($sf) $whereClauses[] = $sf;
            if (strpos($lower, 'present') !== false) {
                $whereClauses[] = "(attendance.status LIKE '%Present%' OR attendance.status LIKE '%present%')";
            } else if (strpos($lower, 'absent') !== false) {
                $whereClauses[] = "(attendance.status LIKE '%Absent%' OR attendance.status LIKE '%absent%')";
            } else if (strpos($lower, 'half day') !== false || strpos($lower, 'halfday') !== false) {
                $whereClauses[] = "(attendance.status LIKE '%Half%' OR attendance.status LIKE '%half%')";
            } else if (strpos($lower, 'pending') !== false) {
                $whereClauses[] = "(attendance.status LIKE '%Pending%' OR attendance.status LIKE '%pending%')";
            }
        } else if (strpos($lower, 'pending') !== false && (strpos($lower, 'expense') !== false || strpos($lower, 'expence') !== false || strpos($lower, 'kharcha') !== false || strpos($lower, 'kharch') !== false)) {
            $selectQuery = "SELECT expenses.id, COALESCE(expense_party.name, bills_party.name) as party_name, expenses.party_type, expense_head.name as cost_category_name, expenses.particular, expenses.amount, expenses.remark, expenses.image, sites.name as site_name, COALESCE(users.name, 'Staff') as user_name, expenses.status, expenses.location, expenses.date FROM expenses LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') LEFT JOIN expense_head ON expense_head.id = expenses.head_id LEFT JOIN sites ON sites.id = expenses.site_id LEFT JOIN users ON users.id = expenses.user_id";
            $whereClauses[] = "(expenses.status LIKE '%Pending%' OR expenses.status LIKE '%pending%')";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if ((strpos($lower, 'verified') !== false || strpos($lower, 'approved') !== false || strpos($lower, 'clear') !== false) && (strpos($lower, 'expense') !== false || strpos($lower, 'expence') !== false || strpos($lower, 'kharcha') !== false || strpos($lower, 'kharch') !== false)) {
            $selectQuery = "SELECT expenses.id, COALESCE(expense_party.name, bills_party.name) as party_name, expenses.party_type, expense_head.name as cost_category_name, expenses.particular, expenses.amount, expenses.remark, expenses.image, sites.name as site_name, COALESCE(users.name, 'Staff') as user_name, expenses.status, expenses.location, expenses.date FROM expenses LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') LEFT JOIN expense_head ON expense_head.id = expenses.head_id LEFT JOIN sites ON sites.id = expenses.site_id LEFT JOIN users ON users.id = expenses.user_id";
            $whereClauses[] = "(expenses.status LIKE '%Approved%' OR expenses.status LIKE '%Verified%' OR expenses.status LIKE '%approved%' OR expenses.status LIKE '%verified%')";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'rejected') !== false && (strpos($lower, 'expense') !== false || strpos($lower, 'expence') !== false || strpos($lower, 'kharcha') !== false || strpos($lower, 'kharch') !== false)) {
            $selectQuery = "SELECT expenses.id, COALESCE(expense_party.name, bills_party.name) as party_name, expenses.party_type, expense_head.name as cost_category_name, expenses.particular, expenses.amount, expenses.remark, expenses.image, sites.name as site_name, COALESCE(users.name, 'Staff') as user_name, expenses.status, expenses.location, expenses.date FROM expenses LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') LEFT JOIN expense_head ON expense_head.id = expenses.head_id LEFT JOIN sites ON sites.id = expenses.site_id LEFT JOIN users ON users.id = expenses.user_id";
            $whereClauses[] = "(expenses.status LIKE '%Rejected%' OR expenses.status LIKE '%rejected%')";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'paid') !== false && (strpos($lower, 'expense') !== false || strpos($lower, 'expence') !== false || strpos($lower, 'kharcha') !== false || strpos($lower, 'kharch') !== false)) {
            $selectQuery = "SELECT expenses.id, COALESCE(expense_party.name, bills_party.name) as party_name, expenses.party_type, expense_head.name as cost_category_name, expenses.particular, expenses.amount, expenses.remark, expenses.image, sites.name as site_name, COALESCE(users.name, 'Staff') as user_name, expenses.status, expenses.location, expenses.date FROM expenses LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') LEFT JOIN expense_head ON expense_head.id = expenses.head_id LEFT JOIN sites ON sites.id = expenses.site_id LEFT JOIN users ON users.id = expenses.user_id";
            $whereClauses[] = "(expenses.status LIKE '%Paid%' OR expenses.status LIKE '%paid%')";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'expense') !== false || strpos($lower, 'expence') !== false || strpos($lower, 'petty') !== false || strpos($lower, 'cost') !== false || strpos($lower, 'audit') !== false || strpos($lower, 'kharcha') !== false || strpos($lower, 'kharch') !== false || strpos($lower, 'hisaab') !== false) {
            $selectQuery = "SELECT expenses.id, COALESCE(expense_party.name, bills_party.name) as party_name, expenses.party_type, expense_head.name as cost_category_name, expenses.particular, expenses.amount, expenses.remark, expenses.image, sites.name as site_name, COALESCE(users.name, 'Staff') as user_name, expenses.status, expenses.location, expenses.date FROM expenses LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') LEFT JOIN expense_head ON expense_head.id = expenses.head_id LEFT JOIN sites ON sites.id = expenses.site_id LEFT JOIN users ON users.id = expenses.user_id";
            $sf = $getSiteFilter('expenses.site_id');
            if ($sf) $whereClauses[] = $sf;
        } else if (strpos($lower, 'stock') !== false || strpos($lower, 'material') !== false || strpos($lower, 'matrial') !== false || strpos($lower, 'steel') !== false || strpos($lower, 'cement') !== false || strpos($lower, 'entry') !== false || strpos($lower, 'maal') !== false || strpos($lower, 'saman') !== false) {
            $selectQuery = "SELECT material_entry.id, materials.name as material_name, material_entry.qty, material_entry.vehical, material_entry.date, material_entry.status FROM material_entry LEFT JOIN materials ON materials.id=material_entry.material_id";
            $sf = $getSiteFilter('material_entry.site_id');
            if ($sf) $whereClauses[] = $sf;
            // Apply status filters when user explicitly asks for pending/approved/returned materials
            if (strpos($lower, 'pending') !== false) {
                $whereClauses[] = "(material_entry.status LIKE '%Pending%' OR material_entry.status LIKE '%pending%')";
            } else if (strpos($lower, 'approved') !== false || strpos($lower, 'verified') !== false) {
                $whereClauses[] = "(material_entry.status LIKE '%Approved%' OR material_entry.status LIKE '%approved%')";
            } else if (strpos($lower, 'returned') !== false) {
                $whereClauses[] = "(material_entry.status LIKE '%Returned%' OR material_entry.status LIKE '%returned%')";
            } else if (strpos($lower, 'rejected') !== false) {
                $whereClauses[] = "(material_entry.status LIKE '%Rejected%' OR material_entry.status LIKE '%rejected%')";
            }
        } else if (strpos($lower, 'task') !== false || strpos($lower, 'taks') !== false || strpos($lower, 'todo') !== false || strpos($lower, 'assignment') !== false || strpos($lower, 'work') !== false || strpos($lower, 'kaam') !== false) {
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
        $conn = $tenant['conn'] ?? config('database.default');
        $dateColumn = $this->detectDateColumnFromSelect($selectQuery, $conn);

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
            } else if (preg_match('/\b(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{4})\s+(?:to|and)\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+(\d{4})\b/i', $queryText, $rangeMatchMonthYear)) {
                $m1 = $rangeMatchMonthYear[1];
                $y1 = $rangeMatchMonthYear[2];
                $m2 = $rangeMatchMonthYear[3];
                $y2 = $rangeMatchMonthYear[4];
                $startDate = date('Y-m-01', strtotime("1 {$m1} {$y1}"));
                $endDate = date('Y-m-t', strtotime("1 {$m2} {$y2}"));
                if ($startDate && $endDate) {
                    $whereClauses[] = "DATE({$dateColumn}) BETWEEN '{$startDate}' AND '{$endDate}'";
                }
            } else if (preg_match('/\b(?:from|between)\s+([A-Za-z0-9,\-\/ ]+?)\s+(?:to|and)\s+([A-Za-z0-9,\-\/ ]+?)(?:\s*(?:for|in|on|$))\b/i', $queryText, $rangeMatch)) {
                $startDate = $parseDateStringToYmd($rangeMatch[1]);
                $endDate = $parseDateStringToYmd($rangeMatch[2]);
                if ($startDate && $endDate) {
                    $whereClauses[] = "DATE({$dateColumn}) BETWEEN '{$startDate}' AND '{$endDate}'";
                }
            } else if (preg_match('/\b(today|todays|today\'s|todays\s+only|aaj|aaj\s+ka)\b/i', $lower)) {
                $whereClauses[] = "DATE({$dateColumn}) = CURDATE()";
            } else if (preg_match('/\b(yesterday|yesterdays|yesterday\'s|kal|kal\s+ka)\b/i', $lower)) {
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

        // Parse LIMIT / top N patterns (e.g., "show me 5 users", "top 10", "limit 5")
        $limitClause = '';
        if (preg_match('/\b(?:show|get|list|give)\s+me\s+(\d{1,4})\b/i', $queryText, $mLimit)
            || preg_match('/\btop\s+(\d{1,4})\b/i', $queryText, $mLimit)
            || preg_match('/\blimit\s+(\d{1,4})\b/i', $queryText, $mLimit)
            || preg_match('/\b(\d{1,4})\s+(?:users|records|rows|results)\b/i', $queryText, $mLimit)) {
            $n = intval($mLimit[1] ?? 0);
            if ($n > 0 && $n <= 1000) {
                $limitClause = " LIMIT {$n}";
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
                    $whereClauses[] = "expenses.particular LIKE '%{$val}%'";
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
            return "{$selectQuery}{$whereSql}{$groupByClause} ORDER BY total_amount DESC" . ($limitClause ?? '');
        }
        return "{$selectQuery}{$whereSql} ORDER BY 1 DESC" . ($limitClause ?? '');
    }

    /**
     * AI Text-to-Query API Processor
     */
    public function processQuery(Request $request)
    {
        $start = microtime(true);

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
                $responsePayload = [
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
                        'pdf_url' => url('/attendance/download-pdf')
                    ]
                ];
                $response = response()->json($responsePayload);

                logUserAuditAction('search', $queryText, 'Buildarya AI Assistant Greeting', $responsePayload, 'ai_chat', null, $request, $conn, (microtime(true) - $start) * 1000);
                return $response;
            }

            $createFormIntent = $this->detectCreateFormIntent($queryText);
            if ($createFormIntent) {
                $html = $this->renderCreateFormHtml($createFormIntent, $tenant);
                $responsePayload = [
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
                        'pdf_url' => url('/attendance/download-pdf')
                    ]
                ];
                $response = response()->json($responsePayload);

                logUserAuditAction('form_open', $queryText, 'Create form opened for ' . ucfirst($createFormIntent), $responsePayload, $createFormIntent, null, $request, $conn, (microtime(true) - $start) * 1000);
                return $response;
            }

            $isPdfRequest = (strpos($lower, 'pdf') !== false || strpos($lower, 'download') !== false || strpos($lower, 'export') !== false);
            $isOtherSiteRequest = (strpos($lower, 'other site') !== false || strpos($lower, 'all site') !== false);

            $pdfUrl = url('/attendance/download-pdf');
            if (preg_match('/\b(today|todays|today\'s|aaj|aaj\s+ka)\b/i', $lower)) {
                $pdfUrl .= '?today=1';
            } elseif (preg_match('/\b(yesterday|kal|kal\s+ka)\b/i', $lower)) {
                $pdfUrl .= '?date=' . date('Y-m-d', strtotime('-1 day'));
            } elseif (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $queryText, $dMatch)) {
                $pdfUrl .= '?date=' . $dMatch[1];
            }

            $sqlToExec = null;
            $provider = 'Buildarya AI SQL Engine';

            // 1. Try LLM query generation first
            $llmResult = $this->callLlmForSql($queryText, $tenant);
            if ($llmResult && !empty($llmResult['sql'])) {
                $sqlToExec = $llmResult['sql'];
                $provider = $llmResult['provider'];
            }

            // 2. If LLM returned no SQL (offline, rate limit, timeout, or model issue),
            // seamlessly fall back to Buildarya's internal Natural Language SQL Engine:
            if (!$sqlToExec) {
                $fallbackSql = $this->generateDynamicSqlFromText($queryText, $tenant);
                if (!empty($fallbackSql)) {
                    $sqlToExec = $fallbackSql;
                    $provider = 'Buildarya AI SQL Engine';
                }
            }

            if ($sqlToExec) {
                $sqlToExec = $this->normalizeExpenseQuery($sqlToExec);
                $sqlToExec = $this->normalizeGeneratedQueryStatus($sqlToExec, $queryText);
            }

            if (!$sqlToExec) {
                $responsePayload = [
                    'status' => 'Failed',
                    'status_code' => 422,
                    'message' => 'AI could not generate a SQL query for this request. Please configure a valid AI provider or ask a query that matches the live schema.'
                ];
                $response = response()->json($responsePayload, 422);

                logUserAuditAction('search', $queryText, 'SQL generation failed', $responsePayload, 'ai_chat', null, $request, $conn, (microtime(true) - $start) * 1000);
                return $response;
            }

            try {
                try {
                    $fetchedRows = DB::connection($conn)->select($sqlToExec);
                } catch (\Exception $dbEx) {
                    // If LLM-generated SQL encountered a DB syntax/schema error,
                    // fall back to schema-safe internal generator
                    $fallbackSql = $this->generateDynamicSqlFromText($queryText, $tenant);
                    if ($fallbackSql && $fallbackSql !== $sqlToExec) {
                        $fallbackSql = $this->normalizeExpenseQuery($fallbackSql);
                        $fallbackSql = $this->normalizeGeneratedQueryStatus($fallbackSql, $queryText);
                        $fetchedRows = DB::connection($conn)->select($fallbackSql);
                        $sqlToExec = $fallbackSql;
                        $provider = 'Buildarya AI SQL Engine (Schema Safe)';
                    } else {
                        throw $dbEx;
                    }
                }
                $fetchedRows = $this->enrichExpenseRecords($fetchedRows, $conn);
                $html = $this->buildDynamicSqlHtml($fetchedRows, $sqlToExec, $provider, $queryText, $tenant, $isOtherSiteRequest, $isPdfRequest, $pdfUrl);

                $responsePayload = [
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
                        'pdf_url' => $pdfUrl
                    ]
                ];
                $response = response()->json($responsePayload);

                // Keep the complete API response in the audit record, including
                // the unmodified rows returned by the tenant database.
                logUserAuditAction('search', $queryText, 'Query executed successfully', $responsePayload, 'ai_chat', null, $request, $conn, (microtime(true) - $start) * 1000);
                return $response;
            } catch (\Exception $e) {
                $responsePayload = [
                    'status' => 'Failed',
                    'status_code' => 500,
                    'message' => 'AI-generated SQL failed to execute: ' . $e->getMessage()
                ];
                $response = response()->json($responsePayload, 500);

                logUserAuditAction('search', $queryText, 'SQL execution failed', $responsePayload, 'ai_chat', null, $request, $conn, (microtime(true) - $start) * 1000);
                return $response;
            }

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
        $lower = rtrim($lower, '.!?');

        $patterns = [
            // 11. Add New Consuption/Wastage (specific multi-word first)
            'consumption_wastage' => [
                'add new consuption/wastage', 'add new consumption/wastage', 'add consuption/wastage', 'add consumption/wastage',
                'add new consumption', 'add new consuption', 'add new wastage',
                'add consumption', 'add consuption', 'add wastage',
                'new consumption', 'new consuption', 'new wastage',
                'consuption/wastage', 'consumption/wastage', 'consumption form', 'wastage form'
            ],
            // 12. Add Stocks Site Transfer
            'stock_site_transfer' => [
                'add stocks site transfer', 'add stock site transfer', 'stocks site transfer', 'stock site transfer',
                'new stocks site transfer', 'new stock site transfer', 'stock transfer form', 'material site transfer',
                'add material site transfer', 'material transfer form', 'stocks transfer form'
            ],
            // 13. Add Stocks Unit Conversion
            'stock_unit_conversion' => [
                'add stocks unit conversion', 'add stock unit conversion', 'stocks unit conversion', 'stock unit conversion',
                'new stocks unit conversion', 'new stock unit conversion', 'unit conversion form', 'stock conversion form'
            ],
            // 14. Add StocksReconcia
            'stock_reconciliation' => [
                'add stocksreconcia', 'stocksreconcia', 'add stocks reconsilation', 'stocks reconsilation',
                'add stock reconciliation', 'stock reconciliation', 'request reconciliation', 'stocks reconcia',
                'add stocks reconciliation', 'reconciliation form', 'stock reconsilation', 'stocksreconcilation'
            ],
            // 17. Add Works Rate
            'bill_rate' => [
                'add works rate', 'add work rate', 'works rate', 'work rate', 'works rate form',
                'add bill works rate', 'add bill work rate', 'add bill rate', 'bill rate form', 'new bill rate',
                'new works rate', 'bill works rate'
            ],
            // 16. Add Works
            'bill_work' => [
                'add works', 'add work', 'add bill works', 'add bill work', 'bill work form', 'new bill work',
                'new bill works', 'bill works form', 'works form'
            ],
            // 15. Add Bill Parties
            'bill_party' => [
                'add bill parties', 'add bill party', 'new bill parties', 'new bill party', 'create bill party',
                'bill party form', 'add new bill party', 'bill parties'
            ],
            // 18. Add New Bill
            'bill' => [
                'add new bill', 'add bill', 'new bill', 'create bill', 'bill form', 'new bill form'
            ],
            // 23. Add Assets\'s Expense Head
            'asset_expense_head' => [
                "add assets's expense head", "add asset's expense head", "add assets expense head", "add asset expense head",
                "assets's expense head", "asset's expense head", "assets expense head", "asset expense head",
                "new asset expense head", "new assets expense head", "asset expense head form", "assets expense head form"
            ],
            // 21. Add expense head / Machinery expense head
            'machinery_expense_head' => [
                'add machinery expense head', 'new machinery expense head', 'add new machinery expense head',
                'machinery expense head form', 'machinery expense head', "machinery's expense head",
                'add expense head', 'new expense head', 'expense head form', 'expense head'
            ],
            // Machinery Head
            'machinery_head' => [
                'add new machinery head', 'new machinery head', 'add machinery head', 'machinery head form', 'machinery head'
            ],
            // 19. Add Machinerises / 20. Add Machiney
            'machinery' => [
                'add machinerises', 'add machiney', 'add machinery', 'new machinery', 'create machinery',
                'machinery form', 'add new machinery', 'machinerises', 'machiney', 'machineries', 'add machineries'
            ],
            // 22. Add Assets
            'asset' => [
                'add assets', 'add asset', 'new asset', 'new assets', 'create asset', 'create assets',
                'asset form', 'add new assets', 'add new asset', 'assets form'
            ],
            // 24. Add Invoice Heads
            'invoice_head' => [
                'add invoice heads', 'add invoice head', 'new invoice head', 'new invoice heads',
                'create invoice head', 'invoice head form', 'add new invoice head', 'sales invoice head'
            ],
            // 25. Add sales Party
            'sales_party' => [
                'add sales party', 'add sales parties', 'new sales party', 'new sales parties',
                'create sales party', 'sales party form', 'add new sales party'
            ],
            // 26. Add project
            'sales_project' => [
                'add sales project', 'new sales project', 'create sales project', 'sales project form', 'add new sales project',
                'add project', 'new project', 'create project', 'project form'
            ],
            // 27. Add new payment voucher
            'payment_voucher' => [
                'add new payment voucher', 'add payment voucher', 'new payment voucher',
                'create payment voucher', 'generate payment voucher', 'generate voucher',
                'voucher form', 'payment voucher form', 'payment voucher'
            ],
            // 28. Add Other Party
            'other_party' => [
                'add other party', 'add other parties', 'new other party', 'new other parties',
                'create other party', 'other party form', 'other party'
            ],
            // 29. Add upload file in my document section
            'my_doc_upload_file' => [
                'add upload file in my document section', 'upload file in my document section',
                'upload file in my document', 'upload file in document section', 'my document upload file',
                'upload document file', 'upload file in document', 'upload my document'
            ],
            // 30. Add Document Head
            'doc_head' => [
                'add document head', 'add doc head', 'new document head', 'new doc head',
                'create document head', 'document head form', 'doc head form', 'document head', 'doc head'
            ],
            // 31. Add New Contact
            'contact' => [
                'add new contact', 'add contact', 'new contact', 'create contact', 'contact form',
                'add new contacts', 'contacts form'
            ],
            // 32. Add Self Check In
            'self_check_in' => [
                'add self check in', 'self check in', 'self checkin', 'check in', 'clock in', 'self clock in',
                'check-in', 'clock-in', 'self check-in'
            ],
            // 33. Add Self Check Out
            'self_check_out' => [
                'add self check out', 'self check out', 'self checkout', 'check out', 'clock out', 'self clock out',
                'check-out', 'clock-out', 'self check-out'
            ],
            // 34. Add Manual Attendance
            'manual_attendance' => [
                'add manual attendance', 'new manual attendance', 'create manual attendance',
                'manual attendance', 'manual attendance form', 'manual attendance record'
            ],
            // Attendance general
            'attendance' => [
                'add attendance', 'new attendance', 'attendance form'
            ],
            // 36. Add Task Category
            'task_category' => [
                'add task category', 'new task category', 'create task category', 'task category form',
                'add task categories', 'task category'
            ],
            // 35. Add Task
            'task' => [
                'add task', 'new task', 'create task', 'task form', 'add new task', 'create task form',
                'add tasks', 'new tasks'
            ],
            // 37. Add New Company
            'company' => [
                'add new company', 'add company', 'new company', 'create company', 'company form',
                'add sales company', 'new sales company', 'add company in contacts'
            ],
            // 38. Add Support Ticket
            'ticket' => [
                'add support ticket', 'new support ticket', 'create support ticket', 'support ticket',
                'add ticket', 'new ticket', 'ticket form', 'support ticket form'
            ],
            // 7. Add Material Suppliers
            'material_supplier' => [
                'add material suppliers', 'add material supplier', 'new material supplier', 'create material supplier',
                'material supplier form', 'add new material supplier', 'add supplier', 'new supplier'
            ],
            // 9. Add Units
            'material_unit' => [
                'add material unit', 'new material unit', 'create material unit', 'material unit form',
                'add new material unit', 'add units', 'add unit', 'new unit', 'new units', 'add new unit'
            ],
            // 10. Add New Materials entry
            'material_entry' => [
                'add new materials entry', 'add new material entry', 'add material entry', 'new material entry',
                'create material entry', 'material entry form', 'material stock entry form'
            ],
            // 8. Add Materials
            'material' => [
                'add materials', 'add material', 'new material', 'new materials', 'create material',
                'material form', 'add new material', 'add material sku'
            ],
            // 6. Add Cost Category
            'cost_category' => [
                'add cost category', 'add cost categories', 'new cost category', 'create cost category',
                'cost category form', 'add new cost category'
            ],
            // 4. Add Expense parties
            'expense_party' => [
                'add expense parties', 'add expense party', 'new expense party', 'new expense parties',
                'create expense party', 'expense party form', 'add new expense party', 'add new expense parties'
            ],
            // 5. Add New Expense
            'expense' => [
                'add new expense', 'add expense', 'new expense', 'create expense', 'expense form',
                'show expense form', 'open expense form', 'add expense entry', 'new expense entry'
            ],
            // 3. Add Role
            'role' => [
                'add role', 'add roles', 'new role', 'new roles', 'create role', 'role form',
                'show role form', 'open role form', 'add new role'
            ],
            // 2. Add Site
            'site' => [
                'add site', 'add sites', 'new site', 'new sites', 'create site', 'site form',
                'show site form', 'open site form', 'add new site', 'add new sites'
            ],
            // 1. Add User
            'user' => [
                'add user', 'add users', 'new user', 'new users', 'create user', 'create users',
                'user form', 'show user form', 'open user form', 'add new user', 'add staff', 'add employee'
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
            'consumption_wastage' => ['consuption', 'consumption', 'wastage'],
            'stock_site_transfer' => ['stocks site transfer', 'stock transfer', 'material transfer'],
            'stock_unit_conversion' => ['unit conversion', 'stock conversion'],
            'stock_reconciliation' => ['stocksreconcia', 'reconcia', 'reconsilation', 'reconciliation'],
            'bill_rate' => ['works rate', 'work rate', 'bill rate'],
            'bill_work' => ['bill work', 'works', 'work'],
            'bill_party' => ['bill party', 'bills party'],
            'bill' => ['bill'],
            'asset_expense_head' => ['asset expense head', 'assets expense head'],
            'machinery_expense_head' => ['machinery expense head', 'expense head'],
            'machinery_head' => ['machinery head'],
            'machinery' => ['machinerises', 'machiney', 'machinery', 'machine'],
            'asset' => ['asset', 'assets'],
            'invoice_head' => ['invoice head', 'sales inv head'],
            'sales_party' => ['sales party'],
            'sales_project' => ['sales project', 'project'],
            'payment_voucher' => ['payment voucher', 'voucher'],
            'other_party' => ['other party'],
            'my_doc_upload_file' => ['upload file', 'document file', 'upload doc'],
            'doc_head' => ['document head', 'doc head'],
            'contact' => ['contact'],
            'self_check_in' => ['check in', 'clock in'],
            'self_check_out' => ['check out', 'clock out'],
            'manual_attendance' => ['manual attendance'],
            'attendance' => ['attendance'],
            'task_category' => ['task category'],
            'task' => ['task', 'todo', 'assignment'],
            'company' => ['company', 'sales company'],
            'ticket' => ['support ticket', 'ticket'],
            'material_supplier' => ['material supplier', 'supplier'],
            'material_unit' => ['material unit', 'unit'],
            'material_entry' => ['material entry', 'stock entry'],
            'material' => ['material'],
            'cost_category' => ['cost category'],
            'expense_party' => ['expense party'],
            'expense' => ['expense', 'petty'],
            'role' => ['role'],
            'site' => ['site'],
            'user' => ['user', 'member', 'staff', 'employee']
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
        $companyName = session()->get('comp_name') ?? ($tenant['comp_name'] ?? 'Company');
        $companyId = session()->get('comp_db_id') ?? ($tenant['comp_db_id'] ?? '');
        $today = date('Y-m-d');
        $nowTime = date('H:i');
        $minDate = date('Y-m-d', strtotime('-30 days'));
        $maxDate = date('Y-m-d', strtotime('+30 days'));

        $siteOptions = '';
        $roleOptions = '';
        $projectOptions = '<option value="" selected disabled>--Select Project--</option><option value="0">No Project</option>';
        $materialOptions = '<option value="" selected disabled>--Select Material--</option>';
        $unitOptions = '<option value="" selected disabled>--Select Unit--</option>';
        $expenseHeadOptions = '<option value="" selected disabled>--Select Expense Head--</option>';
        $costCategoryOptions = '<option value="" selected disabled>--Select Cost Category--</option>';
        $machineryHeadOptions = '<option value="" selected disabled>--Select Machinery Head--</option>';
        $assetHeadOptions = '<option value="" selected disabled>--Select Asset Head--</option>';
        $billPartyOptions = '<option value="" selected disabled>--Select Bill Party--</option>';
        $supplierOptions = '<option value="" selected disabled>--Select Supplier--</option>';
        $userOptions = '<option value="" selected disabled>--Select User / Staff--</option>';
        $docHeadOptions = '<option value="" selected disabled>--Select Document Head--</option>';
        $contactProfileOptions = '<option value="" selected disabled>--Select Company Profile--</option>';
        $taskCategoryOptions = '<option value="" selected disabled>--Select Category--</option>';
        $billWorkOptions = '<option value="" selected disabled>--Select Work--</option>';

        // Safe DB lookups with try...catch
        try {
            if ($conn) {
                $sites = DB::connection($conn)->table('sites')->select('id', 'name')->orderBy('name')->get();
                foreach ($sites as $site) {
                    $siteOptions .= '<option value="' . e($site->id) . '">' . e($site->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        if (empty($siteOptions) && !empty($tenant['site_id']) && !empty($tenant['site_name'])) {
            $siteOptions = '<option value="' . e($tenant['site_id']) . '" selected>' . e($tenant['site_name']) . '</option>';
        }

        try {
            if ($conn) {
                $roles = DB::connection($conn)->table('roles')->select('id', 'name')->orderBy('name')->get();
                foreach ($roles as $role) {
                    $roleOptions .= '<option value="' . e($role->id) . '">' . e($role->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $projects = DB::connection($conn)->table('projects')->select('id', 'name')->orderBy('name')->get();
                foreach ($projects as $project) {
                    $projectOptions .= '<option value="' . e($project->id) . '">' . e($project->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $materials = DB::connection($conn)->table('materials')->select('id', 'name')->orderBy('name')->get();
                foreach ($materials as $material) {
                    $materialOptions .= '<option value="' . e($material->id) . '">' . e($material->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $units = DB::connection($conn)->table('units')->select('id', 'name')->orderBy('name')->get();
                foreach ($units as $unit) {
                    $unitOptions .= '<option value="' . e($unit->id) . '">' . e($unit->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $heads = DB::connection($conn)->table('expense_head')->select('id', 'name')->orderBy('name')->get();
                foreach ($heads as $head) {
                    $expenseHeadOptions .= '<option value="' . e($head->id) . '">' . e($head->name) . '</option>';
                    $costCategoryOptions .= '<option value="' . e($head->id) . '">' . e($head->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $mHeads = DB::connection($conn)->table('machinery_head')->select('id', 'name')->orderBy('name')->get();
                foreach ($mHeads as $mh) {
                    $machineryHeadOptions .= '<option value="' . e($mh->id) . '">' . e($mh->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $aHeads = DB::connection($conn)->table('asset_head')->select('id', 'name')->orderBy('name')->get();
                foreach ($aHeads as $ah) {
                    $assetHeadOptions .= '<option value="' . e($ah->id) . '">' . e($ah->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $bParties = DB::connection($conn)->table('bills_party')->select('id', 'name')->orderBy('name')->get();
                foreach ($bParties as $bp) {
                    $billPartyOptions .= '<option value="' . e($bp->id) . '">' . e($bp->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $mSuppliers = DB::connection($conn)->table('material_supplier')->select('id', 'name')->orderBy('name')->get();
                foreach ($mSuppliers as $ms) {
                    $supplierOptions .= '<option value="' . e($ms->id) . '">' . e($ms->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $users = DB::connection($conn)->table('users')->select('id', 'name')->orderBy('name')->get();
                foreach ($users as $u) {
                    $userOptions .= '<option value="' . e($u->id) . '">' . e($u->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $dHeads = DB::connection($conn)->table('doc_head')->select('id', 'name')->orderBy('name')->get();
                foreach ($dHeads as $dh) {
                    $docHeadOptions .= '<option value="' . e($dh->id) . '">' . e($dh->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $cProfiles = DB::connection($conn)->table('contact_profile')->select('id', 'comp_name', 'contact_name')->orderBy('comp_name')->get();
                foreach ($cProfiles as $cp) {
                    $contactProfileOptions .= '<option value="' . e($cp->id) . '">' . e($cp->comp_name ?: $cp->contact_name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $tCats = DB::connection($conn)->table('task_categories')->select('id', 'name')->orderBy('name')->get();
                foreach ($tCats as $tc) {
                    $taskCategoryOptions .= '<option value="' . e($tc->id) . '">' . e($tc->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        try {
            if ($conn) {
                $bWorks = DB::connection($conn)->table('bills_work')->select('id', 'name')->orderBy('name')->get();
                foreach ($bWorks as $bw) {
                    $billWorkOptions .= '<option value="' . e($bw->id) . '">' . e($bw->name) . '</option>';
                }
            }
        } catch (\Throwable $e) {}

        // Common Input Styles
        $inpStyle = 'width:100%; padding:10px 12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#fff; box-sizing:border-box;';
        $lblStyle = 'display:block; margin-bottom:6px; font-size:12px; color:#d1d5db;';

        // 1. Add User
        if ($entity === 'user') {
            $companyInput = !empty($companyId)
                ? '<div><label style="' . $lblStyle . '">Company</label><input type="text" value="' . e($companyName) . '" readonly style="' . $inpStyle . '"><input type="hidden" name="company_id" value="' . e($companyId) . '"></div>'
                : '<div><label style="' . $lblStyle . '">Company</label><input type="text" value="' . e($companyName) . '" readonly style="' . $inpStyle . '"><input type="hidden" name="company_id" value=""></div>';

            return $this->wrapAiFormHtml('Add New User', url('/users'), url('/addnewuser'), '
                <div style="grid-column: span 2; display:flex; justify-content:center; align-items:center; flex-direction:column; gap:10px;">
                    <img height="120" width="120" src="' . asset('/images/user.png') . '" style="border-radius:50%; object-fit:cover; border:2px solid rgba(148,163,184,0.45); background:#0f172a;" alt="User preview">
                    <input type="file" accept="Image/*" name="image" style="' . $inpStyle . '">
                </div>
                <div><label style="' . $lblStyle . '">Full Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Enter Full Name"></div>
                <div><label style="' . $lblStyle . '">Username</label><input type="text" name="username" required style="' . $inpStyle . '" placeholder="Enter Username"></div>
                <div><label style="' . $lblStyle . '">Email</label><input type="email" name="email" required style="' . $inpStyle . '" placeholder="Enter Email"></div>
                <div><label style="' . $lblStyle . '">Phone</label><input type="text" name="phone" required style="' . $inpStyle . '" placeholder="Enter Phone"></div>
                <div><label style="' . $lblStyle . '">Password</label><input type="password" name="password" required style="' . $inpStyle . '" placeholder="Enter Password"></div>
                <div><label style="' . $lblStyle . '">Role</label><select name="role_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Role--</option>' . $roleOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Assigned Sites</label><select name="site_id[]" multiple style="' . $inpStyle . ' height:90px;">' . $siteOptions . '</select></div>
                ' . $companyInput . '
            ', 'Save User');
        }

        // 2. Add Site
        if ($entity === 'site') {
            return $this->wrapAiFormHtml('Add New Site', url('/sites'), url('/addsites'), '
                <div><label style="' . $lblStyle . '">Site Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Site Name"></div>
                <div><label style="' . $lblStyle . '">Address</label><input type="text" name="address" required style="' . $inpStyle . '" placeholder="Site Address"></div>
                <div><label style="' . $lblStyle . '">Opening Balance</label><input type="number" step="0.01" name="open_balance" required style="' . $inpStyle . '" placeholder="0.00" value="0.00"></div>
                <div><label style="' . $lblStyle . '">Sites Type</label><select name="sitestype" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Sites Type--</option><option value="Official Site">Official Site</option><option value="Working Site">Working Site</option></select></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Project</label><select name="project_id" required style="' . $inpStyle . '">' . $projectOptions . '</select></div>
            ', 'Save Site');
        }

        // 3. Add Role
        if ($entity === 'role') {
            return $this->wrapAiFormHtml('Add New Role', url('/user_roles'), url('/addnewrole'), '
                <div style="grid-column: span 2;">
                    <label style="' . $lblStyle . '">Role Name</label>
                    <input type="text" name="name" required style="' . $inpStyle . '" placeholder="Role Name (e.g. Site Supervisor, Account Manager)">
                </div>
            ', 'Save Role');
        }

        // 4. Add Expense parties
        if ($entity === 'expense_party') {
            return $this->wrapAiFormHtml('Add Expense Party', url('/expense_party'), url('/addexpenseparty'), '
                <div><label style="' . $lblStyle . '">Party Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Expense party name"></div>
                <div><label style="' . $lblStyle . '">Address</label><input type="text" name="address" style="' . $inpStyle . '" placeholder="Address"></div>
                <div><label style="' . $lblStyle . '">PAN No.</label><input type="text" name="pan_no" style="' . $inpStyle . '" placeholder="PAN number"></div>
                <div><label style="' . $lblStyle . '">Cost Category</label><select name="cost_category_id" style="' . $inpStyle . '">' . $costCategoryOptions . '</select></div>
            ', 'Save Expense Party');
        }

        // 5. Add New Expense
        if ($entity === 'expense') {
            return $this->wrapAiFormHtml('Add New Expense', url('/expenses'), url('/addnewExpenses'), '
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id[]" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Expense Date</label><input type="date" name="date[]" required value="' . e($today) . '" min="' . e($minDate) . '" max="' . e($maxDate) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Party Type</label><select name="party_type[]" required style="' . $inpStyle . '"><option value="expense" selected>Expense Party</option><option value="bill">Bill Party</option></select></div>
                <div><label style="' . $lblStyle . '">Party</label><select name="party_id[]" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Party--</option>' . $billPartyOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Expense Head</label><select name="head_id[]" required style="' . $inpStyle . '">' . $expenseHeadOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Particular / Description</label><input type="text" name="particular[]" required style="' . $inpStyle . '" placeholder="Particular detail"></div>
                <div><label style="' . $lblStyle . '">Amount (₹)</label><input type="number" step="0.01" min="0" name="amount[]" required style="' . $inpStyle . '" placeholder="0.00"></div>
                <div><label style="' . $lblStyle . '">Remark</label><input type="text" name="remark[]" style="' . $inpStyle . '" placeholder="Remark (optional)"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Bill / Receipt Image</label><input type="file" name="image[]" accept="image/*" style="' . $inpStyle . '"></div>
            ', 'Save Expense');
        }

        // 6. Add Cost Category
        if ($entity === 'cost_category') {
            return $this->wrapAiFormHtml('Add Cost Category', url('/cost_category'), url('/addcostcategory'), '
                <div style="grid-column: span 2;">
                    <label style="' . $lblStyle . '">Cost Category Name</label>
                    <input type="text" name="name" required style="' . $inpStyle . '" placeholder="Cost category name (e.g. Labour Charges, Site Fuel)">
                </div>
            ', 'Save Cost Category');
        }

        // 7. Add Material Suppliers
        if ($entity === 'material_supplier') {
            return $this->wrapAiFormHtml('Add Material Supplier', url('/materialsupplier'), url('/addmaterialsupplier'), '
                <div><label style="' . $lblStyle . '">Supplier Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Supplier Name"></div>
                <div><label style="' . $lblStyle . '">Address</label><input type="text" name="address" style="' . $inpStyle . '" placeholder="Supplier Address"></div>
                <div><label style="' . $lblStyle . '">GSTIN</label><input type="text" name="gstin" style="' . $inpStyle . '" placeholder="GSTIN Number"></div>
                <div><label style="' . $lblStyle . '">Cost Category</label><select name="cost_category_id" style="' . $inpStyle . '">' . $costCategoryOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Bank A/C No.</label><input type="text" name="bank_ac" style="' . $inpStyle . '" placeholder="Bank Account Number"></div>
                <div><label style="' . $lblStyle . '">Bank IFSC</label><input type="text" name="bank_ifsc" style="' . $inpStyle . '" placeholder="Bank IFSC"></div>
                <div><label style="' . $lblStyle . '">Bank Name</label><input type="text" name="bank_name" style="' . $inpStyle . '" placeholder="Bank Name"></div>
                <div><label style="' . $lblStyle . '">A/C Holder Name</label><input type="text" name="bank_ac_holder" style="' . $inpStyle . '" placeholder="Account Holder Name"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">QR Code Image</label><input type="file" name="qr_code" accept="image/*" style="' . $inpStyle . '"></div>
            ', 'Save Material Supplier');
        }

        // 8. Add Materials
        if ($entity === 'material') {
            return $this->wrapAiFormHtml('Add Material SKU', url('/material'), url('/addmaterial'), '
                <div><label style="' . $lblStyle . '">Material Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Material Name (e.g. OPC Cement 50kg, 10mm TMT Bar)"></div>
                <div><label style="' . $lblStyle . '">Royalty Material</label><select name="is_royalty" style="' . $inpStyle . '"><option value="0" selected>No</option><option value="1">Yes (Royalty Applicable)</option></select></div>
            ', 'Save Material');
        }

        // 9. Add Units
        if ($entity === 'material_unit') {
            return $this->wrapAiFormHtml('Add Material Unit', url('/materialunit'), url('/addmaterialunit'), '
                <div style="grid-column: span 2;">
                    <label style="' . $lblStyle . '">Unit Name</label>
                    <input type="text" name="name" required style="' . $inpStyle . '" placeholder="Unit Name (e.g. Bag, Brass, MT, Sq.Ft, Nos, Liter)">
                </div>
            ', 'Save Unit');
        }

        // 10. Add New Materials entry
        if ($entity === 'material_entry') {
            return $this->wrapAiFormHtml('Add New Materials Entry', url('/new_material'), url('/addnewmaterial'), '
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id[]" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Entry Date</label><input type="date" name="date[]" required value="' . e($today) . '" min="' . e($minDate) . '" max="' . e($maxDate) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Supplier</label><select name="supplier[]" required style="' . $inpStyle . '">' . $supplierOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Material</label><select name="material_id[]" required style="' . $inpStyle . '">' . $materialOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Unit</label><select name="unit[]" required style="' . $inpStyle . '">' . $unitOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Quantity</label><input type="number" step="0.01" min="0" name="qty[]" required style="' . $inpStyle . '" placeholder="0.00"></div>
                <div><label style="' . $lblStyle . '">Vehicle No.</label><input type="text" name="vehical[]" style="' . $inpStyle . '" placeholder="Vehicle Number"></div>
                <div><label style="' . $lblStyle . '">Challan / Bill No.</label><input type="text" name="challan_no[]" style="' . $inpStyle . '" placeholder="Challan / Bill No."></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Remark</label><input type="text" name="remark[]" style="' . $inpStyle . '" placeholder="Remark (optional)"></div>
            ', 'Save Material Entry');
        }

        // 11. Add New Consuption/Wastage
        if ($entity === 'consumption_wastage') {
            return $this->wrapAiFormHtml('Add New Consumption / Wastage', url('/new_consumption'), url('/add_new_consumption'), '
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id[]" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Date</label><input type="date" name="date[]" required value="' . e($today) . '" min="' . e($minDate) . '" max="' . e($maxDate) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Type</label><select name="consumption_wastage[]" required style="' . $inpStyle . '"><option value="Consumption" selected>Consumption</option><option value="Wastage">Wastage</option></select></div>
                <div><label style="' . $lblStyle . '">Material</label><select name="material_id[]" required style="' . $inpStyle . '">' . $materialOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Unit</label><select name="unit[]" required style="' . $inpStyle . '">' . $unitOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Quantity</label><input type="number" step="0.01" min="0" name="qty[]" required style="' . $inpStyle . '" placeholder="0.00"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Remarks / Notes</label><input type="text" name="remarks[]" style="' . $inpStyle . '" placeholder="Used for foundation casting, slab, etc."></div>
            ', 'Save Consumption/Wastage');
        }

        // 12. Add Stocks Site Transfer
        if ($entity === 'stock_site_transfer') {
            return $this->wrapAiFormHtml('Add Stocks Site Transfer', url('/newMaterialSiteTransfer'), url('/newMaterialTransferForm'), '
                <div><label style="' . $lblStyle . '">From Site (Source)</label><select name="from_site" required style="' . $inpStyle . '"><option value="" selected disabled>--Select From Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">To Site (Destination)</label><select name="to_site" required style="' . $inpStyle . '"><option value="" selected disabled>--Select To Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Material</label><select name="material_id" required style="' . $inpStyle . '">' . $materialOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Unit</label><select name="unit" required style="' . $inpStyle . '">' . $unitOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Transfer Quantity</label><input type="number" step="0.01" min="0" name="qty" required style="' . $inpStyle . '" placeholder="0.00"></div>
                <div><label style="' . $lblStyle . '">Transfer Date</label><input type="date" name="date" required value="' . e($today) . '" min="' . e($minDate) . '" max="' . e($maxDate) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Vehicle No.</label><input type="text" name="vehicle_no" style="' . $inpStyle . '" placeholder="Vehicle number"></div>
                <div><label style="' . $lblStyle . '">Remark</label><input type="text" name="remark" style="' . $inpStyle . '" placeholder="Transfer remark"></div>
            ', 'Submit Stock Transfer');
        }

        // 13. Add Stocks Unit Conversion
        if ($entity === 'stock_unit_conversion') {
            return $this->wrapAiFormHtml('Add Stocks Unit Conversion', url('/newStockUnitConversion'), url('/newStockUnitConversionForm'), '
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Material</label><select name="material_id" required style="' . $inpStyle . '">' . $materialOptions . '</select></div>
                <div><label style="' . $lblStyle . '">From Unit (Source)</label><select name="from_unit" required style="' . $inpStyle . '">' . $unitOptions . '</select></div>
                <div><label style="' . $lblStyle . '">To Unit (Target)</label><select name="to_unit" required style="' . $inpStyle . '">' . $unitOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Quantity Deducted</label><input type="number" step="0.01" min="0" name="qty" required style="' . $inpStyle . '" placeholder="0.00"></div>
                <div><label style="' . $lblStyle . '">Converted Quantity Added</label><input type="number" step="0.01" min="0" name="updated_qty" required style="' . $inpStyle . '" placeholder="0.00"></div>
                <div><label style="' . $lblStyle . '">Conversion Date</label><input type="date" name="date" required value="' . e($today) . '" min="' . e($minDate) . '" max="' . e($maxDate) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Remark</label><input type="text" name="remark" style="' . $inpStyle . '" placeholder="Conversion formula/reason"></div>
            ', 'Submit Unit Conversion');
        }

        // 14. Add StocksReconcia
        if ($entity === 'stock_reconciliation') {
            return $this->wrapAiFormHtml('Request Stocks Reconciliation', url('/reconsilation_list'), url('/request_reconsilation'), '
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Site for Stock Reconciliation</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div style="grid-column: span 2; background:rgba(16,163,127,0.1); border:1px solid rgba(16,163,127,0.3); border-radius:8px; padding:12px; font-size:12.5px; color:#a7f3d0;">
                    📌 Submitting this request initiates a stock audit and reconciliation snapshot for all materials at the selected site on ' . e($today) . '.
                </div>
            ', 'Request Reconciliation');
        }

        // 15. Add Bill Parties
        if ($entity === 'bill_party') {
            return $this->wrapAiFormHtml('Add Bill Party', url('/billparty'), url('/addbillparty'), '
                <div><label style="' . $lblStyle . '">Party Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Bill Party Name"></div>
                <div><label style="' . $lblStyle . '">Address</label><input type="text" name="address" required style="' . $inpStyle . '" placeholder="Address"></div>
                <div><label style="' . $lblStyle . '">PAN No.</label><input type="text" name="panno" required style="' . $inpStyle . '" placeholder="PAN Number"></div>
                <div><label style="' . $lblStyle . '">Bank A/C No.</label><input type="text" name="bank_ac" required style="' . $inpStyle . '" placeholder="Bank Account Number"></div>
                <div><label style="' . $lblStyle . '">Bank IFSC</label><input type="text" name="ifsc" required style="' . $inpStyle . '" placeholder="IFSC Code"></div>
                <div><label style="' . $lblStyle . '">Bank Name</label><input type="text" name="bankname" required style="' . $inpStyle . '" placeholder="Bank Name"></div>
                <div><label style="' . $lblStyle . '">A/C Holder Name</label><input type="text" name="ac_holder_name" required style="' . $inpStyle . '" placeholder="Account Holder Name"></div>
                <div><label style="' . $lblStyle . '">Cost Category</label><select name="cost_category_id" required style="' . $inpStyle . '">' . $costCategoryOptions . '</select></div>
            ', 'Save Bill Party');
        }

        // 16. Add Works
        if ($entity === 'bill_work') {
            return $this->wrapAiFormHtml('Add Works', url('/billwork'), url('/addbillwork'), '
                <div><label style="' . $lblStyle . '">Work Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Work Name (e.g. Brick Work, Plaster, Painting)"></div>
                <div><label style="' . $lblStyle . '">Unit of Measurement</label><select name="unit" required style="' . $inpStyle . '">' . $unitOptions . '</select></div>
            ', 'Save Work');
        }

        // 17. Add Works Rate
        if ($entity === 'bill_rate') {
            return $this->wrapAiFormHtml('Add Works Rate', url('/billrate'), url('/addbillrate'), '
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Work Item</label><select name="work_id" required style="' . $inpStyle . '">' . $billWorkOptions . '</select></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Rate per Unit (₹)</label><input type="number" step="0.01" min="0" name="rate" required style="' . $inpStyle . '" placeholder="0.00"></div>
            ', 'Save Work Rate');
        }

        // 18. Add New Bill
        if ($entity === 'bill') {
            return $this->wrapAiFormHtml('Add New Bill', url('/new_bill'), url('/addnewbill'), '
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Bill Party</label><select name="bill_party_id" required style="' . $inpStyle . '">' . $billPartyOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Bill Number</label><input type="text" name="bill_no" required style="' . $inpStyle . '" placeholder="Bill / Invoice No."></div>
                <div><label style="' . $lblStyle . '">Bill Date</label><input type="date" name="bill_date" required value="' . e($today) . '" min="' . e($minDate) . '" max="' . e($maxDate) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Billing Period From</label><input type="date" name="bill_from_date" required value="' . e($today) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Billing Period To</label><input type="date" name="bill_to_date" required value="' . e($today) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Work Item</label><select name="item[]" required style="' . $inpStyle . '">' . $billWorkOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Quantity</label><input type="number" step="0.01" min="0" name="qty[]" required style="' . $inpStyle . '" placeholder="0.00"></div>
                <div><label style="' . $lblStyle . '">Rate (₹)</label><input type="number" step="0.01" min="0" name="rate[]" required style="' . $inpStyle . '" placeholder="0.00"></div>
            ', 'Save Bill');
        }

        // 19 & 20. Add Machinerises / Add Machiney
        if ($entity === 'machinery') {
            return $this->wrapAiFormHtml('Add Machinery', url('/machinery'), url('/add_newmechinery'), '
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Machinery Head</label><select name="head_id" required style="' . $inpStyle . '">' . $machineryHeadOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Machinery Name / Model</label><input type="text" name="macname" required style="' . $inpStyle . '" placeholder="e.g. Concrete Mixer 10/7, JCB 3DX"></div>
                <div><label style="' . $lblStyle . '">Cost Price (₹)</label><input type="number" step="0.01" min="0" name="costprice" required style="' . $inpStyle . '" placeholder="0.00"></div>
            ', 'Save Machinery');
        }

        // 21. Add expense head (Machinery Expense Head)
        if ($entity === 'machinery_expense_head') {
            return $this->wrapAiFormHtml('Add Expense Head (Machinery)', url('/machinery_expense_head'), url('/addmachineryExpensehead'), '
                <div style="grid-column: span 2;">
                    <label style="' . $lblStyle . '">Select Expense Head to Allocate to Machinery</label>
                    <select name="head_id" required style="' . $inpStyle . '">' . $expenseHeadOptions . '</select>
                </div>
            ', 'Save Expense Head');
        }

        // 22. Add Assets
        if ($entity === 'asset') {
            return $this->wrapAiFormHtml('Add New Asset', url('/asset_head'), url('/add_newassets'), '
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Asset Head</label><select name="head_id" required style="' . $inpStyle . '">' . $assetHeadOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Asset Name</label><input type="text" name="assetsname" required style="' . $inpStyle . '" placeholder="e.g. Total Station, Generator 15KVA"></div>
                <div><label style="' . $lblStyle . '">Cost Price (₹)</label><input type="number" step="0.01" min="0" name="costprice" required style="' . $inpStyle . '" placeholder="0.00"></div>
            ', 'Save Asset');
        }

        // 23. Add Assets\'s Expense Head
        if ($entity === 'asset_expense_head') {
            return $this->wrapAiFormHtml("Add Asset's Expense Head", url('/asset_expense_head'), url('/addassetExpensehead'), '
                <div style="grid-column: span 2;">
                    <label style="' . $lblStyle . '">Select Expense Head to Allocate to Assets</label>
                    <select name="head_id" required style="' . $inpStyle . '">' . $expenseHeadOptions . '</select>
                </div>
            ', 'Save Asset Expense Head');
        }

        // 24. Add Invoice Heads
        if ($entity === 'invoice_head') {
            return $this->wrapAiFormHtml('Add Invoice Head', url('/sales_inv_head'), url('/addsalesinv_head'), '
                <div style="grid-column: span 2;">
                    <label style="' . $lblStyle . '">Invoice Head Name</label>
                    <input type="text" name="name" required style="' . $inpStyle . '" placeholder="e.g. Civil Construction Contract, Architecture Consulting">
                </div>
            ', 'Save Invoice Head');
        }

        // 25. Add sales Party
        if ($entity === 'sales_party') {
            return $this->wrapAiFormHtml('Add Sales Party', url('/sales_parties'), url('/addsalesparty'), '
                <div><label style="' . $lblStyle . '">Party Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Client / Sales Party Name"></div>
                <div><label style="' . $lblStyle . '">Phone</label><input type="text" name="phone" style="' . $inpStyle . '" placeholder="Phone Number"></div>
                <div><label style="' . $lblStyle . '">GSTIN</label><input type="text" name="gst" style="' . $inpStyle . '" placeholder="GSTIN Number"></div>
                <div><label style="' . $lblStyle . '">Address</label><input type="text" name="address" style="' . $inpStyle . '" placeholder="Address"></div>
            ', 'Save Sales Party');
        }

        // 26. Add project
        if ($entity === 'sales_project') {
            return $this->wrapAiFormHtml('Add Sales Project', url('/sales_project'), url('/addsalesproject'), '
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Project Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Project Title"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Project Details / Scope</label><textarea name="details" rows="3" style="' . $inpStyle . '" placeholder="Scope of work, milestone details..."></textarea></div>
            ', 'Save Project');
        }

        // 27. Add new payment voucher
        if ($entity === 'payment_voucher') {
            $companyField = !empty($companyId)
                ? '<div><label style="' . $lblStyle . '">Company</label><input type="hidden" name="company_id[]" value="' . e($companyId) . '"><input type="text" readonly value="' . e($companyName) . '" style="' . $inpStyle . '"></div>'
                : '<div><label style="' . $lblStyle . '">Company</label><input type="hidden" name="company_id[]" value=""><input type="text" readonly value="' . e($companyName) . '" style="' . $inpStyle . '"></div>';

            $voucherParties = '<option value="" selected disabled>--Select Voucher Party--</option>';
            try {
                if ($conn) {
                    $mSupps = DB::connection($conn)->table('material_supplier')->select('id', 'name')->get();
                    foreach ($mSupps as $p) { $voucherParties .= '<option value="' . e($p->id) . '||material">' . e($p->name) . ' (Supplier)</option>'; }
                    $bPs = DB::connection($conn)->table('bills_party')->select('id', 'name')->get();
                    foreach ($bPs as $p) { $voucherParties .= '<option value="' . e($p->id) . '||bill">' . e($p->name) . ' (Bill Party)</option>'; }
                    $oPs = DB::connection($conn)->table('other_parties')->select('id', 'name')->get();
                    foreach ($oPs as $p) { $voucherParties .= '<option value="' . e($p->id) . '||other">' . e($p->name) . ' (Other Party)</option>'; }
                }
            } catch (\Throwable $e) {}

            return $this->wrapAiFormHtml('Generate Payment Voucher', url('/new_paymentvoucher'), url('/addnewpaymentvouchers'), '
                <div style="grid-column: span 2; display:flex; gap:16px; flex-wrap:wrap;">
                    <div><label style="' . $lblStyle . '">Voucher Image</label><input type="file" accept="image/*" name="image[]" style="' . $inpStyle . '"></div>
                    <div><label style="' . $lblStyle . '">QR Code Image</label><input type="file" accept="image/*" name="qr_code[]" style="' . $inpStyle . '"></div>
                </div>
                ' . $companyField . '
                <div><label style="' . $lblStyle . '">Voucher Party</label><select name="party_id[]" required style="' . $inpStyle . '">' . $voucherParties . '</select></div>
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id[]" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Voucher No.</label><input type="text" name="voucher_no[]" required style="' . $inpStyle . '" placeholder="Voucher No."></div>
                <div><label style="' . $lblStyle . '">Amount (₹)</label><input type="number" step="0.01" min="0" name="amount[]" required style="' . $inpStyle . '" placeholder="0.00"></div>
                <div><label style="' . $lblStyle . '">Date</label><input type="date" name="date[]" required value="' . e($today) . '" min="' . e($minDate) . '" max="' . e($maxDate) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Payment Details</label><input type="text" name="payment_details[]" style="' . $inpStyle . '" placeholder="Cash / NEFT / Cheque No."></div>
                <div><label style="' . $lblStyle . '">Remark</label><input type="text" name="remark[]" style="' . $inpStyle . '" placeholder="Remark (optional)"></div>
            ', 'Save Voucher');
        }

        // 28. Add Other Party
        if ($entity === 'other_party') {
            return $this->wrapAiFormHtml('Add Other Party', url('/otherparty'), url('/addotherparty'), '
                <div><label style="' . $lblStyle . '">Party Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Party Name"></div>
                <div><label style="' . $lblStyle . '">Address</label><input type="text" name="address" style="' . $inpStyle . '" placeholder="Address"></div>
                <div><label style="' . $lblStyle . '">PAN No.</label><input type="text" name="panno" style="' . $inpStyle . '" placeholder="PAN Number"></div>
                <div><label style="' . $lblStyle . '">Cost Category</label><select name="cost_category_id" style="' . $inpStyle . '">' . $costCategoryOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Bank A/C No.</label><input type="text" name="bank_ac" style="' . $inpStyle . '" placeholder="Bank Account Number"></div>
                <div><label style="' . $lblStyle . '">Bank IFSC</label><input type="text" name="bank_ifsc" style="' . $inpStyle . '" placeholder="Bank IFSC"></div>
                <div><label style="' . $lblStyle . '">Bank Name</label><input type="text" name="bank_name" style="' . $inpStyle . '" placeholder="Bank Name"></div>
                <div><label style="' . $lblStyle . '">A/C Holder Name</label><input type="text" name="bank_ac_holder" style="' . $inpStyle . '" placeholder="Account Holder Name"></div>
            ', 'Save Other Party');
        }

        // 29. Add upload file in my document section
        if ($entity === 'my_doc_upload_file') {
            return $this->wrapAiFormHtml('Upload File in My Document Section', url('/file-structure'), url('/my_doc_upload_file'), '
                <div><label style="' . $lblStyle . '">Document Head / Folder</label><select name="filter" required style="' . $inpStyle . '">' . $docHeadOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Document Title</label><input type="text" name="name[]" required style="' . $inpStyle . '" placeholder="Document Title / Filename"></div>
                <div><label style="' . $lblStyle . '">Date</label><input type="date" name="date[]" required value="' . e($today) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Particular</label><input type="text" name="particular[]" style="' . $inpStyle . '" placeholder="Document Particulars"></div>
                <div><label style="' . $lblStyle . '">Remark</label><input type="text" name="remark[]" style="' . $inpStyle . '" placeholder="Remark (optional)"></div>
                <div><label style="' . $lblStyle . '">Select File</label><input type="file" name="img[]" required style="' . $inpStyle . '"></div>
            ', 'Upload Document');
        }

        // 30. Add Document Head
        if ($entity === 'doc_head') {
            return $this->wrapAiFormHtml('Add Document Head', url('/file-structure'), url('/adddochead'), '
                <div style="grid-column: span 2;">
                    <label style="' . $lblStyle . '">Document Head Name</label>
                    <input type="text" name="name" required style="' . $inpStyle . '" placeholder="e.g. Structural Drawings, Approvals, Purchase Orders">
                </div>
            ', 'Save Document Head');
        }

        // 31. Add New Contact
        if ($entity === 'contact') {
            return $this->wrapAiFormHtml('Add New Contact', url('/contacts'), url('/add_contact'), '
                <div><label style="' . $lblStyle . '">Contact Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Contact Name"></div>
                <div><label style="' . $lblStyle . '">Phone / Mobile</label><input type="text" name="number" required style="' . $inpStyle . '" placeholder="Mobile Number"></div>
                <div><label style="' . $lblStyle . '">Email</label><input type="email" name="email" style="' . $inpStyle . '" placeholder="Email Address"></div>
                <div><label style="' . $lblStyle . '">Position / Designation</label><input type="text" name="position" style="' . $inpStyle . '" placeholder="e.g. Project Manager, Site Engineer"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Company Profile</label><select name="profile_id" required style="' . $inpStyle . '">' . $contactProfileOptions . '</select></div>
            ', 'Save Contact');
        }

        // 32. Add Self Check In
        if ($entity === 'self_check_in') {
            return $this->wrapAiFormHtml('Add Self Check In', url('/attendance'), url('/attendance/clock-in'), '
                <div><label style="' . $lblStyle . '">Site for Check-In</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Check-In Time</label><input type="text" value="' . e($nowTime) . ' (' . e($today) . ')" readonly style="' . $inpStyle . '"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Selfie / Verification Photo (Optional)</label><input type="file" name="photo" accept="image/*" style="' . $inpStyle . '"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Check-In Remarks</label><input type="text" name="remarks" style="' . $inpStyle . '" placeholder="Morning shift check-in..."></div>
                <input type="hidden" name="latitude" value="0.0000">
                <input type="hidden" name="longitude" value="0.0000">
            ', 'Clock In Now');
        }

        // 33. Add Self Check Out
        if ($entity === 'self_check_out') {
            return $this->wrapAiFormHtml('Add Self Check Out', url('/attendance'), url('/attendance/clock-out'), '
                <div><label style="' . $lblStyle . '">Site for Check-Out</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Check-Out Time</label><input type="text" value="' . e($nowTime) . ' (' . e($today) . ')" readonly style="' . $inpStyle . '"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Check-Out Remarks</label><input type="text" name="remarks" style="' . $inpStyle . '" placeholder="Evening shift complete, handover done..."></div>
                <input type="hidden" name="latitude" value="0.0000">
                <input type="hidden" name="longitude" value="0.0000">
            ', 'Clock Out Now');
        }

        // 34. Add Manual Attendance
        if ($entity === 'manual_attendance' || $entity === 'attendance') {
            return $this->wrapAiFormHtml('Add Manual Attendance', url('/attendance'), url('/attendance/manual'), '
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Staff / Employee</label><select name="user_id" required style="' . $inpStyle . '">' . $userOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Date</label><input type="date" name="date" required value="' . e($today) . '" min="' . e($minDate) . '" max="' . e($maxDate) . '" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Status</label><select name="status" required style="' . $inpStyle . '"><option value="Present" selected>Present</option><option value="Absent">Absent</option><option value="Half Day">Half Day</option><option value="Leave">Leave</option></select></div>
                <div><label style="' . $lblStyle . '">Clock In Time</label><input type="time" name="clock_in" value="09:00" style="' . $inpStyle . '"></div>
                <div><label style="' . $lblStyle . '">Clock Out Time</label><input type="time" name="clock_out" value="18:00" style="' . $inpStyle . '"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Remark</label><input type="text" name="remark" style="' . $inpStyle . '" placeholder="Attendance notes (optional)"></div>
            ', 'Save Attendance');
        }

        // 35. Add Task
        if ($entity === 'task') {
            return $this->wrapAiFormHtml('Add New Task', url('/tasks'), url('/tasks'), '
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Task Title</label><input type="text" name="title" required style="' . $inpStyle . '" placeholder="Enter Task Title"></div>
                <div><label style="' . $lblStyle . '">Task Category</label><select name="category_id" style="' . $inpStyle . '">' . $taskCategoryOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Site</label><select name="site_id" required style="' . $inpStyle . '"><option value="" selected disabled>--Select Site--</option>' . $siteOptions . '</select></div>
                <div><label style="' . $lblStyle . '">Priority</label><select name="priority" required style="' . $inpStyle . '"><option value="Medium" selected>Medium</option><option value="Low">Low</option><option value="High">High</option></select></div>
                <div><label style="' . $lblStyle . '">Due Date</label><input type="date" name="due_date" value="' . e($today) . '" style="' . $inpStyle . '"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Assigned To</label><select name="assigned_to" required style="' . $inpStyle . '">' . $userOptions . '</select></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Description</label><textarea name="description" rows="3" style="' . $inpStyle . '" placeholder="Detailed task instructions..."></textarea></div>
            ', 'Create Task');
        }

        // 36. Add Task Category
        if ($entity === 'task_category') {
            return $this->wrapAiFormHtml('Add Task Category', url('/task_category'), url('/addtaskcategory'), '
                <div style="grid-column: span 2;">
                    <label style="' . $lblStyle . '">Category Name</label>
                    <input type="text" name="name" required style="' . $inpStyle . '" placeholder="e.g. Electrical, Plumbing, Painting, Inspection">
                </div>
            ', 'Save Task Category');
        }

        // 37. Add New Company
        if ($entity === 'company') {
            return $this->wrapAiFormHtml('Add New Company', url('/sales_companies'), url('/addsalescompany'), '
                <div><label style="' . $lblStyle . '">Company Name</label><input type="text" name="name" required style="' . $inpStyle . '" placeholder="Company / Entity Name"></div>
                <div><label style="' . $lblStyle . '">Phone</label><input type="text" name="phone" style="' . $inpStyle . '" placeholder="Phone Number"></div>
                <div><label style="' . $lblStyle . '">GSTIN</label><input type="text" name="gst" style="' . $inpStyle . '" placeholder="GSTIN Number"></div>
                <div><label style="' . $lblStyle . '">State</label><input type="text" name="state" style="' . $inpStyle . '" placeholder="State Name"></div>
                <div><label style="' . $lblStyle . '">State Code</label><input type="text" name="state_code" style="' . $inpStyle . '" placeholder="e.g. 27"></div>
                <div><label style="' . $lblStyle . '">Address</label><input type="text" name="address" style="' . $inpStyle . '" placeholder="Address"></div>
            ', 'Save Company');
        }

        // 38. Add Support Ticket
        if ($entity === 'ticket') {
            return $this->wrapAiFormHtml('Add Support Ticket', url('/tickets'), url('/tickets'), '
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Ticket Subject</label><input type="text" name="subject" required style="' . $inpStyle . '" placeholder="Brief summary of the issue"></div>
                <div style="grid-column: span 2;"><label style="' . $lblStyle . '">Description / Details</label><textarea name="description" rows="4" required style="' . $inpStyle . '" placeholder="Explain the problem or requirement in detail..."></textarea></div>
            ', 'Submit Support Ticket');
        }

        // Machinery Head
        if ($entity === 'machinery_head') {
            return $this->wrapAiFormHtml('Add Machinery Head', url('/machinery_head'), url('/addmachineryhead'), '
                <div style="grid-column: span 2;">
                    <label style="' . $lblStyle . '">Machinery Head Name</label>
                    <input type="text" name="name" required style="' . $inpStyle . '" placeholder="e.g. Excavators, Generators, Mixers, Cranes">
                </div>
            ', 'Save Machinery Head');
        }

        return '<div style="padding:14px; color:#f87171;">Form could not be generated for this request.</div>';
    }

    private function wrapAiFormHtml($title, $fullUrl, $actionUrl, $inputsHtml, $buttonLabel = 'Save')
    {
        return '
            <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 14px; color: #f3f4f6;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                    <div>
                        <div style="font-size:12px; color:#34d399; text-transform:uppercase; letter-spacing:0.08em; font-weight:700;">AI Form Action</div>
                        <div style="font-size:20px; font-weight:700; margin-top:4px;">' . e($title) . '</div>
                    </div>
                    <a href="' . $fullUrl . '" target="_blank" style="background:#10a37f; color:#fff; border-radius:8px; padding:8px 12px; text-decoration:none; font-size:12px; font-weight:700;">Open Full Form</a>
                </div>
                <form action="' . $actionUrl . '" method="POST" enctype="multipart/form-data" onsubmit="event.preventDefault(); if (typeof submitAiForm === \'function\') { submitAiForm(this); } else { this.submit(); }" style="display:block;">
                    ' . csrf_field() . '
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px;">
                        ' . $inputsHtml . '
                    </div>
                    <div style="margin-top:14px; display:flex; justify-content:flex-end; gap:10px;">
                        <button type="submit" style="background:#10a37f; color:#fff; border:none; border-radius:8px; padding:10px 14px; font-weight:700; cursor:pointer;">' . e($buttonLabel) . '</button>
                    </div>
                </form>
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
     * Normalize queries targeting the expenses table so proper joins and proper names are used,
     * replacing raw IDs (party_id, head_id, site_id, user_id) and qualifying columns to prevent ambiguous column errors.
     */
    private function normalizeExpenseQuery($sql)
    {
        if (!preg_match('/\bFROM\s+[`"]?expenses[`"]?\b/i', $sql)) {
            return $sql;
        }

        // If it's a pure count/aggregation without row records, keep it
        if (preg_match('/SELECT\s+COUNT\s*\(/i', $sql)) {
            return $sql;
        }

        // Check if query selects raw wildcard or misses necessary joins
        $isWildcard = preg_match('/^SELECT\s+(?:\*|[`"]?expenses[`"]?\.\*)\s+FROM\s+[`"]?expenses[`"]?\b/i', trim($sql));
        $missingJoins = (stripos($sql, 'expense_head') === false || stripos($sql, 'sites') === false || stripos($sql, 'users') === false);

        if ($isWildcard || $missingJoins) {
            if (preg_match('/^SELECT\s+.*?\bFROM\s+[`"]?expenses[`"]?\s*(.*?)$/is', trim($sql), $matches)) {
                $tail = trim($matches[1] ?? '');

                // Remove existing incomplete JOIN clauses so we can attach full standard joins
                $cleanTail = preg_replace('/\b(?:LEFT|RIGHT|INNER)?\s*JOIN\s+[`"]?(?:sites|users|expense_head|expense_party|bills_party)[`"]?\s+ON\s+.*?(?=\bWHERE\b|\bGROUP\b|\bORDER\b|\bLIMIT\b|$)/is', '', $tail);
                $cleanTail = trim($cleanTail);

                // Qualify columns in WHERE/ORDER/GROUP/LIMIT to prevent "column ambiguous" errors
                $ambiguousCols = ['site_id', 'status', 'user_id', 'party_id', 'head_id', 'date', 'amount', 'particular', 'remark', 'location', 'image', 'create_datetime'];
                foreach ($ambiguousCols as $col) {
                    $cleanTail = preg_replace('/(?<![a-zA-Z0-9_.])' . preg_quote($col, '/') . '(?![a-zA-Z0-9_])/i', 'expenses.' . $col, $cleanTail);
                }

                $joinedSelect = "SELECT expenses.id, COALESCE(expense_party.name, bills_party.name) AS party_name, expenses.party_type, expense_head.name AS cost_category_name, expenses.particular, expenses.amount, expenses.remark, expenses.image, sites.name AS site_name, users.name AS user_name, expenses.status, expenses.location, expenses.date FROM expenses LEFT JOIN expense_party ON (expense_party.id = expenses.party_id AND expenses.party_type = 'expense') LEFT JOIN bills_party ON (bills_party.id = expenses.party_id AND expenses.party_type = 'bill') LEFT JOIN expense_head ON expense_head.id = expenses.head_id LEFT JOIN sites ON sites.id = expenses.site_id LEFT JOIN users ON users.id = expenses.user_id";

                if (!empty($cleanTail)) {
                    $sql = $joinedSelect . ' ' . $cleanTail;
                } else {
                    $sql = $joinedSelect . ' ORDER BY expenses.id DESC';
                }
            }
        }

        return $sql;
    }

    /**
     * Post-process generated SQL to ensure requested status filters (pending, verified/approved, rejected, paid)
     * are strictly enforced when user explicitly requests them in free-form text or voice queries.
     */
    private function normalizeGeneratedQueryStatus($sql, $queryText)
    {
        if (empty($sql) || empty($queryText)) {
            return $sql;
        }

        $lower = strtolower(trim($queryText));
        $hasPending = (strpos($lower, 'pending') !== false || strpos($lower, 'unapproved') !== false || strpos($lower, 'baki') !== false || strpos($lower, 'baaki') !== false);
        $hasApproved = (strpos($lower, 'verified') !== false || strpos($lower, 'approved') !== false || strpos($lower, 'clear') !== false || strpos($lower, 'cleared') !== false || strpos($lower, 'pass') !== false);
        $hasRejected = (strpos($lower, 'rejected') !== false || strpos($lower, 'reject') !== false || strpos($lower, 'declined') !== false);
        $hasPaid = (strpos($lower, 'paid') !== false);
        $hasInactive = (strpos($lower, 'inactive') !== false);
        $hasActive = (strpos($lower, 'active') !== false && !$hasInactive);

        if (!$hasPending && !$hasApproved && !$hasRejected && !$hasPaid && !$hasInactive && !$hasActive) {
            return $sql;
        }

        $tables = [
            'expenses' => 'expenses.status',
            'material_entry' => 'material_entry.status',
            'tasks' => 'tasks.status',
            'attendance' => 'attendance.status',
            'payment_vouchers' => 'payment_vouchers.status',
            'bills_party' => 'bills_party.status',
            'users' => 'users.status',
        ];

        foreach ($tables as $tbl => $col) {
            if (preg_match('/\bFROM\s+[`"]?' . preg_quote($tbl, '/') . '[`"]?\b/i', $sql)) {
                // If query already has status filtering for this table or column, skip
                if (preg_match('/\b(?:' . preg_quote($tbl, '/') . '\.)?status\s*(?:LIKE|=|IN)\b/i', $sql)) {
                    continue;
                }

                $condition = null;
                if ($hasPending) {
                    $condition = "({$col} LIKE '%Pending%' OR {$col} LIKE '%pending%')";
                } else if ($hasApproved) {
                    if ($tbl === 'tasks') {
                        $condition = "({$col} LIKE '%Completed%' OR {$col} LIKE '%completed%' OR {$col} LIKE '%Approved%')";
                    } else {
                        $condition = "({$col} LIKE '%Approved%' OR {$col} LIKE '%Verified%' OR {$col} LIKE '%approved%' OR {$col} LIKE '%verified%')";
                    }
                } else if ($hasRejected) {
                    $condition = "({$col} LIKE '%Rejected%' OR {$col} LIKE '%rejected%')";
                } else if ($hasPaid) {
                    $condition = "({$col} LIKE '%Paid%' OR {$col} LIKE '%paid%')";
                } else if ($hasInactive && $tbl === 'users') {
                    $condition = "({$col} LIKE '%Inactive%' OR {$col} LIKE '%inactive%')";
                } else if ($hasActive && $tbl === 'users') {
                    $condition = "({$col} LIKE '%Active%' OR {$col} LIKE '%active%')";
                }

                if ($condition) {
                    if (preg_match('/\bWHERE\b/i', $sql)) {
                        $sql = preg_replace('/\bWHERE\b/i', "WHERE {$condition} AND", $sql, 1);
                    } else if (preg_match('/\b(GROUP\s+BY|ORDER\s+BY|LIMIT)\b/i', $sql, $clauseMatch, PREG_OFFSET_CAPTURE)) {
                        $offset = $clauseMatch[0][1];
                        $sql = substr($sql, 0, $offset) . "WHERE {$condition} " . substr($sql, $offset);
                    } else {
                        $sql .= " WHERE {$condition}";
                    }
                }
            }
        }

        return $sql;
    }

    /**
     * Post-process records fetched from expenses (or any query returning expense foreign keys)
     * by replacing raw IDs (head_id, party_id, site_id, user_id) in-place with proper human-readable names.
     */
    private function enrichExpenseRecords($rows, $conn)
    {
        if (empty($rows)) {
            return $rows;
        }

        $sample = (array)$rows[0];
        $hasHeadId = array_key_exists('head_id', $sample);
        $hasPartyId = array_key_exists('party_id', $sample);
        $hasSiteId = array_key_exists('site_id', $sample);
        $hasUserId = array_key_exists('user_id', $sample);

        if (!$hasHeadId && !$hasPartyId && !$hasSiteId && !$hasUserId) {
            return $rows;
        }

        $headIds = [];
        $siteIds = [];
        $userIds = [];
        $expensePartyIds = [];
        $billPartyIds = [];
        $genericPartyIds = [];

        foreach ($rows as $row) {
            $arr = (array)$row;
            if ($hasHeadId && !empty($arr['head_id'])) {
                $headIds[] = $arr['head_id'];
            }
            if ($hasSiteId && !empty($arr['site_id'])) {
                $siteIds[] = $arr['site_id'];
            }
            if ($hasUserId && !empty($arr['user_id'])) {
                $userIds[] = $arr['user_id'];
            }
            if ($hasPartyId && !empty($arr['party_id'])) {
                $partyType = strtolower(trim((string)($arr['party_type'] ?? '')));
                if ($partyType === 'bill') {
                    $billPartyIds[] = $arr['party_id'];
                } else if ($partyType === 'expense') {
                    $expensePartyIds[] = $arr['party_id'];
                } else {
                    $genericPartyIds[] = $arr['party_id'];
                }
            }
        }

        $headMap = [];
        if (!empty($headIds)) {
            try {
                $headMap = DB::connection($conn)->table('expense_head')
                    ->whereIn('id', array_unique($headIds))
                    ->pluck('name', 'id')
                    ->toArray();
            } catch (\Exception $e) {}
        }

        $siteMap = [];
        if (!empty($siteIds)) {
            try {
                $siteMap = DB::connection($conn)->table('sites')
                    ->whereIn('id', array_unique($siteIds))
                    ->pluck('name', 'id')
                    ->toArray();
            } catch (\Exception $e) {}
        }

        $userMap = [];
        if (!empty($userIds)) {
            try {
                $userMap = DB::connection($conn)->table('users')
                    ->whereIn('id', array_unique($userIds))
                    ->pluck('name', 'id')
                    ->toArray();
            } catch (\Exception $e) {}
        }

        $expensePartyMap = [];
        if (!empty($expensePartyIds) || !empty($genericPartyIds)) {
            try {
                $ids = array_unique(array_merge($expensePartyIds, $genericPartyIds));
                $expensePartyMap = DB::connection($conn)->table('expense_party')
                    ->whereIn('id', $ids)
                    ->pluck('name', 'id')
                    ->toArray();
            } catch (\Exception $e) {}
        }

        $billPartyMap = [];
        if (!empty($billPartyIds) || !empty($genericPartyIds)) {
            try {
                $ids = array_unique(array_merge($billPartyIds, $genericPartyIds));
                $billPartyMap = DB::connection($conn)->table('bills_party')
                    ->whereIn('id', $ids)
                    ->pluck('name', 'id')
                    ->toArray();
            } catch (\Exception $e) {}
        }

        $enrichedRows = [];
        foreach ($rows as $row) {
            $arr = (array)$row;
            $newRow = [];

            foreach ($arr as $key => $val) {
                if ($key === 'party_id') {
                    $partyType = strtolower(trim((string)($arr['party_type'] ?? '')));
                    $partyName = null;
                    if ($partyType === 'bill' && isset($billPartyMap[$val])) {
                        $partyName = $billPartyMap[$val];
                    } else if ($partyType === 'expense' && isset($expensePartyMap[$val])) {
                        $partyName = $expensePartyMap[$val];
                    } else {
                        $partyName = $expensePartyMap[$val] ?? ($billPartyMap[$val] ?? null);
                    }
                    $newRow['party_name'] = $partyName ?: (is_numeric($val) ? "Party #{$val}" : $val);
                } else if ($key === 'head_id') {
                    $catName = $headMap[$val] ?? null;
                    $newRow['cost_category_name'] = $catName ?: (is_numeric($val) ? "Cost Category #{$val}" : $val);
                } else if ($key === 'site_id') {
                    $siteName = $siteMap[$val] ?? null;
                    $newRow['site_name'] = $siteName ?: (is_numeric($val) ? "Site #{$val}" : $val);
                } else if ($key === 'user_id') {
                    $userName = $userMap[$val] ?? null;
                    $newRow['user_name'] = $userName ?: (is_numeric($val) ? "User #{$val}" : $val);
                } else {
                    $newRow[$key] = $val;
                }
            }

            $enrichedRows[] = (object)$newRow;
        }

        return $enrichedRows;
    }

    private function buildDynamicSqlHtml($rows, $sql, $provider, $queryText, $tenant, $isOtherSiteRequest, $isPdfRequest, $pdfUrl = null)
    {
        $sqlBadge = $this->renderSqlBadge($sql, $provider);
        $restriction = $this->renderRestrictionNotice($tenant['user_name'], $tenant['user_username'], $tenant['site_name'], $isOtherSiteRequest, $tenant['is_superadmin']);

        $targetPdfUrl = $pdfUrl ?: url('/attendance/download-pdf');

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
                    <a href="' . e($targetPdfUrl) . '" target="_blank" style="background: #10a37f; color: #ffffff; font-weight: 700; padding: 10px 18px; border-radius: 8px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3); transition: background 0.2s ease;">
                        <i class="zmdi zmdi-download"></i> Download Attendance PDF Report
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
            if ($col === 'head_id' || $col === 'cost_category_name') {
                $formattedHeader = 'Cost Category Name';
            } else if ($col === 'party_id' || $col === 'party_name') {
                $formattedHeader = 'Party Name';
            } else if ($col === 'site_id' || $col === 'site_name') {
                $formattedHeader = 'Site Name';
            } else if ($col === 'user_id' || $col === 'user_name') {
                $formattedHeader = 'User Name';
            } else {
                $formattedHeader = ucwords(str_replace('_', ' ', $col));
            }
            $thHtml .= '<th>' . e($formattedHeader) . '</th>';
        }

        $trHtml = '';
        foreach ($rows as $row) {
            $rowArr = (array)$row;
            $trHtml .= '<tr>';
            foreach ($columns as $col) {
                $val = $rowArr[$col] ?? 'N/A';
                if ($col === 'image' && !empty($val) && $val !== 'N/A' && (is_string($val) && (strpos($val, 'images/') !== false || strpos($val, '.png') !== false || strpos($val, '.jpg') !== false || strpos($val, '.jpeg') !== false))) {
                    $imgUrl = asset($val);
                    $cellHtml = '<a href="' . e($imgUrl) . '" target="_blank" style="color:#10a37f; text-decoration:none; font-weight:600;"><i class="zmdi zmdi-image"></i> View Image</a>';
                } else {
                    if (is_array($val) || is_object($val)) {
                        $val = json_encode($val);
                    }
                    $cellHtml = e((string)$val);
                }
                $trHtml .= '<td>' . $cellHtml . '</td>';
            }
            $trHtml .= '</tr>';
        }

        return "
            {$restriction}
            {$sqlBadge}
            {$pdfBannerHtml}
            <p><strong>🤖 AI Text-to-SQL Dynamic Results — " . e($tenant['site_name']) . " (Generated via {$provider}):</strong></p>
            <table class='ai-interactive-table'>
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

    /**
     * Transcribe user speech audio into text via Gemini Multimodal API.
     * Supports audio/webm, audio/ogg, audio/wav, audio/mp4.
     */
    public function transcribeVoice(Request $request)
    {
        $audioFile = $request->file('audio');
        if (!$audioFile || !$audioFile->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid audio data was received.'
            ], 422);
        }

        $geminiKey = env('GEMINI_API_KEY');
        if (!$geminiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Gemini API key is not configured.'
            ], 500);
        }

        $model = env('GEMINI_MODEL', 'gemini-1.5-flash');
        if (empty($model) || strpos($model, '3.6') !== false) {
            $model = 'gemini-1.5-flash';
        }

        $audioBytes = file_get_contents($audioFile->getRealPath());
        $base64Audio = base64_encode($audioBytes);
        $clientMime = $audioFile->getMimeType() ?: 'audio/webm';
        if (strpos($clientMime, 'webm') !== false) {
            $mimeType = 'audio/webm';
        } else if (strpos($clientMime, 'ogg') !== false) {
            $mimeType = 'audio/ogg';
        } else if (strpos($clientMime, 'mp4') !== false || strpos($clientMime, 'm4a') !== false) {
            $mimeType = 'audio/mp4';
        } else if (strpos($clientMime, 'wav') !== false) {
            $mimeType = 'audio/wav';
        } else {
            $mimeType = 'audio/webm';
        }

        $prompt = "Listen to this audio query and transcribe the user's speech accurately into text. Understand English, Hindi, and Hinglish. Output ONLY the raw transcribed text with NO quotes, explanations, markdown, or punctuation extras.";

        try {
            $response = Http::timeout(15)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Audio
                                ]
                            ],
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $candidates = $response->json()['candidates'] ?? [];
                $transcribedText = trim($candidates[0]['content']['parts'][0]['text'] ?? '');
                $transcribedText = trim($transcribedText, "\"'`\n\r");
                return response()->json([
                    'success' => true,
                    'text' => $transcribedText
                ]);
            }

            // Fallback with header authorization
            $responseHeader = Http::withHeaders(['x-goog-api-key' => $geminiKey])
                ->timeout(15)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'inline_data' => [
                                        'mime_type' => $mimeType,
                                        'data' => $base64Audio
                                    ]
                                ],
                                [
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ]
                ]);

            if ($responseHeader->successful()) {
                $candidates = $responseHeader->json()['candidates'] ?? [];
                $transcribedText = trim($candidates[0]['content']['parts'][0]['text'] ?? '');
                $transcribedText = trim($transcribedText, "\"'`\n\r");
                return response()->json([
                    'success' => true,
                    'text' => $transcribedText
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'AI speech transcription failed.'
            ], 502);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transcription connection error: ' . $e->getMessage()
            ], 500);
        }
    }
}
