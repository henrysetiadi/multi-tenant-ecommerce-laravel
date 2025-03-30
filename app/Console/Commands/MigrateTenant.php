<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class MigrateTenant extends Command
{
    protected $signature = 'tenant:migrate {tenant_id}';
    protected $description = 'Run migrations for a specific tenant database';

    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        // Get the tenant data
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant not found.");
            return;
        }

        // Set the database connection dynamically
        Config::set("database.connections.tenant", [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => $tenant->database, // Tenant-specific database
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', 'password'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);

        DB::purge('tenant'); // Refresh connection
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');

        // Run migrations for the tenant database
        $this->info("Running migrations for tenant: " . $tenant->database);
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant', // Ensure your migrations are here
            '--force' => true
        ]);

        $this->info("Migrations completed for tenant: " . $tenant->database);
    }
}
