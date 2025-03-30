<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class DatabaseHelper
{
    public static function switchToTenantDatabase($databaseName)
    {
        // Set database baru
        Config::set('database.connections.pgsql_dynamic.database', $databaseName);

        // Hapus koneksi lama
        DB::purge('pgsql_dynamic');

        // Hubungkan ulang dengan database baru
        DB::reconnect('pgsql_dynamic');

        return DB::connection('pgsql_dynamic');
    }
}
