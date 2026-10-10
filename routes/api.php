<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\NodeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\OperationController;
use App\Support\ApiError;
use App\Http\Controllers\Api\TransactionController;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::middleware('api.key')->group(function () {
    Route::get('/node/me', [NodeController::class, 'me']);
    Route::get('/accounts/{numero}', [AccountController::class, 'show']);
    Route::post('/accounts', [AccountController::class, 'store']);
    // 02_funciones.sql
    Route::post('/withdraw', [OperationController::class, 'withdraw']);
    Route::post('/deposit',  [OperationController::class, 'deposit']);

    // 03_transacciones.sql
    Route::get('/transactions', [TransactionController::class, 'index']);
});