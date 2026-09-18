# Domain Specification & Database Schemas

## 1. Schema Partitioning Model

The application employs a strict PostgreSQL schema separation model orchestrated by `stancl/tenancy`:
1. **Central Schema (`public`):** Stores global metadata, tenant registry, domain mapping, subscription state machines, and system administrators.
2. **Tenant Schemas (`tenant_{uuid}`):** Completely isolated operational schemas for each clinic, encompassing staff, schedules, appointments, health insurance catalogs, packages, and clinical records.

---

## 2. Central Schema (`public`)

### 2.1 Entity Relationship Overview
```text
[ tenants ] 1 ──< [ domains ]
     │ 1
     ├──< [ subscriptions ]
     └──< [ central_audit_logs ]

```

### 2.2 Core Enums (PHP Backed Enums)

```php
<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum SubscriptionStatus: string
{
    case ACTIVE    = 'ACTIVE';
    case PAST_DUE  = 'PAST_DUE';
    case SUSPENDED = 'SUSPENDED';
    case CANCELLED = 'CANCELLED';
    case COURTESY  = 'COURTESY';

    public function allowsWrites(): bool
    {
        return in_array($this, [self::ACTIVE, self::PAST_DUE, self::COURTESY], true);
    }
}

```

```php
<?php

declare(strict_types=1);

namespace App\Enums\Central;

enum BillingInterval: string
{
    case MONTHLY   = 'MONTHLY';
    case BIMONTHLY = 'BIMONTHLY';
    case QUARTERLY = 'QUARTERLY';
    case YEARLY    = 'YEARLY';
}

```

### 2.3 Central Migrations

#### `tenants` & `subscriptions`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 80)->unique();
            $table->string('tax_id', 32)->nullable(); // CNPJ or CPF
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->jsonb('settings')->nullable();
            $table->timestampsTz();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('status', 32)->default('ACTIVE')->index();
            $table->string('billing_interval', 32)->default('MONTHLY');

            $table->timestampTz('current_period_start_utc');
            $table->timestampTz('current_period_end_utc')->index();
            $table->timestampTz('grace_period_ends_utc')->nullable();

            $table->boolean('is_exempt')->default(false);
            $table->string('gateway_driver', 32)->nullable(); // 'stripe', 'asaas', 'manual_pix'
            $table->string('gateway_subscription_id', 128)->nullable()->index();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');
    }
};

```

---

## 3. Tenant Schema (`tenant_{uuid}`)

### 3.1 Entity Relationship Diagram

```text
[ clinic_rooms ] ────────┐
                         │ 0..1
[ professionals ] 1 ─────┼─────< [ appointments ] >───── 1 [ patients ]
       │ 1               │              │ 1                      │ 1
       ├──< [ availabilities ]          │                        │
       ├──< [ unavailabilities ]        ├──< [ appointment_tokens ]
       │                                ├──< [ files ] (polymorphic)
       ├──< [ professional_insurances ] ├──< [ medical_records ]
       │             │                  │
[ health_insurances ]│                  ├──> 0..1 [ patient_packages ]
       │ 1           │                  └──> 0..1 [ patient_health_insurances ]
       └──< [ health_insurance_plans ]                   │
                     │                                   ▼
                     └───────────────────────────────────┘

```

### 3.2 Tenant Domain Enums

```php
<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum AppointmentStatus: string
{
    case SCHEDULED       = 'SCHEDULED';
    case CONFIRMED       = 'CONFIRMED';
    case ARRIVED         = 'ARRIVED';
    case IN_CONSULTATION = 'IN_CONSULTATION';
    case COMPLETED       = 'COMPLETED';
    case CANCELLED       = 'CANCELLED';
    case NO_SHOW         = 'NO_SHOW';

    public function isFinalized(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::NO_SHOW], true);
    }

    public function isBillable(): bool
    {
        return $this === self::COMPLETED;
    }
}

```

```php
<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum AppointmentPaymentStatus: string
{
    case PENDING                   = 'PENDING';
    case PAID_OFFLINE              = 'PAID_OFFLINE';
    case PAID_ONLINE               = 'PAID_ONLINE';
    case EXEMPT                    = 'EXEMPT';
    case PENDING_INSURANCE_BILLING = 'PENDING_INSURANCE_BILLING';
}

```

```php
<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum PaymentMethod: string
{
    case NONE             = 'NONE';
    case PIX              = 'PIX';
    case CREDIT_CARD      = 'CREDIT_CARD';
    case DEBIT_CARD       = 'DEBIT_CARD';
    case CASH             = 'CASH';
    case HEALTH_INSURANCE = 'HEALTH_INSURANCE';
    case PACKAGE_CREDIT   = 'PACKAGE_CREDIT';
}

```

```php
<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum RecordVisibility: string
{
    case PRIVATE       = 'PRIVATE';
    case SHARED_TEAM   = 'SHARED_TEAM';
    case PUBLIC_CLINIC = 'PUBLIC_CLINIC';
}

```

---

### 3.3 Tenant Database Migrations

#### A. Professionals, Rooms, and Availabilities

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_rooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('professionals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index(); // If linked to login user
            $table->string('name', 120);
            $table->string('specialty', 100);
            $table->string('license_number', 50); // CRM, CRP, CREFITO
            $table->string('color_code', 10)->default('#3B82F6');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('professional_availabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->index(); // 0 = Sun, 6 = Sat
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['professional_id', 'day_of_week', 'is_active'], 'idx_prof_avail_lookup');
        });

        Schema::create('professional_unavailabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->timestampTz('start_time_utc')->index();
            $table->timestampTz('end_time_utc')->index();
            $table->string('reason', 255)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_unavailabilities');
        Schema::dropIfExists('professional_availabilities');
        Schema::dropIfExists('professionals');
        Schema::dropIfExists('clinic_rooms');
    }
};

```

#### B. Health Insurances, Plans, and Appointment Types

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_insurances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('ans_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('health_insurance_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('health_insurance_id')->constrained('health_insurances')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('plan_code', 50)->nullable();
            $table->boolean('requires_prior_authorization')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('professional_insurances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignUuid('health_insurance_id')->constrained('health_insurances')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['professional_id', 'health_insurance_id']);
        });

        Schema::create('appointment_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedInteger('price_in_cents')->default(0);
            $table->boolean('is_return')->default(false);
            $table->unsignedSmallInteger('max_return_days_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_types');
        Schema::dropIfExists('professional_insurances');
        Schema::dropIfExists('health_insurance_plans');
        Schema::dropIfExists('health_insurances');
    }
};

```

#### C. Patients, Packages, and Credit Ledger

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('phone', 30)->index();
            $table->string('email', 150)->nullable()->index();
            $table->string('tax_id', 32)->nullable()->index(); // CPF
            $table->date('birth_date')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
        });

        Schema::create('patient_health_insurances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('health_insurance_id')->constrained('health_insurances')->restrictOnDelete();
            $table->foreignUuid('health_insurance_plan_id')->constrained('health_insurance_plans')->restrictOnDelete();
            $table->string('card_number', 64);
            $table->date('expiration_date')->nullable();
            $table->timestampsTz();

            $table->index(['patient_id', 'health_insurance_id']);
        });

        Schema::create('packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_type_id')->constrained('appointment_types')->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('session_quantity');
            $table->unsignedInteger('total_price_in_cents');
            $table->unsignedSmallInteger('validity_days')->default(90);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('patient_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('package_id')->constrained('packages')->restrictOnDelete();
            $table->unsignedSmallInteger('total_credits');
            $table->unsignedSmallInteger('remaining_credits');
            $table->timestampTz('expires_at_utc')->index();
            $table->string('payment_status', 32)->default('PAID_OFFLINE');
            $table->timestampsTz();

            $table->index(['patient_id', 'remaining_credits', 'expires_at_utc']);
        });

        Schema::create('patient_credit_ledger', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_package_id')->constrained('patient_packages')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->index();
            $table->integer('credits_delta'); // +N for purchase/refund, -1 for deduction
            $table->string('operation_type', 32); // 'PURCHASE', 'APPOINTMENT_DEDUCTION', 'REFUND'
            $table->text('description')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_credit_ledger');
        Schema::dropIfExists('patient_packages');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('patient_health_insurances');
        Schema::dropIfExists('patients');
    }
};

```

#### D. Appointments & Tokens

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignUuid('clinic_room_id')->nullable()->constrained('clinic_rooms')->nullOnDelete();
            $table->foreignUuid('appointment_type_id')->nullable()->constrained('appointment_types')->nullOnDelete();
            $table->foreignUuid('parent_appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUuid('patient_package_id')->nullable()->constrained('patient_packages')->nullOnDelete();
            $table->foreignUuid('patient_health_insurance_id')->nullable()->constrained('patient_health_insurances')->nullOnDelete();

            $table->timestampTz('start_time_utc')->index();
            $table->timestampTz('end_time_utc')->index();

            $table->string('status', 32)->default('SCHEDULED')->index();

            // Operational Lifecycle Audit
            $table->timestampTz('arrived_at_utc')->nullable();
            $table->timestampTz('started_at_utc')->nullable();
            $table->timestampTz('completed_at_utc')->nullable()->index();

            // Financial Fields
            $table->unsignedInteger('price_in_cents')->default(0);
            $table->string('payment_status', 32)->default('PENDING')->index();
            $table->string('payment_method', 32)->default('NONE');
            $table->string('health_insurance_auth_code', 64)->nullable();

            $table->string('origin_timezone', 64)->default('America/Sao_Paulo');
            $table->text('notes')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->foreignUuid('created_by_user_id')->nullable();

            $table->timestampsTz();

            $table->index(['professional_id', 'status', 'start_time_utc', 'end_time_utc'], 'idx_appts_overlap_lookup');
        });

        Schema::create('appointment_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('intended_action', 32); // 'CONFIRMATION', 'RESCHEDULE', 'CHECKIN'
            $table->timestampTz('expires_at_utc')->index();
            $table->timestampTz('used_at_utc')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_tokens');
        Schema::dropIfExists('appointments');
    }
};

```

#### E. Polymorphic Files & Scoped Medical Records

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('fileable');
            $table->string('collection', 50)->default('default')->index();
            $table->string('visibility', 32)->default('SHARED_TEAM')->index();
            $table->string('disk', 32)->default('s3');
            $table->string('file_path', 500);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->foreignUuid('uploaded_by_user_id')->nullable();

            $table->timestampTz('purged_at')->nullable()->index();
            $table->softDeletesTz();
            $table->timestampsTz();

            $table->index(['fileable_type', 'fileable_id', 'collection']);
        });

        Schema::create('medical_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('professional_id')->constrained('professionals')->restrictOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            $table->string('type', 32)->default('CLINICAL_NOTE')->index();
            $table->string('visibility', 32)->default('PRIVATE')->index();
            $table->string('title', 150);
            $table->text('content');

            $table->timestampsTz();
        });

        Schema::create('professional_delegations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('grantor_professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignUuid('grantee_professional_id')->constrained('professionals')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->cascadeOnDelete();

            $table->timestampTz('starts_at_utc')->index();
            $table->timestampTz('ends_at_utc')->index();
            $table->string('reason', 255)->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_delegations');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('files');
    }
};

```

---

## 4. Core Scheduling Engine Specifications

### 4.1 Slot Overlap Condition

Two time intervals $[A_{start}, A_{end}]$ and $[B_{start}, B_{end}]$ collide if and only if:


$$\text{Overlap} \iff (A_{start} < B_{end}) \land (A_{end} > B_{start})$$

### 4.2 Pessimistic Locking Action (`BookAppointmentAction`)

```php
<?php

declare(strict_types=1);

namespace App\Actions\Appointment;

use App\Enums\Tenant\AppointmentStatus;
use App\Models\Tenant\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

if (!class_exists(BookAppointmentAction::class)) {
    final class BookAppointmentAction
    {
        /**
         * @param array{
         *     professional_id: string,
         *     patient_id: string,
         *     clinic_room_id?: string|null,
         *     appointment_type_id?: string|null,
         *     start_time_utc: CarbonImmutable,
         *     end_time_utc: CarbonImmutable,
         *     origin_timezone: string,
         *     notes?: string|null,
         *     created_by_user_id?: string|null
         * } $data
         */
        public static function execute(array $data): Appointment
        {
            return DB::transaction(function () use ($data): Appointment {                 // 1. Lock check for professional availability$professionalCollision = Appointment::query()
                    ->where('professional_id', $data['professional_id'])
                    ->whereNotIn('status', [AppointmentStatus::CANCELLED->value, AppointmentStatus::NO_SHOW->value])
                    ->where('start_time_utc', '<', $data['end_time_utc'])
                    ->where('end_time_utc', '>', $data['start_time_utc'])
                    ->lockForUpdate()
                    ->exists();

                if ($professionalCollision) {
                    throw new RuntimeException('The selected professional already has an appointment during this time.');
                }

                // 2. Lock check for room collision if room specified
                if (!empty($data['clinic_room_id'])) {$roomCollision = Appointment::query()
                        ->where('clinic_room_id', $data['clinic_room_id'])
                        ->whereNotIn('status', [AppointmentStatus::CANCELLED->value, AppointmentStatus::NO_SHOW->value])
                        ->where('start_time_utc', '<', $data['end_time_utc'])
                        ->where('end_time_utc', '>', $data['start_time_utc'])
                        ->lockForUpdate()
                        ->exists();

                    if ($roomCollision) {
                        throw new RuntimeException('The selected clinic room is already occupied during this time.');
                    }
                }

                return Appointment::create([
                    'professional_id'     => $data['professional_id'],
                    'patient_id'          => $data['patient_id'],
                    'clinic_room_id'      => $data['clinic_room_id'] ?? null,
                    'appointment_type_id' => $data['appointment_type_id'] ?? null,
                    'start_time_utc'      => $data['start_time_utc'],
                    'end_time_utc'        => $data['end_time_utc'],
                    'status'              => AppointmentStatus::SCHEDULED,
                    'origin_timezone'     => $data['origin_timezone'],
                    'notes'               => $data['notes'] ?? null,
                    'created_by_user_id'  => $data['created_by_user_id'] ?? null,
                ]);
            });
        }
    }
}

```
