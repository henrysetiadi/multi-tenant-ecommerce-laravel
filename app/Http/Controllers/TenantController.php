<?php

namespace App\Http\Controllers;

use App\Services\TenantDatabaseService;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function createDatabase(Request $request)
    {
        $dbName = $request->input('database_name');
        $tenantUser = $request->input('tenant_user');
        $tenantPassword = $request->input('tenant_password');

        $result = TenantDatabaseService::createTenantDatabase($dbName, $tenantUser, $tenantPassword);

        return response()->json(['message' => $result]);
    }
}
