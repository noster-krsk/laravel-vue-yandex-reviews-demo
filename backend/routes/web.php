<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return file_get_contents(public_path('dist/index.html'));
});

// Все остальные не-API роуты тоже отдаем Vue
Route::get('/{any}', function () {
    return file_get_contents(public_path('dist/index.html'));
})->where('any', '^(?!api).*$');
