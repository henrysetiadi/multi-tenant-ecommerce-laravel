<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\Tenant;
use Stancl\Tenancy\Tenancy;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class CartController extends Controller
{
    public function __construct(Request $request)
    {
        if ($request->route()->getActionMethod() === 'getCart') {
            return; // Skip tenant enforcement for cross-db fetching
        }
        // Ambil user yang sedang login
        $user = Auth::user();

        if (!$user) {
            abort(403, "Unauthorized");
        }

        // Ambil tenant_id dari cache
        $tenantId = Cache::get('tenant_id_' . $user->id);

        if (!$tenantId) {
            abort(403, "Tenant not found");
        }

        // Atur koneksi ke tenant yang sesuai
        TenantService::setTenantConnection($tenantId);
    }


    public function index()
    {
        $tenantDb = DB::connection()->getDatabaseName(); // Ambil nama database tenant saat ini

        $cartItems = Cart::where('user_id', Auth::id())
                        ->with('product')
                        ->get();

        return response()->json($cartItems);
    }

    public function getCart(){
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        //get Database Central For FirstTime
        $centralDb = DB::connection()->getDatabaseName();
        Log::info('tenant db: '.$centralDb);

        $getDatabaseUserLogin = DB::table('tenants')
                                ->where('id', $user->id)
                                ->first();

        if (!$getDatabaseUserLogin) {
            return response()->json(['message' => 'Tenant not found'], 200);
        }

        $dbName = $getDatabaseUserLogin->database;

        Config::set("database.connections.tenant", [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => $dbName,
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', 'password'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge('tenant');
        DB::reconnect('tenant');


        // Step 1: Get all unique tenant_id values from the cart
        $cartItems = DB::connection('tenant')->table('carts')
                        ->where('user_id', $user->id)
                        ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 200);
        }

        $tenantIds = $cartItems->pluck('tenant_id')->unique();

        Config::set("database.connections.central", [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => $centralDb,
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', 'password'),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        // Step 2: Get database names for these tenant IDs from central DB
        $tenantDatabases = DB::connection('central')->table('tenants')
                            ->whereIn('id', $tenantIds)
                            ->pluck('database', 'id'); // ['tenant_id' => 'database']

        $cartDetails = [];

        // Step 3: Loop through each cart item and fetch the product details
        foreach ($cartItems as $cartItem) {
            $tenantId = $cartItem->tenant_id;

            if (!isset($tenantDatabases[$tenantId])) {
                continue; // Skip if tenant DB not found
            }

            $tenantDb = $tenantDatabases[$tenantId];

            // Step 4: Switch to the correct tenant database
            Config::set("database.connections.tenant", [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => $tenantDb,
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'password'),
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            // Step 5: Fetch the product details
            $product = DB::connection('tenant')->table('products')
                        ->where('id', $cartItem->product_id)
                        ->first();

            if ($product) {
                // Step 6: Append full details
                $cartDetails[] = [
                    'cart_id' => $cartItem->id,
                    'tenant_id' => $cartItem->tenant_id,
                    //'tenant_database' => $tenantDb,
                    'product_id' => $cartItem->product_id,
                    'product_name' => $product->name ?? 'Unknown Product',
                    //'product_description' => $product->description ?? '',
                    'product_price' => $product->price ?? 0,
                    'product_stock' => $product->stock ?? 0,
                    'product_image' => $product->image ?? '',
                    'quantity' => $cartItem->quantity,
                ];
            }
        }

        return response()->json($cartDetails);
    }


    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'quantity' => 'required|integer|min:1',
            'tenant_id' => 'required'
        ]);

        $userLoginDb = DB::connection()->getDatabaseName();

        Config::get('database.connections.pgsql');

        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $tenant = DB::connection('pgsql')->table('tenants')
                ->where('id', $request->tenant_id)
                ->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        $databaseName = $tenant->database; // Get tenant database name

        Config::set('database.connections.pgsql_dynamic.database', $databaseName);

        DB::purge('pgsql_dynamic');
        DB::reconnect('pgsql_dynamic');

        $product = DB::connection('pgsql_dynamic')->table('products')
                 ->where('id', $request->product_id)
                 ->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found in tenant database'], 404);
        }

        // 4️⃣ Check stock availability
        if ($request->quantity > $product->stock) {
            return response()->json(['message' => 'Not enough stock available'], 400);
        }

        Config::set('database.connections.pgsql_dynamic.database', $userLoginDb);

        DB::purge('pgsql_dynamic');
        DB::reconnect('pgsql_dynamic');


        // Get the current cart item (if exists)
        $existingCartItem = DB::connection('pgsql_dynamic')->table('carts')
        ->where('user_id', Auth::id())
        ->where('product_id', $request->product_id)
        ->where('tenant_id', $request->tenant_id)
        ->first();

        // Calculate new quantity if item already exists in cart
        $newQuantity = $existingCartItem ? ($existingCartItem->quantity + $request->quantity) : $request->quantity;

        // Check if requested quantity exceeds stock
        if ($newQuantity > $product->stock) {
            return response()->json(['message' => 'Not enough stock available'], 400);
        }

        // Update or create the cart item
        if ($existingCartItem) {
            DB::connection('pgsql_dynamic')->table('carts')
                ->where('id', $existingCartItem->id)
                ->update(['quantity' => $newQuantity, 'updated_at' => now()]);
        } else {

            DB::connection('pgsql_dynamic')->table('carts')->insert([
                'user_id' => Auth::id(),
                'product_id' => $request->product_id,
                'tenant_id' => $request->tenant_id,
                'quantity' => $request->quantity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Product added to cart']);
    }


    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        // Get current tenant database name (User's active session DB)
        $dbActive = DB::connection()->getDatabaseName();

        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tenantIdRequest = $request->tenantId;

        // **Step 1: Check if Cart Item Exists in User's Active Tenant DB**
        try {
            $cartItem = DB::table('carts')
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantIdRequest)
                ->first();

            if (!$cartItem) {
                return response()->json(['message' => 'Cart item not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Database connection error'], 500);
        }

        // **Step 2: Get the Database Name of the Requested Tenant from Central DB**
        try {
            DB::purge('pgsql');
            Config::get('database.connections.pgsql');
            DB::reconnect('pgsql');

            $tenantDatabase = DB::connection('pgsql')->table('tenants')
                ->where('id', $tenantIdRequest)
                ->first();

            if (!$tenantDatabase) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to connect to central database'], 500);
        }

        // **Step 3: Switch to Tenant Database and Check Product Stock**
        try {
            DB::purge('pgsql_dynamic');
            Config::set('database.connections.pgsql_dynamic.database', $tenantDatabase->database);
            DB::reconnect('pgsql_dynamic');

            $product = DB::connection('pgsql_dynamic')->table('products')
                ->where('id', $cartItem->product_id)
                ->first();

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            if ($request->quantity > $product->stock) {
                return response()->json(['message' => 'Not enough stock available'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to check product stock'], 500);
        }

        // **Step 4: Switch Back to User's Active Tenant DB to Update Cart**
        try {
            DB::purge('activeTenant');
            Config::set("database.connections.activeTenant", [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '5432'),
                'database' => $dbActive,  // Original database where the cart is located
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'password'),
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ]);
            DB::reconnect('activeTenant');

            DB::connection('activeTenant')->table('carts')
                ->where('id', $id)
                ->update(['quantity' => $request->quantity, 'updated_at' => now()]);

            return response()->json(['message' => 'Cart updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update cart'], 500);
        }
    }


    public function destroy($id)
    {
        $cartItem = Cart::where('id', $id)
                        ->where('user_id', Auth::id())
                        ->firstOrFail();

        $cartItem->delete();

        return response()->json(['message' => 'Product removed from cart']);
    }
}
