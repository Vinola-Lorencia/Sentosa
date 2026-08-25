<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'name' => 'Vinola Lorencia',
            'email' => 'vinola@tokosentosa.com',
            'password' => Hash::make('password123'),
            'role_id' => Role::firstOrCreate(['role_name' => 'Pemilik'])->id,
        ]);

        $this->call(GmProductSeeder::class);
    }
}
