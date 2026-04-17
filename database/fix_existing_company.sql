-- ============================================================
-- SQL fix for existing company data
-- Run this ONCE on the 'rsgeotech' (main/default) database
-- ============================================================

-- Fix the existing company record: populate db_name, db_conn_name, username
-- so the dynamic CompanyDatabaseProvider can register the connection properly.
-- The db_conn_name matches the database name used in DB::connection() calls.
UPDATE `companies`
SET
    `db_name` = 'rsgeotech',
    `db_conn_name` = 'rsgeotech',
    `username` = 'root',
    `db_pass` = '',
    `db_host` = '127.0.0.1',
    `db_port` = '3306'
WHERE `id` = 1;

-- Verify the fix
SELECT `id`, `name`, `uid`, `db_name`, `db_conn_name`, `username`, `status` FROM `companies`;
