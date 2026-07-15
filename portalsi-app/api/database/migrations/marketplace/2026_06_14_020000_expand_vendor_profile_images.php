<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'marketplace';

    public function up(): void
    {
        $c = DB::connection($this->connection);
        $t = $c->getTablePrefix() . 'vendors';
        $c->statement("ALTER TABLE {$t} MODIFY avatar LONGTEXT NULL");
        $c->statement("ALTER TABLE {$t} MODIFY banner LONGTEXT NULL");
    }

    public function down(): void
    {
        $c = DB::connection($this->connection);
        $t = $c->getTablePrefix() . 'vendors';
        $c->statement("ALTER TABLE {$t} MODIFY avatar TEXT NULL");
        $c->statement("ALTER TABLE {$t} MODIFY banner TEXT NULL");
    }
};
