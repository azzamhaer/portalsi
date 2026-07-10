<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'reporter_user_id', 'target_type', 'target_id', 'category',
        'description', 'attachments', 'status', 'admin_response',
        'resolved_at', 'resolved_by'
    ];
    protected $casts = [
        'attachments' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_user_id'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }

    /** Kategori untuk dropdown frontend */
    public const CATEGORIES = [
        'PROHIBITED_GOODS'      => 'Barang ilegal (narkotika, senjata, obat keras tanpa resep)',
        'COUNTERFEIT'           => 'Barang palsu / KW / pelanggaran HKI',
        'SCAM'                  => 'Penipuan / dugaan penipuan',
        'INAPPROPRIATE_CONTENT' => 'Konten tidak pantas / pornografi / SARA',
        'HARASSMENT'            => 'Pelecehan / ujaran kebencian',
        'MISLEADING'            => 'Deskripsi menyesatkan / penipuan harga',
        'COPYRIGHT'             => 'Pelanggaran hak cipta',
        'UNSAFE'                => 'Produk berbahaya / tidak aman',
        'SPAM'                  => 'Spam / iklan menyimpang',
        'OTHER'                 => 'Lainnya (jelaskan detail)',
    ];
}
