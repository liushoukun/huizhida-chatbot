<?php

use App\Http\Controllers\GatewayController;
use HuiZhiDa\Gateway\Http\Controllers\CallbackController;
use Illuminate\Support\Facades\Route;


Route::get('gateway', [GatewayController::class, 'index']);
Route::any('gateway/{channel}/{id}', [CallbackController::class, 'handle']);