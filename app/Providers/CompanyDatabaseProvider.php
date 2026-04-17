<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class CompanyDatabaseProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     * Dynamically register database connections for all active companies.
     */
    public function boot()
    {
        try {
            // Only proceed if the companies table exists in the main DB
            if (!Schema::connection('mysql')->hasTable('companies')) {
                return;
            }

            $companies = DB::connection('mysql')
                ->table('companies')
                ->where('status', 'Active')
                ->whereNotNull('db_name')
                ->where('db_name', '!=', '')
                ->get();

            foreach ($companies as $company) {
                $connName = $company->db_conn_name ?: $company->db_name;

                // Skip if empty
                if (empty($connName)) {
                    continue;
                }

                // If this connection name already exists in config, check if it
                // points to the same database. If so, skip (it's already usable).
                if (Config::has("database.connections.{$connName}")) {
                    $existingDb = Config::get("database.connections.{$connName}.database");
                    if ($existingDb === $company->db_name) {
                        continue; // Already configured correctly
                    }
                }

                Config::set("database.connections.{$connName}", [
                    'driver'    => 'mysql',
                    'host'      => $company->db_host ?: env('DB_HOST', '127.0.0.1'),
                    'port'      => $company->db_port ?: env('DB_PORT', '3306'),
                    'database'  => $company->db_name,
                    'username'  => $company->username ?: env('DB_USERNAME', 'root'),
                    'password'  => $company->db_pass ?: env('DB_PASSWORD', ''),
                    'charset'   => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix'    => '',
                    'prefix_indexes' => true,
                    'strict'    => true,
                    'engine'    => null,
                    'options'   => extension_loaded('pdo_mysql') ? array_filter([
                        \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                    ]) : [],
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail during migrations or when DB is not yet set up
            \Log::warning('CompanyDatabaseProvider: Could not load company connections - ' . $e->getMessage());
        }
    }

    /**
     * Dynamically register a single company connection at runtime.
     * Useful after creating a new company so the connection is immediately available.
     */
    public static function registerConnection($company)
    {
        $connName = $company->db_conn_name ?: $company->db_name;

        Config::set("database.connections.{$connName}", [
            'driver'    => 'mysql',
            'host'      => $company->db_host ?: env('DB_HOST', '127.0.0.1'),
            'port'      => $company->db_port ?: env('DB_PORT', '3306'),
            'database'  => $company->db_name,
            'username'  => $company->username ?: env('DB_USERNAME', 'root'),
            'password'  => $company->db_pass ?: env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'prefix_indexes' => true,
            'strict'    => true,
            'engine'    => null,
            'options'   => extension_loaded('pdo_mysql') ? array_filter([
                \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ]);

        // Purge any cached connection so it re-reads config
        DB::purge($connName);
    }
}
