<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Services\TenantService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class ProductController extends Controller
{
    public function __construct(Request $request)
    {
        // Ambil user yang sedang login
        if ($request->route()->getActionMethod() === 'getAllProductCrossDb' || $request->route()->getActionMethod() === 'getAllProductCrossDbExceptMe') {
            return; // Skip tenant enforcement for cross-db fetching
        }

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
        $products = Product::all();
        return response()->json($products);
    }

    public function getProductById($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'product' => $product,
            'image_url' => $product->image ? asset('storage/' . $product->image) : null
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $tenantDatabase = DB::connection()->getDatabaseName();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store("products/{$tenantDatabase}", 'public');
            // Saves to storage/app/public/products/{tenant_database}/filename.jpg
        }

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'image' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
            'image_url' => asset('storage/' . $imagePath), // Generate public URL
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate image file
        ]);

        // Get tenant database name dynamically
        $tenantDatabase = DB::connection()->getDatabaseName();

        // Handle image upload
        $imagePath = $product->image; // Default to current image

        if ($request->hasFile('image')) {
            // Delete the old image if it exists
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            // Upload new image
            $imagePath = $request->file('image')->store("products/{$tenantDatabase}", 'public');
        }

        // Update product
        $product->update([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'image' => $imagePath, // Save new or existing image path
        ]);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product,
            'image_url' => $imagePath ? asset('storage/' . $imagePath) : null, // Generate public URL
        ]);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if ($product->image) {
            Storage::disk('public')->delete($product->image); // Delete the image file
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    // load all of products for guest mode
    public function getAllProductCrossDb()
    {
        $tenants = Tenant::all();

        $allProducts = [];

        foreach ($tenants as $tenant) {
            try {

                Log::info("Switching to database: " . $tenant->database);
                // Switch to the tenant's database
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
                DB::purge('tenant'); // Clear the old connection
                DB::reconnect('tenant'); // Reconnect to ensure it applies

                $currentDb = DB::connection('tenant')->getDatabaseName();
                Log::info("Connected to database: " . $currentDb);

                // Fetch all products from the tenant's database
                $products = DB::connection('tenant')->table('products')->get();

                // Add tenant ID to products (optional, for identification)
                foreach ($products as $product) {
                    $product->tenant_id = $tenant->id;
                    $allProducts[] = $product;
                }
            } catch (\Exception $e) {
                // Log errors if a tenant database is unreachable
                \Log::error("Failed to fetch products from tenant: {$tenant->id}", ['error' => $e->getMessage()]);
            }
        }

        return response()->json($allProducts);
    }

    // load all of products except user login's product
    public function getAllProductCrossDbExceptMe()
    {
        $user = Auth::user(); // Get the logged-in user

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

         // Get the tenant database of the logged-in user
        $userTenant = Tenant::where('id', $user->id)->first();
        if (!$userTenant) {
            return response()->json(['error' => 'User tenant not found'], 404);
        }

        $tenants = Tenant::all();

        $allProducts = [];

        foreach ($tenants as $tenant) {
            try {
                Log::info("Switching to database: " . $tenant->database);

                if ($tenant->database === $userTenant->database) {
                    continue;
                }

                // Switch to the tenant's database
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
                DB::purge('tenant'); // Clear the old connection
                DB::reconnect('tenant'); // Reconnect to ensure it applies

                $currentDb = DB::connection('tenant')->getDatabaseName();
                Log::info("Connected to database: " . $currentDb);


                $products = DB::connection('tenant')
                    ->table('products')
                    ->get();

                // Add tenant ID to products (optional, for identification)
                foreach ($products as $product) {
                    $product->tenant_id = $tenant->id;
                    $allProducts[] = $product;
                }
            } catch (\Exception $e) {
                \Log::error("Failed to fetch products from tenant: {$tenant->id}", ['error' => $e->getMessage()]);
            }
        }

        return response()->json($allProducts);
    }

}
