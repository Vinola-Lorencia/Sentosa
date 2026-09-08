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

        Role::firstOrCreate(['role_name' => 'Karyawan']);

        $karyawanRoleId = Role::firstOrCreate(['role_name' => 'Karyawan'])->id;

        User::factory()->create([
            'name' => 'Karyawan',
            'email' => 'karyawan@tokosentosa.com',
            'password' => Hash::make('karyawan123'),
            'role_id' => $karyawanRoleId,
        ]);

        User::firstOrCreate(
            ['email' => 'agus@tokosentosa.com'],
            [
                'name' => 'Agus',
                'password' => Hash::make('agus123'),
                'role_id' => $karyawanRoleId,
            ]
        );

        User::firstOrCreate(
            ['email' => 'faisal@tokosentosa.com'],
            [
                'name' => 'Faisal',
                'password' => Hash::make('faisal123'),
                'role_id' => $karyawanRoleId,
            ]
        );

        User::firstOrCreate(
            ['email' => 'anwar@tokosentosa.com'],
            [
                'name' => 'Anwar',
                'password' => Hash::make('anwar123'),
                'role_id' => $karyawanRoleId,
            ]
        );

        $this->call(GmProductSeeder::class);
    }
}
