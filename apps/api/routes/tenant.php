<?php

declare(strict_types=1);

use App\Enums\Central\PermissionName;
use App\Http\Controllers\Tenant\AppointmentController;
use App\Http\Controllers\Tenant\AppointmentTokenController;
use App\Http\Controllers\Tenant\AvailabilityController;
use App\Http\Controllers\Tenant\ClinicSettingsController;
use App\Http\Controllers\Tenant\FileController;
use App\Http\Controllers\Tenant\MedicalRecordController;
use App\Http\Controllers\Tenant\PatientAuthController;
use App\Http\Controllers\Tenant\PatientController;
use App\Http\Controllers\Tenant\PatientMessageController;
use App\Http\Controllers\Tenant\PatientPortalController;
use App\Http\Controllers\Tenant\ProfessionalController;
use App\Http\Controllers\Tenant\ProfessionalDelegationController;
use App\Http\Controllers\Tenant\ProfessionalUnavailabilityController;
use App\Http\Controllers\Tenant\TenantWhatsAppManagementController;
use App\Http\Controllers\Tenant\UserManagementController;
use App\Http\Controllers\Tenant\WaitlistController;
use App\Http\Controllers\Tenant\WhatsAppVerificationController;
use App\Http\Middleware\CheckTenantSubscription;
use App\Http\Middleware\EnsurePatientPortalAccess;
use App\Http\Middleware\InitializeTenancyByHeader;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// Web routes with domain identification
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function (): void {
    Route::get('/', fn (): string => 'Multi-tenant clinic portal. Tenant: ' . tenant('id'));
});

// REST API routes with header identification e subscription enforcement
Route::prefix('api/tenant')->middleware([
    'api',
    InitializeTenancyByHeader::class,
    CheckTenantSubscription::class,
])->group(function (): void {
    // Public Fast Action Tokens e Patient OTP Passwordless Auth
    Route::get('/tokens/{token}', [AppointmentTokenController::class, 'show']);
    Route::post('/tokens/{token}/consume', [AppointmentTokenController::class, 'consume']);
    Route::post('/auth/otp/request', [PatientAuthController::class, 'requestOtp']);
    Route::post('/auth/otp/verify', [PatientAuthController::class, 'verifyOtp']);

    // Authenticated Sanctum endpoints with Spatie Permission middlewares
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [PatientAuthController::class, 'me']);

        // Patient Portal (strictly PATIENT or roleless User)
        Route::prefix('portal')->middleware(EnsurePatientPortalAccess::class)->group(function (): void {
            Route::get('/summary', [PatientPortalController::class, 'summary']);
            Route::get('/appointments', [PatientPortalController::class, 'appointments']);
            Route::post('/appointments/{id}/confirm', [PatientPortalController::class, 'confirmAppointment']);
            Route::post('/appointments/{id}/cancel', [PatientPortalController::class, 'cancelAppointment']);
            Route::get('/documents', [PatientPortalController::class, 'documents']);
            Route::get('/documents/{fileId}/download', [PatientPortalController::class, 'downloadDocument']);
            Route::get('/profile', [PatientPortalController::class, 'profile']);
            Route::put('/profile', [PatientPortalController::class, 'updateProfile']);
        });

        // Clinic User e Permission Management (ACL)
        Route::get('/permissions', [UserManagementController::class, 'permissionsCatalog'])
            ->middleware('permission:' . PermissionName::USERS_VIEW->value);
        Route::get('/users', [UserManagementController::class, 'index'])
            ->middleware('permission:' . PermissionName::USERS_VIEW->value);
        Route::get('/users/{userId}', [UserManagementController::class, 'show'])
            ->middleware('permission:' . PermissionName::USERS_VIEW->value);
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('permission:' . PermissionName::USERS_CREATE->value);
        Route::put('/users/{userId}', [UserManagementController::class, 'update'])
            ->middleware('permission:' . PermissionName::USERS_UPDATE->value);
        Route::put('/users/{userId}/permissions', [UserManagementController::class, 'updatePermissions'])
            ->middleware('permission:' . PermissionName::USERS_PERMISSIONS_ASSIGN->value);
        Route::post('/users/{userId}/toggle-active', [UserManagementController::class, 'toggleActive'])
            ->middleware('permission:' . PermissionName::USERS_UPDATE->value);
        Route::post('/users/{userId}/send-password-reset', [UserManagementController::class, 'sendPasswordReset'])
            ->middleware('permission:' . PermissionName::USERS_UPDATE->value);

        // Availability, Professionals, Unavailabilities e Appointments
        Route::get('/professionals', [ProfessionalController::class, 'index'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_VIEW->value);
        Route::get('/clinic-rooms', [ProfessionalController::class, 'rooms'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_VIEW->value);
        Route::get('/unavailabilities', [ProfessionalUnavailabilityController::class, 'index'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_VIEW->value);
        Route::post('/unavailabilities', [ProfessionalUnavailabilityController::class, 'store'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_CREATE->value);
        Route::delete('/unavailabilities/{id}', [ProfessionalUnavailabilityController::class, 'destroy'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_CANCEL->value);
        Route::get('/availability/slots', [AvailabilityController::class, 'index'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_VIEW->value);
        Route::get('/appointments', [AppointmentController::class, 'index'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_VIEW->value);
        Route::post('/appointments', [AppointmentController::class, 'store'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_CREATE->value);
        Route::get('/appointments/{id}', [AppointmentController::class, 'show'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_VIEW->value);
        Route::post('/appointments/{id}/confirm', [AppointmentController::class, 'confirm'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_CREATE->value);
        Route::post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_CANCEL->value);
        Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_CREATE->value);
        Route::post('/appointments/{id}/summon', [AppointmentController::class, 'summon'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_SUMMON->value);
        Route::patch('/appointments/{id}/status', [AppointmentController::class, 'updateStatus'])
            ->middleware('permission:' . PermissionName::APPOINTMENTS_CREATE->value);

        // Medical Records e Delegations
        Route::get('/medical-records', [MedicalRecordController::class, 'list'])
            ->middleware('permission:' . PermissionName::MEDICAL_RECORDS_VIEW->value);
        Route::get('/medical-records/{recordId}', [MedicalRecordController::class, 'showRecord'])
            ->middleware('permission:' . PermissionName::MEDICAL_RECORDS_VIEW->value);
        Route::put('/medical-records/{recordId}', [MedicalRecordController::class, 'update'])
            ->middleware('permission:' . PermissionName::MEDICAL_RECORDS_CREATE->value);
        Route::post('/medical-records/{recordId}/finalize', [MedicalRecordController::class, 'finalize'])
            ->middleware('permission:' . PermissionName::MEDICAL_RECORDS_CREATE->value);
        Route::get('/patients/{patientId}/medical-records', [MedicalRecordController::class, 'index'])
            ->middleware('permission:' . PermissionName::MEDICAL_RECORDS_VIEW->value);
        Route::post('/patients/{patientId}/medical-records', [MedicalRecordController::class, 'store'])
            ->middleware('permission:' . PermissionName::MEDICAL_RECORDS_CREATE->value);
        Route::get('/patients/{patientId}/medical-records/{recordId}', [MedicalRecordController::class, 'show'])
            ->middleware('permission:' . PermissionName::MEDICAL_RECORDS_VIEW->value);
        Route::post('/delegations', [ProfessionalDelegationController::class, 'store'])
            ->middleware('permission:' . PermissionName::DELEGATIONS_MANAGE->value);

        // Patient Documents e Files
        Route::get('/patients/{patientId}/files', [FileController::class, 'index'])
            ->middleware('permission:' . PermissionName::FILES_VIEW->value);
        Route::post('/patients/{patientId}/files', [FileController::class, 'store'])
            ->middleware('permission:' . PermissionName::FILES_UPLOAD->value);
        Route::get('/patients/{patientId}/files/{fileId}/download', [FileController::class, 'download'])
            ->middleware('permission:' . PermissionName::FILES_VIEW->value);
        Route::delete('/patients/{patientId}/files/{fileId}', [FileController::class, 'destroy'])
            ->middleware('permission:' . PermissionName::FILES_UPLOAD->value);

        // Smart Waitlist
        Route::get('/waitlist', [WaitlistController::class, 'index'])
            ->middleware('permission:' . PermissionName::WAITLIST_VIEW->value);
        Route::post('/waitlist', [WaitlistController::class, 'store'])
            ->middleware('permission:' . PermissionName::WAITLIST_MANAGE->value);

        // Clinic Information (open read for clinic staff; write restricted to CLINIC_SETTINGS_MANAGE)
        Route::get('/clinic', [ClinicSettingsController::class, 'show']);
        Route::put('/clinic', [ClinicSettingsController::class, 'update'])
            ->middleware('permission:' . PermissionName::CLINIC_SETTINGS_MANAGE->value);

        // Clinic WhatsApp Management (no create/credentials edit; toggle active, default, category, attendant)
        Route::get('/whatsapp/instances', [TenantWhatsAppManagementController::class, 'index'])
            ->middleware('permission:' . PermissionName::CLINIC_SETTINGS_MANAGE->value);
        Route::put('/whatsapp/instances/{instanceId}', [TenantWhatsAppManagementController::class, 'update'])
            ->middleware('permission:' . PermissionName::CLINIC_SETTINGS_MANAGE->value);
        Route::post('/whatsapp/instances/{instanceId}/test-connection', [TenantWhatsAppManagementController::class, 'testConnection'])
            ->middleware('permission:' . PermissionName::CLINIC_SETTINGS_MANAGE->value);
        Route::post('/whatsapp/instances/{instanceId}/send-test', [TenantWhatsAppManagementController::class, 'sendTest'])
            ->middleware('permission:' . PermissionName::CLINIC_SETTINGS_MANAGE->value);

        // Patients e WhatsApp Number Verification
        Route::get('/patients', [PatientController::class, 'index'])
            ->middleware('permission:' . PermissionName::PATIENTS_VIEW->value);
        Route::post('/patients', [PatientController::class, 'store'])
            ->middleware('permission:' . PermissionName::PATIENTS_CREATE->value);
        Route::get('/patients/{patientId}', [PatientController::class, 'show'])
            ->middleware('permission:' . PermissionName::PATIENTS_VIEW->value);
        Route::put('/patients/{patientId}', [PatientController::class, 'update'])
            ->middleware('permission:' . PermissionName::PATIENTS_UPDATE->value);
        Route::post('/patients/{patientId}/internal-note/reveal', [PatientController::class, 'revealInternalNote'])
            ->middleware('permission:' . PermissionName::PATIENTS_VIEW->value);
        Route::put('/patients/{patientId}/internal-note', [PatientController::class, 'updateInternalNote'])
            ->middleware('permission:' . PermissionName::PATIENTS_UPDATE->value);
        Route::get('/patients/{patientId}/messages', [PatientMessageController::class, 'index'])
            ->middleware('permission:' . PermissionName::PATIENTS_VIEW->value);
        Route::post('/patients/{patientId}/messages', [PatientMessageController::class, 'store'])
            ->middleware('permission:' . PermissionName::PATIENTS_VIEW->value);
        Route::post('/patients/{patientId}/messages/{messageId}/cancel', [PatientMessageController::class, 'cancel'])
            ->middleware('permission:' . PermissionName::PATIENTS_VIEW->value);
        Route::post('/patients/{patientId}/messages/{messageId}/send-now', [PatientMessageController::class, 'sendNow'])
            ->middleware('permission:' . PermissionName::PATIENTS_VIEW->value);
        Route::get('/whatsapp/status', [WhatsAppVerificationController::class, 'status'])
            ->middleware('permission:' . PermissionName::PATIENTS_VIEW->value);
        Route::post('/whatsapp/check-number', [WhatsAppVerificationController::class, 'checkNumber'])
            ->middleware('permission:' . PermissionName::PATIENTS_CREATE->value);
    });
});
