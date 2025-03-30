<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tenant;
use Stancl\Tenancy\Tenancy;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }


    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ]);

        $centralUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Buat tenant baru dan database
        $tenant = $this->tenantService->createTenant($request->name);

        $centralUser->tenant_id = $tenant->id;
        $centralUser->save(); // Simpan perubahan tenant_id

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }

    public function login(Request $request , Tenancy $tenancy)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);


         // ** Cek User di Central Database**
        $user = User::on('pgsql')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        Cache::put('tenant_id_' . $user->id, $user->tenant_id, now()->addHours(1));


        $token = $user->createToken('API Token')->plainTextToken;

        $user->tokens()->create([
            'name' => 'API Token',
            'token' => hash('sha256', $token),
            'abilities' => '[]', // Define token abilities (permissions) here if needed
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            //'tenant' => $tenantUser
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete(); // Menghapus semua token pengguna

        return response()->json([
            'message' => 'Logout berhasil'
        ], 200);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
