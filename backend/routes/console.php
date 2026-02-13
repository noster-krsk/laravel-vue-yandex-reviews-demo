<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Обновлять отзывы с Яндекс Карт каждые 6 часов
Schedule::command('parse:yandex-reviews --all')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground();
