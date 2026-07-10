<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE vendors MODIFY avatar LONGTEXT NULL');
        DB::statement('ALTER TABLE vendors MODIFY banner LONGTEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vendors MODIFY avatar TEXT NULL');
        DB::statement('ALTER TABLE vendors MODIFY banner TEXT NULL');
    }
};
