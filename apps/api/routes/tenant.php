<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\AppointmentController;
use App\Http\Controllers\Tenant\AppointmentTokenController;
use App\Http\Controllers\Tenant\AvailabilityController;
use App\Http\Controllers\Tenant\FileController;
use App\Http\Controllers\Tenant\MedicalRecordController;
use App\Http\Controllers\Tenant\PatientAuthController;
use App\Http\Controllers\Tenant\ProfessionalDelegationController;
use App\Http\Controllers\Tenant\WaitlistController;
use App\Http\Middleware\CheckTenantSubscription;
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
    Route::get('/', fn (): string => 'Multi-tenant clinic portal. Tenant: ' . tenant('id'));
});

// REST API routes with header identification & subscription enforcement
Route::prefix('api/tenant')->middleware([
    'api',
    InitializeTenancyByHeader::class,
    CheckTenantSubscription::class,
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

    // Medical Records & Delegations
    Route::get('/patients/{patientId}/medical-records', [MedicalRecordController::class, 'index']);
    Route::post('/patients/{patientId}/medical-records', [MedicalRecordController::class, 'store']);
    Route::get('/patients/{patientId}/medical-records/{recordId}', [MedicalRecordController::class, 'show']);
    Route::post('/delegations', [ProfessionalDelegationController::class, 'store']);

    // Patient Documents & Files
    Route::get('/patients/{patientId}/files', [FileController::class, 'index']);
    Route::post('/patients/{patientId}/files', [FileController::class, 'store']);

    // Smart Waitlist
    Route::get('/waitlist', [WaitlistController::class, 'index']);
    Route::post('/waitlist', [WaitlistController::class, 'store']);

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
