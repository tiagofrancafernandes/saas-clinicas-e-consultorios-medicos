<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AppointmentController;
use App\Http\Controllers\Tenant\AppointmentTokenController;
use App\Http\Controllers\Tenant\AvailabilityController;
use App\Http\Controllers\Tenant\PatientAuthController;
use App\Http\Middleware\InitializeTenancyByHeader;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register tenant-scoped routes for the application.
| Web routes use domain/subdomain identification.
| API routes use X-Tenant-ID header or ?tenant query parameter.
|
*/

// Web routes with domain identification
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function (): void {
    Route::get('/', function (): string {
        return 'Multi-tenant clinic portal. Tenant: '.tenant('id');
    });
});

// REST API routes with header identification
Route::prefix('api/tenant')->middleware([
    'api',
    InitializeTenancyByHeader::class,
])->group(function (): void {
    // Availability calculation
    Route::get('/availability/slots', [AvailabilityController::class, 'index']);

    // Appointment management
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule']);
    Route::post('/appointments/{id}/summon', [AppointmentController::class, 'summon']);

    // Fast Action Tokens (Appointment confirmation/cancellation)
    Route::get('/tokens/{token}', [AppointmentTokenController::class, 'show']);
    Route::post('/tokens/{token}/consume', [AppointmentTokenController::class, 'consume']);

    // Patient OTP Passwordless Authentication
    Route::post('/auth/otp/request', [PatientAuthController::class, 'requestOtp']);
    Route::post('/auth/otp/verify', [PatientAuthController::class, 'verifyOtp']);

    // Authenticated patient endpoints
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [PatientAuthController::class, 'me']);
    });
});
