<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\NodeController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::middleware('api.key')->group(function () {
    Route::get('/node/me', [NodeController::class, 'me']);
    Route::get('/accounts/{numero}', [AccountController::class, 'show']);

    // Se activan al tener 02_funciones.sql
    // Route::post('/withdraw', [OperationController::class, 'withdraw']);
    // Route::post('/deposit',  [OperationController::class, 'deposit']);
});