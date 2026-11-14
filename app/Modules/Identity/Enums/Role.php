<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * Staff roles from spec §2.6. Machine clients (API tokens) and guests are not
 * session users, so they are intentionally not listed here.
 */
enum Role: string
{
    case SuperAdmin = 'super-admin';
    case PropertyManager = 'property-manager';
    case FrontDeskAgent = 'front-desk-agent';
    case RevenueManager = 'revenue-manager';
    case HousekeepingSupervisor = 'housekeeping-supervisor';
    case HousekeepingAttendant = 'housekeeping-attendant';
    case MaintenanceStaff = 'maintenance-staff';
    case Accountant = 'accountant';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::PropertyManager => 'Property Manager',
            self::FrontDeskAgent => 'Front Desk Agent',
            self::RevenueManager => 'Revenue Manager',
            self::HousekeepingSupervisor => 'Housekeeping Supervisor',
            self::HousekeepingAttendant => 'Housekeeping Attendant',
            self::MaintenanceStaff => 'Maintenance Staff',
            self::Accountant => 'Accountant / Finance',
            self::Auditor => 'Auditor',
        };
    }
}
