<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleName): User
    {
        $role = Role::create(['role_name' => $roleName]);

        return User::create([
            'name'     => $roleName,
            'email'    => strtolower($roleName) . '@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);
    }

    public function test_pemilik_redirects_to_dashboard()
    {
        $user = $this->makeUser('Pemilik');

        $response = $this->post(route('login'), [
            'email' => $user->email, 'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_karyawan_redirects_to_products()
    {
        $user = $this->makeUser('Karyawan');

        $response = $this->post(route('login'), [
            'email' => $user->email, 'password' => 'password',
        ]);

        $response->assertRedirect(route('products.index'));
    }

    public function test_pembeli_redirects_to_toko()
    {
        $user = $this->makeUser('Pembeli');

        $response = $this->post(route('login'), [
            'email' => $user->email, 'password' => 'password',
        ]);

        $response->assertRedirect(route('toko.index'));
    }

    public function test_karyawan_can_view_and_edit_products_but_not_delete()
    {
        $user = $this->makeUser('Karyawan');

        $this->actingAs($user)->get(route('products.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('products.create'))->assertStatus(200);

        $product = \App\Models\Product::create([
            'product_name' => 'Test', 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 1,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
        ]);

        $this->actingAs($user)->delete(route('products.destroy', $product))->assertStatus(403);
    }

    public function test_karyawan_cannot_access_laporan_or_customers()
    {
        $user = $this->makeUser('Karyawan');

        $this->actingAs($user)->get(route('laporan.penjualan'))->assertStatus(403);
        $this->actingAs($user)->get(route('customers.index'))->assertStatus(403);
    }

    public function test_karyawan_can_view_and_create_sales_but_not_delete()
    {
        $user = $this->makeUser('Karyawan');

        $this->actingAs($user)->get(route('sales.index'))->assertStatus(200);
        $this->actingAs($user)->get(route('sales.create'))->assertStatus(200);

        $customer = \App\Models\Customer::create([
            'customer_name' => 'Test Customer',
            'phone'         => '08123456789',
        ]);

        $sale = \App\Models\Sale::create([
            'customer_id'    => $customer->id,
            'user_id'        => $user->id,
            'sale_date'      => now()->format('Y-m-d'),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount'   => 10000,
        ]);

        $this->actingAs($user)->get(route('sales.show', $sale->sale_id))->assertStatus(200);
        $this->actingAs($user)->delete(route('sales.destroy', $sale->sale_id))->assertStatus(403);
    }
}
