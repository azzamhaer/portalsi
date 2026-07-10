<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null) {
        $row = self::find($key);
        return $row ? $row->value : $default;
    }

    public static function put(string $key, $value): void {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function all_kv(): array {
        return self::all()->pluck('value', 'key')->toArray();
    }
}
