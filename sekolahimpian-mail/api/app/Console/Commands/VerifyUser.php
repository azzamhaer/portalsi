<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class VerifyUser extends Command
{
    protected $signature = 'user:verify {login : email atau username}';

    protected $description = 'Tandai satu akun sebagai email terverifikasi (agar bisa masuk tanpa klik tautan).';

    public function handle(): int
    {
        $login = strtolower(trim((string) $this->argument('login')));
        $user = User::where('email', $login)->orWhere('username', $login)->first();

        if (! $user) {
            $this->error("Akun '{$login}' tidak ditemukan.");

            return self::FAILURE;
        }

        if ($user->email_verified_at) {
            $this->info("Akun {$user->username} <{$user->email}> sudah terverifikasi.");

            return self::SUCCESS;
        }

        $user->email_verified_at = now();
        $user->save();

        $this->info("Akun {$user->username} <{$user->email}> kini TERVERIFIKASI. Silakan masuk.");

        return self::SUCCESS;
    }
}
