<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tenants')->insert([
            ['name' => 'Store A', 'database' => 'store_a_db', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Store B', 'database' => 'store_b_db', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
