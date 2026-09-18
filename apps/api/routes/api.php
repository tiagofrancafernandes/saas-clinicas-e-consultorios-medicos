<?php

declare(strict_types=1);

use App\Http\Controllers\Central\TenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('central')->group(function (): void {
    Route::get('/tenants', [TenantController::class, 'index']);
    Route::post('/tenants', [TenantController::class, 'store']);
    Route::get('/tenants/{id}', [TenantController::class, 'show']);
});
