<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Creates a Pemilik account with a random password that is printed once
 * to the terminal and never stored anywhere in plain text.
 */
class CreateOwnerUser extends Command
{
    public const ROLE_NAME = 'Pemilik';

    public const PASSWORD_LENGTH = 16;

    private const UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijkmnopqrstuvwxyz';

    private const DIGITS = '23456789';

    private const SYMBOLS = '!@#%*-_=+?';

    protected $signature = 'users:create-owner
                            {email : Email login pemilik}
                            {name : Nama pemilik}
                            {--dry-run : Hanya tampilkan apa yang akan dibuat}';

    protected $description = 'Buat user dengan role Pemilik dan kata sandi acak';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->argument('name'));

        $validator = Validator::make(['email' => $email, 'name' => $name], [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] Akan dibuat user:');
            $this->line("  Nama  : {$name}");
            $this->line("  Email : {$email}");
            $this->line('  Role  : '.self::ROLE_NAME.(Role::where('role_name', self::ROLE_NAME)->exists() ? '' : ' (role belum ada, akan dibuat)'));
            $this->line('Tidak ada yang dibuat. Jalankan tanpa --dry-run untuk membuat user.');

            return self::SUCCESS;
        }

        $password = $this->generatePassword();

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => Role::firstOrCreate(['role_name' => self::ROLE_NAME])->id,
        ]);

        $this->info("User Pemilik {$email} berhasil dibuat.");
        $this->line("Kata sandi (hanya ditampilkan sekali, simpan sekarang): {$password}");

        return self::SUCCESS;
    }

    /**
     * Random password with at least one upper, lower, digit and symbol.
     */
    private function generatePassword(): string
    {
        $sets = [self::UPPER, self::LOWER, self::DIGITS, self::SYMBOLS];
        $all = implode('', $sets);

        $chars = array_map(fn (string $set) => $set[random_int(0, strlen($set) - 1)], $sets);
        while (count($chars) < self::PASSWORD_LENGTH) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Fisher–Yates with random_int so the guaranteed characters are not
        // always in the first four positions.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
