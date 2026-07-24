<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Popup kebijakan dinamis yang dikelola admin. Ditampilkan ke pengguna saat login.
 * Konten (slide/halaman) disimpan sebagai JSON agar admin bebas menambah halaman,
 * gambar, judul, dan deskripsi tanpa mengubah kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('title');                       // nama internal/utama kebijakan
            $table->text('description')->nullable();        // ringkasan singkat (opsional)
            $table->json('slides');                         // [{title, body, image_url}, ...]
            $table->unsignedInteger('read_seconds')->default(5); // wajib baca sekian detik
            $table->boolean('require_agreement')->default(true);
            $table->text('agreement_text')->nullable();     // pernyataan yang harus disetujui
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('version')->default(1); // naik tiap diubah → minta setuju lagi
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
