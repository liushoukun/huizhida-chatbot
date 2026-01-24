<?php

use HuiZhiDa\Gateway\Http\Controllers\CallbackController;
use Illuminate\Support\Facades\Route;


Route::get('gateway/{channel}/{id}', [CallbackController::class, 'health']);
Route::post('gateway/{channel}/{id}', [CallbackController::class, 'handle']);