<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KaryawanProductsModalTest extends TestCase
{
    use RefreshDatabase;

    private User $pemilik;
    private User $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $pemilikRole  = Role::create(['role_name' => 'Pemilik']);
        $karyawanRole = Role::create(['role_name' => 'Karyawan']);

        $this->pemilik = User::create([
            'name' => 'Owner', 'email' => 'owner@test.com',
            'password' => bcrypt('password'), 'role_id' => $pemilikRole->id,
        ]);

        $this->karyawan = User::create([
            'name' => 'Budi', 'email' => 'budi@test.com',
            'password' => bcrypt('password'), 'role_id' => $karyawanRole->id,
        ]);
    }

    public function test_pemilik_can_fetch_karyawan_products_json()
    {
        Product::create([
            'product_name' => 'Punya Budi', 'sale_price' => 1000, 'sale_unit' => 'Buah',
            'purchase_unit' => 'Dus', 'dus_to_unit' => 1, 'stock' => 12,
            'default_buy_price' => 500, 'default_buy_discount' => 0,
            'created_by' => $this->karyawan->id,
        ]);

        $response = $this->actingAs($this->pemilik)
            ->getJson("/api/karyawan-products/{$this->karyawan->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'products');
        $response->assertJsonFragment(['product_name' => 'Punya Budi']);
        $response->assertJsonStructure([
            'products' => [['id', 'product_name', 'stock', 'stok_display', 'created_at', 'edit_url']],
        ]);
    }

    public function test_karyawan_products_endpoint_forbidden_for_karyawan()
    {
        $response = $this->actingAs($this->karyawan)
            ->getJson("/api/karyawan-products/{$this->karyawan->id}");

        $response->assertStatus(403);
    }

    public function test_karyawan_products_endpoint_requires_auth()
    {
        $response = $this->getJson("/api/karyawan-products/{$this->karyawan->id}");

        $response->assertStatus(401);
    }

    public function test_dashboard_renders_clickable_karyawan_card()
    {
        $response = $this->actingAs($this->pemilik)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('data-user-id="' . $this->karyawan->id . '"', false);
        $response->assertSee('openKaryawanProductsModal(' . $this->karyawan->id, false);
    }
}
