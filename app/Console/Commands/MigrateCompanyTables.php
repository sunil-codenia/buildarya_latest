<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateCompanyTables extends Command
{
    /**
     * The name and signature of the console command.
     * Run: php artisan company:migrate-tables
     */
    protected $signature = 'company:migrate-tables
                            {--conn= : Migrate only a specific company connection name}';

    protected $description = 'Create attendance and tasks tables in all existing company databases (safe: uses CREATE TABLE IF NOT EXISTS)';

    public function handle()
    {
        $specificConn = $this->option('conn');

        // Load all companies from the main DB
        $companies = DB::table('companies')->get();

        if ($companies->isEmpty()) {
            $this->error('No companies found in the main database.');
            return 1;
        }

        $success = 0;
        $failed  = 0;

        foreach ($companies as $company) {
            $connName = $company->db_conn_name;

            if (!$connName) {
                $this->warn("  [SKIP] Company #{$company->id} ({$company->name}) — no db_conn_name set.");
                continue;
            }

            // If filtering to a specific connection
            if ($specificConn && $specificConn !== $connName) {
                continue;
            }

            // Register the connection dynamically
            try {
                \App\Providers\CompanyDatabaseProvider::registerConnection($company);
            } catch (\Exception $e) {
                $this->warn("  [SKIP] Could not register connection '{$connName}': " . $e->getMessage());
                $failed++;
                continue;
            }

            $this->line("  Processing: <info>{$company->name}</info> [{$connName}]");

            // ─── ATTENDANCE TABLE ────────────────────────────────────────────
            try {
                DB::connection($connName)->statement("
                    CREATE TABLE IF NOT EXISTS `attendance` (
                        `id`             INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id`        INT NULL DEFAULT NULL,
                        `bills_party_id` INT NULL DEFAULT NULL,
                        `site_id`        INT DEFAULT NULL,
                        `date`           DATE NOT NULL,
                        `in_time`        DATETIME DEFAULT NULL,
                        `out_time`       DATETIME DEFAULT NULL,
                        `status`         ENUM('Present','Absent','Half Day','Leave','Holiday') NOT NULL DEFAULT 'Present',
                        `in_location`    TEXT DEFAULT NULL,
                        `out_location`   TEXT DEFAULT NULL,
                        `image`          VARCHAR(500) DEFAULT NULL,
                        `out_image`      VARCHAR(500) DEFAULT NULL,
                        `remarks`        TEXT DEFAULT NULL,
                        `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX `idx_user_id` (`user_id`),
                        INDEX `idx_bills_party_id` (`bills_party_id`),
                        INDEX `idx_site_id` (`site_id`),
                        INDEX `idx_date`    (`date`),
                        UNIQUE KEY `attendance_user_date_unique` (`user_id`, `date`),
                        UNIQUE KEY `attendance_party_date_unique` (`bills_party_id`, `date`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Alter user_id to make it nullable if it already exists as NOT NULL
                try {
                    DB::connection($connName)->statement("ALTER TABLE `attendance` MODIFY COLUMN `user_id` INT NULL DEFAULT NULL");
                } catch (\Exception $e) {
                    // Ignore if alter fails or not needed
                }

                // Add bills_party_id if it does not exist
                if (!\Illuminate\Support\Facades\Schema::connection($connName)->hasColumn('attendance', 'bills_party_id')) {
                    try {
                        DB::connection($connName)->statement("ALTER TABLE `attendance` ADD COLUMN `bills_party_id` INT NULL DEFAULT NULL AFTER `user_id`");
                        DB::connection($connName)->statement("ALTER TABLE `attendance` ADD INDEX `idx_bills_party_id` (`bills_party_id`)");
                    } catch (\Exception $e) {
                        // Ignore if exists
                    }
                }

                // Ensure unique key on bills_party_id + date exists
                try {
                    DB::connection($connName)->statement("ALTER TABLE `attendance` ADD UNIQUE KEY `attendance_party_date_unique` (`bills_party_id`, `date`)");
                } catch (\Exception $e) {
                    // Ignore if unique key already exists
                }

                // Ensure unique key on user_id + date exists
                try {
                    DB::connection($connName)->statement("ALTER TABLE `attendance` ADD UNIQUE KEY `attendance_user_date_unique` (`user_id`, `date`)");
                } catch (\Exception $e) {
                    // Ignore if unique key already exists
                }

                $this->line("    ✓ attendance table OK");
            } catch (\Exception $e) {
                $this->error("    ✗ attendance table FAILED: " . $e->getMessage());
                $failed++;
            }

            // ─── TASKS TABLE ─────────────────────────────────────────────────
            try {
                DB::connection($connName)->statement("
                    CREATE TABLE IF NOT EXISTS `tasks` (
                        `id`           INT AUTO_INCREMENT PRIMARY KEY,
                        `title`        VARCHAR(255) NOT NULL,
                        `description`  TEXT DEFAULT NULL,
                        `site_id`      INT DEFAULT NULL,
                        `assigned_to`  INT DEFAULT NULL,
                        `assigned_by`  INT DEFAULT NULL,
                        `priority`     ENUM('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
                        `status`       ENUM('Pending','In Progress','Completed','On Hold','Cancelled') NOT NULL DEFAULT 'Pending',
                        `due_date`     DATE DEFAULT NULL,
                        `completed_at` TIMESTAMP NULL DEFAULT NULL,
                        `remarks`      TEXT DEFAULT NULL,
                        `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX `idx_site_id`     (`site_id`),
                        INDEX `idx_assigned_to` (`assigned_to`),
                        INDEX `idx_assigned_by` (`assigned_by`),
                        INDEX `idx_due_date`    (`due_date`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
                $this->line("    ✓ tasks table OK");
            } catch (\Exception $e) {
                $this->error("    ✗ tasks table FAILED: " . $e->getMessage());
                $failed++;
            }

            // ─── TASK CHATS TABLE ────────────────────────────────────────────
            try {
                DB::connection($connName)->statement("
                    CREATE TABLE IF NOT EXISTS `task_chats` (
                        `id`           INT AUTO_INCREMENT PRIMARY KEY,
                        `user_id`      INT NOT NULL,
                        `sender_id`    INT NOT NULL,
                        `message`      TEXT NULL,
                        `image`        VARCHAR(255) NULL,
                        `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX `idx_chat_user_id` (`user_id`),
                        INDEX `idx_chat_sender_id` (`sender_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Alter message to be nullable if it exists as NOT NULL
                try {
                    DB::connection($connName)->statement("ALTER TABLE `task_chats` MODIFY COLUMN `message` TEXT NULL");
                } catch (\Exception $e) {
                    // Ignore if fails
                }

                // Add image column if it does not exist
                if (!\Illuminate\Support\Facades\Schema::connection($connName)->hasColumn('task_chats', 'image')) {
                    try {
                        DB::connection($connName)->statement("ALTER TABLE `task_chats` ADD COLUMN `image` VARCHAR(255) NULL AFTER `message`");
                    } catch (\Exception $e) {
                        // Ignore if fails
                    }
                }

                $this->line("    ✓ task_chats table OK");
            } catch (\Exception $e) {
                $this->error("    ✗ task_chats table FAILED: " . $e->getMessage());
                $failed++;
            }

            $success++;
            DB::purge($connName);
        }

        $this->newLine();
        $this->info("Done! Processed: {$success} | Failed: {$failed}");
        return 0;
    }
}
