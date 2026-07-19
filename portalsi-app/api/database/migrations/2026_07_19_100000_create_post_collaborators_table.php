<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_collaborators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id');
            // pending = diundang menunggu persetujuan; accepted = sudah jadi co-author.
            $table->string('status', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->foreign('post_id')->references('post_id')->on('posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('user_id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_collaborators');
    }
};
