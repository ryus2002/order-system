<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\OrderPageController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/', [OrderPageController::class, 'index']);