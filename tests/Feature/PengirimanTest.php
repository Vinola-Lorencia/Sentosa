<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengirimanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['role_name' => 'Pemilik']);
        $this->user = User::create([
            'name'     => 'Test User',
            'email'    => 'test@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);

        $product = Product::create([
            'product_name'         => 'Produk Test',
            'brand'                => 'Brand Test',
            'default_buy_price'    => 10000,
            'default_buy_discount' => 0,
            'sale_price'           => 15000,
            'sale_unit'            => 'Lusin',
            'purchase_unit'        => 'Dus',
            'dus_to_unit'          => 12,
            'stock'                => 10,
        ]);

        $this->sale = Sale::create([
            'user_id'        => $this->user->id,
            'sale_date'      => now()->format('Y-m-d'),
            'payment_method' => 'Cash',
            'payment_status' => 'Lunas',
            'total_amount'   => 15000,
        ]);

        SaleDetail::create([
            'sale_id'    => $this->sale->sale_id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit'       => 'Lusin',
            'price'      => 15000,
            'discount'   => 0,
            'subtotal'   => 15000,
        ]);
    }

    public function test_can_view_pengiriman_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('pengiriman.index'));

        $response->assertStatus(200);
    }

    public function test_can_view_create_pengiriman_form()
    {
        $response = $this->actingAs($this->user)
            ->get(route('pengiriman.create'));

        $response->assertStatus(200);
    }

    public function test_can_create_pengiriman_dikirim()
    {
        $response = $this->actingAs($this->user)
            ->post(route('pengiriman.store'), [
                'sale_id'         => $this->sale->sale_id,
                'delivery_date'   => now()->format('Y-m-d'),
                'type'            => 'Dikirim',
                'status'          => 'Pending',
                'recipient_name'  => 'Budi',
                'recipient_phone' => '08123456789',
                'address'         => 'Jl. Test No. 1',
                'expedition'      => 'JNE',
                'shipping_cost'   => 15000,
                'colly'           => 2,
                'notes'           => null,
            ]);

        $response->assertRedirect(route('pengiriman.show', 1));
        $this->assertDatabaseHas('deliveries', [
            'sale_id'   => $this->sale->sale_id,
            'type'      => 'Dikirim',
            'status'    => 'Pending',
            'expedition'=> 'JNE',
            'colly'     => 2,
        ]);
    }

    public function test_can_create_pengiriman_ambil_sendiri()
    {
        $response = $this->actingAs($this->user)
            ->post(route('pengiriman.store'), [
                'sale_id'         => $this->sale->sale_id,
                'delivery_date'   => now()->format('Y-m-d'),
                'type'            => 'Ambil Sendiri',
                'status'          => 'Pending',
                'recipient_name'  => 'Budi',
                'recipient_phone' => '08123456789',
                'address'         => null,
                'expedition'      => null,
                'shipping_cost'   => 0,
                'colly'           => 1,
                'notes'           => null,
            ]);

        $response->assertRedirect(route('pengiriman.show', 1));
        $this->assertDatabaseHas('deliveries', [
            'sale_id' => $this->sale->sale_id,
            'type'    => 'Ambil Sendiri',
            'colly'   => 1,
        ]);
    }

    public function test_can_update_pengiriman_status()
    {
        $delivery = Delivery::create([
            'sale_id'        => $this->sale->sale_id,
            'delivery_date'  => now()->format('Y-m-d'),
            'type'           => 'Dikirim',
            'status'         => 'Pending',
            'recipient_name' => 'Budi',
            'shipping_cost'  => 0,
            'colly'          => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('pengiriman.update', $delivery->delivery_id), [
                'delivery_date'   => now()->format('Y-m-d'),
                'type'            => 'Dikirim',
                'status'          => 'Dikirim', // update status
                'recipient_name'  => 'Budi',
                'recipient_phone' => '08123456789',
                'address'         => 'Jl. Test No. 1',
                'expedition'      => 'JNE',
                'shipping_cost'   => 15000,
                'colly'           => 1,
                'notes'           => null,
            ]);

        $response->assertRedirect(route('pengiriman.show', $delivery->delivery_id));
        $this->assertDatabaseHas('deliveries', [
            'delivery_id' => $delivery->delivery_id,
            'status'      => 'Dikirim',
        ]);
    }

    public function test_can_delete_pengiriman()
    {
        $delivery = Delivery::create([
            'sale_id'        => $this->sale->sale_id,
            'delivery_date'  => now()->format('Y-m-d'),
            'type'           => 'Dikirim',
            'status'         => 'Pending',
            'recipient_name' => 'Budi',
            'shipping_cost'  => 0,
            'colly'          => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('pengiriman.destroy', $delivery->delivery_id));

        $response->assertRedirect(route('pengiriman.index'));
        $this->assertDatabaseMissing('deliveries', [
            'delivery_id' => $delivery->delivery_id,
        ]);
    }

    public function test_guest_cannot_access_pengiriman()
    {
        $response = $this->get(route('pengiriman.index'));
        $response->assertRedirect(route('login'));
    }
}