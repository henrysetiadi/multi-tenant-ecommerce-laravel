<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Tenant;

class MultiTenantCartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set database connection to central
        config(['database.default' => 'pgsql']);

        // Run central database migrations
        Artisan::call('migrate', ['--database' => 'pgsql']);

        // Create a tenant user in the central database
        $this->storeOwner = User::create([
            'name' => 'Shop Owner',
            'email' => 'shop_owner@example.com',
            'password' => bcrypt('password'),
        ]);

        // Generate database name dynamically
        $tenantDbName = 'dcbe_' . strtolower(str_replace(' ', '_', $this->storeOwner->name)) . '_db';

        // Ensure uniqueness (optional, if same username exists)
        if (DB::table('tenants')->where('database', $tenantDbName)->exists()) {
            $tenantDbName .= '_' . time(); // Append timestamp
        }

        // Create a tenant entry in the central `tenants` table
        $this->tenant = Tenant::create([
            'user_id' => $this->storeOwner->id,
            'name' => $this->storeOwner->name, // Store owner name in the tenants table
            'database' => $tenantDbName,
        ]);

        // Check if the tenant database exists, if not, create it
        $databaseExists = DB::connection('pgsql')
            ->select("SELECT datname FROM pg_database WHERE datname = ?", [$tenantDbName]);

        if (!$databaseExists) {
            DB::connection('pgsql')->statement("CREATE DATABASE {$tenantDbName}");
        }

        // Switch to tenant database
        config(['database.default' => 'tenant']);

        // Run tenant migrations
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
        ]);

        // Create the same user inside the tenant database
        $this->storeOwnerTenant = User::create([
            'id' => $this->storeOwner->id, // Ensure the ID is the same as in the central DB
            'name' => 'Store Owner',
            'email' => 'store_owner@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create a product in the tenant database (belongs to store owner)
        $this->product = Product::create([
            'name' => 'Test Product',
            'description' => 'A great product',
            'price' => 100.50,
            'stock' => 10,
        ]);
    }

    public function test_other_user_can_add_product_to_cart_in_correct_tenant_database()
    {
        // Set database connection to tenant
        config(['database.default' => 'tenant']);

        // Create another user who will buy the product
        $buyer = User::create([
            'name' => 'Buyer User',
            'email' => 'buyer@example.com',
            'password' => bcrypt('password'),
        ]);

        // Ensure the buyer is not the store owner
        $this->assertNotEquals($buyer->id, $this->storeOwnerTenant->id);

        // Add product to cart with buyer's user_id and tenant_id
        $cart = Cart::create([
            'user_id' => $buyer->id, // Cart belongs to the buyer
            'product_id' => $this->product->id,
            'quantity' => 2,
            'tenant_id' => $this->tenant->id, // Ensure the tenant_id is set
        ]);

        // Assert the product exists in the cart
        $this->assertDatabaseHas('carts', [
            'user_id' => $buyer->id, // Ensure cart is linked to the buyer
            'product_id' => $this->product->id,
            'quantity' => 2,
            'tenant_id' => $this->tenant->id, // Ensure correct tenant
        ], 'tenant');
    }
}
