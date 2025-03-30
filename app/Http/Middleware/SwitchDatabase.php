<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SwitchDatabase
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if (session()->has('tenant_id')) {
                $tenantId = session('tenant_id');

                // Set koneksi ke database tenant berdasarkan tenant_id
                TenantService::setTenantConnection($tenantId);
            } else {
                // Tenant tidak ditemukan dalam session
                return response()->json(['message' => 'Tenant not found'], 404);
            }

        } catch (\Exception $e) {
            Log::error("Middleware SwitchDatabase error: " . $e->getMessage());
            abort(500, "Terjadi kesalahan saat menghubungkan ke database tenant.");
        }

        return $next($request);
    }
}
