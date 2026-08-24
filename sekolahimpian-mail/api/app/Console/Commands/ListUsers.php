<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ListUsers extends Command
{
    protected $signature = 'user:list';

    protected $description = 'Daftar akun (id, username, email, status verifikasi).';

    public function handle(): int
    {
        $rows = User::orderBy('id')->get(['id', 'username', 'full_name', 'email', 'email_verified_at']);

        if ($rows->isEmpty()) {
            $this->info('Belum ada akun.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Username', 'Nama', 'Email', 'Verified'],
            $rows->map(fn ($u) => [
                $u->id,
                $u->username,
                $u->full_name,
                $u->email,
                $u->email_verified_at ? 'ya' : '—',
            ])->all()
        );

        return self::SUCCESS;
    }
}
