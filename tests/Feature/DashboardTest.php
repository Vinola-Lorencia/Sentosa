<?php

namespace Tests\Feature;

use App\Models\Role;
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
            'name'     => 'Pemilik Test',
            'email'    => 'pemilik@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_pembeli_cannot_view_dashboard()
    {
        $role = Role::create(['role_name' => 'Pembeli']);
        $user = User::create([
            'name'     => 'Pembeli Test',
            'email'    => 'pembeli@test.com',
            'password' => bcrypt('password'),
            'role_id'  => $role->id,
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
}