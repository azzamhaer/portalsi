<?php

namespace App\Console\Commands;

use App\Models\MailSetting;
use Illuminate\Console\Command;

class SetMasterPassword extends Command
{
    protected $signature = 'mail:master {password} {--disable-gate} {--enable-gate}';

    protected $description = 'Set master password gate (dan enable/disable gate) untuk Sekolah Impian Mail';

    public function handle(): int
    {
        $s = MailSetting::current();
        $s->master_password = bcrypt($this->argument('password'));
        if ($this->option('disable-gate')) {
            $s->gate_enabled = false;
        }
        if ($this->option('enable-gate')) {
            $s->gate_enabled = true;
        }
        $s->save();

        $this->info('Master password diset. gate_enabled=' . ($s->gate_enabled ? 'true' : 'false'));

        return self::SUCCESS;
    }
}
