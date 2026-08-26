<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SendPendingTaskReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:send-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send WhatsApp reminders at 12 PM for tasks that are pending and due today';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Disable execution time limit
        set_time_limit(0);

        $this->info('Starting Pending Task Reminder Cron Job...');

        // Fetch all active companies
        try {
            $companies = DB::connection('mysql')
                ->table('companies')
                ->where('status', 'Active')
                ->get();
        } catch (\Exception $e) {
            $this->error('Failed to query companies table: ' . $e->getMessage());
            return 1;
        }

        if ($companies->isEmpty()) {
            $this->warn('No active companies found.');
            return 0;
        }

        $todayDate = Carbon::today()->toDateString();
        $formattedTodayDate = Carbon::today()->format('d-m-Y');

        foreach ($companies as $company) {
            $connName = $company->db_conn_name ?: $company->db_name;
            if (empty($connName)) {
                continue;
            }

            $this->info("Processing company: {$company->name} [{$connName}]");

            // Register database connection dynamically if not already set up
            try {
                \App\Providers\CompanyDatabaseProvider::registerConnection($company);
            } catch (\Exception $e) {
                $this->warn("  [SKIP] Could not register connection '{$connName}': " . $e->getMessage());
                continue;
            }

            // Query pending tasks due today using chunking
            try {
                DB::connection($connName)
                    ->table('tasks')
                    ->where('status', 'Pending')
                    ->where('due_date', $todayDate)
                    ->orderBy('id')
                    ->chunk(100, function ($tasks) use ($connName, $formattedTodayDate) {
                        foreach ($tasks as $task) {
                            if (empty($task->assigned_to)) {
                                continue;
                            }

                            $assignedIds = array_filter(explode(',', $task->assigned_to));
                            if (empty($assignedIds)) {
                                continue;
                            }

                            // Fetch users assigned to this task
                            $users = DB::connection($connName)
                                ->table('users')
                                ->whereIn('id', $assignedIds)
                                ->where('status', 'Active')
                                ->get();

                            foreach ($users as $user) {
                                if (empty($user->contact_no)) {
                                    continue;
                                }

                                // Clean mobile number (keep only digits)
                                $mobile = preg_replace('/[^0-9]/', '', $user->contact_no);
                                
                                // Remove leading 0 if present
                                if (substr($mobile, 0, 1) === '0') {
                                    $mobile = substr($mobile, 1);
                                }

                                // Check if starts with 91, if not prepends 91
                                if (substr($mobile, 0, 2) !== '91') {
                                    $mobile = '91' . $mobile;
                                }

                                $employeeName = !empty($user->name) ? $user->name : 'Employee';
                                $taskTitle = !empty($task->title) ? $task->title : 'Task';
                                $priority = !empty($task->priority) ? $task->priority : 'Medium';

                                $this->line("  Sending task reminder for '{$taskTitle}' to {$employeeName} ({$mobile})...");
                                $this->sendWhatsAppReminder($mobile, $employeeName, $taskTitle, $formattedTodayDate, $priority);
                            }
                        }
                    });
            } catch (\Exception $e) {
                $this->warn("  [SKIP] Failed to process tasks for connection '{$connName}': " . $e->getMessage());
                continue;
            }

            // Clean up connection cache
            DB::purge($connName);
        }

        $this->info('Pending Task Reminder Cron Job Completed.');
        return 0;
    }

    /**
     * Send WhatsApp reminder via Graph API
     */
    private function sendWhatsAppReminder($mobile, $name, $taskTitle, $date, $priority)
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $mobile,
                'type' => 'template',
                'template' => [
                    'name' => 'buildarya_task_reminder',
                    'language' => [
                        'code' => 'en'
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $name
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $taskTitle
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'BuildArya'
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $date
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $priority
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://graph.facebook.com/v19.0/1324753710718871/messages',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => json_encode($payload),
              CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . env('WHATSAPP_API_TOKEN'),
                'Content-Type: application/json'
              ),
            ));

            $response = curl_exec($curl);

            if (curl_errno($curl)) {
                $error_msg = curl_error($curl);
                \Log::error("WhatsApp task reminder failed to send to {$mobile}: " . $error_msg);
            } else {
                \Log::info("WhatsApp task reminder response for {$mobile}: " . $response);
            }

            curl_close($curl);
        } catch (\Exception $e) {
            \Log::error("Failed to send WhatsApp task reminder: " . $e->getMessage());
        }
    }
}
