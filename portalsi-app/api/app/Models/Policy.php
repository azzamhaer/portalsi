<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    protected $fillable = [
        'title',
        'description',
        'slides',
        'read_seconds',
        'require_agreement',
        'agreement_text',
        'is_active',
        'version',
        'created_by',
    ];

    protected $casts = [
        'slides' => 'array',
        'read_seconds' => 'integer',
        'require_agreement' => 'boolean',
        'is_active' => 'boolean',
        'version' => 'integer',
    ];

    public function acceptances()
    {
        return $this->hasMany(PolicyAcceptance::class);
    }
}
