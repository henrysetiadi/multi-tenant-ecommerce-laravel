<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TenantDatabaseService
{
    public static function createTenantDatabase($dbName, $tenantUser, $tenantPassword)
    {
        try {
            // Periksa apakah database sudah ada
            $checkDb = DB::select("SELECT 1 FROM pg_database WHERE datname = ?", [$dbName]);

            if (!$checkDb) {
                // Buat database baru
                DB::statement("CREATE DATABASE $dbName");

                // Buat user khusus untuk tenant (jika belum ada)
                DB::statement("CREATE USER $tenantUser WITH PASSWORD '$tenantPassword'");

                // Berikan hak akses ke user baru untuk database tenant
                DB::statement("GRANT ALL PRIVILEGES ON DATABASE $dbName TO $tenantUser");

                return "Database '$dbName' dengan user '$tenantUser' berhasil dibuat.";
            } else {
                return "Database '$dbName' sudah ada.";
            }
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
