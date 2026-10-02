<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_pemilik_can_view_dashboard()
    {
        $role = Role::create(['role_name' => 'Pemilik']);
        $user = User::create([
            'name' => 'Pemilik Test',
            'email' => 'pemilik@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_pembeli_cannot_view_dashboard()
    {
        $role = Role::create(['role_name' => 'Pembeli']);
        $user = User::create([
            'name' => 'Pembeli Test',
            'email' => 'pembeli@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertStatus(403);
    }

    public function test_guest_cannot_view_dashboard()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Covers the N+1 fix: karyawan stats used to be computed with 4 separate
     * queries PER karyawan; now it's 4 grouped queries total, mapped back in
     * PHP. This verifies the grouped/mapped result still matches per user —
     * including a karyawan with zero products, which must show 0/'-', not
     * throw or silently disappear.
     */
    public function test_dashboard_shows_correct_per_karyawan_product_stats()
    {
        $pemilikRole = Role::create(['role_name' => 'Pemilik']);
        $pemilik = User::create([
            'name' => 'Pemilik Test',
            'email' => 'pemilik@test.com',
            'password' => bcrypt('password'),
            'role_id' => $pemilikRole->id,
        ]);

        $karyawanRole = Role::create(['role_name' => 'Karyawan']);
        $budi = User::create([
            'name' => 'Budi',
            'email' => 'budi@test.com',
            'password' => bcrypt('password'),
            'role_id' => $karyawanRole->id,
        ]);
        $siti = User::create([
            'name' => 'Siti',
            'email' => 'siti@test.com',
            'password' => bcrypt('password'),
            'role_id' => $karyawanRole->id,
        ]);

        $product1 = Product::create([
            'product_name' => 'Produk Budi Awal',
            'sale_price' => 10000,
            'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 5,
            'default_buy_price' => 5000,
            'default_buy_discount' => 0,
            'created_by' => $budi->id,
        ]);

        $product2 = Product::create([
            'product_name' => 'Produk Budi Terbaru',
            'sale_price' => 20000,
            'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 5,
            'default_buy_price' => 10000,
            'default_buy_discount' => 0,
            'created_by' => $budi->id,
        ]);
        // Force distinct created_at ordering so "terakhir" is unambiguous.
        $product1->created_at = now()->subDay();
        $product1->save();
        $product2->created_at = now();
        $product2->save();

        // Budi also re-photographed an old product he didn't create — should
        // count toward foto_bulan_ini/foto_terakhir, not jumlah_bulan_ini.
        $oldProduct = Product::create([
            'product_name' => 'Produk Lama Difoto Budi',
            'sale_price' => 15000,
            'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus',
            'dus_to_unit' => 1,
            'stock' => 5,
            'default_buy_price' => 7500,
            'default_buy_discount' => 0,
            'updated_by' => $budi->id,
        ]);
        $oldProduct->updated_at = now();
        $oldProduct->save();

        // Siti has zero products — must render 0/'-' without error.
        $response = $this->actingAs($pemilik)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Budi');
        $response->assertSee('Siti');
        $response->assertSee('2 produk');
        // Precise phrase match (not just assertSee the product name), since
        // the product names also appear in the unrelated "Produk Terbaru"
        // widget elsewhere on the page regardless of this section's logic.
        $response->assertSee('Terakhir: Produk Budi Terbaru', false); // latest of Budi's 2 created products
        $response->assertDontSee('Terakhir: Produk Budi Awal', false);
        $response->assertSee('1 foto');
        $response->assertSee('Foto terakhir: Produk Lama Difoto Budi', false);
        $response->assertSee('0 produk'); // Siti
    }

    /**
     * Covers the 6-month sales chart, which used to run 6 separate
     * whereMonth/whereYear queries and now runs one query grouped in PHP.
     */
    public function test_dashboard_sales_chart_totals_current_month_correctly()
    {
        $role = Role::create(['role_name' => 'Pemilik']);
        $user = User::create([
            'name' => 'Pemilik Test',
            'email' => 'pemilik@test.com',
            'password' => bcrypt('password'),
            'role_id' => $role->id,
        ]);

        Sale::create([
            'user_id' => $user->id,
            'sale_date' => now()->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount' => 150000,
        ]);

        Sale::create([
            'user_id' => $user->id,
            'sale_date' => now()->subMonths(2)->toDateString(),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount' => 90000,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('150k');
        $response->assertSee('90k');
    }
}
