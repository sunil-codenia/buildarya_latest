<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SendCheckoutReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:send-checkout-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send checkout reminders via WhatsApp to users checked in but not checked out by 6:15 PM';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Disable execution time limit
        set_time_limit(0);

        $this->info('Starting Daily Checkout Reminder Cron Job...');

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

            // Query users checked in today but not checked out using chunking
            try {
                DB::connection($connName)
                    ->table('attendance')
                    ->where('date', $todayDate)
                    ->whereNotNull('user_id')
                    ->whereNotNull('in_time')
                    ->whereNull('out_time')
                    ->orderBy('id')
                    ->chunk(100, function ($records) use ($connName) {
                        foreach ($records as $record) {
                            // Find the user details
                            $user = DB::connection($connName)
                                ->table('users')
                                ->where('id', $record->user_id)
                                ->where('status', 'Active')
                                ->first();

                            if (!$user || empty($user->contact_no)) {
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

                            // Get site name
                            $siteName = 'BuildArya Site';
                            if (!empty($record->site_id)) {
                                try {
                                    $site = DB::connection($connName)
                                        ->table('sites')
                                        ->where('id', $record->site_id)
                                        ->first();
                                    if ($site && !empty($site->name)) {
                                        $siteName = $site->name;
                                    }
                                } catch (\Exception $e) {
                                    // Keep default siteName
                                }
                            }

                            $this->line("  Sending checkout reminder to {$employeeName} ({$mobile})...");
                            $this->sendWhatsAppReminder($mobile, $employeeName, $siteName);
                        }
                    });
            } catch (\Exception $e) {
                $this->warn("  [SKIP] Failed to process attendance for connection '{$connName}': " . $e->getMessage());
                continue;
            }

            // Clean up connection cache
            DB::purge($connName);
        }

        $this->info('Daily Checkout Reminder Cron Job Completed.');
        return 0;
    }

    /**
     * Send WhatsApp reminder via Graph API
     */
    private function sendWhatsAppReminder($mobile, $name, $siteName)
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $mobile,
                'type' => 'template',
                'template' => [
                    'name' => 'final_reminder_checkout',
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
                                    'text' => $siteName
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
                \Log::error("WhatsApp checkout reminder failed to send to {$mobile}: " . $error_msg);
            } else {
                \Log::info("WhatsApp checkout reminder response for {$mobile}: " . $response);
            }

            curl_close($curl);
        } catch (\Exception $e) {
            \Log::error("Failed to send WhatsApp checkout reminder: " . $e->getMessage());
        }
    }
}
