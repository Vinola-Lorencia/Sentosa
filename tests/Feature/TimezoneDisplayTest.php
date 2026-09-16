<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the timezone bug: products.created_at (and similar) are stored as
 * naive UTC literals (`timestamp without time zone` columns). Displaying
 * them raw — without converting through Carbon::toWib() — showed a time
 * 7 hours behind the real WIB clock. config('app.timezone') stays 'UTC' on
 * purpose (see AppServiceProvider); these tests confirm the conversion
 * happens at the three display sites that needed it, without that config
 * ever changing.
 */
class TimezoneDisplayTest extends TestCase
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

    private function makeProductAt(string $utcCreatedAt, ?int $createdBy = null): Product
    {
        $product = Product::create([
            'product_name'         => 'Produk Waktu Test',
            'sale_price'           => 15000,
            'sale_unit'            => 'Buah',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 1,
            'stock'                => 10,
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'created_by'           => $createdBy,
        ]);

        // Bypass Eloquent's auto-timestamping so this lands as the exact
        // literal the real DB column type would hold — a naive UTC string,
        // matching how the bug was actually reported.
        $product->created_at = $utcCreatedAt;
        $product->save();

        return $product;
    }

    public function test_app_timezone_config_stays_utc()
    {
        // The whole point of the chosen fix: config('app.timezone') is
        // never changed, so every write (past and future) stays a
        // consistent, unambiguous UTC literal.
        $this->assertEquals('UTC', config('app.timezone'));
    }

    public function test_dashboard_produk_terbaru_shows_wib_time_not_raw_utc()
    {
        // Exactly the bug report's own numbers: stored 05:52 UTC must
        // display as 12:52 WIB, not verbatim 05:52.
        $this->makeProductAt('2026-09-16 05:52:08', $this->karyawan->id);

        $response = $this->actingAs($this->pemilik)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('16 Sep 2026 12:52');
        $response->assertDontSee('16 Sep 2026 05:52');
    }

    public function test_karyawan_products_endpoint_shows_wib_time()
    {
        $this->makeProductAt('2026-09-16 05:52:08', $this->karyawan->id);

        $response = $this->actingAs($this->pemilik)
            ->getJson("/api/karyawan-products/{$this->karyawan->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['created_at' => '16 Sep 2026 12:52']);
    }

    public function test_my_products_page_shows_wib_time()
    {
        $this->makeProductAt('2026-09-16 05:52:08', $this->karyawan->id);

        $response = $this->actingAs($this->karyawan)->get(route('products.my'));

        $response->assertStatus(200);
        $response->assertSee('16 Sep 2026 12:52');
        $response->assertDontSee('16 Sep 2026 05:52');
    }

    public function test_time_crossing_midnight_shows_the_correct_wib_calendar_date()
    {
        // 23:30 UTC on the 16th is already 06:30 WIB on the 17th — the kind
        // of case where an unconverted display would show the wrong DAY,
        // not just the wrong hour.
        $this->makeProductAt('2026-09-16 23:30:00', $this->karyawan->id);

        $response = $this->actingAs($this->karyawan)->get(route('products.my'));

        $response->assertStatus(200);
        $response->assertSee('17 Sep 2026 06:30');
    }
}
