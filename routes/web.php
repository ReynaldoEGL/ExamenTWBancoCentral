<?php

use App\Http\Controllers\Admin\ApiKeyController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CashMovementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NodeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Middleware\EnsureCentralAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(session()->has('central_admin_id') ? 'admin.dashboard' : 'central.login');
});

Route::get('/login', [AuthController::class, 'create'])->name('central.login');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('central.login.store');
Route::post('/logout', [AuthController::class, 'destroy'])->name('central.logout');

// Rutas exclusivas del panel. No sustituir las rutas API de la Persona 1.
Route::middleware([EnsureCentralAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/nodes', [NodeController::class, 'index'])->name('nodes.index');
    Route::get('/nodes/create', [NodeController::class, 'create'])->name('nodes.create');
    Route::post('/nodes', [NodeController::class, 'store'])->name('nodes.store');
    Route::get('/nodes/{nodeId}/edit', [NodeController::class, 'edit'])->name('nodes.edit');
    Route::put('/nodes/{nodeId}', [NodeController::class, 'update'])->name('nodes.update');
    Route::get('/nodes/{nodeId}', [NodeController::class, 'show'])->name('nodes.show');

    Route::post('/nodes/{nodeId}/keys', [ApiKeyController::class, 'store'])->name('nodes.keys.store');
    Route::post('/nodes/{nodeId}/keys/{keyId}/revoke', [ApiKeyController::class, 'revoke'])->name('nodes.keys.revoke');
    Route::post('/nodes/{nodeId}/cash-movements', [CashMovementController::class, 'store'])->name('nodes.cash-movements.store');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
});
