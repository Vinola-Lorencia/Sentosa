<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CreateOwnerUserCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Runs the command and returns the plain password it printed.
     */
    private function createOwner(string $email = 'pemilik@contoh.test', string $name = 'Pemilik Baru'): string
    {
        $exitCode = Artisan::call('users:create-owner', ['email' => $email, 'name' => $name]);
        $this->assertSame(0, $exitCode);

        preg_match('/Kata sandi \(hanya ditampilkan sekali, simpan sekarang\): (\S+)/', Artisan::output(), $m);
        $this->assertNotEmpty($m[1] ?? null, 'Password was not printed');

        return $m[1];
    }

    public function test_creates_user_with_pemilik_role_and_hashed_password(): void
    {
        Role::create(['role_name' => 'Karyawan']);

        $password = $this->createOwner();

        $user = User::where('email', 'pemilik@contoh.test')->firstOrFail();
        $this->assertSame('Pemilik Baru', $user->name);
        $this->assertSame('Pemilik', $user->role->role_name);

        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::isHashed($user->password));
        $this->assertTrue(Hash::check($password, $user->password));

        // The account really works: login lands on the owner dashboard.
        $this->post('/login', ['email' => 'pemilik@contoh.test', 'password' => $password]);
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_reuses_existing_pemilik_role(): void
    {
        $role = Role::create(['role_name' => 'Pemilik']);

        $this->createOwner();

        $this->assertSame(1, Role::where('role_name', 'Pemilik')->count());
        $this->assertSame($role->id, User::firstWhere('email', 'pemilik@contoh.test')->role_id);
    }

    public function test_password_is_16_chars_with_upper_lower_digit_and_symbol(): void
    {
        $passwords = [];
        foreach (range(1, 5) as $i) {
            $password = $this->createOwner("pemilik{$i}@contoh.test");

            $this->assertSame(16, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Z]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);
            $this->assertMatchesRegularExpression('/[^A-Za-z0-9]/', $password);
            $passwords[] = $password;
        }

        $this->assertCount(5, array_unique($passwords));
    }

    public function test_password_is_not_logged(): void
    {
        Log::spy();

        $this->createOwner();

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $existing = User::factory()->create(['email' => 'pemilik@contoh.test', 'name' => 'Lama']);

        $this->artisan('users:create-owner', ['email' => 'Pemilik@Contoh.test', 'name' => 'Baru'])
            ->expectsOutputToContain('email has already been taken')
            ->assertFailed();

        $this->assertSame(1, User::where('email', 'pemilik@contoh.test')->count());
        $this->assertSame('Lama', $existing->fresh()->name);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $this->artisan('users:create-owner', ['email' => 'bukan-email', 'name' => 'Baru'])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_dry_run_creates_nothing_and_prints_no_password(): void
    {
        $this->artisan('users:create-owner', ['email' => 'pemilik@contoh.test', 'name' => 'Pemilik Baru', '--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN]')
            ->expectsOutputToContain('pemilik@contoh.test')
            ->expectsOutputToContain('Pemilik (role belum ada, akan dibuat)')
            ->doesntExpectOutputToContain('Kata sandi')
            ->assertSuccessful();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Role::count());
    }
}
