<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $t = DB::getTablePrefix() . 'users';
        // Ubah enum jadi: dev, teacher, parent, student, other
        DB::statement("ALTER TABLE {$t} MODIFY role ENUM('dev', 'teacher', 'parent', 'student', 'other') DEFAULT 'student'");
    }

    public function down(): void
    {
        $t = DB::getTablePrefix() . 'users';
        // Balik ke enum sebelumnya (tanpa 'other')
        DB::statement("ALTER TABLE {$t} MODIFY role ENUM('dev', 'teacher', 'parent', 'student') DEFAULT 'student'");
    }
};
