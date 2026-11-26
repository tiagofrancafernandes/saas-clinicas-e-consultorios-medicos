<?php

declare(strict_types=1);

use App\Enums\Central\UserRole;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Central\CentralUserManagementController;
use App\Http\Controllers\Central\InboundWebhookController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Central\TenantWhatsAppInstanceController;
use Illuminate\Support\Facades\Route;

// Authentication endpoints
Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Super Admin Central routes
Route::prefix('central')
    ->middleware(['auth:sanctum', 'role:' . UserRole::SUPER_ADMIN->value])
    ->group(function (): void {
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::get('/tenants/{id}', [TenantController::class, 'show']);

        // Super Admin User e ACL Management
        Route::get('/users', [CentralUserManagementController::class, 'index']);
        Route::post('/users', [CentralUserManagementController::class, 'store']);
        Route::put('/users/{userId}/permissions', [CentralUserManagementController::class, 'updatePermissions']);

        // Super Admin WhatsApp (Evolution API) management per tenant
        Route::get('/tenants/{tenantId}/whatsapp-instances', [TenantWhatsAppInstanceController::class, 'index']);
        Route::post('/tenants/{tenantId}/whatsapp-instances', [TenantWhatsAppInstanceController::class, 'store']);
        Route::put('/tenants/{tenantId}/whatsapp-instances/{instanceId}', [TenantWhatsAppInstanceController::class, 'update']);
        Route::delete('/tenants/{tenantId}/whatsapp-instances/{instanceId}', [TenantWhatsAppInstanceController::class, 'destroy']);
        Route::post('/tenants/{tenantId}/whatsapp-instances/{instanceId}/test-connection', [TenantWhatsAppInstanceController::class, 'testConnection']);
        Route::post('/tenants/{tenantId}/whatsapp-instances/{instanceId}/send-test', [TenantWhatsAppInstanceController::class, 'sendTest']);
    });

Route::post('/webhooks/{provider}', [InboundWebhookController::class, 'handle']);
