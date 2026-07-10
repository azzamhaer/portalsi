<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Build for the long term.');
})->purpose('Display a quote');
