<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum PermissionName: string
{
    // Central / Super Admin
    case TENANTS_MANAGE = 'tenants.manage';
    case WHATSAPP_MANAGE = 'whatsapp.manage';
    case CENTRAL_AUDIT_VIEW = 'central_audit.view';

    // Users e ACL
    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_UPDATE = 'users.update';
    case USERS_PERMISSIONS_ASSIGN = 'users.permissions.assign';

    // Patients
    case PATIENTS_VIEW = 'patients.view';
    case PATIENTS_CREATE = 'patients.create';
    case PATIENTS_UPDATE = 'patients.update';

    // Appointments e Waitlist
    case APPOINTMENTS_VIEW = 'appointments.view';
    case APPOINTMENTS_CREATE = 'appointments.create';
    case APPOINTMENTS_CANCEL = 'appointments.cancel';
    case APPOINTMENTS_SUMMON = 'appointments.summon';
    case WAITLIST_VIEW = 'waitlist.view';
    case WAITLIST_MANAGE = 'waitlist.manage';

    // Medical Records e Files
    case MEDICAL_RECORDS_VIEW = 'medical_records.view';
    case MEDICAL_RECORDS_CREATE = 'medical_records.create';
    case MEDICAL_RECORDS_CREATE_ON_BEHALF = 'medical_records.create_on_behalf';
    case DELEGATIONS_MANAGE = 'delegations.manage';
    case FILES_VIEW = 'files.view';
    case FILES_UPLOAD = 'files.upload';

    // Clinic Settings
    case CLINIC_SETTINGS_MANAGE = 'clinic.settings.manage';

    // Profile
    case PROFILE_IDENTITY_UPDATE = 'profile.identity.update';
}
