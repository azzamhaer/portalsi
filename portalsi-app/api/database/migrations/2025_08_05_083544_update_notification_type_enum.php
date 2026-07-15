<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $t = DB::getTablePrefix() . 'notifications';
        DB::statement("ALTER TABLE {$t} MODIFY type ENUM(
            'like',
            'comment',
            'follow',
            'mention',
            'reply',
            'follow_accepted'
        ) NOT NULL");
    }

    public function down()
    {
        $t = DB::getTablePrefix() . 'notifications';
        DB::statement("ALTER TABLE {$t} MODIFY type ENUM(
            'like',
            'comment',
            'follow',
            'mention',
            'reply'
        ) NOT NULL");
    }
};
