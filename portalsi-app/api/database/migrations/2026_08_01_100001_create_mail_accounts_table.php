<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            // Satu akun email per user Portal SI. Tanpa FK DB (tipe users.id bervariasi
            // antar-instalasi); integritas dijaga di level aplikasi + index unik.
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('local_part', 64)->unique();  // "abc"
            $table->string('email')->unique();            // "abc@portalsi.com"
            // Password mailbox (dienkripsi dengan APP_KEY) — dipakai webmail untuk
            // login IMAP/SMTP atas nama user tanpa user perlu menghafalnya.
            $table->text('password');
            $table->string('quota')->default('unlimited');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
