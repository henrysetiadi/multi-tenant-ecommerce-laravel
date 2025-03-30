<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Exception;

class TenantService
{
    /**
     * Set tenant connection dynamically.
     */
    public static function setTenantConnection($tenantId)
    {
        try {
            $tenant = Tenant::where('id', $tenantId)->firstOrFail();

            // Configure the tenant database connection dynamically
            Config::set("database.connections.tenant", [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => $tenant->database,
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'password'),
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ]);

            DB::purge('tenant');
            DB::reconnect('tenant');
            DB::setDefaultConnection('tenant');

        } catch (Exception $e) {
            Log::error("Failed to set tenant connection: " . $e->getMessage());
            abort(500, "Error connecting to tenant database.");
        }
    }

    /**
     * Create a new tenant and run migrations.
     */
    public function createTenant($name)
    {
        try {
            $databaseName = 'cdbe_' . strtolower(str_replace(' ', '_', $name)) . '_db';

            // Check if the database already exists
            if ($this->databaseExists($databaseName)) {
                throw new Exception("Database already exists!");
            }

            // Create the tenant database
            DB::statement("CREATE DATABASE {$databaseName}");

            // Save tenant details in the main database
            $tenant = Tenant::create([
                'name' => $name,
                'database' => $databaseName
            ]);

            // Set the connection to the newly created tenant database
            self::setTenantConnection($tenant->id);

            // Run tenant-specific migrations
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant', // Ensure tenant migrations are stored in this path
                '--force' => true
            ]);

            Log::info("Migrations completed for tenant: " . $databaseName);

            return $tenant;
        } catch (Exception $e) {
            Log::error("Failed to create tenant: " . $e->getMessage());
            throw new Exception("Failed to create tenant.");
        }
    }

    /**
     * Check if a database exists.
     */
    public function databaseExists($databaseName)
    {
        try {
            DB::purge('pgsql');
            $result = DB::connection('pgsql')->select("SELECT 1 FROM pg_database WHERE datname = ?", [$databaseName]);
            return !empty($result);
        } catch (Exception $e) {
            return false;
        }
    }
}
