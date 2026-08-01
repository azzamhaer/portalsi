<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            // Gate master password untuk beta: bila true, user wajib memasukkan
            // master password setelah login sebelum bisa setup/akses webmail.
            $table->boolean('gate_enabled')->default(true);
            // Hash bcrypt dari master password global (null = belum diset).
            $table->string('master_password')->nullable();
            $table->timestamps();
        });

        // Baris tunggal (singleton) — gate aktif tanpa password sampai admin set.
        DB::table('mail_settings')->insert([
            'id' => 1,
            'gate_enabled' => true,
            'master_password' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
