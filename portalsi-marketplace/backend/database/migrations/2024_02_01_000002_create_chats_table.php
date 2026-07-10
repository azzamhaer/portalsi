<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();    // pembeli
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();  // toko
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'vendor_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users');
            $table->string('sender_type'); // BUYER | SELLER
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            $table->index(['thread_id', 'created_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
    }
};
