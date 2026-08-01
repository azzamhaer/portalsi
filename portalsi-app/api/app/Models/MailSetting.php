<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = [
        'gate_enabled',
        'master_password',
    ];

    protected $casts = [
        'gate_enabled' => 'boolean',
    ];

    protected $hidden = [
        'master_password',
    ];

    /** Ambil baris setting singleton (buat bila belum ada). */
    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['gate_enabled' => true, 'master_password' => null],
        );
    }

    public function hasMasterPassword(): bool
    {
        return ! empty($this->master_password);
    }
}
