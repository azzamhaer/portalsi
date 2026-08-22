<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = ['gate_enabled', 'master_password'];

    protected $casts = ['gate_enabled' => 'boolean'];

    /** Baris singleton (id=1). */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], ['gate_enabled' => true]);
    }

    public function hasMasterPassword(): bool
    {
        return ! empty($this->master_password);
    }
}
