<?php

use Illuminate\Support\Facades\Route;

// One Blade shell; Vue Router handles every page. /api/* and /up are excluded.
Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api(/|$)|up$).*$');
