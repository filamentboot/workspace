<?php

use App\Http\Controllers\OfficialMarketIndexController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/plugin-market/index.json', OfficialMarketIndexController::class)
    ->name('plugin-market.index');
